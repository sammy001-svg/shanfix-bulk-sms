<?php
require_once 'includes/db.php';
require_once 'includes/actions/purchases.php';

$purchaseId = 51; // One of the pending ones
echo "Attempting to complete purchase #$purchaseId...\n";

$res = Purchase::complete($purchaseId);

if ($res) {
    echo "SUCCESS: Purchase completed.\n";
} else {
    echo "FAILED: Purchase completion failed. Check tmp/purchase_debug.log\n";
}
