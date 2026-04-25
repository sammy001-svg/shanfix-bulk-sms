<?php
/**
 * Client Action: Edit Contact - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('client');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $phone    = sanitize($_POST['phone'] ?? '');
    $name     = sanitize($_POST['name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $groupId  = $_POST['group_id'] ? (int)$_POST['group_id'] : null;
    $uid      = $_SESSION['user_id'];

    if (!$id || !$phone) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Phone number is required.'];
        redirect($_SERVER['HTTP_REFERER'] ?? '/client/contacts.php');
    }

    // Verify ownership and update
    $sql = "UPDATE contacts SET phone = ?, name = ?, email = ?, group_id = ? WHERE id = ? AND user_id = ?";
    $success = DB::execute($sql, [$phone, $name, $email, $groupId, $id, $uid]);

    if ($success !== false) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Contact updated successfully.'];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to update contact.'];
    }

    redirect('/client/contacts.php');
}
