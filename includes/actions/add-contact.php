<?php
/**
 * Action: Add Single Contact - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
$user = auth_user();
if (!$user || !in_array($user['role'], ['reseller', 'client'])) redirect('/login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone   = sanitize($_POST['phone'] ?? '');
    $name    = sanitize($_POST['name'] ?? '');
    $email   = sanitize($_POST['email'] ?? '');
    $groupId = (int)($_POST['group_id'] ?? 0);

    if ($phone) {
        // Clean phone number (strip spaces, ensure +)
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (strpos($phone, '0') === 0) $phone = '+254' . substr($phone, 1);
        if (strpos($phone, '+') !== 0) $phone = '+' . $phone;

        $success = DB::insert("INSERT INTO contacts (user_id, group_id, phone, name, email, created_at) VALUES (?, ?, ?, ?, ?, NOW())", 
                         [$user['id'], $groupId ?: null, $phone, $name, $email]);

        if ($success) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Contact added successfully."];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to add contact. Duplicate phone?'];
        }
    }

    redirect(safe_referer('/client/contacts.php'));
}
