<?php
/**
 * Admin Action: Approve/Reject Sender ID - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = sanitize($_POST['action'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');
    
    $status = ($action === 'approve') ? 'approved' : 'rejected';

    if ($id) {
        $approvedBy = $_SESSION['user']['id'];
        $approvedAt = ($status === 'approved') ? date('Y-m-d H:i:s') : null;
        
        $success = DB::execute("UPDATE sender_ids SET status = ?, reject_reason = ?, approved_by = ?, approved_at = ? WHERE id = ?", 
                                [$status, $reason, $approvedBy, $approvedAt, $id]);
        
        if ($success) {
            // Get user_id and sender_id name for notification
            $sid = DB::queryOne("SELECT user_id, sender_id FROM sender_ids WHERE id = ?", [$id]);
            if ($sid) {
                $title = ($status === 'approved') ? 'Sender ID Approved' : 'Sender ID Rejected';
                $msg = ($status === 'approved') 
                    ? "Your Sender ID '{$sid['sender_id']}' has been approved. You can now use it to send messages."
                    : "Your Sender ID '{$sid['sender_id']}' was rejected. Reason: $reason";
                $type = ($status === 'approved') ? 'success' : 'danger';
                notify($sid['user_id'], $title, $msg, $type);
            }

            $_SESSION['flash'] = ['type' => 'success', 'message' => "Sender ID ".ucfirst($status)." successfully."];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to update Sender ID status.'];
        }
    }
    redirect($_SERVER['HTTP_REFERER'] ?? '/admin/sender-ids.php');
}
