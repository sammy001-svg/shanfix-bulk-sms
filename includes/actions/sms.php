<?php
/**
 * Core SMS Processing Engine - Shanfix Technology
 * Handles validation, unit deduction, and provider interfacing.
 */
require_once __DIR__ . '/../db.php';

class SMS {

    private const BATCH_SIZE   = 20; // Onfon hard limit: max 20 recipients per SendBulkSMS call
    private const CONCURRENCY  = 5;  // parallel API calls per flush (100 recipients at a time)

    /**
     * Spawn a detached PHP process that runs the cron processor.
     *
     * The spawned process is outside PHP-FPM's request pool, so it will
     * never be killed by request_terminate_timeout — safe for 1M+ contacts.
     *
     * Tries exec() → shell_exec() → popen() in order; one of these is
     * always available even on restricted cPanel shared hosting.
     *
     * Returns true if a process was successfully launched.
     */
    public static function spawnBackground(): bool {
        $cron = realpath(__DIR__ . '/../../cron/process_campaigns.php');
        if (!$cron) return false;

        // Resolve the PHP CLI binary — try PHP_BINARY first, fall back to 'php'
        // On cPanel the CLI binary is usually /usr/local/bin/php or /usr/bin/php
        $php = PHP_BINARY ?: '';
        if (!$php || !is_file($php)) {
            foreach (['/usr/local/bin/php', '/usr/bin/php', 'php'] as $try) {
                if ($try === 'php' || is_file($try)) { $php = $try; break; }
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            // Windows dev environment
            if (function_exists('exec')) {
                @exec('start /B "' . $php . '" "' . $cron . '"');
                return true;
            }
            return false;
        }

        // Linux / cPanel — build the command once
        $cmd = '"' . $php . '" "' . $cron . '" > /dev/null 2>&1 &';

        if (function_exists('exec') && !self::isFunctionDisabled('exec')) {
            @exec($cmd);
            return true;
        }
        if (function_exists('shell_exec') && !self::isFunctionDisabled('shell_exec')) {
            @shell_exec($cmd);
            return true;
        }
        if (function_exists('popen') && !self::isFunctionDisabled('popen')) {
            $h = @popen($cmd, 'r');
            if ($h !== false) { pclose($h); return true; }
        }
        if (function_exists('proc_open') && !self::isFunctionDisabled('proc_open')) {
            $desc = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
            $p    = @proc_open($cmd, $desc, $pipes);
            if ($p !== false) { proc_close($p); return true; }
        }
        return false;
    }

    /**
     * Spawn a detached process to run a single campaign.
     * Called by process_campaigns.php so multiple campaigns run in parallel.
     */
    public static function spawnCampaign(int $campaignId): bool {
        $script = realpath(__DIR__ . '/../../cron/run_campaign.php');
        if (!$script) return false;

        $php = PHP_BINARY ?: '';
        if (!$php || !is_file($php)) {
            foreach (['/usr/local/bin/php', '/usr/bin/php', 'php'] as $try) {
                if ($try === 'php' || is_file($try)) { $php = $try; break; }
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            if (function_exists('exec')) {
                @exec('start /B "' . $php . '" "' . $script . '" ' . $campaignId);
                return true;
            }
            return false;
        }

        $cmd = '"' . $php . '" "' . $script . '" ' . $campaignId . ' > /dev/null 2>&1 &';

        if (function_exists('exec') && !self::isFunctionDisabled('exec')) {
            @exec($cmd);
            return true;
        }
        if (function_exists('shell_exec') && !self::isFunctionDisabled('shell_exec')) {
            @shell_exec($cmd);
            return true;
        }
        if (function_exists('popen') && !self::isFunctionDisabled('popen')) {
            $h = @popen($cmd, 'r');
            if ($h !== false) { pclose($h); return true; }
        }
        if (function_exists('proc_open') && !self::isFunctionDisabled('proc_open')) {
            $desc = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
            $p    = @proc_open($cmd, $desc, $pipes);
            if ($p !== false) { proc_close($p); return true; }
        }
        return false;
    }

    /** Check if a function is in PHP's disable_functions list. */
    private static function isFunctionDisabled(string $fn): bool {
        static $disabled = null;
        if ($disabled === null) {
            $disabled = array_map('trim', explode(',', ini_get('disable_functions')));
        }
        return in_array($fn, $disabled, true);
    }

    /**
     * Returns true when $msg contains characters outside the GSM-7 alphabet,
     * meaning the gateway must use UCS-2 encoding (70 chars/part, not 160).
     *
     * Built from the GSM 03.38 basic + extended character tables.
     * Uses Unicode code points to avoid source-file encoding issues.
     */
    public static function isUnicode(string $msg): bool {
        static $gsm7 = null;
        if ($gsm7 === null) {
            $pts = [
                // Basic charset
                0x40, 0xA3, 0x24, 0xA5, 0xE8, 0xE9, 0xF9, 0xEC, 0xF2, 0xC7,
                0x0A, 0xD8, 0xF8, 0x0D, 0xC5, 0xE5, 0x394, 0x5F, 0x3A6, 0x393,
                0x39B, 0x3A9, 0x3A0, 0x3A8, 0x3A3, 0x398, 0x39E, 0x1B, 0xC6, 0xE6,
                0xDF, 0xC9,
                // Space and printable range (0x20–0x7A) covered individually below
                0x20, 0x21, 0x22, 0x23, 0xA4, 0x25, 0x26, 0x27, 0x28, 0x29,
                0x2A, 0x2B, 0x2C, 0x2D, 0x2E, 0x2F,
                0x30, 0x31, 0x32, 0x33, 0x34, 0x35, 0x36, 0x37, 0x38, 0x39,
                0x3A, 0x3B, 0x3C, 0x3D, 0x3E, 0x3F, 0xA1,
                0x41, 0x42, 0x43, 0x44, 0x45, 0x46, 0x47, 0x48, 0x49, 0x4A,
                0x4B, 0x4C, 0x4D, 0x4E, 0x4F, 0x50, 0x51, 0x52, 0x53, 0x54,
                0x55, 0x56, 0x57, 0x58, 0x59, 0x5A,
                0xC4, 0xD6, 0xD1, 0xDC, 0xA7, 0xBF,
                0x61, 0x62, 0x63, 0x64, 0x65, 0x66, 0x67, 0x68, 0x69, 0x6A,
                0x6B, 0x6C, 0x6D, 0x6E, 0x6F, 0x70, 0x71, 0x72, 0x73, 0x74,
                0x75, 0x76, 0x77, 0x78, 0x79, 0x7A,
                0xE4, 0xF6, 0xF1, 0xFC, 0xE0,
                // Extended charset (ESC-prefixed, still GSM-7)
                0x7C, 0x5E, 0x20AC, 0x7B, 0x7D, 0x5B, 0x7E, 0x5D, 0x5C,
            ];
            $gsm7 = array_flip(array_map('mb_chr', $pts));
        }
        foreach (mb_str_split($msg) as $char) {
            if (!isset($gsm7[$char])) return true;
        }
        return false;
    }

    /**
     * Send a single SMS message (used for one-off sends only).
     */
    public static function send($userId, $to, $message, $senderId = 'SHANFIX', $campaignId = null) {
        try {
            $user = DB::queryOne("SELECT id FROM users WHERE id = ?", [$userId]);
            if (!$user) return ['success' => false, 'error' => 'User not found'];

            $isUnicode = self::isUnicode($message);
            $divisor   = $isUnicode ? 70 : 160;
            $cost      = (float)max(1, (int)ceil(mb_strlen($message) / $divisor));

            $validSender = DB::queryOne(
                "SELECT sender_id FROM sender_ids WHERE user_id = ? AND BINARY sender_id = ? AND status = 'approved'",
                [$userId, $senderId]
            );
            if (!$validSender) {
                return ['success' => false, 'error' => "Sender ID '$senderId' is not approved for your account."];
            }

            $senderId = $validSender['sender_id'];

            // Atomic deduct — no TOCTOU race between the balance check and the UPDATE
            $deducted = DB::execute(
                "UPDATE users SET sms_units = sms_units - ? WHERE id = ? AND sms_units >= ?",
                [$cost, $userId, $cost]
            );
            if (!$deducted) {
                return ['success' => false, 'error' => 'Insufficient SMS units. Need ' . $cost . ' unit(s).'];
            }

            $msgId = DB::insert(
                "INSERT INTO messages (user_id, campaign_id, sender_id, recipient, message, units_charged, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'queued', NOW())",
                [$userId, $campaignId, $senderId, $to, $message, $cost]
            );

            require_once __DIR__ . '/../gateways/onfon.php';
            $providerResult = Onfon::sendSMS($to, $message, $senderId, $isUnicode);

            if ($providerResult['success']) {
                DB::execute(
                    "UPDATE messages SET status = 'sent', gateway_msg_id = ?, sent_at = NOW() WHERE id = ?",
                    [$providerResult['id'], $msgId]
                );
                return ['success' => true, 'id' => $msgId, 'cost' => $cost];
            } else {
                $errMsg = $providerResult['error'] ?? 'Provider connection failed';
                DB::execute(
                    "UPDATE messages SET status = 'failed', failed_reason = ? WHERE id = ?",
                    [$errMsg, $msgId]
                );
                DB::execute("UPDATE users SET sms_units = sms_units + ? WHERE id = ?", [$cost, $userId]);
                return ['success' => false, 'error' => $errMsg];
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
        // Atomic lock: stamp locked_at so the cron can detect if we die mid-way
        $locked = DB::execute(
            "UPDATE campaigns SET status = 'sending', locked_at = NOW()
             WHERE id = ? AND status IN ('queued', 'scheduled', 'running')",
            [$campaignId]
        );
        if (!$locked) return; // Another process already claimed it

        $campaign = DB::queryOne("SELECT * FROM campaigns WHERE id = ?", [$campaignId]);
        if (!$campaign) return;

        set_time_limit(0);
        ini_set('memory_limit', '256M');

        try {
            self::runCampaign($campaign);
        } catch (Throwable $e) {
            // Log the error and mark the campaign failed so it surfaces to the user
            error_log("processCampaign #$campaignId crashed: " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            DB::execute(
                "UPDATE campaigns SET status = 'failed', locked_at = NULL WHERE id = ?",
                [$campaignId]
            );
        }
    }

    /**
     * Inner implementation — called by processCampaign inside a try-catch.
     */
    private static function runCampaign(array $campaign): void {
        $campaignId  = $campaign['id'];
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
            DB::execute("UPDATE campaigns SET status = 'failed', locked_at = NULL WHERE id = ?", [$campaignId]);
            return;
        }
        $senderId = $validSender['sender_id'];

        $isUnicode   = self::isUnicode($msgTemplate);
        $partsPerMsg = max(1, (int)ceil(mb_strlen($msgTemplate) / ($isUnicode ? 70 : 160)));

        // On crash-recovery the cron rescues the campaign back to 'queued' but
        // preserves sent_count / failed_count.  Skip those recipients so we never
        // re-send to people who already received the message.
        $skipCount = (int)$campaign['sent_count'] + (int)$campaign['failed_count'];

        $sent = $skipCount > 0 ? (int)$campaign['sent_count']   : 0;
        $failed = $skipCount > 0 ? (int)$campaign['failed_count'] : 0;
        $totalUnits = 0.0;
        $batch = [];

        // Flush the current batch to Onfon and reset
        $batchNum = 0;
        $flush = function () use (&$batch, &$sent, &$failed, &$totalUnits, &$batchNum, $userId, $senderId, $partsPerMsg, $campaignId, $isUnicode) {
            if (empty($batch)) return;
            DB::keepAlive(); // Reconnect if MySQL dropped the connection during a long run
            if ($batchNum > 0) usleep(100000); // 100ms pause between batches — avoids Onfon rate-limit
            $batchNum++;
            [$bs, $bf, $bu] = self::dispatchBatch($userId, $senderId, $batch, $partsPerMsg, $campaignId, $isUnicode);
            $sent       += $bs;
            $failed     += $bf;
            $totalUnits += $bu;
            $batch = [];
            // Live progress so campaigns page shows real-time counts
            DB::execute(
                "UPDATE campaigns SET sent_count = ?, failed_count = ? WHERE id = ?",
                [$sent, $failed, $campaignId]
            );
        };

        // --- Source 1: Uploaded file (CSV or raw XLSX — convert first if needed) ---
        if ($filePath && file_exists($filePath)) {

            // Convert XLSX to CSV in the background worker (not in the web request)
            $fileExt = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if ($fileExt === 'xlsx') {
                require_once __DIR__ . '/../helpers/XlsxReader.php';
                $csvPath = preg_replace('/\.xlsx$/i', '.csv', $filePath);
                try {
                    $rowCount = XlsxReader::toCsv($filePath, $csvPath);
                } catch (\RuntimeException $e) {
                    @unlink($filePath);
                    error_log("XLSX conversion failed for campaign #$campaignId: " . $e->getMessage());
                    DB::execute(
                        "UPDATE campaigns SET status = 'failed', locked_at = NULL WHERE id = ?",
                        [$campaignId]
                    );
                    return;
                }
                @unlink($filePath); // Remove raw XLSX; keep only CSV
                $filePath = $csvPath;
                // Update total_count now that we know it
                DB::execute(
                    "UPDATE campaigns SET file_path = ?, total_count = ? WHERE id = ?",
                    [$filePath, $rowCount, $campaignId]
                );
            }

            $fh = fopen($filePath, 'r');
            if ($fh === false) {
                throw new \RuntimeException("Cannot open campaign file: $filePath");
            }

            $headers  = fgetcsv($fh);
            $headers  = $headers ?: [];
            $cleanHdr = array_map(fn($h) => strtolower(trim($h)), $headers);

            // Accept phone / mobile / number / contact / any header containing those words
            $phoneIdx = -1;
            foreach ($cleanHdr as $i => $h) {
                if ($h === 'phone' || $h === 'mobile' || $h === 'number' || $h === 'contact'
                    || strpos($h, 'phone') !== false || strpos($h, 'mobile') !== false) {
                    $phoneIdx = $i;
                    break;
                }
            }
            if ($phoneIdx === -1) {
                fclose($fh);
                @unlink($filePath);
                error_log("Campaign #$campaignId failed: no phone column in [" . implode(', ', $cleanHdr) . "]");
                DB::execute(
                    "UPDATE campaigns SET status = 'failed', locked_at = NULL WHERE id = ?",
                    [$campaignId]
                );
                return;
            }
            // Normalise header name so placeholder replacement is consistent
            $cleanHdr[$phoneIdx] = 'phone';

            $csvRowCount = 0;
            while (($row = fgetcsv($fh)) !== false) {
                if ($csvRowCount < $skipCount) { $csvRowCount++; continue; } // resume: skip already-sent
                $rawPhone = trim($row[$phoneIdx] ?? '');
                $phone    = self::normalizePhone($rawPhone);
                if (!$phone) { $failed++; $csvRowCount++; continue; }

                $msg = $msgTemplate;
                foreach ($cleanHdr as $i => $hdr) {
                    $val = trim($row[$i] ?? '');
                    $msg = str_replace('##' . ucfirst($hdr) . '##', $val, $msg);
                    $msg = str_replace('##' . $hdr . '##', $val, $msg);
                    $msg = str_replace('{' . $hdr . '}', $val, $msg);
                }
                $batch[] = ['phone' => $phone, 'message' => $msg];
                $csvRowCount++;
                if (count($batch) >= self::BATCH_SIZE * self::CONCURRENCY) $flush();
            }
            fclose($fh);

            // For CSV files (no prior row count), set total_count now
            if ($fileExt !== 'xlsx') {
                DB::execute(
                    "UPDATE campaigns SET total_count = ? WHERE id = ? AND total_count = 0",
                    [$csvRowCount, $campaignId]
                );
            }
        }

        // --- Source 2: Contact group (paginated, 1 000 rows at a time) ---
        if ($groupId) {
            $offset = $skipCount; // resume: jump past already-sent contacts
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
                    if (count($batch) >= self::BATCH_SIZE * self::CONCURRENCY) $flush();
                }
                $offset += 1000;
            } while (count($contacts) === 1000);
        }

        // --- Source 3: Manual comma-separated numbers ---
        if ($numbers) {
            $nums = array_values(array_unique(array_filter(array_map('trim', explode(',', $numbers)))));
            if ($skipCount > 0) $nums = array_slice($nums, $skipCount); // resume: skip already-sent
            foreach ($nums as $n) {
                $phone = self::normalizePhone($n);
                if (!$phone) { $failed++; continue; }
                $batch[] = ['phone' => $phone, 'message' => $msgTemplate];
                if (count($batch) >= self::BATCH_SIZE * self::CONCURRENCY) $flush();
            }
        }

        $flush(); // Dispatch any remaining recipients

        DB::execute(
            "UPDATE campaigns SET status = 'completed', total_count = ?, sent_count = ?, failed_count = ?,
             units_used = ?, sent_at = NOW(), locked_at = NULL WHERE id = ?",
            [$sent + $failed, $sent, $failed, $totalUnits, $campaignId]
        );

        // Delete the file AFTER marking completed — keeps it available for cron
        // retry if the process was killed before reaching this line.
        if ($filePath && file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    /**
     * Deduct units, fire sub-batches in parallel via Onfon::sendBulkBatchMulti(),
     * refund failures, and bulk-log all messages.
     * Returns [$sent, $failed, $unitsUsed].
     */
    private static function dispatchBatch(
        int $userId, string $senderId, array $recipients,
        int $partsPerMsg, int $campaignId, bool $isUnicode = false
    ): array {
        $count      = count($recipients);
        $totalCost  = $count * $partsPerMsg;
        $allIndexes = range(0, $count - 1);

        // Atomically deduct; skip API call if user has no units
        $deducted = DB::execute(
            "UPDATE users SET sms_units = sms_units - ? WHERE id = ? AND sms_units >= ?",
            [$totalCost, $userId, $totalCost]
        );

        if (!$deducted) {
            error_log("SMS Campaign #$campaignId batch skipped: insufficient balance for $count recipients × $partsPerMsg parts.");
            $allFailed  = array_flip($allIndexes);
            $allReasons = array_fill_keys($allIndexes, 'Insufficient SMS balance');
            self::bulkLogMessages($userId, $senderId, $recipients, $partsPerMsg, $campaignId, 'sent', [], $allFailed, $allReasons);
            return [0, $count, 0.0];
        }

        require_once __DIR__ . '/../gateways/onfon.php';

        // Split into BATCH_SIZE chunks and fire all in parallel
        $subBatches   = array_chunk($recipients, self::BATCH_SIZE);
        $batchResults = Onfon::sendBulkBatchMulti($subBatches, $senderId, $isUnicode);

        $sentCount   = 0;
        $failedCount = 0;
        $sentMsgIds  = [];
        $failedSet   = [];
        $reasons     = [];
        $offset      = 0;

        foreach ($subBatches as $bi => $subBatch) {
            $r = $batchResults[$bi] ?? [
                'sent'    => [],
                'failed'  => range(0, count($subBatch) - 1),
                'reasons' => array_fill_keys(range(0, count($subBatch) - 1), 'No response from gateway'),
            ];
            foreach ($r['sent'] as $s) {
                $sentMsgIds[$offset + $s['idx']] = $s['msg_id'];
                $sentCount++;
            }
            foreach ($r['failed'] as $fi) {
                $g             = $offset + $fi;
                $failedSet[$g] = true;
                $reasons[$g]   = $r['reasons'][$fi] ?? 'Unknown error';
                $failedCount++;
            }
            $offset += count($subBatch);
        }

        if ($failedCount === $count && $sentCount === 0) {
            error_log("SMS Campaign #$campaignId: entire batch of $count failed — check Onfon API credentials and sender ID '$senderId'.");
        }

        if ($failedCount > 0) {
            DB::execute(
                "UPDATE users SET sms_units = sms_units + ? WHERE id = ?",
                [$failedCount * $partsPerMsg, $userId]
            );
        }

        self::bulkLogMessages($userId, $senderId, $recipients, $partsPerMsg, $campaignId, 'sent', $sentMsgIds, $failedSet, $reasons);

        return [$sentCount, $failedCount, $sentCount * (float)$partsPerMsg];
    }

    /**
     * Insert all message logs for a batch in a single SQL statement.
     * $reasons maps recipient index → human-readable failure description.
     */
    private static function bulkLogMessages(
        int $userId, string $senderId, array $recipients,
        int $partsPerMsg, int $campaignId, string $defaultStatus,
        array $sentMsgIds, array $failedSet = [], array $reasons = []
    ): void {
        if (empty($recipients)) return;

        $placeholders = [];
        $params       = [];
        $now          = date('Y-m-d H:i:s');

        foreach ($recipients as $idx => $r) {
            $isFailed = isset($failedSet[$idx]) || $defaultStatus === 'failed';
            $status   = $isFailed ? 'failed' : 'sent';
            $msgId    = $sentMsgIds[$idx] ?? null;
            $sentAt   = $isFailed ? null : $now;
            $reason   = $isFailed ? ($reasons[$idx] ?? null) : null;

            $placeholders[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            array_push(
                $params,
                $userId, $campaignId, $senderId,
                $r['phone'], $r['message'],
                $partsPerMsg, $status, $msgId, $sentAt, $reason
            );
        }

        DB::execute(
            "INSERT INTO messages
             (user_id, campaign_id, sender_id, recipient, message, units_charged, status, gateway_msg_id, sent_at, failed_reason)
             VALUES " . implode(',', $placeholders),
            $params
        );
    }

    /**
     * Normalize a phone number to +254XXXXXXXXX (Kenyan format).
     * Handles scientific notation (2.54712E+11), spaces, dashes, dots.
     * Returns null if the number is unrecognizable.
     */
    public static function normalizePhone(string $phone): ?string {
        $phone = trim($phone);
        if ($phone === '') return null;

        // Handle scientific notation (e.g. 2.54712345678E+11 from Excel number cells)
        if (preg_match('/[eE]/', $phone)) {
            $as_float = (float)$phone;
            if ($as_float > 0) {
                $phone = number_format($as_float, 0, '.', '');
            }
        }

        // Strip everything except digits
        $n = preg_replace('/[^0-9]/', '', $phone);
        if (!$n) return null;

        // Expand local Kenyan formats to full 254XXXXXXXXX
        if (strlen($n) === 9 && ($n[0] === '7' || $n[0] === '1')) {
            $n = '254' . $n;                        // 712345678  → 254712345678
        } elseif (strlen($n) === 10 && $n[0] === '0') {
            $n = '254' . substr($n, 1);             // 0712345678 → 254712345678
        } elseif (strlen($n) === 14 && substr($n, 0, 2) === '00') {
            $n = substr($n, 2);                     // 00254xxxxxxxxx (14) → 254xxxxxxxxx (12)
        }

        // Valid form: exactly 12 digits starting with 254
        if (strlen($n) !== 12 || substr($n, 0, 3) !== '254') {
            return null;
        }

        return '+' . $n;
    }
}
