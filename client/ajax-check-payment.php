<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$uid = $_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);
$type = sanitize($_GET['type'] ?? 'purchase'); // 'purchase' or 'ussd'

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Missing ID']);
    exit;
}

if ($type === 'ussd') {
    $tx = DB::queryOne("SELECT * FROM ussd_transactions WHERE id = ? AND user_id = ?", [$id, $uid]);
    if (!$tx) {
        echo json_encode(['success' => false, 'error' => 'Transaction not found']);
        exit;
    }
    $status = $tx['status'];
} else {
    $purchase = DB::queryOne("SELECT * FROM purchases WHERE id = ? AND user_id = ?", [$id, $uid]);
    if (!$purchase) {
        echo json_encode(['success' => false, 'error' => 'Purchase not found']);
        exit;
    }
    $status = $purchase['status'];
}

// Fetch fresh balance
$user = DB::queryOne("SELECT sms_units, whatsapp_balance, ussd_balance FROM users WHERE id = ?", [$uid]);

echo json_encode([
    'success' => true,
    'status' => $status,
    'balances' => [
        'sms' => $user['sms_units'],
        'whatsapp' => $user['whatsapp_balance'],
        'ussd' => $user['ussd_balance']
    ]
]);
