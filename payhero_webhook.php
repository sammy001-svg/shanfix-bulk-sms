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
file_put_contents(__DIR__ . '/payhero_callback.log', "[".date('Y-m-d H:i:s')."] " . $input . PHP_EOL, FILE_APPEND);

$status = $data['status'] ?? ($data['success'] ? 'SUCCESSFUL' : 'FAILED');
$purchaseId = $data['external_reference'] 
           ?? $data['response']['external_reference'] 
           ?? $data['reference']
           ?? null;

error_log("Payhero Webhook Received: Status=$status, ID=$purchaseId");

if (in_array(strtoupper($status), ['SUCCESSFUL', 'SUCCESS']) && $purchaseId) {
    $completed = Purchase::complete($purchaseId);
    if ($completed) {
        echo "OK - Units Updated";
    } else {
        echo "ERROR - Purchase::complete failed for ID $purchaseId. Check PHP error logs.";
    }
} else {
    echo "IGNORE - Status: $status, ID: $purchaseId";
}
