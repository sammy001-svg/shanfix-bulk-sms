<?php
/**
 * Client Action: Update Profile - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('client');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid      = $_SESSION['user_id'];
    $name     = sanitize($_POST['name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $phone    = sanitize($_POST['phone'] ?? '');
    $company  = sanitize($_POST['company'] ?? '');
    $current  = $_POST['current_password'] ?? '';
    $newPass  = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Verify current password
    $user = DB::queryOne("SELECT password_hash FROM users WHERE id = ?", [$uid]);
    if (!password_verify($current, $user['password_hash'])) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Incorrect current password.'];
        redirect('/client/profile.php');
    }

    // Check email uniqueness
    $existing = DB::queryOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $uid]);
    if ($existing) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Email address is already in use.'];
        redirect('/client/profile.php');
    }

    // Update basic info
    $sql = "UPDATE users SET name = ?, email = ?, phone = ?, company = ? WHERE id = ?";
    DB::execute($sql, [$name, $email, $phone, $company, $uid]);

    // Update password if requested
    if ($newPass) {
        if ($newPass !== $confirm) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'New passwords do not match.'];
            redirect('/client/profile.php');
        }
        $hash = password_hash($newPass, PASSWORD_BCRYPT);
        DB::execute("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $uid]);
    }

    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile updated successfully!'];
    redirect('/client/profile.php');
}
