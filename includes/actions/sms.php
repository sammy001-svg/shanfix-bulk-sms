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
    public static function send($userId, $to, $message, $senderId = 'SHANFIX') {
        try {
            $user = DB::queryOne("SELECT id, sms_units FROM users WHERE id = ?", [$userId]);
            if (!$user) return ['success' => false, 'error' => 'User not found'];

            // Calculate cost (1 unit per 160 chars)
            $len = mb_strlen($message);
            $parts = ceil($len / 160) ?: 1;
            $cost = $parts * 1.0; // Customizable rate

            if ($user['sms_units'] < $cost) {
                return ['success' => false, 'error' => 'Insufficient SMS units. Need ' . $cost . ' units.'];
            }

            // Validate Sender ID (Must be approved for this user)
            // Using BINARY to ensure case-sensitive matching for the specific sender ID selected
            $validSender = DB::queryOne("SELECT sender_id FROM sender_ids WHERE user_id = ? AND BINARY sender_id = ? AND status = 'approved'", [$userId, $senderId]);
            if (!$validSender) {
                return ['success' => false, 'error' => "Sender ID '$senderId' is not whitelisted or approved for your account."];
            }
            
            // Use the exact casing from the database to avoid provider mismatch (Onfon is case-sensitive)
            $senderId = $validSender['sender_id'];

            // Deduct units
            DB::execute("UPDATE users SET sms_units = sms_units - ? WHERE id = ?", [$cost, $userId]);

            // Create message record (Status is 'queued' until provider confirms)
            $msgId = DB::insert("INSERT INTO messages (user_id, sender_id, recipient, message, units_charged, status, created_at) 
                       VALUES (?, ?, ?, ?, ?, 'queued', NOW())", 
                       [$userId, $senderId, $to, $message, $cost]);

            // REAL PROVIDER CALL (Onfon Media)
            require_once __DIR__ . '/../gateways/onfon.php';
            $providerResult = Onfon::sendSMS($to, $message, $senderId);

            if ($providerResult['success']) {
                // Update to sent/delivered
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
        if (!$campaign || $campaign['status'] !== 'scheduled' && $campaign['status'] !== 'draft') return;

        DB::execute("UPDATE campaigns SET status = 'running' WHERE id = ?", [$campaignId]);

        // Implement recipient fetching logic (from group, file, or stored string)
        // This is a stub for the background worker
        
        DB::execute("UPDATE campaigns SET status = 'completed' WHERE id = ?", [$campaignId]);
    }

    private static function mockProviderCall($to, $message, $senderId) {
        // Simulating a 95% success rate for the sandbox
        return (rand(1, 100) <= 95) ? ['status' => 'success'] : ['status' => 'failed'];
    }
}
