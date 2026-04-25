<?php
/**
 * Admin Action: Sync Onfon Balance - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/gateways/onfon.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
        redirect('/admin/index.php');
    }

    $liveBalance = Onfon::getBalance();

    if ($liveBalance !== null) {
        $admin = current_user();
        DB::execute("UPDATE users SET sms_units = ? WHERE role = 'admin'", [$liveBalance]);
        
        $_SESSION['flash'] = [
            'type' => 'success', 
            'message' => 'Balance synchronized successfully! Live units: ' . number_format($liveBalance, 2)
        ];
    } else {
        $_SESSION['flash'] = [
            'type' => 'danger', 
            'message' => 'Failed to connect to Onfon Media API. Check your credentials.'
        ];
    }

    redirect('/admin/index.php');
}
