<?php
/**
 * Single-campaign runner — spawned by process_campaigns.php
 * Usage: php run_campaign.php <campaign_id>
 */
set_time_limit(0);
ini_set('memory_limit', '256M');

$campaignId = (int)($argv[1] ?? 0);
if ($campaignId <= 0) {
    fwrite(STDERR, "run_campaign.php: missing or invalid campaign ID\n");
    exit(1);
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/actions/sms.php';

SMS::processCampaign($campaignId);
