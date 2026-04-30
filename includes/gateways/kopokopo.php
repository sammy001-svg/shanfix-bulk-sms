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
            "User-Agent: ShanfixBulkSMS/1.0"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        ]));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("Kopo Kopo Token CURL Error: " . $curlError);
            return false;
        }

        $data = json_decode($response, true);
        
        if ($httpCode !== 200) {
            error_log("Kopo Kopo Token HTTP Error ($httpCode): " . $response);
            return false;
        }

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
        $host = $_SERVER['HTTP_HOST'] ?? 'shanfix.com';
        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            $host = 'shanfix.com'; // Use a public-looking domain for localhost testing to avoid WAF 403
        }
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $callbackUrl = "$protocol://$host/includes/callbacks/kopokopo.php";

        $body = [
            'payment_channel' => 'm_pesa',
            'till_number' => $till,
            'subscriber' => [
                'first_name' => 'Customer',
                'last_name' => 'Purchase',
                'phone_number' => $phone
            ],
            'amount' => [
                'currency' => 'KES',
                'value' => number_format((float)$amount, 2, '.', '')
            ],
            'callback_url' => $callbackUrl,
            'metadata' => [
                'purchase_id' => (string)$purchaseId
            ]
        ];

        $jsonBody = json_encode($body);
        error_log("Kopo Kopo Request Body: " . $jsonBody);

        $ch = curl_init("$baseUrl/api/v1/incoming_payments");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json",
            "Accept: application/json",
            "User-Agent: ShanfixBulkSMS/1.0"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $data];
        }

        // Log initiation failure for debugging
        error_log("Kopo Kopo Initiation Failure: Code=$httpCode, URL=$baseUrl/api/v1/incoming_payments, Body=$jsonBody, Response=" . $response);

        $errorMsg = $data['errors'][0]['message'] ?? $data['message'] ?? 'Failed to initiate STK push.';
        return ['success' => false, 'error' => $errorMsg . " (Response: " . $response . ")"];
    }
}
