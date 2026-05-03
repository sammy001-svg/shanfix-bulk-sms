<?php
/**
 * Global Action: Core Purchase Logic - Shanfix Technology
 * Handles the logic of verifying and applying unit purchases.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../gateways/payhero.php';

class Purchase {
    public static function create($userId, $data) {
        $planId  = (int)($data['plan_id'] ?? 0);
        $units   = (float)($data['custom_units'] ?? 0);
        $method  = sanitize($data['payment_method'] ?? 'mpesa');
        $ref     = sanitize($data['payment_ref'] ?? '');

        // Fetch user to check for custom rate and parent
        $user = DB::queryOne("SELECT id, parent_id, custom_unit_price FROM users WHERE id = ?", [$userId]);
        if (!$user) return ['success' => false, 'error' => 'User not found.'];

        // Determine parent account for deduction
        $parentId = $user['parent_id'];
        if (!$parentId) {
            $admin = DB::queryOne("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            $parentId = $admin['id'] ?? 1; // Fallback to 1 if no admin found
        }
        
        $parentComp = DB::queryOne("SELECT id, sms_units, role FROM users WHERE id = ?", [$parentId]);

        $customRate = ($user['custom_unit_price'] > 0) ? (float)$user['custom_unit_price'] : null;

        // If no custom rate, check if parent (reseller) has a set unit price
        if (!$customRate && $user['parent_id']) {
            $resellerSettings = DB::queryOne("SELECT unit_price FROM reseller_settings WHERE reseller_id = ?", [$user['parent_id']]);
            if ($resellerSettings && $resellerSettings['unit_price'] > 0) {
                $customRate = (float)$resellerSettings['unit_price'];
            }
        }

        if ($planId) {
            $plan = DB::queryOne("SELECT * FROM pricing_plans WHERE id = ?", [$planId]);
            if (!$plan) return ['success' => false, 'error' => 'Invalid plan selected.'];
            
            $units = $plan['units'];
            $amount = $plan['price']; // Use the fixed price of the plan
        } else {
            if ($units <= 0) return ['success' => false, 'error' => 'Invalid unit amount.'];
            $rate = $customRate ?? 1.00; // Use custom/reseller rate or default to 1.00
            $amount = $units * $rate;
        }

        // Check if parent has enough units
        if ($parentComp['sms_units'] < $units) {
            $parentLabel = ($parentComp['role'] === 'admin') ? 'The Platform' : 'Your Reseller';
            return ['success' => false, 'error' => "$parentLabel has insufficient units to fulfill this request."];
        }

        $type    = sanitize($data['type'] ?? 'sms');

        $id = DB::insert("INSERT INTO purchases (user_id, type, units, amount, currency, status, payment_method, transaction_ref, created_at) 
                         VALUES (?, ?, ?, ?, 'KES', 'pending', ?, ?, NOW())", 
                         [$userId, $type, $units, $amount, $method, $ref]);

        if ($id) {
            // Check if this is a reseller client
            if ($user['parent_id']) {
                // ALWAYS use manual/reseller-managed flow for reseller clients
                DB::execute("UPDATE purchases SET payment_method = 'manual_mpesa' WHERE id = ?", [$id]);
                return ['success' => true, 'id' => $id, 'manual' => true];
            }

            // AUTOMATED STK PUSH FLOW (For direct platform clients)
            $sitePrefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', SITE_NAME), 0, 3));
            $prefixedId = "{$sitePrefix}{$id}";
            $res = Payhero::initiateSTKPush($ref, $amount, $prefixedId);
            
            if ($res['success']) {
                return ['success' => true, 'id' => $id, 'manual' => false];
            } else {
                error_log("STK Push Initiation Failed for Purchase #$id: " . $res['error']);
                DB::execute("UPDATE purchases SET status = 'failed' WHERE id = ?", [$id]);
                return ['success' => false, 'error' => $res['error']];
            }
        }

        return ['success' => false, 'error' => 'Database error recording purchase.'];
    }

    public static function complete($purchaseId) {
        $purchase = DB::queryOne("SELECT * FROM purchases WHERE id = ?", [$purchaseId]);
        
        if (!$purchase) {
            $err = "[".date('Y-m-d H:i:s')."] Purchase::complete - ERROR: Purchase #$purchaseId NOT FOUND in database." . PHP_EOL;
            @file_put_contents(__DIR__ . '/../../tmp/purchase_debug.log', $err, FILE_APPEND);
            return false;
        }

        if ($purchase['status'] !== 'pending') {
            $err = "[".date('Y-m-d H:i:s')."] Purchase::complete - IGNORE: Purchase #$purchaseId is already '{$purchase['status']}'." . PHP_EOL;
            @file_put_contents(__DIR__ . '/../../tmp/purchase_debug.log', $err, FILE_APPEND);
            return false;
        }

        $userId = $purchase['user_id'];
        $user = DB::queryOne("SELECT parent_id FROM users WHERE id = ?", [$userId]);
        
        $parentId = $user['parent_id'];
        if (!$parentId) {
            $admin = DB::queryOne("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            $parentId = $admin['id'] ?? 1;
        }

        // Custom log for debugging webhooks
        $tmpDir = __DIR__ . '/../../tmp';
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0777, true);
        $logMsg = "[".date('Y-m-d H:i:s')."] Purchase::complete - ID: $purchaseId, User: $userId, Parent: $parentId, Units: {$purchase['units']}" . PHP_EOL;
        @file_put_contents($tmpDir . '/purchase_debug.log', $logMsg, FILE_APPEND);

        // Use transaction for atomic balance update
        try {
            DB::beginTransaction();
            
            if ($purchase['type'] === 'whatsapp') {
                // Add money to whatsapp balance
                $added = DB::execute("UPDATE users SET whatsapp_balance = whatsapp_balance + ? WHERE id = ?", [$purchase['amount'], $userId]);
                $deducted = true; // No parent deduction for money topups in this model? 
                // Or maybe deduct from reseller's whatsapp balance? 
                // Usually resellers topup their own wallet at admin rate.
            } else {
                // Deduct from parent
                $deducted = DB::execute("UPDATE users SET sms_units = sms_units - ? WHERE id = ?", [$purchase['units'], $parentId]);
                // Add to child
                $added = DB::execute("UPDATE users SET sms_units = sms_units + ? WHERE id = ?", [$purchase['units'], $userId]);
            }
            
            // Mark purchase as completed
            $marked = DB::execute("UPDATE purchases SET status = 'completed' WHERE id = ?", [$purchaseId]);
            
            if (($deducted !== false) && $added !== false && $marked !== false) {
                DB::commit();
                error_log("Purchase #$purchaseId completed successfully. Units: {$purchase['units']}");
                
                // Notify User
                notify($userId, 'Purchase Successful', "Your purchase of " . number_format($purchase['units']) . " units was successful.", 'success');
                
                return true;
            } else {
                DB::rollback();
                $err = "Purchase completion failed for #$purchaseId: Deducted=$deducted, Added=$added, Marked=$marked. Rolling back." . PHP_EOL;
                file_put_contents(__DIR__ . '/../../tmp/purchase_debug.log', $err, FILE_APPEND);
                error_log($err);
                return false;
            }
        } catch (Exception $e) {
            DB::rollback();
            $err = "Purchase completion exception for #$purchaseId: " . $e->getMessage() . PHP_EOL;
            file_put_contents(__DIR__ . '/../../tmp/purchase_debug.log', $err, FILE_APPEND);
            error_log($err);
            return false;
        }
    }
}
