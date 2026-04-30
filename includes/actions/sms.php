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

            // Calculate cost (1 unit per 160 chars)
            $len = mb_strlen($message);
            $parts = ceil($len / 160) ?: 1;
            $cost = $parts * 1.0; 

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
        $message  = $campaign['message'];
        $groupId  = $campaign['group_id'];
        $numbers  = $campaign['recipients']; // Comma separated

        $recipients = [];
        if ($groupId) {
            $contacts = DB::query("SELECT phone FROM contacts WHERE group_id = ? AND user_id = ?", [$groupId, $userId]);
            foreach ($contacts as $c) $recipients[] = $c['phone'];
        }
        if ($numbers) {
            $nums = explode(',', $numbers);
            foreach ($nums as $n) {
                $n = trim($n);
                if ($n) $recipients[] = $n;
            }
        }

        $recipients = array_unique($recipients);
        $total = count($recipients);
        $sent = 0; $failed = 0;

        foreach ($recipients as $to) {
            $res = self::send($userId, $to, $message, $senderId, $campaignId);
            if ($res['success']) $sent++; else $failed++;
        }

        DB::execute("UPDATE campaigns SET status = 'completed', sent_count = ?, failed_count = ?, sent_at = NOW() WHERE id = ?", [$sent, $failed, $campaignId]);
    }

    private static function mockProviderCall($to, $message, $senderId) {
        // Simulating a 95% success rate for the sandbox
        return (rand(1, 100) <= 95) ? ['status' => 'success'] : ['status' => 'failed'];
    }
}
