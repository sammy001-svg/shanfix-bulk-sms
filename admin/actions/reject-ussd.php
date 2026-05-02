<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'CSRF Token mismatch.');
        redirect('/admin/ussd-requests.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $reason = sanitize($_POST['reason'] ?? '');

    if (empty($reason)) {
        flash_set('danger', 'Rejection reason is required.');
        redirect('/admin/ussd-requests.php');
    }

    $request = DB::queryOne("SELECT * FROM ussd_codes WHERE id = ?", [$id]);
    if (!$request) {
        flash_set('danger', 'Request not found.');
        redirect('/admin/ussd-requests.php');
    }

    DB::execute(
        "UPDATE ussd_codes SET status = 'rejected', reject_reason = ? WHERE id = ?",
        [$reason, $id]
    );

    flash_set('warning', "USSD request for user #{$request['user_id']} has been rejected.");
    redirect('/admin/ussd-requests.php?status=rejected');
}
