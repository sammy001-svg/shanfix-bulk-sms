<?php
/**
 * Admin Action: Create User - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/clients.php');
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
    redirect('/admin/clients.php');
}

$name     = sanitize($_POST['name'] ?? '');
$email    = sanitize($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role     = $_POST['role'] ?? '';
$units    = (float)($_POST['initial_units'] ?? 0);

// Required field check
if (!$name || !$email || !$password) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Name, email, and password are required.'];
    redirect(safe_referer('/admin/clients.php'));
}

// Whitelist role — admin accounts may only be created directly in the DB
if (!in_array($role, ['reseller', 'client'], true)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid role selected.'];
    redirect(safe_referer('/admin/clients.php'));
}

// Basic email format check
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid email address format.'];
    redirect(safe_referer('/admin/clients.php'));
}

// Units must not be negative
if ($units < 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Initial units cannot be negative.'];
    redirect(safe_referer('/admin/clients.php'));
}

$existing = DB::queryOne("SELECT id FROM users WHERE email = ?", [$email]);
if ($existing) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Email already registered.'];
    redirect(safe_referer('/admin/clients.php'));
}

$hash    = password_hash($password, PASSWORD_BCRYPT);
$success = DB::execute(
    "INSERT INTO users (name, email, password_hash, role, sms_units, status, created_at)
     VALUES (?, ?, ?, ?, ?, 'active', NOW())",
    [$name, $email, $hash, $role, $units]
);

if ($success) {
    $_SESSION['flash'] = ['type' => 'success', 'message' => ucfirst($role) . ' account created successfully.'];
} else {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to create user. Please try again.'];
}

$target = ($role === 'reseller') ? '/admin/clients.php?role=reseller' : '/admin/clients.php';
redirect($target);
