<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'CSRF Token mismatch.');
        redirect('/admin/ussd-codes.php');
    }

    $userId   = (int)($_POST['user_id'] ?? 0);
    $type     = $_POST['type'] ?? 'shared';
    $ussdCode = sanitize($_POST['ussd_code'] ?? '');
    $purpose  = sanitize($_POST['purpose'] ?? 'Delegated by Admin');

    if (!$userId || empty($ussdCode)) {
        flash_set('danger', 'User and USSD Code are required.');
        redirect('/admin/ussd-codes.php');
    }

    $userExists = DB::queryValue("SELECT id FROM users WHERE id = ?", [$userId]);
    if (!$userExists) {
        flash_set('danger', 'Selected user does not exist.');
        redirect('/admin/ussd-codes.php');
    }

    DB::insert(
        "INSERT INTO ussd_codes (user_id, type, requested_code, purpose, status, approved_at, created_at) VALUES (?, ?, ?, ?, 'approved', NOW(), NOW())",
        [$userId, $type, $ussdCode, $purpose]
    );

    flash_set('success', "USSD code $ussdCode has been successfully delegated to user #$userId.");
    redirect('/admin/ussd-codes.php');
}
