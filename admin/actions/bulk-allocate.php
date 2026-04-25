<?php
/**
 * Admin Action: Bulk Allocate Units - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
        redirect('/admin/units.php');
    }

    $adminUser = current_user();
    $targetGroup = sanitize($_POST['target_group'] ?? '');
    $unitsPerUser = (float)($_POST['units'] ?? 0);
    $note = sanitize($_POST['note'] ?? 'Bulk allocation by Admin');

    if ($unitsPerUser <= 0) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid unit amount.'];
        redirect('/admin/units.php');
    }

    // Determine target users
    $roleQuery = match($targetGroup) {
        'resellers' => "role = 'reseller'",
        'clients'   => "role = 'client'",
        'all'       => "role IN ('client', 'reseller')",
        default     => ""
    };

    if (!$roleQuery) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid target group.'];
        redirect('/admin/units.php');
    }

    $targetUsers = DB::query("SELECT id FROM users WHERE $roleQuery AND status = 'active'");
    $userCount = count($targetUsers);

    if ($userCount === 0) {
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'No active users found in the selected group.'];
        redirect('/admin/units.php');
    }

    $totalNeeded = $userCount * $unitsPerUser;

    // Check Admin balance
    $admin = DB::queryOne("SELECT sms_units FROM users WHERE id = ?", [$adminUser['id']]);
    if ($admin['sms_units'] < $totalNeeded) {
        $_SESSION['flash'] = [
            'type' => 'danger', 
            'message' => "Insufficient units. You need " . number_format($totalNeeded) . " units, but only have " . number_format($admin['sms_units']) . " available."
        ];
        redirect('/admin/units.php');
    }

    try {
        // Start "Transaction" (Manual batch logic)
        // 1. Deduct from Admin
        DB::execute("UPDATE users SET sms_units = sms_units - ? WHERE id = ?", [$totalNeeded, $adminUser['id']]);

        // 2. Add to Users & Log
        foreach ($targetUsers as $u) {
            DB::execute("UPDATE users SET sms_units = sms_units + ? WHERE id = ?", [$unitsPerUser, $u['id']]);
            
            DB::insert("INSERT INTO purchases (user_id, units, amount, status, payment_method, transaction_ref, created_at) 
                       VALUES (?, ?, 0, 'completed', 'admin_bulk_allocation', ?, NOW())", 
                       [$u['id'], $unitsPerUser, $note]);
        }

        $_SESSION['flash'] = [
            'type' => 'success', 
            'message' => "Successfully allocated " . number_format($unitsPerUser) . " units to each of the $userCount users (Total: " . number_format($totalNeeded) . ")."
        ];
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'An error occurred during bulk allocation.'];
    }

    redirect('/admin/units.php');
}
