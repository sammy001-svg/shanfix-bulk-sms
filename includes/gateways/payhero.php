<?php
/**
 * Payhero Kenya Gateway Helper - Shanfix Technology
 * Handles M-Pesa Express (STK Push) via Payhero API v2
 */
class Payhero {
    private static function get_setting($key) {
        $res = DB::queryOne("SELECT value FROM system_settings WHERE `key` = ?", ["payhero_$key"]);
        return $res['value'] ?? '';
    }

    public static function initiateSTKPush($phoneNumber, $amount, $purchaseId) {
        $apiUsername = self::get_setting('api_username');
        $apiPassword = self::get_setting('api_password');
        $channelId   = self::get_setting('channel_id');
        
        if (!$apiUsername || !$apiPassword || !$channelId) {
            return ['success' => false, 'error' => 'Payhero configuration is incomplete.'];
        }

        // Format phone number to 07... or 01... as Payhero prefers local format or 254...
        // Let's ensure it's in the format Payhero expects (usually 07... or 254...)
        $phone = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (strpos($phone, '254') === 0 && strlen($phone) == 12) {
            $phone = '0' . substr($phone, 3);
        } elseif (strlen($phone) == 9) {
            $phone = '0' . $phone;
        }

        // Detect Site URL for Callback
        $siteUrl = DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'site_url'")['value'] ?? '';
        if (empty($siteUrl)) {
            // Fallback to dynamic detection
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'sms.shanfixtechnology.com';
            $siteUrl = "$protocol://$host";
        }
        $siteUrl = rtrim($siteUrl, '/');
        $callbackUrl = "$siteUrl/payhero_webhook.php";

        $body = [
            'amount' => (float)$amount,
            'phone_number' => $phone,
            'channel_id' => (int)$channelId,
            'provider' => 'm-pesa',
            'external_reference' => (string)$purchaseId,
            'callback_url' => $callbackUrl
        ];

        $ch = curl_init("https://backend.payhero.co.ke/api/v2/payments");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic " . base64_encode("$apiUsername:$apiPassword"),
            "Content-Type: application/json",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($data['success']) && $data['success']) {
            return ['success' => true, 'data' => $data];
        }

        // Detailed error for debugging
        error_log("Payhero Error: " . $response);
        $errorMsg = $data['message'] ?? $data['status'] ?? 'Bad Request';
        return ['success' => false, 'error' => "Payhero: $errorMsg (HTTP $httpCode). Response: $response"];
    }
}
