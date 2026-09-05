<?php
/**
 * Admin Action: Delete Sender ID - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
        redirect('/admin/sender-ids.php');
    }

    $id = (int)($_POST['id'] ?? 0);

    if ($id) {
        DB::execute("DELETE FROM sender_ids WHERE id = ?", [$id]);
        flash_set('success', 'Sender ID deleted successfully.');
    }

    redirect('/admin/sender-ids.php');
}
