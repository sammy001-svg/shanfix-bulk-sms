<?php
/**
 * Admin Action: Create User - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = sanitize($_POST['name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = sanitize($_POST['role'] ?? 'client');
    $units    = (float)($_POST['initial_units'] ?? 0);

    if (!$name || !$email || !$password) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'All fields are required.'];
        redirect($_SERVER['HTTP_REFERER'] ?? '/admin/clients.php');
    }

    // Check if email exists
    $existing = DB::queryOne("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Email already registered.'];
        redirect($_SERVER['HTTP_REFERER'] ?? '/admin/clients.php');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    
    $success = DB::execute("INSERT INTO users (name, email, password_hash, role, sms_units, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())", 
                         [$name, $email, $hash, $role, $units]);

    if ($success) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => ucfirst($role) . " account created successfully!"];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to create user. Dashboard database error.'];
    }

    // Determine target page based on role
    $target = ($role === 'reseller') ? '/admin/clients.php?role=reseller' : '/admin/clients.php';
    redirect($target);
}
