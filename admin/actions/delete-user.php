<?php
/**
 * Admin Action: Delete User - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
        redirect('/admin/clients.php');
    }

    $userId = (int)($_POST['id'] ?? 0);

    if ($userId) {
        // Prevent deleting yourself or other admins
        $userToDelete = DB::queryOne("SELECT role, name FROM users WHERE id = ?", [$userId]);
        
        if (!$userToDelete) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'User not found.'];
        } elseif ($userToDelete['role'] === 'admin') {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Administrator accounts cannot be deleted.'];
        } else {
            // Hard delete - Database constraints will handle related data (ON DELETE CASCADE)
            $success = DB::execute("DELETE FROM users WHERE id = ? AND role != 'admin'", [$userId]);
            
            if ($success) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => "User account ({$userToDelete['name']}) has been permanently deleted."];
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to delete user account.'];
            }
        }
    }

    redirect(safe_referer('/admin/clients.php'));
}
