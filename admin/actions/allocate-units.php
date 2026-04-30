<?php
/**
 * Admin Action: Allocate Units - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminUser = current_user();
    $adminId   = $adminUser['id'];
    $toUser    = (int)($_POST['to_user'] ?? 0);
    $units     = (float)($_POST['units'] ?? 0);
    $action    = $_POST['action'] ?? 'add';
    $note      = sanitize($_POST['note'] ?? 'Manual adjustment');

    if ($toUser && $units > 0) {
        if ($action === 'add') {
            // Check Admin balance
            $admin = DB::queryOne("SELECT sms_units FROM users WHERE id = ?", [$adminId]);
            if ($admin['sms_units'] < $units) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Insufficient units in your Admin account to allocate.'];
            } else {
                DB::beginTransaction();
                try {
                    DB::execute("UPDATE users SET sms_units = sms_units - ? WHERE id = ?", [$units, $adminId]);
                    DB::execute("UPDATE users SET sms_units = sms_units + ? WHERE id = ?", [$units, $toUser]);
                    
                    DB::insert("INSERT INTO purchases (user_id, units, amount, status, payment_method, transaction_ref, created_at) 
                               VALUES (?, ?, 0, 'completed', 'admin_allocation', ?, NOW())", 
                               [$toUser, $units, "Credit: $note"]);
                               
                    DB::commit();
                    $_SESSION['flash'] = ['type' => 'success', 'message' => "Successfully added " . number_format($units) . " units."];
                } catch (Exception $e) {
                    DB::rollback();
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to add units.'];
                }
            }
        } else {
            // REMOVE UNITS
            $target = DB::queryOne("SELECT sms_units FROM users WHERE id = ?", [$toUser]);
            if ($target['sms_units'] < $units) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'User does not have enough units to remove that amount.'];
            } else {
                DB::beginTransaction();
                try {
                    DB::execute("UPDATE users SET sms_units = sms_units - ? WHERE id = ?", [$units, $toUser]);
                    DB::execute("UPDATE users SET sms_units = sms_units + ? WHERE id = ?", [$units, $adminId]); // Return to pool
                    
                    DB::insert("INSERT INTO purchases (user_id, units, amount, status, payment_method, transaction_ref, created_at) 
                               VALUES (?, ?, 0, 'completed', 'admin_debit', ?, NOW())", 
                               [$toUser, -$units, "Debit: $note"]);
                               
                    DB::commit();
                    $_SESSION['flash'] = ['type' => 'success', 'message' => "Successfully removed " . number_format($units) . " units."];
                } catch (Exception $e) {
                    DB::rollback();
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to remove units.'];
                }
            }
        }
    } else {
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Invalid unit amount or target user.'];
    }

    redirect($_SERVER['HTTP_REFERER'] ?? '/admin/units.php');
}
