<?php
/**
 * Admin Action: Approve/Reject Sender ID - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $status = sanitize($_POST['status'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');

    if ($id && in_array($status, ['approved', 'rejected'])) {
        $approvedAt = ($status === 'approved') ? 'NOW()' : 'NULL';
        $success = DB::execute("UPDATE sender_ids SET status = ?, rejection_reason = ?, approved_at = $approvedAt WHERE id = ?", [$status, $reason, $id]);
        
        if ($success) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Sender ID ".ucfirst($status)."."];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to update Sender ID status.'];
        }
    }
    redirect($_SERVER['HTTP_REFERER'] ?? '/admin/sender-ids.php');
}
