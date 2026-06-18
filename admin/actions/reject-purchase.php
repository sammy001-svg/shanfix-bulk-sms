<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    flash_set('danger', 'Invalid request.');
    redirect('/admin/finances.php');
}

$id     = (int)($_POST['id'] ?? 0);
$reason = trim(sanitize($_POST['reason'] ?? ''));

if (!$id) {
    flash_set('danger', 'Purchase ID missing.');
    redirect('/admin/finances.php');
}

$purchase = DB::queryOne(
    "SELECT p.*, u.name as user_name, u.id as uid FROM purchases p JOIN users u ON p.user_id = u.id WHERE p.id = ?",
    [$id]
);

if (!$purchase) {
    flash_set('danger', 'Purchase not found.');
    redirect(safe_referer('/admin/finances.php'));
}

if ($purchase['status'] !== 'pending') {
    flash_set('warning', 'Purchase #' . $id . ' is already ' . $purchase['status'] . ' — cannot reject.');
    redirect(safe_referer('/admin/finances.php'));
}

DB::execute("UPDATE purchases SET status = 'failed' WHERE id = ? AND status = 'pending'", [$id]);

// Notify the user
$note = 'Your payment of KES ' . number_format($purchase['amount'], 2) . ' (Purchase #' . $id . ') was rejected.';
if ($reason) $note .= ' Reason: ' . $reason;
notify($purchase['uid'], 'Payment Rejected', $note, 'danger');

flash_set('success', 'Purchase #' . $id . ' rejected for ' . htmlspecialchars($purchase['user_name']) . '.');
redirect(safe_referer('/admin/finances.php'));
