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

if (!csrf_verify()) {
    flash_set('danger', 'Invalid security token.');
    redirect('/admin/clients.php');
}

$name     = sanitize($_POST['name'] ?? '');
$email    = sanitize($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role     = $_POST['role'] ?? '';
$units    = (float)($_POST['initial_units'] ?? 0);

// Required field check
if (!$name || !$email || !$password) {
    flash_set('danger', 'Name, email, and password are required.');
    redirect(safe_referer('/admin/clients.php'));
}

// Whitelist role — admin accounts may only be created directly in the DB
if (!in_array($role, ['reseller', 'client'], true)) {
    flash_set('danger', 'Invalid role selected.');
    redirect(safe_referer('/admin/clients.php'));
}

// Basic email format check
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('danger', 'Invalid email address format.');
    redirect(safe_referer('/admin/clients.php'));
}

// Units must not be negative
if ($units < 0) {
    flash_set('danger', 'Initial units cannot be negative.');
    redirect(safe_referer('/admin/clients.php'));
}

$existing = DB::queryOne("SELECT id FROM users WHERE email = ?", [$email]);
if ($existing) {
    flash_set('danger', 'Email already registered.');
    redirect(safe_referer('/admin/clients.php'));
}

$hash    = password_hash($password, PASSWORD_BCRYPT);
DB::execute(
    "INSERT INTO users (name, email, password_hash, role, sms_units, status, created_at)
     VALUES (?, ?, ?, ?, ?, 'active', NOW())",
    [$name, $email, $hash, $role, $units]
);

flash_set('success', ucfirst($role) . ' account created successfully.');

$target = ($role === 'reseller') ? '/admin/clients.php?role=reseller' : '/admin/clients.php';
redirect($target);
