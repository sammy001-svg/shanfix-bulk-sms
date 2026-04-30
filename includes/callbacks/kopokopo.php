<?php
/**
 * Kopo Kopo Callback Handler - Shanfix Technology
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../actions/purchases.php';

// Get JSON post body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) exit;

// For logging purposes in dev
file_put_contents(__DIR__ . '/../../tmp/kk_callback.log', $input . PHP_EOL, FILE_APPEND);

$status = $data['data']['attributes']['status'] ?? '';
$purchaseId = $data['data']['attributes']['metadata']['reference'] ?? null;

if ($status === 'Success' && $purchaseId) {
    Purchase::complete($purchaseId);
    echo "Payment Completed";
} else {
    echo "Payment Status: " . $status;
}
