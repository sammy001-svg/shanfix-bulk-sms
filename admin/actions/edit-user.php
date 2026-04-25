<?php
/**
 * Admin Action: Edit User - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $name     = sanitize($_POST['name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $phone    = sanitize($_POST['phone'] ?? '');
    $company  = sanitize($_POST['company'] ?? '');
    $role     = sanitize($_POST['role'] ?? 'client');
    $status   = sanitize($_POST['status'] ?? 'active');
    $parentId = $_POST['parent_id'] ? (int)$_POST['parent_id'] : null;
    $units    = (float)($_POST['sms_units'] ?? 0);
    $customPrice = $_POST['custom_unit_price'] !== '' ? (float)$_POST['custom_unit_price'] : null;
    $password = $_POST['password'] ?? '';

    if (!$id || !$name || !$email) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Name and Email are required.'];
        redirect($_SERVER['HTTP_REFERER'] ?? '/admin/resellers.php');
    }

    // Check if email exists for other users
    $existing = DB::queryOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id]);
    if ($existing) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Email address is already in use.'];
        redirect($_SERVER['HTTP_REFERER'] ?? '/admin/resellers.php');
    }

    $sql = "UPDATE users SET name=?, email=?, phone=?, company=?, role=?, status=?, parent_id=?, sms_units=?, custom_unit_price=? WHERE id=?";
    $params = [$name, $email, $phone, $company, $role, $status, $parentId, $units, $customPrice, $id];

    $success = DB::execute($sql, $params);

    // Update password if provided
    if ($password) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        DB::execute("UPDATE users SET password_hash=? WHERE id=?", [$hash, $id]);
    }

    if ($success !== false) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => "User account updated successfully!"];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to update user. Database error.'];
    }

    redirect('/admin/edit-user.php?id=' . $id);
}
