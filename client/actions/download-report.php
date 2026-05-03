<?php
/**
 * Action: Download Message Report (CSV/Excel)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_role('client');

$user = current_user();
$uid  = $user['id'];
$from = sanitize($_GET['from'] ?? date('Y-m-01'));
$to   = sanitize($_GET['to']   ?? date('Y-m-d'));
$format = $_GET['format'] ?? 'csv';

$filename = "Message_Report_{$from}_to_{$to}." . ($format === 'excel' ? 'xls' : 'csv');

// Fetch ALL data for the period
$query = "SELECT m.* FROM messages m WHERE m.user_id=? AND DATE(m.created_at) BETWEEN ? AND ? ORDER BY m.created_at DESC";
$data = DB::query($query, [$uid, $from, $to]);

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// Add BOM for Excel compatibility (especially for UTF-8)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Column Headers
fputcsv($output, [
    'Message ID',
    'Sender ID',
    'Recipient',
    'Message',
    'Units Charged',
    'Status',
    'Delivered At',
    'Created At'
]);

// Rows
if (!empty($data)) {
    foreach ($data as $row) {
        fputcsv($output, [
            $row['id'],
            $row['sender_id'],
            $row['recipient'],
            $row['message'],
            $row['units_charged'],
            ucfirst($row['status']),
            $row['sent_at'] ?: '—',
            $row['created_at']
        ]);
    }
}

fclose($output);
exit();
