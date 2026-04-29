<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/actions/purchases.php';

echo "<h2>Improved Payment Simulation</h2>";

function checkTransfer($userRole) {
    echo "<h3>Testing for $userRole:</h3>";
    
    // 1. Get the user
    $user = DB::queryOne("SELECT id, email, sms_units, parent_id FROM users WHERE role = ? LIMIT 1", [$userRole]);
    if (!$user) { echo "No $userRole found.<br>"; return; }

    // 2. Determine parent
    $parentId = $user['parent_id'];
    if (!$parentId) {
        $admin = DB::queryOne("SELECT id, email FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
        $parentId = $admin['id'];
        $parentLabel = "Admin ({$admin['email']})";
    } else {
        $parent = DB::queryOne("SELECT id, email FROM users WHERE id = ?", [$parentId]);
        $parentLabel = "Parent ({$parent['email']})";
    }

    $parentData = DB::queryOne("SELECT sms_units FROM users WHERE id = ?", [$parentId]);
    
    echo "User: <b>{$user['email']}</b> (Units: {$user['sms_units']})<br>";
    echo "Parent: <b>$parentLabel</b> (Units: {$parentData['sms_units']})<br>";

    // 3. Create a pending purchase
    $unitsToBuy = 50;
    $amount = 40;
    $purchaseId = DB::insert("INSERT INTO purchases (user_id, units, amount, currency, status, payment_method, transaction_ref, created_at) 
                             VALUES (?, ?, ?, 'KES', 'pending', 'mpesa', 'SIM_".time()."', NOW())", 
                             [$user['id'], $unitsToBuy, $amount]);

    echo "Created purchase ID: $purchaseId<br>";

    // 4. Complete it
    $success = Purchase::complete($purchaseId);

    if ($success) {
        $newUser = DB::queryOne("SELECT sms_units FROM users WHERE id = ?", [$user['id']]);
        $newParent = DB::queryOne("SELECT sms_units FROM users WHERE id = ?", [$parentId]);

        echo "<span style='color:green'>SUCCESS!</span><br>";
        echo "New User Balance: <b>{$newUser['sms_units']}</b><br>";
        echo "New Parent Balance: <b>{$newParent['sms_units']}</b><br>";
    } else {
        echo "<span style='color:red'>FAILED! (Check parent balance)</span><br>";
    }
    echo "<hr>";
}

checkTransfer('reseller');
checkTransfer('client');
