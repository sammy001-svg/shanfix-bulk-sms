<?php
/**
 * Reseller Action: Allocate Units to Client - Shanfix Technology
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

$user   = current_user();
$uid    = $user['id'];
$toUser = (int)($_POST['to_user'] ?? 0);
$units  = (float)($_POST['units'] ?? 0);
$note   = sanitize($_POST['note'] ?? 'Transfer from Reseller');

if (!$toUser || $units <= 0) {
    flash_set('warning', 'Please provide a valid unit amount.');
    redirect(safe_referer('/reseller/clients.php'));
}

// Confirm target belongs to this reseller — authorization check before touching money
$target = DB::queryOne(
    "SELECT id, name FROM users WHERE id = ? AND parent_id = ? AND role = 'client'",
    [$toUser, $uid]
);
if (!$target) {
    flash_set('danger', 'Unauthorized: transfer only allowed to your own clients.');
    redirect(safe_referer('/reseller/clients.php'));
}

DB::beginTransaction();
try {
    // Atomic deduct from reseller: WHERE guard prevents going negative (no TOCTOU race)
    $deducted = DB::execute(
        "UPDATE users SET sms_units = sms_units - ? WHERE id = ? AND sms_units >= ?",
        [$units, $uid, $units]
    );
    if (!$deducted) {
        DB::rollback();
        flash_set('danger', 'Insufficient units in your balance for this transfer.');
        redirect(safe_referer('/reseller/clients.php'));
    }

    DB::execute("UPDATE users SET sms_units = sms_units + ? WHERE id = ?", [$units, $toUser]);
    DB::insert(
        "INSERT INTO purchases (user_id, units, amount, status, payment_method, transaction_ref, created_at)
         VALUES (?, ?, 0, 'completed', 'reseller_allocation', ?, NOW())",
        [$toUser, $units, $note]
    );
    DB::commit();

    flash_set('success', "Successfully transferred " . number_format($units) . " units to {$target['name']}.");
} catch (Exception $e) {
    DB::rollback();
    flash_set('danger', 'Failed to complete the transfer.');
}

redirect(safe_referer('/reseller/clients.php'));
