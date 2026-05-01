<?php
/**
 * Payhero Public Webhook Handler
 * Located in root to avoid firewall blocks on 'includes' folder.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/actions/purchases.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo "Payhero Webhook is Active. Waiting for POST data...";
    exit;
}

// Log callback for debugging
$logFile = __DIR__ . '/tmp/payhero_callback.log';
if (!is_dir(__DIR__ . '/tmp')) mkdir(__DIR__ . '/tmp', 0777, true);
file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] RAW: " . $input . PHP_EOL, FILE_APPEND);

$status = $data['response']['Status'] ?? $data['status'] ?? ($data['success'] ? 'Successful' : 'Failed');
$purchaseId = $data['response']['ExternalReference'] 
           ?? $data['external_reference']
           ?? $data['ExternalReference'] 
           ?? $data['reference']
           ?? $data['CheckoutRequestID'] // Fallback to CheckoutRequestID if needed
           ?? null;

file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] PARSED: Status=$status, ID=$purchaseId" . PHP_EOL, FILE_APPEND);
error_log("Payhero Webhook Received: Status=$status, ID=$purchaseId");

if (in_array(strtoupper((string)$status), ['SUCCESSFUL', 'SUCCESS']) && $purchaseId) {
    $completed = Purchase::complete($purchaseId);
    if ($completed) {
        echo "OK - Units Updated for Purchase #$purchaseId";
    } else {
        echo "ERROR - Purchase::complete failed for ID $purchaseId.";
    }
} else {
    echo "IGNORE - Status: $status, ID: $purchaseId";
}
