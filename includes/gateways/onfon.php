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
        $clientId = $creds['onfon_user_id'] ?? ''; // Onfon often uses UserId as ClientId

        if (!$apiKey || !$clientId) return false;

        $url = "https://api.onfonmedia.co.ke/v1/sms/SendBulkSMS";

        $payload = [
            'SenderId' => $senderId,
            'IsUnicode' => false,
            'IsFlash' => false,
            'MessageParameters' => [
                [
                    'Number' => $to,
                    'Text' => $message
                ]
            ],
            'ApiKey' => $apiKey,
            'ClientId' => $clientId
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        // Debug logging
        file_put_contents(__DIR__ . '/../../tmp/onfon_debug.log', "[" . date('Y-m-d H:i:s') . "] SEND: to=$to, http=$httpCode, response=" . $response . PHP_EOL, FILE_APPEND);

        if ($httpCode === 200 && isset($result['ErrorCode']) && $result['ErrorCode'] === 0) {
            return $result;
        }

        return false;
    }

    /**
     * Get account balance from Onfon
     * @return float|null Balance in units/KES, or null on failure
     */
    public static function getBalance() {
        $creds = self::getSettings();
        $apiKey = $creds['onfon_api_key'] ?? '';
        $clientId = $creds['onfon_user_id'] ?? '';

        if (!$apiKey || !$clientId) return null;

        // Correct working pattern for Balance is GET with query parameters
        $url = "https://api.onfonmedia.co.ke/v1/sms/Balance?ApiKey=" . urlencode($apiKey) . "&ClientId=" . urlencode($clientId);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        // Debug logging
        file_put_contents(__DIR__ . '/../../tmp/onfon_debug.log', "[" . date('Y-m-d H:i:s') . "] BALANCE: http=$httpCode, response=" . $response . PHP_EOL, FILE_APPEND);

        if ($httpCode === 200 && isset($result['ErrorCode']) && $result['ErrorCode'] === 0) {
            if (isset($result['Data'][0]['Credits'])) {
                return (float)$result['Data'][0]['Credits'];
            }
        }

        return null;
    }
}
