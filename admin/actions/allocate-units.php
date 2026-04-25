<?php
/**
 * Admin Action: Allocate Units - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = current_user();
    $toUser = (int)($_POST['to_user'] ?? 0);
    $units  = (float)($_POST['units'] ?? 0);
    $note   = sanitize($_POST['note'] ?? 'Manual allocation by Admin');

    if ($toUser && $units > 0) {
        $adminId = $user['id'];
        
        // Check Admin balance
        $admin = DB::queryOne("SELECT sms_units FROM users WHERE id = ?", [$adminId]);
        
        if ($admin['sms_units'] < $units) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Insufficient units in your Admin account.'];
        } else {
            // Transactional update: Deduct from Admin, Add to Target
            $d1 = DB::execute("UPDATE users SET sms_units = sms_units - ? WHERE id = ?", [$units, $adminId]);
            $d2 = DB::execute("UPDATE users SET sms_units = sms_units + ? WHERE id = ?", [$units, $toUser]);
            
            if ($d2) {
                // Log the transaction
                DB::insert("INSERT INTO purchases (user_id, units, amount, status, payment_method, transaction_ref, created_at) 
                           VALUES (?, ?, 0, 'completed', 'admin_allocation', ?, NOW())", 
                           [$toUser, $units, $note]);
                           
                $_SESSION['flash'] = ['type' => 'success', 'message' => "Successfully allocated " . number_format($units) . " units."];
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to allocate units.'];
            }
        }
    } else {
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Invalid unit amount or target user.'];
    }

    redirect($_SERVER['HTTP_REFERER'] ?? '/admin/units.php');
}
