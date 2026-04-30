<?php
/**
 * Manual Callback Simulator
 * Use this to verify if the unit update logic works.
 * URL: yoursite.com/test_callback.php?id=PURCHASE_ID
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/actions/purchases.php';

header('Content-Type: text/plain');

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Error: Please provide a purchase ID in the URL (e.g., ?id=123)");
}

echo "Testing Purchase Completion for ID: $id\n";

$purchase = DB::queryOne("SELECT * FROM purchases WHERE id = ?", [$id]);
if (!$purchase) {
    die("Error: Purchase #$id not found in database.");
}

echo "Current Status: " . $purchase['status'] . "\n";
echo "Units to add: " . $purchase['units'] . "\n";

if ($purchase['status'] === 'completed') {
    die("Note: This purchase is already marked as completed.");
}

// Reset to pending just for this test if it was failed
if ($purchase['status'] === 'failed') {
    DB::execute("UPDATE purchases SET status = 'pending' WHERE id = ?", [$id]);
    echo "Reset status from failed to pending for testing...\n";
}

$res = Purchase::complete($id);

if ($res) {
    echo "\nSUCCESS! The units have been added to the user account.\n";
} else {
    echo "\nFAILED! The Purchase::complete method returned false. Check error logs.\n";
}
