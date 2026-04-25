<?php
/**
 * Client Action: Edit Group - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('client');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $uid  = $_SESSION['user_id'];

    if (!$id || !$name) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Group name is required.'];
        redirect($_SERVER['HTTP_REFERER'] ?? '/client/groups.php');
    }

    // Verify ownership and update
    $success = DB::execute("UPDATE contact_groups SET name = ? WHERE id = ? AND user_id = ?", [$name, $id, $uid]);

    if ($success !== false) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Group updated successfully.'];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to update group.'];
    }

    redirect('/client/groups.php');
}
