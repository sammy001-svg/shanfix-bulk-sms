<?php
/**
 * Onfon Media SMS Gateway Implementation
 * Shanfix Technology
 */
class Onfon {

    private static function getSettings(): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        $rows  = DB::query("SELECT `key`, `value` FROM system_settings WHERE `key` IN ('onfon_api_key', 'onfon_access_key', 'onfon_user_id')");
        $cache = [];
        foreach ($rows as $s) $cache[$s['key']] = $s['value'];
        return $cache;
    }

    /**
     * Map Onfon per-message error codes to human-readable descriptions.
     * Used as a fallback when MessageErrorDescription is empty.
     */
    private static function describeErrorCode(int $code): string {
        static $map = [
            1   => 'Invalid or unregistered mobile number',
            2   => 'Sender ID not approved for this account',
            3   => 'Insufficient Onfon account credits',
            4   => 'Mobile network not supported',
            5   => 'Message text too long',
            100 => 'Invalid Onfon API key',
            101 => 'Invalid Onfon Client ID',
            102 => 'Invalid Onfon access key',
            103 => 'Onfon account inactive or suspended',
            104 => 'Sending IP address not whitelisted',
            200 => 'Onfon gateway internal server error',
        ];
        return $map[$code] ?? "Onfon gateway error (code $code)";
    }

    /**
     * Send SMS via Onfon Media (single recipient, used for one-off sends only).
     */
    public static function sendSMS($to, $message, $senderId, bool $isUnicode = false) {
        $creds     = self::getSettings();
        $apiKey    = $creds['onfon_api_key']    ?? '';
        $clientId  = $creds['onfon_user_id']    ?? '';
        $accessKey = $creds['onfon_access_key'] ?? '';

        if (!$apiKey || !$clientId) {
            return ['success' => false, 'error' => 'Onfon API not configured in system settings.'];
        }

        $phone = preg_replace('/[^0-9]/', '', $to);
        if (strpos($phone, '0') === 0) {
            $phone = '254' . substr($phone, 1);
        } elseif (strpos($phone, '254') !== 0 && strlen($phone) == 9) {
            $phone = '254' . $phone;
        }

        $senderId    = trim($senderId);
        $url         = "https://api.onfonmedia.co.ke/v1/sms/SendBulkSMS";
        $jsonPayload = json_encode([
            'SenderId'          => $senderId,
            'IsUnicode'         => $isUnicode,
            'IsFlash'           => false,
            'MessageParameters' => [['Number' => $phone, 'Text' => $message]],
            'ApiKey'            => $apiKey,
            'ClientId'          => $clientId,
            'AccessKey'         => $accessKey,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_RESOLVE        => [
                "api.onfonmedia.co.ke:443:104.20.9.168",
                "api.onfonmedia.co.ke:443:104.20.8.168",
            ],
        ]);

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => "CURL Error: $curlError"];
        }

        $result  = json_decode($response, true);
        $logPath = __DIR__ . '/onfon_debug.log';
        @file_put_contents($logPath, "[" . date('Y-m-d H:i:s') . "] SENDER: $senderId | TO: $phone | HTTP: $httpCode | RESP: $response" . PHP_EOL, FILE_APPEND);

        if ($httpCode === 200 && isset($result['ErrorCode']) && $result['ErrorCode'] === 0) {
            $msgData = $result['Data'][0] ?? null;
            if ($msgData && isset($msgData['MessageErrorCode']) && $msgData['MessageErrorCode'] !== 0) {
                $errCode = (int)$msgData['MessageErrorCode'];
                $desc    = !empty($msgData['MessageErrorDescription']) ? $msgData['MessageErrorDescription'] : self::describeErrorCode($errCode);
                return ['success' => false, 'error' => $desc];
            }
            return ['success' => true, 'id' => $msgData['MessageId'] ?? uniqid()];
        }

        $errMsg = $result['Description'] ?? ($result['Message'] ?? "HTTP Error $httpCode");
        return ['success' => false, 'error' => "Onfon Error: $errMsg"];
    }

    /**
     * Send a single batch of up to BATCH_SIZE recipients.
     * Delegates to sendBulkBatchMulti for retry + logging consistency.
     */
    public static function sendBulkBatch(array $recipients, string $senderId): array {
        $results = self::sendBulkBatchMulti([$recipients], $senderId);
        $r       = $results[0] ?? null;
        if ($r) return $r;
        $allIdx = range(0, count($recipients) - 1);
        return ['sent' => [], 'failed' => $allIdx, 'reasons' => array_fill_keys($allIdx, 'No response from gateway')];
    }

    /**
     * Fire multiple batches in parallel via curl_multi.
     *
     * Retries transient failures (curl error, HTTP 0/429/5xx) up to 3 times
     * with exponential backoff: 1 s → 2 s → 4 s.
     * Gateway-level rejections (Onfon ErrorCode != 0, HTTP 4xx) are permanent.
     *
     * @param  array  $batches   [ [['phone'=>'254...','message'=>'...'],...], ... ]
     * @param  string $senderId  Approved sender ID
     * @return array  Indexed by batch position — each entry:
     *                ['sent'=>[['idx'=>N,'msg_id'=>'...'],...], 'failed'=>[N,...], 'reasons'=>[N=>str,...]]
     */
    public static function sendBulkBatchMulti(array $batches, string $senderId, bool $isUnicode = false): array {
        $creds     = self::getSettings();
        $apiKey    = $creds['onfon_api_key']    ?? '';
        $clientId  = $creds['onfon_user_id']    ?? '';
        $accessKey = $creds['onfon_access_key'] ?? '';

        if (!$apiKey || !$clientId) {
            $results = [];
            foreach ($batches as $i => $batch) {
                $allIdx      = range(0, count($batch) - 1);
                $results[$i] = ['sent' => [], 'failed' => $allIdx, 'reasons' => array_fill_keys($allIdx, 'Onfon API not configured')];
            }
            return $results;
        }

        $results    = [];
        $pending    = array_keys($batches);
        $attempts   = array_fill_keys($pending, 0);
        $maxRetries = 3;
        $logPath    = __DIR__ . '/onfon_debug.log';

        while (!empty($pending)) {
            $mh      = curl_multi_init();
            $handles = [];

            foreach ($pending as $i) {
                $messageParams = [];
                foreach ($batches[$i] as $r) {
                    $phone = preg_replace('/[^0-9]/', '', $r['phone']);
                    if (strpos($phone, '0') === 0)                              $phone = '254' . substr($phone, 1);
                    elseif (strpos($phone, '254') !== 0 && strlen($phone) == 9) $phone = '254' . $phone;
                    $messageParams[] = ['Number' => $phone, 'Text' => $r['message']];
                }

                $ch = curl_init("https://api.onfonmedia.co.ke/v1/sms/SendBulkSMS");
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => json_encode([
                        'SenderId'          => trim($senderId),
                        'IsUnicode'         => $isUnicode,
                        'IsFlash'           => false,
                        'MessageParameters' => $messageParams,
                        'ApiKey'            => $apiKey,
                        'ClientId'          => $clientId,
                        'AccessKey'         => $accessKey,
                    ]),
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 60,
                    CURLOPT_RESOLVE        => [
                        "api.onfonmedia.co.ke:443:104.20.9.168",
                        "api.onfonmedia.co.ke:443:104.20.8.168",
                    ],
                ]);

                curl_multi_add_handle($mh, $ch);
                $handles[$i] = $ch;
            }

            // Execute all handles to completion
            $running = null;
            do {
                curl_multi_exec($mh, $running);
                if ($running > 0) curl_multi_select($mh, 1.0);
            } while ($running > 0);

            $toRetry = [];

            foreach ($handles as $i => $ch) {
                $response  = curl_multi_getcontent($ch);
                $curlError = curl_error($ch);
                $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);

                $batch  = $batches[$i];
                $allIdx = range(0, count($batch) - 1);
                $attempts[$i]++;

                @file_put_contents($logPath,
                    "[" . date('Y-m-d H:i:s') . "] MULTI[batch=$i att={$attempts[$i]}]"
                    . " | SENDER:$senderId | CNT:" . count($batch)
                    . " | HTTP:$httpCode | CURL:" . ($curlError ?: 'ok')
                    . " | " . substr((string)$response, 0, 500) . "\n",
                    FILE_APPEND
                );

                // Transient: curl error, rate-limited, or server error → retry with backoff
                if (!$response || $httpCode === 0 || $httpCode === 429 || $httpCode >= 500) {
                    if ($attempts[$i] < $maxRetries) {
                        $toRetry[] = $i;
                        continue;
                    }
                    $reason      = $curlError ?: "HTTP $httpCode after $maxRetries attempts";
                    $results[$i] = ['sent' => [], 'failed' => $allIdx, 'reasons' => array_fill_keys($allIdx, $reason)];
                    continue;
                }

                $parsed = json_decode((string)$response, true);

                // Gateway-level rejection (bad credentials, invalid sender, etc.) — permanent
                if (!isset($parsed['ErrorCode']) || $parsed['ErrorCode'] !== 0 || empty($parsed['Data'])) {
                    $reason      = $parsed['Data'][0]['MessageErrorDescription']
                        ?? $parsed['Description']
                        ?? $parsed['Message']
                        ?? 'Onfon error ' . ($parsed['ErrorCode'] ?? 'unknown');
                    error_log("Onfon batch[$i] rejected: $reason | sender=$senderId | count=" . count($batch));
                    $results[$i] = ['sent' => [], 'failed' => $allIdx, 'reasons' => array_fill_keys($allIdx, $reason)];
                    continue;
                }

                // Per-recipient outcomes
                $sent = []; $failed = []; $reasons = [];
                foreach ($parsed['Data'] as $idx => $item) {
                    $errCode = $item['MessageErrorCode'] ?? -1;
                    if ($errCode === 0 || $errCode === '0') {
                        $sent[] = ['idx' => $idx, 'msg_id' => $item['MessageId'] ?? null];
                    } else {
                        $failed[]      = $idx;
                        $reasons[$idx] = !empty($item['MessageErrorDescription']) ? $item['MessageErrorDescription'] : self::describeErrorCode((int)$errCode);
                    }
                }
                // Onfon returned fewer rows than we sent
                for ($j = count($parsed['Data']); $j < count($batch); $j++) {
                    $failed[]    = $j;
                    $reasons[$j] = 'No response from gateway for this recipient';
                }
                $results[$i] = ['sent' => $sent, 'failed' => $failed, 'reasons' => $reasons];
            }

            curl_multi_close($mh);

            $pending = $toRetry;
            if (!empty($pending)) {
                // Exponential backoff: 1 s after attempt 1, 2 s after attempt 2, 4 s after attempt 3
                $backoff = (int)(1_000_000 * (2 ** min($attempts[reset($pending)] - 1, 2)));
                usleep($backoff);
            }
        }

        return $results;
    }

    public static function getBalance() {
        $creds    = self::getSettings();
        $apiKey   = $creds['onfon_api_key']   ?? '';
        $clientId = $creds['onfon_user_id']   ?? '';
        if (!$apiKey || !$clientId) return null;

        $url = "https://api.onfonmedia.co.ke/v1/sms/Balance?ApiKey=" . urlencode($apiKey) . "&ClientId=" . urlencode($clientId);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_RESOLVE        => [
                "api.onfonmedia.co.ke:443:104.20.9.168",
                "api.onfonmedia.co.ke:443:104.20.8.168",
            ],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        if (isset($result['Data'][0]['Credits'])) {
            return (float)$result['Data'][0]['Credits'];
        }
        return null;
    }
}
