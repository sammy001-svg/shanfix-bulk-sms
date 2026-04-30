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
                    
                    // RECORD IN MYSQL (unit_allocations table)
                    DB::insert("INSERT INTO unit_allocations (from_user, to_user, units, note, created_at) 
                               VALUES (?, ?, ?, ?, NOW())", 
                               [$adminId, $toUser, $units, "Credit: $note"]);
                               
                    DB::commit();
                    
                    // Notify User
                    notify($toUser, 'Units Allocated', "Admin has credited " . number_format($units) . " units to your account.", 'success');
                    
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
                    
                    // RECORD IN MYSQL (unit_allocations table)
                    DB::insert("INSERT INTO unit_allocations (from_user, to_user, units, note, created_at) 
                               VALUES (?, ?, ?, ?, NOW())", 
                               [$adminId, $toUser, -$units, "Debit: $note"]);
                               
                    DB::commit();
                    
                    // Notify User
                    notify($toUser, 'Units Adjusted', "Admin has debited " . number_format($units) . " units from your account.", 'warning');
                    
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
