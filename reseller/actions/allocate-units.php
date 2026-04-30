<?php
/**
 * Reseller Action: Allocate Units to Client - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = current_user();
    $toUser = (int)($_POST['to_user'] ?? 0);
    $units  = (float)($_POST['units'] ?? 0);
    $note   = sanitize($_POST['note'] ?? 'Transfer from Reseller');
    $uid    = $user['id'];

    if ($toUser && $units > 0) {
        // Verify target user belongs to this reseller
        $target = DB::queryOne("SELECT id, name, parent_id, sms_units FROM users WHERE id = ? AND parent_id = ?", [$toUser, $uid]);
        
        if (!$target) {
            flash_set('danger', 'Unauthorized: Direct transfer only allowed to your own clients.');
        } elseif ($user['sms_units'] < $units) {
            flash_set('danger', 'Insufficient units in your balance for this transfer.');
        } else {
            // Transactional update
            $d1 = DB::execute("UPDATE users SET sms_units = sms_units - ? WHERE id = ?", [$units, $uid]);
            $d2 = DB::execute("UPDATE users SET sms_units = sms_units + ? WHERE id = ?", [$units, $toUser]);
            
            if ($d2) {
                // Log as a purchase/allocation
                DB::insert("INSERT INTO purchases (user_id, units, amount, status, payment_method, transaction_ref, created_at) 
                           VALUES (?, ?, 0, 'completed', 'reseller_allocation', ?, NOW())", 
                           [$toUser, $units, $note]);
                           
                flash_set('success', "Successfully transferred " . number_format($units) . " units to " . $target['name'] . ".");
            } else {
                flash_set('danger', 'Failed to complete the transfer.');
            }
        }
    } else {
        flash_set('warning', 'Please provide a valid unit amount.');
    }

    redirect($_SERVER['HTTP_REFERER'] ?? '/reseller/clients.php');
}
