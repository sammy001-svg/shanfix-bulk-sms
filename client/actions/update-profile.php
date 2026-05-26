<?php
/**
 * Client Action: Update Profile - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('client');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/client/profile.php');
}

if (!csrf_verify()) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
    redirect('/client/profile.php');
}

$uid     = $_SESSION['user_id'];
$name    = sanitize($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = sanitize($_POST['phone'] ?? '');
$company = sanitize($_POST['company'] ?? '');
$current = $_POST['current_password'] ?? '';
$newPass = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if (!$name) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Name is required.'];
    redirect('/client/profile.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid email address.'];
    redirect('/client/profile.php');
}

// Verify current password before allowing any changes
$user = DB::queryOne("SELECT password_hash FROM users WHERE id = ?", [$uid]);
if (!password_verify($current, $user['password_hash'])) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Incorrect current password.'];
    redirect('/client/profile.php');
}

// Email uniqueness check
$existing = DB::queryOne("SELECT id FROM users WHERE email = ? AND id != ?", [strtolower($email), $uid]);
if ($existing) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Email address is already in use.'];
    redirect('/client/profile.php');
}

DB::execute(
    "UPDATE users SET name = ?, email = ?, phone = ?, company = ? WHERE id = ?",
    [$name, strtolower($email), $phone, $company, $uid]
);

if ($newPass !== '') {
    if (strlen($newPass) < 8) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'New password must be at least 8 characters.'];
        redirect('/client/profile.php');
    }
    if ($newPass !== $confirm) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'New passwords do not match.'];
        redirect('/client/profile.php');
    }
    DB::execute("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($newPass, PASSWORD_BCRYPT), $uid]);
}

$_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile updated successfully!'];
redirect('/client/profile.php');
