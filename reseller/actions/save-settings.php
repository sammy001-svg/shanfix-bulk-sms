<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/reseller/settings.php');
}

if (!csrf_verify()) {
    flash_set('danger', 'Security token mismatch. Please try again.');
    redirect('/reseller/settings.php');
}

$user   = current_user();
$userId = $user['id'];
$tab    = $_POST['tab'] ?? 'profile';

if ($tab === 'profile') {
    $name  = sanitize($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');

    if (!$name || !$email || !$phone) {
        flash_set('danger', 'All fields are required.');
        redirect('/reseller/settings.php?tab=profile');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('danger', 'Invalid email address.');
        redirect('/reseller/settings.php?tab=profile');
    }

    $existing = DB::queryOne("SELECT id FROM users WHERE email = ? AND id != ?", [strtolower($email), $userId]);
    if ($existing) {
        flash_set('danger', 'Email address is already in use by another account.');
        redirect('/reseller/settings.php?tab=profile');
    }

    try {
        DB::execute("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?", [$name, strtolower($email), $phone, $userId]);
        // Refresh session so navbar name/email updates immediately
        $_SESSION['user'] = DB::queryOne("SELECT * FROM users WHERE id = ?", [$userId]);
        unset($_SESSION['user']['password_hash']);
        flash_set('success', 'Profile updated successfully.');
    } catch (Exception $e) {
        flash_set('danger', 'Failed to update profile.');
    }

} elseif ($tab === 'security') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($newPass)) {
        flash_set('danger', 'Please enter a new password.');
        redirect('/reseller/settings.php?tab=security');
    }

    if (strlen($newPass) < 8) {
        flash_set('danger', 'New password must be at least 8 characters.');
        redirect('/reseller/settings.php?tab=security');
    }

    if ($newPass !== $confirmPass) {
        flash_set('danger', 'New passwords do not match.');
        redirect('/reseller/settings.php?tab=security');
    }

    $row = DB::queryOne("SELECT password_hash FROM users WHERE id = ?", [$userId]);
    if (!password_verify($currentPass, $row['password_hash'])) {
        flash_set('danger', 'Current password is incorrect.');
        redirect('/reseller/settings.php?tab=security');
    }

    try {
        DB::execute("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($newPass, PASSWORD_BCRYPT), $userId]);
        flash_set('success', 'Password changed successfully.');
    } catch (Exception $e) {
        flash_set('danger', 'Failed to change password.');
    }
}

redirect('/reseller/settings.php?tab=' . $tab);
