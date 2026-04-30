<?php
/**
 * Action: Create Contact Group - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
// Allows both resellers and clients
$user = auth_user();
if (!$user || !in_array($user['role'], ['reseller', 'client'])) redirect('/login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');

    if ($name) {
        $id = DB::insert("INSERT INTO contact_groups (name, user_id, created_at) VALUES (?, ?, NOW())", [$name, $user['id']]);
        if ($id) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Group '$name' created successfully."];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to create group.'];
        }
    }

    redirect($_SERVER['HTTP_REFERER'] ?? '/client/groups.php');
}
