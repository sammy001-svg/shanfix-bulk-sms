<?php
/**
 * Cron: Campaign Processor - Shanfix Technology
 *
 * Handles two cases:
 *   1. Campaigns left 'queued' (immediate sends the web process didn't pick up)
 *   2. Scheduled campaigns whose send time has arrived
 *   3. Campaigns stuck in 'sending' for > 5 minutes (web process was killed)
 *
 * Recommended crontab (every minute):
 *   * * * * * php /home/user/public_html/cron/process_campaigns.php >> /home/user/logs/cron.log 2>&1
 *
 * CONCURRENCY CAP
 * ---------------
 * MAX_CONCURRENT_CAMPAIGNS controls how many campaign worker processes may run
 * at the same time across all users. Each worker holds one PHP process and one
 * MySQL connection. Tune this to fit your server:
 *
 *   - Shared hosting (512 MB RAM):  2–3
 *   - VPS 2 GB RAM:                 5  (default)
 *   - Dedicated 8 GB RAM:           15–20
 *
 * You can also set it in system_settings with key 'max_concurrent_campaigns'.
 * The DB value takes precedence when present.
 */

define('DEFAULT_MAX_CONCURRENT', 5);

set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/actions/sms.php';

$log = function (string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
};

// ------------------------------------------------------------------
// Housekeeping: prune stale rate-counter rows ~once per hour.
// Runs on 1-in-60 cron ticks so cleanup never blocks campaigns.
// api_rate_counters: keep 2h (1-min windows expire fast anyway)
// rate_limits:       keep 48h (low-balance alert de-dup window is 24h)
// ------------------------------------------------------------------
if (random_int(1, 60) === 1) {
    $c1 = (int)DB::execute("DELETE FROM api_rate_counters WHERE window_start < NOW() - INTERVAL 2 HOUR");
    $c2 = (int)DB::execute("DELETE FROM rate_limits WHERE hit_at < NOW() - INTERVAL 48 HOUR");
    if ($c1 + $c2 > 0) $log("Pruned {$c1} rate-counter row(s) and {$c2} rate-limit row(s).");

    // Rotate gateway debug logs — they grow unbounded (one line per gateway
    // call / DLR).  When a log passes 10 MB keep only the newest ~2 MB.
    foreach (['onfon_debug.log', 'dlr_debug.log'] as $logFile) {
        $path = __DIR__ . '/../includes/gateways/' . $logFile;
        if (is_file($path) && filesize($path) > 10 * 1024 * 1024) {
            $fh = fopen($path, 'rb');
            fseek($fh, -2 * 1024 * 1024, SEEK_END);
            $tail = stream_get_contents($fh);
            fclose($fh);
            // Drop the partial first line, then atomically replace
            $tail = substr($tail, (int)strpos($tail, "\n") + 1);
            file_put_contents($path, $tail, LOCK_EX);
            $log("Rotated {$logFile} (kept newest 2 MB).");
        }
    }
}

// ------------------------------------------------------------------
// Housekeeping: mark stale 'queued' standalone messages as 'failed'.
//
// When a PHP-FPM worker is killed mid-request (request_terminate_timeout,
// OOM, server restart) while waiting for Onfon's response, the message
// row stays in 'queued' status permanently — the gateway may have
// already accepted and delivered the message, but our record is orphaned.
//
// After 5 minutes with no update, it is safe to assume the request
// died.  We mark these 'failed' so they surface in reports and
// the customer can investigate or retry.  campaign_id IS NULL because
// campaign messages are tracked by a separate heartbeat mechanism.
// ------------------------------------------------------------------
$stale = (int)DB::execute(
    "UPDATE messages
     SET status = 'failed', failed_reason = 'Request timed out before gateway responded — check if the message was delivered, then retry if needed.'
     WHERE status = 'queued'
       AND campaign_id IS NULL
       AND created_at < NOW() - INTERVAL 5 MINUTE"
);
if ($stale > 0) {
    $log("Marked {$stale} stale standalone queued message(s) as failed.");
}

// ------------------------------------------------------------------
// Read worker cap — DB setting overrides the compile-time constant.
// ------------------------------------------------------------------
$capRow = DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'max_concurrent_campaigns'");
$maxWorkers = (int)($capRow['value'] ?? DEFAULT_MAX_CONCURRENT);
if ($maxWorkers < 1) $maxWorkers = 1;

// ------------------------------------------------------------------
// Step 1: Rescue campaigns whose worker has gone silent.
//
// last_heartbeat_at is updated after every batch (~every few seconds
// for an active worker).  If it hasn't moved in 5 minutes the process
// is dead — reset to 'queued' so we can re-pick it up this tick.
//
// A SLOW campaign (large list, gateway latency) keeps its heartbeat
// current, so it is never incorrectly rescued and double-sent.
// ------------------------------------------------------------------
$rescued = DB::execute(
    "UPDATE campaigns
     SET status = 'queued', locked_at = NULL, last_heartbeat_at = NULL
     WHERE status = 'sending'
       AND last_heartbeat_at < NOW() - INTERVAL 5 MINUTE"
);
if ($rescued > 0) {
    $log("Rescued {$rescued} stuck campaign(s) → re-queued.");
}

// ------------------------------------------------------------------
// Step 2: Count active workers AFTER rescue so freed slots are visible.
// Each campaign in 'sending' status is running as a live worker process.
// ------------------------------------------------------------------
$activeWorkers = (int) DB::queryValue(
    "SELECT COUNT(*) FROM campaigns WHERE status = 'sending'"
);
$slots = $maxWorkers - $activeWorkers;

if ($slots <= 0) {
    $log("Worker cap reached ({$activeWorkers}/{$maxWorkers} active). Waiting for a slot to free up.");
    exit(0);
}

$log("Active workers: {$activeWorkers}/{$maxWorkers}. {$slots} slot(s) available.");

// ------------------------------------------------------------------
// Step 3: Fetch only as many campaigns as there are open slots.
// LIMIT prevents spawning more workers than the cap allows even if
// hundreds of campaigns are queued simultaneously.
// ------------------------------------------------------------------
$now       = date('Y-m-d H:i:s');
// One campaign per user per dispatch round (round-robin fairness).
// Prevents a single user with many queued campaigns from monopolising
// all worker slots while other users' campaigns wait indefinitely.
$campaigns = DB::query(
    "SELECT MIN(id) AS id
     FROM campaigns
     WHERE status = 'queued'
        OR (status = 'scheduled' AND scheduled_at <= ?)
     GROUP BY user_id
     ORDER BY MIN(created_at) ASC
     LIMIT " . $slots,
    [$now]
);

if (empty($campaigns)) {
    $log('No queued campaigns.');
    exit(0);
}

$log('Dispatching ' . count($campaigns) . ' campaign(s) (cap: ' . $maxWorkers . ')...');

foreach ($campaigns as $c) {
    if (SMS::spawnCampaign($c['id'])) {
        $log("  → Spawned campaign #{$c['id']}.");
    } else {
        // Fallback: spawn unavailable (restricted host) — process inline.
        // Inline processing counts as one worker for the duration of the run.
        $log("  → Campaign #{$c['id']} starting inline (spawn unavailable)...");
        SMS::processCampaign($c['id']);
        $log("  ✓ Campaign #{$c['id']} done.");
    }
}

$log('Done. Any remaining queued campaigns will be picked up on the next cron tick.');
