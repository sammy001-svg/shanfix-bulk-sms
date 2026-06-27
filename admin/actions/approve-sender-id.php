<?php
/**
 * Admin Action: Approve/Reject Sender ID - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/sender-ids.php');
}

if (!csrf_verify()) {
    flash_set('danger', 'Invalid security token.');
    redirect('/admin/sender-ids.php');
}

$id     = (int)($_POST['id'] ?? 0);
$action = sanitize($_POST['action'] ?? '');
$reason = sanitize($_POST['reason'] ?? '');

if (!$id || !in_array($action, ['approve', 'reject'], true)) {
    flash_set('danger', 'Invalid request.');
    redirect(safe_referer('/admin/sender-ids.php'));
}

$status     = ($action === 'approve') ? 'approved' : 'rejected';
$approvedBy = current_user()['id'];
$approvedAt = ($status === 'approved') ? date('Y-m-d H:i:s') : null;

DB::execute(
    "UPDATE sender_ids SET status = ?, reject_reason = ?, approved_by = ?, approved_at = ? WHERE id = ?",
    [$status, $reason, $approvedBy, $approvedAt, $id]
);

$sid = DB::queryOne("SELECT user_id, sender_id FROM sender_ids WHERE id = ?", [$id]);
if ($sid) {
    $title = ($status === 'approved') ? 'Sender ID Approved' : 'Sender ID Rejected';
    $msg   = ($status === 'approved')
        ? "Your Sender ID '{$sid['sender_id']}' has been approved. You can now use it to send messages."
        : "Your Sender ID '{$sid['sender_id']}' was rejected. Reason: $reason";
    notify($sid['user_id'], $title, $msg, ($status === 'approved') ? 'success' : 'danger');
}
flash_set('success', "Sender ID " . ucfirst($status) . " successfully.");

redirect(safe_referer('/admin/sender-ids.php'));
