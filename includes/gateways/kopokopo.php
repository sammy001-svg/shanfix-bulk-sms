<?php
/**
 * Kopo Kopo Gateway Helper - Shanfix Technology
 * Handles M-Pesa Express (STK Push) via Kopo Kopo API
 */
class KopoKopo {
    private static function get_setting($key) {
        $res = DB::queryOne("SELECT value FROM system_settings WHERE `key` = ?", ["kk_$key"]);
        return $res['value'] ?? '';
    }

    public static function getToken() {
        $clientId = self::get_setting('client_id');
        $clientSecret = self::get_setting('client_secret');
        $baseUrl = self::get_setting('base_url') ?: 'https://api.kopokopo.com';

        if (!$clientId || !$clientSecret) return false;

        $ch = curl_init("$baseUrl/oauth/token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: application/json",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        ]));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        return $data['access_token'] ?? false;
    }

    public static function initiateSTKPush($phoneNumber, $amount, $purchaseId) {
        $token = self::getToken();
        if (!$token) return ['success' => false, 'error' => 'Failed to obtain access token.'];

        $baseUrl = self::get_setting('base_url') ?: 'https://api.kopokopo.com';
        $till = self::get_setting('till_number');
        
        $phone = preg_replace('/^\+/', '', $phoneNumber);
        if (strpos($phone, '0') === 0) $phone = '254' . substr($phone, 1);
        $phone = '+' . $phone;

        // Calculate dynamic callback URL
        $callbackUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/includes/callbacks/kopokopo.php";
        // NOTE: On localhost, Kopo Kopo won't be able to reach this. Public domain is required for auto-completion.

        $body = [
            'payment_channel' => 'M-PESA STK Push',
            'till_number' => $till,
            'subscriber' => [
                'first_name' => 'Customer',
                'last_name' => 'Ref#'.$purchaseId,
                'phone_number' => $phone
            ],
            'amount' => [
                'currency' => 'KES',
                'value' => number_format((float)$amount, 2, '.', '')
            ],
            'callback_url' => $callbackUrl
        ];

        $ch = curl_init("$baseUrl/api/v1/incoming_payments");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json",
            "Accept: application/json",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $data];
        }

        // Log initiation failure for debugging
        error_log("Kopo Kopo Initiation Failure: Code=$httpCode, Response=" . $response);

        $errorMsg = $data['errors'][0]['message'] ?? $data['message'] ?? 'Failed to initiate STK push.';
        return ['success' => false, 'error' => $errorMsg . " (Response: " . $response . ")"];
    }
}
