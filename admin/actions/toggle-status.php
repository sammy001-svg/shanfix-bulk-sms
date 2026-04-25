<?php
/**
 * Admin Action: Toggle User Status - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? '');

    if ($userId && in_array($status, ['active', 'suspended'])) {
        $success = DB::execute("UPDATE users SET status = ? WHERE id = ? AND role != 'admin'", [$status, $userId]);
        
        if ($success) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => "User status updated to $status."];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to update user status.'];
        }
    }

    redirect($_SERVER['HTTP_REFERER'] ?? '/admin/clients.php');
}
