<?php
/**
 * Payment status poll — client portal.
 *
 * Called every 2s by ShanfixSTK.checkStatus while the customer is entering
 * their M-Pesa PIN.
 *
 * When the row is still pending this does NOT just report "pending" — it asks
 * Kopo Kopo directly for the outcome and credits the account there and then.
 * That way units land instantly even if the webhook is delayed, blocked by a
 * firewall, or misconfigured; the webhook simply becomes the fast path rather
 * than the only path.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/actions/payment-reconcile.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$uid  = $_SESSION['user_id'];
$id   = (int)($_GET['id'] ?? 0);
$type = sanitize($_GET['type'] ?? 'purchase'); // 'purchase' or 'ussd'

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Missing ID']);
    exit;
}

$gatewayError = null;

if ($type === 'ussd') {
    // Ownership is checked before any reconciliation so one user can never
    // trigger a gateway call against another user's transaction.
    $tx = DB::queryOne("SELECT * FROM ussd_transactions WHERE id = ? AND user_id = ?", [$id, $uid]);
    if (!$tx) {
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        exit;
    }

    $status = $tx['status'];
    if ($status === 'pending') {
        $res          = PaymentReconcile::ussd($id);
        $status       = $res['status'];
        $gatewayError = $res['error'] ?? null;
    }
} else {
    $purchase = DB::queryOne("SELECT * FROM purchases WHERE id = ? AND user_id = ?", [$id, $uid]);
    if (!$purchase) {
        echo json_encode(['success' => false, 'error' => 'Purchase not found']);
        exit;
    }

    $status = $purchase['status'];
    if ($status === 'pending') {
        $res          = PaymentReconcile::purchase($id);
        $status       = $res['status'];
        $gatewayError = $res['error'] ?? null;
    }
}

// Read the balance after reconciling so a just-credited payment is reflected.
$user = DB::queryOne("SELECT sms_units, whatsapp_balance, ussd_balance FROM users WHERE id = ?", [$uid]);

echo json_encode([
    'success'  => true,
    'status'   => $status,
    'balances' => [
        'sms'      => $user['sms_units'],
        'whatsapp' => $user['whatsapp_balance'],
        'ussd'     => $user['ussd_balance'],
    ],
    // Diagnostic only — the UI keeps polling regardless.
    'gateway_note' => $gatewayError,
]);
