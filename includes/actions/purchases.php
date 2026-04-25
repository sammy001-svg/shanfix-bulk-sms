<?php
/**
 * Global Action: Core Purchase Logic - Shanfix Technology
 * Handles the logic of verifying and applying unit purchases.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../gateways/kopokopo.php';

class Purchase {
    public static function create($userId, $data) {
        $planId  = (int)($data['plan_id'] ?? 0);
        $units   = (float)($data['custom_units'] ?? 0);
        $method  = sanitize($data['payment_method'] ?? 'mpesa');
        $ref     = sanitize($data['payment_ref'] ?? '');

        // Fetch user to check for custom rate and parent
        $user = DB::queryOne("SELECT id, parent_id, custom_unit_price FROM users WHERE id = ?", [$userId]);
        if (!$user) return ['success' => false, 'error' => 'User not found.'];

        // Determine parent account for deduction (Admin ID 1 if no parent_id)
        $parentId = $user['parent_id'] ?: 1; 
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
            // Initiate real Kopo Kopo STK Push
            $res = KopoKopo::initiateSTKPush($ref, $amount, $id);
            
            if ($res['success']) {
                return ['success' => true, 'id' => $id];
            } else {
                // If STK push fails, we should ideally delete or mark the purchase as failed
                DB::execute("UPDATE purchases SET status = 'failed' WHERE id = ?", [$id]);
                return ['success' => false, 'error' => 'Kopo Kopo Error: ' . $res['error']];
            }
        }

        return ['success' => false, 'error' => 'Database error recording purchase.'];
    }

    public static function complete($purchaseId) {
        $purchase = DB::queryOne("SELECT * FROM purchases WHERE id = ? AND status = 'pending'", [$purchaseId]);
        if (!$purchase) return false;

        $userId = $purchase['user_id'];
        $user = DB::queryOne("SELECT parent_id FROM users WHERE id = ?", [$userId]);
        $parentId = $user['parent_id'] ?: 1;

        // Deduct from parent and add to child
        DB::execute("UPDATE users SET sms_units = sms_units - ? WHERE id = ?", [$purchase['units'], $parentId]);
        DB::execute("UPDATE users SET sms_units = sms_units + ? WHERE id = ?", [$purchase['units'], $userId]);
        DB::execute("UPDATE purchases SET status = 'completed' WHERE id = ?", [$purchaseId]);
        
        return true;
    }
}
