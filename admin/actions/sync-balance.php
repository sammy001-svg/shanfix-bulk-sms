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
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
        redirect('/admin/index.php');
    }

    $liveBalance = Onfon::getBalance();

    if ($liveBalance !== null) {
        DB::execute("UPDATE users SET sms_units = ? WHERE role = 'admin'", [$liveBalance]);
        flash_set('success', 'Balance synchronized successfully! Live units: ' . number_format($liveBalance, 2));
    } else {
        flash_set('danger', 'Failed to connect to Onfon Media API. Check your credentials.');
    }

    redirect('/admin/index.php');
}
