<?php
/**
 * Action: Download Message Report (CSV)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('client');

$user = current_user();
$uid  = $user['id'];

// Sanitize and parse dates
$from = sanitize($_GET['from'] ?? date('Y-m-01'));
$to   = sanitize($_GET['to']   ?? date('Y-m-d'));

// Validate date format to prevent injection via Content-Disposition filename
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

// Cap range at 31 days — prevents fetching millions of rows in one request
$fromTs = strtotime($from);
$toTs   = strtotime($to);
if ($toTs < $fromTs) [$fromTs, $toTs] = [$toTs, $fromTs]; // swap if reversed
if ($toTs - $fromTs > 31 * 86400) {
    $fromTs = $toTs - 31 * 86400;
}
$from = date('Y-m-d', $fromTs);
$to   = date('Y-m-d', $toTs);

// Optional campaign filter
$campaignId = (int)($_GET['campaign_id'] ?? 0);

// Hard row limit — a CSV of 100k rows is ~30 MB; beyond that tell the user to narrow the range
const MAX_ROWS = 100_000;

$sql    = "SELECT id, campaign_id, sender_id, recipient, message, units_charged, status, failed_reason, sent_at, created_at
           FROM messages
           WHERE user_id = ? AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)";
$params = [$uid, $from, $to];

if ($campaignId > 0) {
    $sql    .= " AND campaign_id = ?";
    $params[] = $campaignId;
}

$sql .= " ORDER BY created_at DESC LIMIT " . (MAX_ROWS + 1);
$data = DB::query($sql, $params);

$truncated = count($data) > MAX_ROWS;
if ($truncated) {
    $data = array_slice($data, 0, MAX_ROWS);
}

$filename = $campaignId > 0
    ? "Campaign_{$campaignId}_Report_{$from}_to_{$to}.csv"
    : "Message_Report_{$from}_to_{$to}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store');

$output = fopen('php://output', 'w');

// BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

if ($truncated) {
    fputcsv($output, ["NOTE: Results capped at " . number_format(MAX_ROWS) . " rows. Narrow the date range to see all records."]);
}

fputcsv($output, ['Message ID', 'Campaign ID', 'Sender ID', 'Recipient', 'Message', 'Units Charged', 'Status', 'Failure Reason', 'Sent At', 'Created At']);

foreach ($data as $row) {
    fputcsv($output, [
        $row['id'],
        $row['campaign_id'] ?: '',
        $row['sender_id'],
        $row['recipient'],
        $row['message'],
        $row['units_charged'],
        ucfirst($row['status']),
        $row['failed_reason'] ?? '',
        $row['sent_at'] ?: '',
        $row['created_at'],
    ]);
}

fclose($output);
exit;
