<?php
/**
 * Core SMS Processing Engine - Shanfix Technology
 * Handles validation, unit deduction, and provider interfacing.
 */
require_once __DIR__ . '/../db.php';

class SMS {

    private const BATCH_SIZE = 500; // Recipients per Onfon API call

    /**
     * Send a single SMS message (used for one-off sends only).
     */
    public static function send($userId, $to, $message, $senderId = 'SHANFIX', $campaignId = null) {
        try {
            $user = DB::queryOne("SELECT id, sms_units FROM users WHERE id = ?", [$userId]);
            if (!$user) return ['success' => false, 'error' => 'User not found'];

            $len   = mb_strlen($message);
            $parts = max(1, (int)ceil($len / 160));
            $cost  = (float)$parts;

            if ($user['sms_units'] < $cost) {
                return ['success' => false, 'error' => 'Insufficient SMS units. Need ' . $cost . ' units.'];
            }

            $validSender = DB::queryOne(
                "SELECT sender_id FROM sender_ids WHERE user_id = ? AND BINARY sender_id = ? AND status = 'approved'",
                [$userId, $senderId]
            );
            if (!$validSender) {
                return ['success' => false, 'error' => "Sender ID '$senderId' is not approved for your account."];
            }

            $senderId = $validSender['sender_id'];
            DB::execute("UPDATE users SET sms_units = sms_units - ? WHERE id = ?", [$cost, $userId]);

            $msgId = DB::insert(
                "INSERT INTO messages (user_id, campaign_id, sender_id, recipient, message, units_charged, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'queued', NOW())",
                [$userId, $campaignId, $senderId, $to, $message, $cost]
            );

            require_once __DIR__ . '/../gateways/onfon.php';
            $providerResult = Onfon::sendSMS($to, $message, $senderId);

            if ($providerResult['success']) {
                DB::execute(
                    "UPDATE messages SET status = 'sent', gateway_msg_id = ?, sent_at = NOW() WHERE id = ?",
                    [$providerResult['id'], $msgId]
                );
                return ['success' => true, 'id' => $msgId, 'cost' => $cost];
            } else {
                DB::execute("UPDATE messages SET status = 'failed' WHERE id = ?", [$msgId]);
                DB::execute("UPDATE users SET sms_units = sms_units + ? WHERE id = ?", [$cost, $userId]);
                return ['success' => false, 'error' => $providerResult['error'] ?? 'Provider connection failed'];
            }

        } catch (Exception $e) {
            error_log("SMS Send Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'System Error'];
        }
    }

    /**
     * Process a campaign in batches — streams file/group/numbers without timeout.
     * Sends up to 500 recipients per Onfon API call; bulk-inserts message logs.
     */
    public static function processCampaign($campaignId) {
        // Atomic lock: only one process can claim this campaign
        $locked = DB::execute(
            "UPDATE campaigns SET status = 'sending' WHERE id = ? AND status IN ('queued', 'scheduled', 'running')",
            [$campaignId]
        );
        if (!$locked) return;

        $campaign = DB::queryOne("SELECT * FROM campaigns WHERE id = ?", [$campaignId]);
        if (!$campaign) return;

        set_time_limit(0);
        ini_set('memory_limit', '256M');

        $userId      = $campaign['user_id'];
        $senderId    = $campaign['sender_id'];
        $msgTemplate = $campaign['message'];
        $groupId     = $campaign['group_id'];
        $numbers     = $campaign['recipients'];
        $filePath    = $campaign['file_path'] ?? null;

        // Validate sender ID once up front
        $validSender = DB::queryOne(
            "SELECT sender_id FROM sender_ids WHERE user_id = ? AND BINARY sender_id = ? AND status = 'approved'",
            [$userId, $senderId]
        );
        if (!$validSender) {
            DB::execute("UPDATE campaigns SET status = 'failed' WHERE id = ?", [$campaignId]);
            return;
        }
        $senderId = $validSender['sender_id'];

        $partsPerMsg = max(1, (int)ceil(mb_strlen($msgTemplate) / 160));
        $sent = 0; $failed = 0; $totalUnits = 0.0;
        $batch = [];

        // Flush the current batch to Onfon and reset
        $flush = function () use (&$batch, &$sent, &$failed, &$totalUnits, $userId, $senderId, $partsPerMsg, $campaignId, &$flush) {
            if (empty($batch)) return;
            [$bs, $bf, $bu] = self::dispatchBatch($userId, $senderId, $batch, $partsPerMsg, $campaignId);
            $sent       += $bs;
            $failed     += $bf;
            $totalUnits += $bu;
            $batch = [];
            // Live progress update so campaigns page reflects real-time counts
            DB::execute(
                "UPDATE campaigns SET sent_count = ?, failed_count = ? WHERE id = ?",
                [$sent, $failed, $campaignId]
            );
        };

        // --- Source 1: Uploaded CSV file (streamed row by row) ---
        if ($filePath && file_exists($filePath)) {
            $fh      = fopen($filePath, 'r');
            $headers = fgetcsv($fh);
            if ($headers) {
                $cleanHdr = array_map(fn($h) => strtolower(trim($h)), $headers);
                $phoneIdx = array_search('phone', $cleanHdr);
                if ($phoneIdx !== false) {
                    while (($row = fgetcsv($fh)) !== false) {
                        $phone = self::normalizePhone(trim($row[$phoneIdx] ?? ''));
                        if (!$phone) { $failed++; continue; }

                        $msg = $msgTemplate;
                        foreach ($cleanHdr as $i => $hdr) {
                            $val = trim($row[$i] ?? '');
                            $msg = str_replace('##' . ucfirst($hdr) . '##', $val, $msg);
                            $msg = str_replace('{' . $hdr . '}', $val, $msg);
                        }
                        $batch[] = ['phone' => $phone, 'message' => $msg];
                        if (count($batch) >= self::BATCH_SIZE) $flush();
                    }
                }
            }
            fclose($fh);
            @unlink($filePath);
        }

        // --- Source 2: Contact group (paginated to avoid memory overload) ---
        if ($groupId) {
            $offset = 0;
            do {
                $contacts = DB::query(
                    "SELECT phone, metadata FROM contacts WHERE group_id = ? AND user_id = ? LIMIT 1000 OFFSET ?",
                    [$groupId, $userId, $offset]
                );
                foreach ($contacts as $c) {
                    $phone = self::normalizePhone($c['phone']);
                    if (!$phone) { $failed++; continue; }

                    $msg = $msgTemplate;
                    if ($c['metadata']) {
                        $meta = json_decode($c['metadata'], true);
                        if (is_array($meta)) {
                            foreach ($meta as $k => $v) {
                                $msg = str_replace('{' . $k . '}', $v, $msg);
                            }
                        }
                    }
                    $batch[] = ['phone' => $phone, 'message' => $msg];
                    if (count($batch) >= self::BATCH_SIZE) $flush();
                }
                $offset += 1000;
            } while (count($contacts) === 1000);
        }

        // --- Source 3: Manual comma-separated numbers ---
        if ($numbers) {
            $nums = array_unique(array_filter(array_map('trim', explode(',', $numbers))));
            foreach ($nums as $n) {
                $phone = self::normalizePhone($n);
                if (!$phone) { $failed++; continue; }
                $batch[] = ['phone' => $phone, 'message' => $msgTemplate];
                if (count($batch) >= self::BATCH_SIZE) $flush();
            }
        }

        $flush(); // Send any remaining recipients

        DB::execute(
            "UPDATE campaigns SET status = 'completed', total_count = ?, sent_count = ?, failed_count = ?, units_used = ?, sent_at = NOW() WHERE id = ?",
            [$sent + $failed, $sent, $failed, $totalUnits, $campaignId]
        );
    }

    /**
     * Deduct units, call Onfon bulk API, refund failures, bulk-log messages.
     * Returns [$sent, $failed, $unitsUsed].
     */
    private static function dispatchBatch(
        int $userId, string $senderId, array $recipients,
        int $partsPerMsg, int $campaignId
    ): array {
        $count     = count($recipients);
        $totalCost = $count * $partsPerMsg;

        // Atomically deduct; skip API call if user has no units
        $deducted = DB::execute(
            "UPDATE users SET sms_units = sms_units - ? WHERE id = ? AND sms_units >= ?",
            [$totalCost, $userId, $totalCost]
        );

        if (!$deducted) {
            self::bulkLogMessages($userId, $senderId, $recipients, $partsPerMsg, $campaignId, 'failed', []);
            return [0, $count, 0.0];
        }

        require_once __DIR__ . '/../gateways/onfon.php';
        $result = Onfon::sendBulkBatch($recipients, $senderId);

        $sentCount   = count($result['sent']);
        $failedCount = count($result['failed']);

        if ($failedCount > 0) {
            DB::execute(
                "UPDATE users SET sms_units = sms_units + ? WHERE id = ?",
                [$failedCount * $partsPerMsg, $userId]
            );
        }

        // Build a lookup: index -> gateway message id (for sent messages)
        $sentMsgIds = [];
        foreach ($result['sent'] as $s) {
            $sentMsgIds[$s['idx']] = $s['msg_id'];
        }
        $failedSet = array_flip($result['failed']);

        self::bulkLogMessages($userId, $senderId, $recipients, $partsPerMsg, $campaignId, 'sent', $sentMsgIds, $failedSet);

        return [$sentCount, $failedCount, $sentCount * (float)$partsPerMsg];
    }

    /**
     * Insert all message logs for a batch in a single SQL statement.
     */
    private static function bulkLogMessages(
        int $userId, string $senderId, array $recipients,
        int $partsPerMsg, int $campaignId, string $defaultStatus,
        array $sentMsgIds, array $failedSet = []
    ): void {
        if (empty($recipients)) return;

        $placeholders = [];
        $params       = [];
        $now          = date('Y-m-d H:i:s');

        foreach ($recipients as $idx => $r) {
            $isFailed = isset($failedSet[$idx]);
            $status   = $isFailed ? 'failed' : $defaultStatus;
            $msgId    = $sentMsgIds[$idx] ?? null;
            $sentAt   = $isFailed ? null : $now;

            $placeholders[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?)";
            array_push(
                $params,
                $userId, $campaignId, $senderId,
                $r['phone'], $r['message'],
                $partsPerMsg, $status, $msgId, $sentAt
            );
        }

        DB::execute(
            "INSERT INTO messages
             (user_id, campaign_id, sender_id, recipient, message, units_charged, status, gateway_msg_id, sent_at)
             VALUES " . implode(',', $placeholders),
            $params
        );
    }

    /**
     * Normalize a phone number to +254XXXXXXXXX (Kenyan format).
     * Returns null if the number is unrecognizable.
     */
    public static function normalizePhone(string $phone): ?string {
        $n = preg_replace('/[^0-9]/', '', $phone);
        if (!$n) return null;

        if (strlen($n) === 9 && ($n[0] === '7' || $n[0] === '1')) {
            $n = '254' . $n;
        } elseif (strlen($n) === 10 && $n[0] === '0') {
            $n = '254' . substr($n, 1);
        } elseif (strlen($n) === 12 && strpos($n, '254') === 0) {
            // already correct
        } elseif (strlen($n) < 9) {
            return null;
        }

        return strpos($n, '254') === 0 ? '+' . $n : '+' . $n;
    }
}
