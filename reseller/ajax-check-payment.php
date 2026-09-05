<?php
/**
 * Payment status poll — reseller portal.
 *
 * Same contract as the client endpoint: while a purchase is still pending this
 * asks Kopo Kopo for the outcome and credits the account immediately, so units
 * land even if the webhook never arrives.
 *
 * The response keeps this portal's original shape ('type' + single 'balance')
 * because reseller/layout.php reads those fields.
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

    $user = DB::queryOne("SELECT sms_units, whatsapp_balance, ussd_balance FROM users WHERE id = ?", [$uid]);
    echo json_encode([
        'success'      => true,
        'status'       => $status,
        'type'         => 'ussd',
        'balance'      => $user['ussd_balance'],
        'gateway_note' => $gatewayError,
    ]);
    exit;
}

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

// Read the balance after reconciling so a just-credited payment is reflected.
$user = DB::queryOne("SELECT sms_units, whatsapp_balance FROM users WHERE id = ?", [$uid]);

echo json_encode([
    'success'      => true,
    'status'       => $status,
    'type'         => $purchase['type'],
    'balance'      => ($purchase['type'] === 'whatsapp') ? $user['whatsapp_balance'] : $user['sms_units'],
    'gateway_note' => $gatewayError,
]);
