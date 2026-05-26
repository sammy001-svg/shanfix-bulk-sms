<?php
/**
 * Action: Create Client - Shanfix Technology (Reseller Version)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/reseller/clients.php');
}

if (!csrf_verify()) {
    flash_set('danger', 'Invalid security token.');
    redirect('/reseller/clients.php');
}

$user     = current_user();
$name     = sanitize($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$units    = (float)($_POST['initial_units'] ?? 0);

if (!$name || !$email || !$password) {
    flash_set('danger', 'All fields are required.');
    redirect(safe_referer('/reseller/clients.php'));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_set('danger', 'Invalid email address.');
    redirect(safe_referer('/reseller/clients.php'));
}

if (strlen($password) < 8) {
    flash_set('danger', 'Password must be at least 8 characters.');
    redirect(safe_referer('/reseller/clients.php'));
}

if ($units < 0) {
    flash_set('danger', 'Initial units cannot be negative.');
    redirect(safe_referer('/reseller/clients.php'));
}

$existing = DB::queryOne("SELECT id FROM users WHERE email = ?", [strtolower($email)]);
if ($existing) {
    flash_set('danger', 'Email already registered.');
    redirect(safe_referer('/reseller/clients.php'));
}

$hash = password_hash($password, PASSWORD_BCRYPT);

DB::beginTransaction();
try {
    if ($units > 0) {
        // Atomic deduct from reseller — guard prevents going negative
        $deducted = DB::execute(
            "UPDATE users SET sms_units = sms_units - ? WHERE id = ? AND sms_units >= ?",
            [$units, $user['id'], $units]
        );
        if (!$deducted) {
            DB::rollback();
            flash_set('danger', 'Insufficient units to allocate to client.');
            redirect(safe_referer('/reseller/clients.php'));
        }
    }

    $clientId = DB::insert(
        "INSERT INTO users (name, email, password_hash, role, sms_units, status, parent_id, created_at)
         VALUES (?, ?, ?, 'client', ?, 'active', ?, NOW())",
        [$name, strtolower($email), $hash, $units, $user['id']]
    );

    if (!$clientId) {
        DB::rollback();
        flash_set('danger', 'Failed to create client. Database error.');
        redirect(safe_referer('/reseller/clients.php'));
    }

    DB::commit();
    flash_set('success', "Client account created successfully!" . ($units > 0 ? " " . number_format($units) . " units transferred." : ""));
} catch (Exception $e) {
    DB::rollback();
    flash_set('danger', 'Failed to create client. Please try again.');
}

redirect('/reseller/clients.php');
