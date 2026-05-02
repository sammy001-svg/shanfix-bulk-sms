<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'CSRF Token mismatch.');
        redirect('/admin/ussd-requests.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $finalCode = sanitize($_POST['final_code'] ?? '');

    if (empty($finalCode)) {
        flash_set('danger', 'USSD code is required for approval.');
        redirect('/admin/ussd-requests.php');
    }

    $request = DB::queryOne("SELECT * FROM ussd_codes WHERE id = ?", [$id]);
    if (!$request) {
        flash_set('danger', 'Request not found.');
        redirect('/admin/ussd-requests.php');
    }

    DB::execute(
        "UPDATE ussd_codes SET status = 'approved', requested_code = ?, approved_at = NOW() WHERE id = ?",
        [$finalCode, $id]
    );

    // Optional: Log action or send notification
    // Log::admin("Approved USSD Request ID: $id with code $finalCode");
    
    flash_set('success', "USSD request for user #{$request['user_id']} has been approved.");
    redirect('/admin/ussd-requests.php?status=approved');
}
