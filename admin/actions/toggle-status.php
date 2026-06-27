<?php
/**
 * Admin Action: Toggle User Status - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
        redirect('/admin/clients.php');
    }

    $userId = (int)($_POST['user_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? '');

    if ($userId && in_array($status, ['active', 'suspended'])) {
        $affected = DB::execute("UPDATE users SET status = ? WHERE id = ? AND role != 'admin'", [$status, $userId]);

        if ($affected) {
            $title = ($status === 'suspended') ? 'Account Suspended' : 'Account Re-activated';
            $msg   = ($status === 'suspended')
                ? 'Your account has been suspended by the administrator. Please contact support.'
                : 'Your account has been re-activated. You can now use all features again.';
            notify($userId, $title, $msg, ($status === 'suspended') ? 'danger' : 'success');
            $_SESSION['flash'] = ['type' => 'success', 'message' => "User status updated to $status."];
        } else {
            $_SESSION['flash'] = ['type' => 'warning', 'message' => 'No change — user not found or status already set.'];
        }
    }

    redirect(safe_referer('/admin/clients.php'));
}
