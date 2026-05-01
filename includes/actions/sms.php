<?php
/**
 * Core SMS Processing Engine - Shanfix Technology
 * Handles validation, unit deduction, and provider interfacing.
 */
require_once __DIR__ . '/../db.php';

class SMS {
    /**
     * Send a single SMS message
     */
    public static function send($userId, $to, $message, $senderId = 'SHANFIX', $campaignId = null) {
        try {
            $user = DB::queryOne("SELECT id, sms_units FROM users WHERE id = ?", [$userId]);
            if (!$user) return ['success' => false, 'error' => 'User not found'];

            // Calculate cost (Enforced: 1 unit per 160 characters)
            $len = mb_strlen($message);
            $parts = ceil($len / 160) ?: 1;
            $cost = (float)$parts; 

            if ($user['sms_units'] < $cost) {
                return ['success' => false, 'error' => 'Insufficient SMS units. Need ' . $cost . ' units.'];
            }

            // Validate Sender ID
            $validSender = DB::queryOne("SELECT sender_id FROM sender_ids WHERE user_id = ? AND BINARY sender_id = ? AND status = 'approved'", [$userId, $senderId]);
            if (!$validSender) {
                return ['success' => false, 'error' => "Sender ID '$senderId' is not whitelisted or approved for your account."];
            }
            
            $senderId = $validSender['sender_id'];

            // Deduct units
            DB::execute("UPDATE users SET sms_units = sms_units - ? WHERE id = ?", [$cost, $userId]);

            // Create message record
            $msgId = DB::insert("INSERT INTO messages (user_id, campaign_id, sender_id, recipient, message, units_charged, status, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, 'queued', NOW())", 
                       [$userId, $campaignId, $senderId, $to, $message, $cost]);

            // REAL PROVIDER CALL (Onfon Media)
            require_once __DIR__ . '/../gateways/onfon.php';
            $providerResult = Onfon::sendSMS($to, $message, $senderId);

            if ($providerResult['success']) {
                DB::execute("UPDATE messages SET status = 'sent', gateway_msg_id = ?, sent_at = NOW() WHERE id = ?", [$providerResult['id'], $msgId]);
                return ['success' => true, 'id' => $msgId, 'cost' => $cost];
            } else {
                DB::execute("UPDATE messages SET status = 'failed' WHERE id = ?", [$msgId]);
                // Refund units on failure
                DB::execute("UPDATE users SET sms_units = sms_units + ? WHERE id = ?", [$cost, $userId]);
                return ['success' => false, 'error' => $providerResult['error'] ?? 'Provider connection failed'];
            }

        } catch (Exception $e) {
            error_log("SMS Send Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'System Error'];
        }
    }

    /**
     * Process a campaign (Bulk SMS)
     */
    public static function processCampaign($campaignId) {
        $campaign = DB::queryOne("SELECT * FROM campaigns WHERE id = ?", [$campaignId]);
        if (!$campaign || !in_array($campaign['status'], ['scheduled', 'running', 'queued'])) return;

        DB::execute("UPDATE campaigns SET status = 'sending' WHERE id = ?", [$campaignId]);

        $userId   = $campaign['user_id'];
        $senderId = $campaign['sender_id'];
        $msgTemplate = $campaign['message'];
        $groupId  = $campaign['group_id'];
        $numbers  = $campaign['recipients']; // Comma separated

        $sent = 0; $failed = 0;

        // 1. Handle Group Recipients (with personalization support)
        if ($groupId) {
            $contacts = DB::query("SELECT phone, metadata FROM contacts WHERE group_id = ? AND user_id = ?", [$groupId, $userId]);
            foreach ($contacts as $c) {
                $to = $c['phone'];
                $personalizedMsg = $msgTemplate;
                
                // Replace placeholders if metadata exists
                if ($c['metadata']) {
                    $meta = json_decode($c['metadata'], true);
                    if (is_array($meta)) {
                        foreach ($meta as $key => $val) {
                            $personalizedMsg = str_replace('{' . $key . '}', $val, $personalizedMsg);
                        }
                    }
                }
                
                $res = self::send($userId, $to, $personalizedMsg, $senderId, $campaignId);
                if ($res['success']) $sent++; else $failed++;
            }
        }

        // 2. Handle Manual Numbers (no personalization for raw numbers)
        if ($numbers) {
            $nums = array_unique(array_filter(explode(',', $numbers)));
            foreach ($nums as $to) {
                $to = trim($to);
                if (!$to) continue;
                
                // For manual numbers, we just send the template as is
                $res = self::send($userId, $to, $msgTemplate, $senderId, $campaignId);
                if ($res['success']) $sent++; else $failed++;
            }
        }

        DB::execute("UPDATE campaigns SET status = 'completed', sent_count = ?, failed_count = ?, sent_at = NOW() WHERE id = ?", [$sent, $failed, $campaignId]);
    }

    private static function mockProviderCall($to, $message, $senderId) {
        // Simulating a 95% success rate for the sandbox
        return (rand(1, 100) <= 95) ? ['status' => 'success'] : ['status' => 'failed'];
    }
}
