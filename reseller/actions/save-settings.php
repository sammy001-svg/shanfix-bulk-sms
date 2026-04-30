<?php
/**
 * Action: Save Reseller Settings
 */
require_once __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../settings.php');
    exit;
}

$userId = $_SESSION['user_id'];
$tab = $_POST['tab'] ?? 'profile';

if ($tab === 'profile') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');

    if (!$name || !$email || !$phone) {
        set_flash('error', 'All fields are required.');
        header('Location: ../settings.php?tab=profile');
        exit;
    }

    // Check if email is already taken by another user
    $existing = DB::queryOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
    if ($existing) {
        set_flash('error', 'Email address is already in use by another account.');
        header('Location: ../settings.php?tab=profile');
        exit;
    }

    $updated = DB::execute("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?", [$name, $email, $phone, $userId]);
    
    if ($updated !== false) {
        set_flash('success', 'Profile updated successfully.');
    } else {
        set_flash('error', 'Failed to update profile.');
    }
} elseif ($tab === 'security') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($newPass)) {
        set_flash('error', 'Please enter a new password.');
        header('Location: ../settings.php?tab=security');
        exit;
    }

    if ($newPass !== $confirmPass) {
        set_flash('error', 'New passwords do not match.');
        header('Location: ../settings.php?tab=security');
        exit;
    }

    // Verify current password
    $user = DB::queryOne("SELECT password_hash FROM users WHERE id = ?", [$userId]);
    if (!password_verify($currentPass, $user['password_hash'])) {
        set_flash('error', 'Current password is incorrect.');
        header('Location: ../settings.php?tab=security');
        exit;
    }

    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $updated = DB::execute("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $userId]);

    if ($updated !== false) {
        set_flash('success', 'Password changed successfully.');
    } else {
        set_flash('error', 'Failed to change password.');
    }
}

header('Location: ../settings.php?tab=' . $tab);
exit;
