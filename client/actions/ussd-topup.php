<?php
/**
 * Action: USSD Wallet Top Up (Client) - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/gateways/kopokopo.php';
require_role('client');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
        redirect(safe_referer('/client/ussd-wallet.php'));
    }

    $user = current_user();
    $amount = (float)($_POST['amount'] ?? 0);
    $phone = sanitize($_POST['phone'] ?? '');

    if ($amount < 10) {
        flash_set('danger', 'Minimum top up amount is KES 10.');
        redirect(safe_referer('/client/ussd-wallet.php'));
    }

    if (empty($phone)) {
        flash_set('danger', 'Phone number is required.');
        redirect(safe_referer('/client/ussd-wallet.php'));
    }

    // 1. Record pending transaction
    $transId = DB::insert("
        INSERT INTO ussd_transactions (user_id, amount, type, status, description, reference, created_at)
        VALUES (?, ?, 'credit', 'pending', 'USSD Wallet Top Up (STK Push)', ?, NOW())
    ", [$user['id'], $amount, $phone]);

    if ($transId) {
        // 2. Initiate STK Push
        $externalRef = "USD" . $transId;
        $res = KopoKopo::initiateSTKPush($phone, $amount, $externalRef);

        if ($res['success']) {
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'id' => $transId]);
                exit;
            }
            flash_set('success', 'STK Push initiated! Please enter your PIN on your phone.');
        } else {
            DB::execute("UPDATE ussd_transactions SET status = 'failed' WHERE id = ?", [$transId]);
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $res['error']]);
                exit;
            }
            flash_set('danger', 'Failed to initiate STK Push: ' . $res['error']);
        }
    } else {
        flash_set('danger', 'Internal database error. Please try again.');
    }

    redirect(safe_referer('/client/ussd-wallet.php'));
}
