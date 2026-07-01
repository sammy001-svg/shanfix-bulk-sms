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

// ── Build network scope (reseller + clients) ──────────────────────────────────
$clientRows  = DB::query("SELECT id, name FROM users WHERE role='client' AND parent_id=?", [$uid]);
$clients     = [];
foreach ($clientRows as $c) {
    $clients[(int)$c['id']] = $c['name'];
}

$clientFilter = (int)($_GET['client_id'] ?? 0);
$showSelf     = ($clientFilter === -1);

if ($showSelf) {
    $networkIds = [$uid];
} elseif ($clientFilter > 0 && isset($clients[$clientFilter])) {
    $networkIds = [$clientFilter];
} else {
    $networkIds = array_merge([$uid], array_keys($clients));
}

if (empty($networkIds)) {
    $networkIds = [0];
}

$in = implode(',', array_fill(0, count($networkIds), '?'));

$sql    = "SELECT m.id, m.campaign_id, u.name as sender_name, m.user_id, m.sender_id,
                  m.recipient, m.message, m.units_charged, m.status, m.failed_reason,
                  m.sent_at, m.created_at
           FROM messages m
           JOIN users u ON m.user_id = u.id
           WHERE m.user_id IN ($in) AND m.created_at >= ? AND m.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
$params = array_merge($networkIds, [$from, $to]);

if ($statusFilter) {
    $sql    .= " AND m.status = ?";
    $params[] = $statusFilter;
}

const MAX_ROWS = 100_000;
$sql .= " ORDER BY m.created_at DESC LIMIT " . (MAX_ROWS + 1);
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

fputcsv($output, ['Message ID', 'Campaign ID', 'Account', 'Sender ID', 'Recipient', 'Message', 'Units Charged', 'Status', 'Failure Reason', 'Sent At', 'Created At']);

foreach ($data as $row) {
    $accountName = ((int)$row['user_id'] === $uid) ? $user['name'] . ' (You)' : $row['sender_name'];
    fputcsv($output, [
        $row['id'],
        $row['campaign_id'] ?: '',
        $accountName,
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
