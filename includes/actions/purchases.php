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

        if ($planId) {
            $plan = DB::queryOne("SELECT * FROM pricing_plans WHERE id = ?", [$planId]);
            if (!$plan) return ['success' => false, 'error' => 'Invalid plan selected.'];
            
            $units = $plan['units'];
            $amount = $customRate ? ($units * $customRate) : $plan['price'];
        } else {
            if ($units <= 0) return ['success' => false, 'error' => 'Invalid unit amount.'];
            $rate = $customRate ?? 0.8; 
            $amount = $units * $rate;
        }

        // Check if parent has enough units
        if ($parentComp['sms_units'] < $units) {
            $parentLabel = ($parentComp['role'] === 'admin') ? 'The Platform' : 'Your Reseller';
            return ['success' => false, 'error' => "$parentLabel has insufficient units to fulfill this request."];
        }

        $id = DB::insert("INSERT INTO purchases (user_id, units, amount, currency, status, payment_method, transaction_ref, created_at) 
                         VALUES (?, ?, ?, 'KES', 'pending', ?, ?, NOW())", 
                         [$userId, $units, $amount, $method, $ref]);

        if ($id) {
            // Initiate real Payhero STK Push
            $res = Payhero::initiateSTKPush($ref, $amount, $id);
            
            if ($res['success']) {
                return ['success' => true, 'id' => $id];
            } else {
                // If STK push fails, we should ideally delete or mark the purchase as failed
                error_log("STK Push Initiation Failed for Purchase #$id: " . $res['error']);
                DB::execute("UPDATE purchases SET status = 'failed' WHERE id = ?", [$id]);
                return ['success' => false, 'error' => $res['error']];
            }
        }

        return ['success' => false, 'error' => 'Database error recording purchase.'];
    }

    public static function complete($purchaseId) {
        $purchase = DB::queryOne("SELECT * FROM purchases WHERE id = ? AND status = 'pending'", [$purchaseId]);
        if (!$purchase) return false;

        $userId = $purchase['user_id'];
        $user = DB::queryOne("SELECT parent_id FROM users WHERE id = ?", [$userId]);
        
        $parentId = $user['parent_id'];
        if (!$parentId) {
            $admin = DB::queryOne("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            $parentId = $admin['id'] ?? 1;
        }

        // DEBUG
        error_log("Purchase::complete - ID: $purchaseId, User: $userId, Parent: $parentId, Units: {$purchase['units']}");

        // Use transaction for atomic balance update
        try {
            DB::beginTransaction();
            
            // Deduct from parent
            $deducted = DB::execute("UPDATE users SET sms_units = sms_units - ? WHERE id = ?", [$purchase['units'], $parentId]);
            
            // Add to child
            $added = DB::execute("UPDATE users SET sms_units = sms_units + ? WHERE id = ?", [$purchase['units'], $userId]);
            
            // Mark purchase as completed
            $marked = DB::execute("UPDATE purchases SET status = 'completed' WHERE id = ?", [$purchaseId]);
            
            if ($deducted && $added && $marked) {
                DB::commit();
                
                // Notify User
                notify($userId, 'Purchase Successful', "Your purchase of " . number_format($purchase['units']) . " units was successful.", 'success');
                
                return true;
            } else {
                DB::rollback();
                error_log("Purchase completion failed: Deduction=$deducted, Added=$added, Marked=$marked");
                return false;
            }
        } catch (Exception $e) {
            DB::rollback();
            error_log("Purchase completion error: " . $e->getMessage());
            return false;
        }
    }
}
