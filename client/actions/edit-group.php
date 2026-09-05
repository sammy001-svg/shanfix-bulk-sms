<?php
/**
 * Client Action: Edit Group - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('client');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'Security token mismatch. Please try again.');
        redirect('/client/groups.php');
    }

    $user = current_user();
    $id   = (int)($_POST['id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');

    if (!$id || !$name) {
        flash_set('danger', 'Group name is required.');
        redirect('/client/groups.php');
    }

    $updated = DB::execute(
        "UPDATE contact_groups SET name = ? WHERE id = ? AND user_id = ?",
        [$name, $id, $user['id']]
    );

    if ($updated > 0) {
        flash_set('success', 'Group updated successfully.');
    } else {
        flash_set('danger', 'Group not found or you do not have permission to edit it.');
    }

    redirect('/client/groups.php');
}
