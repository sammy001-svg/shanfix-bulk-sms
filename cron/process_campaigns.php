<?php
/**
 * Cron: Process Queued & Scheduled Campaigns - Shanfix Technology
 *
 * Set up in cPanel (every minute):
 *   * * * * * php /home/user/public_html/cron/process_campaigns.php >> /home/user/logs/cron.log 2>&1
 *
 * What this does each run:
 *  1. Rescues campaigns stuck in 'sending' for > 10 minutes (process died mid-run)
 *  2. Picks up newly queued campaigns and scheduled campaigns whose time has come
 *  3. Processes each in fast bulk batches (500 recipients per Onfon API call)
 */
set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/actions/sms.php';

// ------------------------------------------------------------------
// Step 1: Rescue campaigns stuck in 'sending'
// If locked_at is older than 10 minutes, the process that claimed it
// must have died. Reset to 'queued' so we pick it up fresh this run.
// ------------------------------------------------------------------
$rescued = DB::execute(
    "UPDATE campaigns
     SET status = 'queued', locked_at = NULL
     WHERE status = 'sending'
       AND locked_at IS NOT NULL
       AND locked_at < NOW() - INTERVAL 10 MINUTE"
);
if ($rescued > 0) {
    echo "[" . date('H:i:s') . "] Rescued $rescued stuck campaign(s) → re-queued.\n";
}

// ------------------------------------------------------------------
// Step 2: Pick up campaigns to process this tick
// Limit to 5 per run; the next cron tick handles any overflow.
// ------------------------------------------------------------------
$now       = date('Y-m-d H:i:s');
$campaigns = DB::query(
    "SELECT id FROM campaigns
     WHERE status = 'queued'
        OR (status = 'scheduled' AND scheduled_at <= ?)
     ORDER BY created_at ASC
     LIMIT 5",
    [$now]
);

if (empty($campaigns)) {
    exit(0);
}

foreach ($campaigns as $c) {
    echo "[" . date('H:i:s') . "] Processing campaign #{$c['id']}...\n";
    SMS::processCampaign($c['id']);
    echo "[" . date('H:i:s') . "] Campaign #{$c['id']} done.\n";
}
