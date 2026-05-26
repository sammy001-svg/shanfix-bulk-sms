<?php
/**
 * Generic Action: Delete Entry - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = current_user();
    $id   = (int)($_POST['id'] ?? 0);
    $type = $_POST['type'] ?? ''; // 'contact', 'group', 'campaign'

    if (!$id || !$type) redirect(safe_referer('/'));

    $table = '';
    switch ($type) {
        case 'contact': $table = 'contacts'; break;
        case 'group':   $table = 'contact_groups'; break;
        case 'campaign':$table = 'campaigns'; break;
    }

    if ($table && $id) {
        $success = DB::execute("DELETE FROM `$table` WHERE id = ? AND user_id = ?", [$id, $user['id']]);
        if ($success) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => ucfirst($type) . " deleted successfully."];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => "Failed to delete " . $type . "."];
        }
    }

    redirect(safe_referer('/'));
}
