<?php
/**
 * Admin Action: Edit User - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/resellers.php');
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
    redirect('/admin/resellers.php');
}

$id          = (int)($_POST['id'] ?? 0);
$name        = sanitize($_POST['name'] ?? '');
$email       = sanitize($_POST['email'] ?? '');
$phone       = sanitize($_POST['phone'] ?? '');
$company     = sanitize($_POST['company'] ?? '');
$role        = $_POST['role']   ?? '';
$status      = $_POST['status'] ?? '';
$parentId    = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
$units       = (float)($_POST['sms_units'] ?? 0);
$customPrice = ($_POST['custom_unit_price'] ?? '') !== '' ? (float)$_POST['custom_unit_price'] : null;
$waUnits     = (float)($_POST['whatsapp_balance'] ?? 0);
$waRate      = (float)($_POST['whatsapp_rate'] ?? 1.00);
$password    = $_POST['password'] ?? '';

if (!$id || !$name || !$email) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Name and Email are required.'];
    redirect(safe_referer('/admin/resellers.php'));
}

// Whitelist role and status — admin role cannot be assigned via this handler
if (!in_array($role, ['reseller', 'client'], true)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid role.'];
    redirect(safe_referer('/admin/resellers.php'));
}
if (!in_array($status, ['active', 'suspended', 'pending'], true)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid status.'];
    redirect(safe_referer('/admin/resellers.php'));
}

// Email format check
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid email address format.'];
    redirect(safe_referer('/admin/resellers.php'));
}

// Financial fields must not be negative
if ($units < 0 || $waUnits < 0 || $waRate <= 0 || ($customPrice !== null && $customPrice < 0)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Unit balances and prices must be non-negative.'];
    redirect(safe_referer('/admin/resellers.php'));
}

$existing = DB::queryOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id]);
if ($existing) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Email address is already in use.'];
    redirect(safe_referer('/admin/resellers.php'));
}

try {
    DB::beginTransaction();

    DB::execute(
        "UPDATE users SET name=?, email=?, phone=?, company=?, role=?, status=?,
         parent_id=?, sms_units=?, custom_unit_price=?, whatsapp_balance=?, whatsapp_rate=?
         WHERE id=?",
        [$name, $email, $phone, $company, $role, $status,
         $parentId, $units, $customPrice, $waUnits, $waRate, $id]
    );

    if ($password !== '') {
        DB::execute(
            "UPDATE users SET password_hash=? WHERE id=?",
            [password_hash($password, PASSWORD_BCRYPT), $id]
        );
    }

    DB::commit();
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'User account updated successfully.'];

} catch (Exception $e) {
    DB::rollback();
    error_log("edit-user failed for id=$id: " . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to update user. Please try again.'];
}

redirect('/admin/edit-user.php?id=' . $id);
