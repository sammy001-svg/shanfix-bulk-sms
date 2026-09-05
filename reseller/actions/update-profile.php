<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/reseller/profile.php');
}

if (!csrf_verify()) {
    flash_set('danger', 'Security token mismatch. Please try again.');
    redirect('/reseller/profile.php');
}

$user    = current_user();
$uid     = $user['id'];
$name    = sanitize($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = sanitize($_POST['phone'] ?? '');
$company = sanitize($_POST['company'] ?? '');
$current = $_POST['current_password'] ?? '';
$newPass = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if (!$name) {
    flash_set('danger', 'Name is required.');
    redirect('/reseller/profile.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('danger', 'Invalid email address.');
    redirect('/reseller/profile.php');
}

// Verify current password before allowing any changes
$row = DB::queryOne("SELECT password_hash FROM users WHERE id = ?", [$uid]);
if (!password_verify($current, $row['password_hash'])) {
    flash_set('danger', 'Incorrect current password.');
    redirect('/reseller/profile.php');
}

// Validate new password before touching the DB
if ($newPass !== '') {
    if (strlen($newPass) < 8) {
        flash_set('danger', 'New password must be at least 8 characters.');
        redirect('/reseller/profile.php');
    }
    if ($newPass !== $confirm) {
        flash_set('danger', 'New passwords do not match.');
        redirect('/reseller/profile.php');
    }
}

// Email uniqueness check
$existing = DB::queryOne("SELECT id FROM users WHERE email = ? AND id != ?", [strtolower($email), $uid]);
if ($existing) {
    flash_set('danger', 'Email address is already in use.');
    redirect('/reseller/profile.php');
}

DB::execute(
    "UPDATE users SET name = ?, email = ?, phone = ?, company = ? WHERE id = ?",
    [$name, strtolower($email), $phone, $company, $uid]
);

if ($newPass !== '') {
    DB::execute("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($newPass, PASSWORD_BCRYPT), $uid]);
}

// Refresh session so displayed name/email updates immediately
$_SESSION['user'] = DB::queryOne("SELECT * FROM users WHERE id = ?", [$uid]);
unset($_SESSION['user']['password_hash']);

flash_set('success', 'Profile updated successfully!');
redirect('/reseller/profile.php');
