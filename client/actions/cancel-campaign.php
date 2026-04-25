<?php
/**
 * Action: Cancel Scheduled Campaign - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $user = current_user();
        $success = DB::execute("UPDATE campaigns SET status = 'cancelled' WHERE id = ? AND user_id = ?", [$id, $user['id']]);
        if ($success) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Scheduled campaign cancelled.'];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to cancel campaign. It may have already started.'];
        }
    }
    redirect($_SERVER['HTTP_REFERER']);
}
