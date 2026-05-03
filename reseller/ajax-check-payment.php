<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$uid = $_SESSION['user_id'];
$purchaseId = (int)($_GET['id'] ?? 0);

if (!$purchaseId) {
    echo json_encode(['success' => false, 'error' => 'Missing ID']);
    exit;
}

$purchase = DB::queryOne("SELECT * FROM purchases WHERE id = ? AND user_id = ?", [$purchaseId, $uid]);

if (!$purchase) {
    echo json_encode(['success' => false, 'error' => 'Purchase not found']);
    exit;
}

$status = $purchase['status']; 

// Fetch fresh balance to update UI
$user = DB::queryOne("SELECT sms_units, whatsapp_balance FROM users WHERE id = ?", [$uid]);

echo json_encode([
    'success' => true,
    'status' => $status,
    'type' => $purchase['type'],
    'balance' => ($purchase['type'] === 'whatsapp') ? $user['whatsapp_balance'] : $user['sms_units']
]);
