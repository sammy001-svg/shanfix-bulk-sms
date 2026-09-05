<?php
/**
 * Action: USSD Wallet Top Up - Shanfix Technology
 * Handles STK Push initiation for USSD balance.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/gateways/kopokopo.php';
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
        redirect(safe_referer('/reseller/ussd-wallet.php'));
    }

    $user = current_user();
    $amount = (float)($_POST['amount'] ?? 0);
    $phone = sanitize($_POST['phone'] ?? '');

    if ($amount < 10) {
        flash_set('danger', 'Minimum top up amount is KES 10.');
        redirect(safe_referer('/reseller/ussd-wallet.php'));
    }

    if (empty($phone)) {
        flash_set('danger', 'Phone number is required.');
        redirect(safe_referer('/reseller/ussd-wallet.php'));
    }

    // 1. Record pending transaction
    $transId = DB::insert("
        INSERT INTO ussd_transactions (user_id, amount, type, status, description, reference, created_at)
        VALUES (?, ?, 'credit', 'pending', 'USSD Wallet Top Up (STK Push)', ?, NOW())
    ", [$user['id'], $amount, $phone]);

    if ($transId) {
        // 2. Initiate STK Push
        // We use 'USD' prefix to distinguish USSD payments from SMS payments
        $externalRef = "USD" . $transId;
        $res = KopoKopo::initiateSTKPush($phone, $amount, $externalRef);

        if ($res['success']) {
            flash_set('success', 'STK Push initiated! Please enter your PIN on your phone.');
        } else {
            DB::execute("UPDATE ussd_transactions SET status = 'failed' WHERE id = ?", [$transId]);
            flash_set('danger', 'Failed to initiate STK Push: ' . $res['error']);
        }
    } else {
        flash_set('danger', 'Internal database error. Please try again.');
    }

    redirect(safe_referer('/reseller/ussd-wallet.php'));
}
