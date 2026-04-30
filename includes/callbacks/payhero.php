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
file_put_contents(__DIR__ . '/../../tmp/payhero_callback.log', "[".date('Y-m-d H:i:s')."] " . $input . PHP_EOL, FILE_APPEND);

/**
 * Payhero v2 Callback structure can vary. 
 * We check multiple possible keys for the purchase ID.
 */
$status = $data['status'] ?? ($data['success'] ? 'SUCCESSFUL' : 'FAILED');
$purchaseId = $data['external_reference'] 
           ?? $data['response']['external_reference'] 
           ?? null;

// If still null, check if it's in the root as 'reference'
if (!$purchaseId) {
    $purchaseId = $data['reference'] ?? null;
}

error_log("Payhero Callback: Status=$status, PurchaseID=$purchaseId");

if (($status === 'SUCCESSFUL' || $status === 'SUCCESS') && $purchaseId) {
    $completed = Purchase::complete($purchaseId);
    if ($completed) {
        echo "Payment Completed and Units Updated";
    } else {
        echo "Payment Logged but Unit Update Failed";
    }
} else {
    echo "Payment Status: " . $status;
}
