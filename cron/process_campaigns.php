<?php
/**
 * Cron: Campaign Processor - Shanfix Technology
 *
 * Handles two cases:
 *   1. Campaigns left 'queued' (immediate sends that the web process didn't pick up)
 *   2. Scheduled campaigns whose send time has arrived
 *   3. Campaigns stuck in 'sending' for > 5 minutes (web process was killed)
 *
 * Recommended crontab (every minute):
 *   * * * * * php /home/user/public_html/cron/process_campaigns.php >> /home/user/logs/cron.log 2>&1
 *
 * This script can also be spawned on-demand by SMS::spawnBackground() so that
 * sending starts immediately rather than waiting for the next cron tick.
 */
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/actions/sms.php';

$log = function (string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
};

// ------------------------------------------------------------------
// Step 1: Rescue campaigns stuck in 'sending'
// If locked_at is older than 5 minutes the web/spawn process died.
// Reset to 'queued' so we pick them up this run.
// ------------------------------------------------------------------
$rescued = DB::execute(
    "UPDATE campaigns
     SET status = 'queued', locked_at = NULL
     WHERE status = 'sending'
       AND locked_at IS NOT NULL
       AND locked_at < NOW() - INTERVAL 5 MINUTE"
);
if ($rescued > 0) {
    $log("Rescued $rescued stuck campaign(s) → re-queued.");
}

// ------------------------------------------------------------------
// Step 2: Process ALL queued and due-scheduled campaigns.
// No LIMIT — a single cron invocation handles everything available.
// processCampaign() uses an atomic lock so concurrent runs are safe.
// ------------------------------------------------------------------
$now       = date('Y-m-d H:i:s');
$campaigns = DB::query(
    "SELECT id FROM campaigns
     WHERE status = 'queued'
        OR (status = 'scheduled' AND scheduled_at <= ?)
     ORDER BY created_at ASC",
    [$now]
);

if (empty($campaigns)) {
    exit(0);
}

$log('Processing ' . count($campaigns) . ' campaign(s)...');

foreach ($campaigns as $c) {
    $log("  → Campaign #{$c['id']} starting...");
    SMS::processCampaign($c['id']);
    $log("  ✓ Campaign #{$c['id']} done.");
}

$log('All campaigns processed.');
