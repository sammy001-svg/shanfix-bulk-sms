<?php
/**
 * Admin Action: Allocate Units - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/units.php');
}

if (!csrf_verify()) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
    redirect('/admin/units.php');
}

$adminUser = current_user();
$adminId   = $adminUser['id'];
$toUser    = (int)($_POST['to_user'] ?? 0);
$units     = (float)($_POST['units'] ?? 0);
$action    = ($_POST['action'] ?? '') === 'remove' ? 'remove' : 'add';
$note      = sanitize($_POST['note'] ?? 'Manual adjustment');

if (!$toUser || $units <= 0) {
    $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Invalid unit amount or target user.'];
    redirect(safe_referer('/admin/units.php'));
}

if ($action === 'add') {
    DB::beginTransaction();
    try {
        // Atomic deduct: fails if admin balance is insufficient (no TOCTOU race)
        $deducted = DB::execute(
            "UPDATE users SET sms_units = sms_units - ? WHERE id = ? AND sms_units >= ?",
            [$units, $adminId, $units]
        );
        if (!$deducted) {
            DB::rollback();
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Insufficient units in your Admin account to allocate.'];
            redirect(safe_referer('/admin/units.php'));
        }

        DB::execute("UPDATE users SET sms_units = sms_units + ? WHERE id = ?", [$units, $toUser]);
        DB::insert(
            "INSERT INTO unit_allocations (from_user, to_user, units, note, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$adminId, $toUser, $units, "Credit: $note"]
        );
        DB::commit();

        notify($toUser, 'Units Allocated', "Admin has credited " . number_format($units) . " units to your account.", 'success');
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Successfully added " . number_format($units) . " units."];
    } catch (Exception $e) {
        DB::rollback();
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to add units.'];
    }
} else {
    DB::beginTransaction();
    try {
        // Atomic deduct from target: fails if they don't have enough
        $deducted = DB::execute(
            "UPDATE users SET sms_units = sms_units - ? WHERE id = ? AND sms_units >= ?",
            [$units, $toUser, $units]
        );
        if (!$deducted) {
            DB::rollback();
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'User does not have enough units to remove that amount.'];
            redirect(safe_referer('/admin/units.php'));
        }

        DB::execute("UPDATE users SET sms_units = sms_units + ? WHERE id = ?", [$units, $adminId]);
        DB::insert(
            "INSERT INTO unit_allocations (from_user, to_user, units, note, created_at) VALUES (?, ?, ?, ?, NOW())",
            [$adminId, $toUser, -$units, "Debit: $note"]
        );
        DB::commit();

        notify($toUser, 'Units Adjusted', "Admin has debited " . number_format($units) . " units from your account.", 'warning');
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Successfully removed " . number_format($units) . " units."];
    } catch (Exception $e) {
        DB::rollback();
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to remove units.'];
    }
}

redirect(safe_referer('/admin/units.php'));
