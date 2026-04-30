<?php
/**
 * Onfon Media SMS Gateway Implementation
 * Shanfix Technology
 */
class Onfon {
    private static function getSettings() {
        $settings = DB::query("SELECT `key`, `value` FROM system_settings WHERE `key` IN ('onfon_api_key', 'onfon_access_key', 'onfon_user_id')");
        $mapped = [];
        foreach ($settings as $s) {
            $mapped[$s['key']] = $s['value'];
        }
        return $mapped;
    }

    /**
     * Send SMS via Onfon Media
     * @param string $to Recipient number (e.g. 254712345678)
     * @param string $message The message text
     * @param string $senderId Approved Sender ID
     * @return array|bool Response array on success, false on failure
     */
    public static function sendSMS($to, $message, $senderId) {
        $creds = self::getSettings();
        $apiKey = $creds['onfon_api_key'] ?? '';
        $clientId = $creds['onfon_user_id'] ?? '';
        $accessKey = $creds['onfon_access_key'] ?? '';

        if (!$apiKey || !$clientId) {
            return ['success' => false, 'error' => 'Onfon API not configured in system settings.'];
        }

        // Format number to 254XXXXXXXXX
        $phone = preg_replace('/[^0-9]/', '', $to);
        if (strpos($phone, '0') === 0) {
            $phone = '254' . substr($phone, 1);
        } elseif (strpos($phone, '254') !== 0 && strlen($phone) == 9) {
            $phone = '254' . $phone;
        }

        $senderId = trim($senderId); // Keen Cleanup
        $url = "https://api.onfonmedia.co.ke/v1/sms/SendBulkSMS";
        $payload = [
            'SenderId' => $senderId,
            'IsUnicode' => false,
            'IsFlash' => false,
            'MessageParameters' => [
                ['Number' => $phone, 'Text' => $message]
            ],
            'ApiKey' => $apiKey,
            'ClientId' => $clientId,
            'AccessKey' => $accessKey
        ];

        $jsonPayload = json_encode($payload);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // DNS Bypass
        curl_setopt($ch, CURLOPT_RESOLVE, [
            "api.onfonmedia.co.ke:443:104.20.9.168",
            "api.onfonmedia.co.ke:443:104.20.8.168"
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => "CURL Error: $curlError"];
        }

        $result = json_decode($response, true);
        
        // Log for debugging
        $logPath = __DIR__ . '/onfon_debug.log';
        @file_put_contents($logPath, "[".date('Y-m-d H:i:s')."] SENDER: $senderId | TO: $phone | PAYLOAD: $jsonPayload | HTTP: $httpCode | RESP: $response" . PHP_EOL, FILE_APPEND);

        if ($httpCode === 200 && isset($result['ErrorCode']) && $result['ErrorCode'] === 0) {
            $msgData = $result['Data'][0] ?? null;
            if ($msgData && isset($msgData['MessageErrorCode']) && $msgData['MessageErrorCode'] !== 0) {
                return [
                    'success' => false,
                    'error'   => $msgData['MessageErrorDescription'] ?? 'Onfon Error ' . $msgData['MessageErrorCode']
                ];
            }
            return ['success' => true, 'id' => $msgData['MessageId'] ?? uniqid()];
        }

        $errMsg = $result['Description'] ?? ($result['Message'] ?? "HTTP Error $httpCode");
        return ['success' => false, 'error' => "Onfon Error: $errMsg"];
    }

    public static function getBalance() {
        $creds = self::getSettings();
        $apiKey = $creds['onfon_api_key'] ?? '';
        $clientId = $creds['onfon_user_id'] ?? '';
        if (!$apiKey || !$clientId) return null;

        $url = "https://api.onfonmedia.co.ke/v1/sms/Balance?ApiKey=" . urlencode($apiKey) . "&ClientId=" . urlencode($clientId);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        // DNS Bypass
        curl_setopt($ch, CURLOPT_RESOLVE, [
            "api.onfonmedia.co.ke:443:104.20.9.168",
            "api.onfonmedia.co.ke:443:104.20.8.168"
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
