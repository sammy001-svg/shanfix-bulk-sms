<?php
/**
 * Global Action: Core Purchase Logic - Shanfix Technology
 * Handles the logic of verifying and applying unit purchases.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../gateways/kopokopo.php';

class Purchase {
    /**
     * Public entry point. Wraps the real work so a database or gateway problem
     * comes back as ['success' => false, 'error' => ...] instead of an uncaught
     * exception, which would print an HTML fatal and break the AJAX caller's
     * response.json().
     */
    public static function create($userId, $data) {
        try {
            return self::doCreate($userId, $data);
        } catch (Throwable $e) {
            error_log('Purchase::create failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            require_once __DIR__ . '/../helpers/json-fatal-guard.php';
            return ['success' => false, 'error' => json_fatal_hint($e->getMessage())];
        }
    }

    private static function doCreate($userId, $data) {
        $planId  = (int)($data['plan_id'] ?? 0);
        $units   = (float)($data['custom_units'] ?? 0);
        $amount  = (float)($data['amount'] ?? 0);
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

        $type    = sanitize($data['type'] ?? 'sms');

        if ($planId) {
            $plan = DB::queryOne("SELECT * FROM pricing_plans WHERE id = ?", [$planId]);
            if (!$plan) return ['success' => false, 'error' => 'Invalid plan selected.'];
            
            $units = $plan['units'];
            $amount = $plan['price']; // Use the fixed price of the plan
        } else {
            if ($type === 'whatsapp') {
                if ($amount <= 0) return ['success' => false, 'error' => 'Invalid top-up amount.'];
                $units = 0; // WhatsApp wallet is money-based, not unit-based
            } else {
                if ($units <= 0) return ['success' => false, 'error' => 'Invalid unit amount.'];
                $rate = $customRate ?? 1.00; // Use custom/reseller rate or default to 1.00
                $amount = $units * $rate;
            }
        }

        // Check if parent has enough units (Only for SMS)
        if ($type === 'sms' && $parentComp['sms_units'] < $units) {
            $parentLabel = ($parentComp['role'] === 'admin') ? 'The Platform' : 'Your Reseller';
            return ['success' => false, 'error' => "$parentLabel has insufficient units to fulfill this request."];
        }


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
            $res = KopoKopo::initiateSTKPush($ref, $amount, $prefixedId);

            if ($res['success']) {
                // Keep the payment resource URL: it lets us confirm the outcome
                // directly with Kopo Kopo if the webhook is delayed or blocked,
                // so units are still credited automatically.
                //
                // The STK push has already gone to the customer's phone by now,
                // so a bookkeeping failure here must not fail the request. If
                // the column is missing (migration not run) we log it and carry
                // on; the webhook still credits the account.
                if (!empty($res['location'])) {
                    try {
                        DB::execute("UPDATE purchases SET gateway_ref = ? WHERE id = ?", [$res['location'], $id]);
                    } catch (Throwable $e) {
                        error_log("Purchase #$id: could not store gateway_ref (run database/kopokopo_autocredit_migration.sql): " . $e->getMessage());
                    }
                }
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
        $purchaseId = (int)$purchaseId;
        if (!$purchaseId) return false;

        $tmpDir  = __DIR__ . '/../../tmp';
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0777, true);
        $logFile = $tmpDir . '/purchase_debug.log';

        try {
            DB::beginTransaction();

            // Atomic first-claim: lock the row and verify it is still pending.
            // If a concurrent webhook already claimed it, FOR UPDATE blocks until that
            // transaction commits, then this SELECT returns 0 rows (status no longer 'pending').
            $purchase = DB::queryOne(
                "SELECT * FROM purchases WHERE id = ? AND status = 'pending' FOR UPDATE",
                [$purchaseId]
            );

            if (!$purchase) {
                DB::rollback();
                $state = DB::queryOne("SELECT status FROM purchases WHERE id = ?", [$purchaseId]);
                $reason = $state ? "already '{$state['status']}'" : 'not found';
                @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] complete #$purchaseId SKIP: $reason\n", FILE_APPEND);
                return false;
            }

            $userId = $purchase['user_id'];
            $user   = DB::queryOne("SELECT parent_id FROM users WHERE id = ?", [$userId]);

            $parentId = $user['parent_id'];
            if (!$parentId) {
                $admin    = DB::queryOne("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
                $parentId = $admin['id'] ?? 1;
            }

            @file_put_contents($logFile,
                "[" . date('Y-m-d H:i:s') . "] complete #$purchaseId: user=$userId parent=$parentId units={$purchase['units']}\n",
                FILE_APPEND
            );

            if ($purchase['type'] === 'whatsapp') {
                DB::execute(
                    "UPDATE users SET whatsapp_balance = whatsapp_balance + ? WHERE id = ?",
                    [$purchase['amount'], $userId]
                );
            } else {
                // Deduct from parent — guard prevents the parent going negative.
                $deducted = DB::execute(
                    "UPDATE users SET sms_units = sms_units - ? WHERE id = ? AND sms_units >= ?",
                    [$purchase['units'], $parentId, $purchase['units']]
                );
                if (!$deducted) {
                    DB::rollback();
                    $msg = "[" . date('Y-m-d H:i:s') . "] complete #$purchaseId FAIL: parent $parentId has insufficient balance\n";
                    @file_put_contents($logFile, $msg, FILE_APPEND);
                    error_log("Purchase #$purchaseId: parent $parentId insufficient balance — rolling back.");
                    return false;
                }
                DB::execute(
                    "UPDATE users SET sms_units = sms_units + ? WHERE id = ?",
                    [$purchase['units'], $userId]
                );
            }

            DB::execute("UPDATE purchases SET status = 'completed' WHERE id = ?", [$purchaseId]);
            DB::commit();

            error_log("Purchase #$purchaseId completed. Units: {$purchase['units']}");
            notify($userId, 'Purchase Successful',
                "Your purchase of " . number_format($purchase['units']) . " units was successful.", 'success');

            return true;

        } catch (Exception $e) {
            DB::rollback();
            $msg = "[" . date('Y-m-d H:i:s') . "] complete #$purchaseId EXCEPTION: " . $e->getMessage() . "\n";
            @file_put_contents($logFile, $msg, FILE_APPEND);
            error_log("Purchase #$purchaseId exception: " . $e->getMessage());
            return false;
        }
    }
}
