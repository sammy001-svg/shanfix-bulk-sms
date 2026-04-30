<?php
/**
 * Payhero Public Webhook Handler
 * Located in root to avoid firewall blocks on 'includes' folder.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/actions/purchases.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) exit;

// Log callback for debugging
file_put_contents(__DIR__ . '/tmp/payhero_callback.log', "[".date('Y-m-d H:i:s')."] " . $input . PHP_EOL, FILE_APPEND);

$status = $data['status'] ?? ($data['success'] ? 'SUCCESSFUL' : 'FAILED');
$purchaseId = $data['external_reference'] 
           ?? $data['response']['external_reference'] 
           ?? $data['reference']
           ?? null;

if (($status === 'SUCCESSFUL' || $status === 'SUCCESS') && $purchaseId) {
    Purchase::complete($purchaseId);
    echo "OK";
} else {
    echo "Status: " . $status;
}
