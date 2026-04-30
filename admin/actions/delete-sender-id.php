<?php
/**
 * Admin Action: Delete Sender ID - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
        redirect('/admin/sender-ids.php');
    }

    $id = (int)($_POST['id'] ?? 0);

    if ($id) {
        $success = DB::execute("DELETE FROM sender_ids WHERE id = ?", [$id]);
        
        if ($success) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Sender ID deleted successfully.'];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to delete Sender ID.'];
        }
    }

    redirect('/admin/sender-ids.php');
}
