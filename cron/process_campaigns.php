<?php
/**
 * Cron: Process Scheduled Campaigns - Shanfix Technology
 * Run this every minute: * * * * * php /path/to/cron/process_campaigns.php
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/actions/sms.php';

// 1. Find campaigns that are scheduled and ready to be sent
$now = date('Y-m-d H:i:s');
$campaigns = DB::query("SELECT id FROM campaigns WHERE status = 'scheduled' AND scheduled_at <= ?", [$now]);

if (empty($campaigns)) {
    // echo "No campaigns scheduled for now.\n";
    exit();
}

foreach ($campaigns as $c) {
    // echo "Processing Campaign #{$c['id']}...\n";
    SMS::processCampaign($c['id']);
}
