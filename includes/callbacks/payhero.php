<?php
/**
 * Payhero Callback Handler - Shanfix Technology
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../actions/purchases.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) exit;

// Log callback for debugging
file_put_contents(__DIR__ . '/../../tmp/payhero_callback.log', $input . PHP_EOL, FILE_APPEND);

/**
 * Payhero v2 Callback structure:
 * {
 *   "success": true,
 *   "status": "SUCCESSFUL",
 *   "response": {
 *      "external_reference": "123",
 *      "amount": 100,
 *      ...
 *   }
 * }
 */

$status = $data['status'] ?? '';
$purchaseId = $data['response']['external_reference'] ?? null;

if ($status === 'SUCCESSFUL' && $purchaseId) {
    Purchase::complete($purchaseId);
    echo "Payment Completed";
} else {
    echo "Payment Status: " . $status;
}
