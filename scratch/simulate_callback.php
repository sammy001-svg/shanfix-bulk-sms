<?php
/**
 * Test script: Simulate Kopo Kopo Callback
 * This script inserts a dummy purchase and then calls the callback handler.
 */
require_once __DIR__ . '/../includes/db.php';

try {
    // 1. Get a test user (client or reseller)
    $user = DB::queryOne("SELECT id, email, sms_units FROM users WHERE role IN ('client', 'reseller') LIMIT 1");
    if (!$user) {
        die("No test user found in database. Please ensure you have at least one client or reseller.\n");
    }

    echo "Found test user: {$user['email']} (Current units: {$user['sms_units']})\n";

    // 2. Insert a dummy pending purchase
    $units = 100;
    $amount = 80;
    $purchaseId = DB::insert("INSERT INTO purchases (user_id, units, amount, currency, status, payment_method, transaction_ref, created_at) 
                             VALUES (?, ?, ?, 'KES', 'pending', 'mpesa', 'TEST_".time()."', NOW())", 
                             [$user['id'], $units, $amount]);

    if (!$purchaseId) {
        die("Failed to create dummy purchase.\n");
    }

    echo "Created dummy purchase ID: $purchaseId (Units: $units)\n";

    // 3. Prepare mock Kopo Kopo callback payload
    $payload = [
        'data' => [
            'type' => 'incoming_payment',
            'attributes' => [
                'status' => 'Success',
                'metadata' => [
                    'purchase_id' => (string)$purchaseId
                ]
            ]
        ]
    ];

    $jsonPayload = json_encode($payload);

    // 4. Send mock callback via CURL to our local callback handler
    $callbackUrl = "http://localhost:8080/includes/callbacks/kopokopo.php";
    
    echo "Sending mock callback to $callbackUrl...\n";

    $ch = curl_init($callbackUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Callback Response (HTTP $httpCode): $response\n";

    // 5. Verify the results
    $updatedUser = DB::queryOne("SELECT sms_units FROM users WHERE id = ?", [$user['id']]);
    $purchaseStatus = DB::queryOne("SELECT status FROM purchases WHERE id = ?", [$purchaseId]);

    echo "New User Balance: {$updatedUser['sms_units']}\n";
    echo "Purchase Status: {$purchaseStatus['status']}\n";

    if ($purchaseStatus['status'] === 'completed') {
        echo "\nSUCCESS: The purchase was completed and units added!\n";
    } else {
        echo "\nFAILED: The purchase status is still {$purchaseStatus['status']}.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
