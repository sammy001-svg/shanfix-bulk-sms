<?php
/**
 * Cron: Process Queued & Scheduled Campaigns - Shanfix Technology
 *
 * Run every minute:
 *   * * * * * php /path/to/cron/process_campaigns.php >> /path/to/cron.log 2>&1
 *
 * This script uses Onfon's bulk API (500 recipients per call) and streams
 * large files row-by-row, so it handles millions of contacts without timeout.
 */
set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/actions/sms.php';

$now = date('Y-m-d H:i:s');

// Pick up both explicitly queued campaigns AND scheduled ones whose time has arrived.
// Process up to 5 campaigns per cron tick to avoid single-run overload.
$campaigns = DB::query(
    "SELECT id FROM campaigns
     WHERE (status = 'queued')
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
