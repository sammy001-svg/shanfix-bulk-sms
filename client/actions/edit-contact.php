<?php
/**
 * Client Action: Edit Contact - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('client');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/client/contacts.php');
}

if (!csrf_verify()) {
    flash_set('danger', 'Security token mismatch. Please try again.');
    redirect(safe_referer('/client/contacts.php'));
}

$user    = current_user();
$id      = (int)($_POST['id'] ?? 0);
$phone   = sanitize($_POST['phone'] ?? '');
$name    = sanitize($_POST['name'] ?? '');
$email   = sanitize($_POST['email'] ?? '');
$groupId = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;

if (!$id || !$phone) {
    flash_set('danger', 'Phone number is required.');
    redirect(safe_referer('/client/contacts.php'));
}

$affected = DB::execute(
    "UPDATE contacts SET phone = ?, name = ?, email = ?, group_id = ? WHERE id = ? AND user_id = ?",
    [$phone, $name, $email, $groupId, $id, $user['id']]
);

flash_set($affected ? 'success' : 'danger', $affected ? 'Contact updated.' : 'Contact not found.');
redirect('/client/contacts.php');
