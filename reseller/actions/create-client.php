<?php
/**
 * Action: Create Client - Shanfix Technology (Reseller Version)
 * Resellers can only create 'client' role users.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = current_user();
    $name     = sanitize($_POST['name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $units    = (float)($_POST['initial_units'] ?? 0);

    if (!$name || !$email || !$password) {
        flash_set('danger', 'All fields are required.');
        redirect($_SERVER['HTTP_REFERER'] ?? '../clients.php');
    }

    // Check if email exists
    $existing = DB::queryOne("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        flash_set('danger', 'Email already registered.');
        redirect($_SERVER['HTTP_REFERER'] ?? '../clients.php');
    }

    // Cost logic for Resellers: Deduction from their own units? 
    // Usually yes, but for now we follow the 'creation' flow.
    if (($user['sms_units'] ?? 0) < $units) {
        flash_set('danger', 'Insufficient units to allocate to client.');
        redirect($_SERVER['HTTP_REFERER'] ?? '../clients.php');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Deduct from reseller
    DB::execute("UPDATE users SET sms_units = sms_units - ? WHERE id = ?", [$units, $user['id']]);

    // Create client
    $success = DB::insert("INSERT INTO users (name, email, password_hash, role, sms_units, status, parent_id, created_at) 
                          VALUES (?, ?, ?, 'client', ?, 'active', ?, NOW())", 
                          [$name, $email, $hash, $units, $user['id']]);

    if ($success) {
        flash_set('success', "Client account created successfully! " . number_format($units) . " units transferred.");
    } else {
        flash_set('danger', 'Failed to create client. Database error.');
    }

    redirect('/reseller/clients.php');
}
