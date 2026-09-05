<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/reseller/groups.php');
}

if (!csrf_verify()) {
    flash_set('danger', 'Security token mismatch. Please try again.');
    redirect('/reseller/groups.php');
}

$user = current_user();
$id   = (int)($_POST['id'] ?? 0);
$name = sanitize($_POST['name'] ?? '');

if (!$id || !$name) {
    flash_set('danger', 'Group name is required.');
    redirect('/reseller/groups.php');
}

// WHERE user_id = ? enforces ownership — cannot rename another reseller's group.
$updated = DB::execute(
    "UPDATE contact_groups SET name = ? WHERE id = ? AND user_id = ?",
    [$name, $id, $user['id']]
);

if ($updated > 0) {
    flash_set('success', 'Group renamed successfully.');
} else {
    flash_set('danger', 'Group not found or you do not have permission to edit it.');
}

redirect('/reseller/groups.php');
