<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

$user = current_user();
$uid  = $user['id'];

$from = sanitize($_GET['from'] ?? date('Y-m-01'));
$to   = sanitize($_GET['to']   ?? date('Y-m-d'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

// Cap range at 31 days
$fromTs = strtotime($from);
$toTs   = strtotime($to);
if ($toTs < $fromTs) [$fromTs, $toTs] = [$toTs, $fromTs];
if ($toTs - $fromTs > 31 * 86400) {
    $fromTs = $toTs - 31 * 86400;
}
$from = date('Y-m-d', $fromTs);
$to   = date('Y-m-d', $toTs);

$validStatuses = ['sent', 'delivered', 'failed', 'queued', 'undelivered'];
$statusFilter  = in_array($_GET['status'] ?? '', $validStatuses, true) ? $_GET['status'] : '';

const MAX_ROWS = 100_000;

$sql    = "SELECT id, campaign_id, sender_id, recipient, message, units_charged, status, failed_reason, sent_at, created_at
           FROM messages
           WHERE user_id = ? AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)";
$params = [$uid, $from, $to];

if ($statusFilter) {
    $sql    .= " AND status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY created_at DESC LIMIT " . (MAX_ROWS + 1);
$data = DB::query($sql, $params);

$truncated = count($data) > MAX_ROWS;
if ($truncated) $data = array_slice($data, 0, MAX_ROWS);

$filename = "Reseller_Report_{$from}_to_{$to}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM for Excel

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
