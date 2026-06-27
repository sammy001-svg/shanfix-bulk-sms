<?php
/**
 * Admin Action: Delete User - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
        redirect('/admin/clients.php');
    }

    $userId = (int)($_POST['id'] ?? 0);

    if ($userId) {
        // Prevent deleting yourself or other admins
        $userToDelete = DB::queryOne("SELECT role, name FROM users WHERE id = ?", [$userId]);
        
        if (!$userToDelete) {
            flash_set('danger', 'User not found.');
        } elseif ($userToDelete['role'] === 'admin') {
            flash_set('danger', 'Administrator accounts cannot be deleted.');
        } else {
            DB::execute("DELETE FROM users WHERE id = ? AND role != 'admin'", [$userId]);
            
            flash_set('success', "User account ({$userToDelete['name']}) has been permanently deleted.");
        }
    }

    redirect(safe_referer('/admin/clients.php'));
}
