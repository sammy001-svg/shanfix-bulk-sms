<?php
/**
 * Kopo Kopo Gateway Helper - Shanfix Technology
 * Handles M-Pesa Express (STK Push) via the Kopo Kopo Incoming Payments API.
 *
 * Drop-in replacement for the retired Payhero gateway: initiateSTKPush() keeps
 * the same ($phone, $amount, $reference) signature and the same
 * ['success' => bool, 'data'|'error'] return shape, so every call site behaves
 * identically.
 *
 * REFERENCE CONTRACT
 * ------------------
 * $reference is passed through to the callback verbatim in metadata.reference.
 * The webhook relies on its prefix to route the payment:
 *   "<SITEPREFIX><id>"  → a purchases row      (Purchase::complete)
 *   "USD<id>"           → a ussd_transactions row (USSD_Wallet::complete)
 * Never rewrite or decorate the reference here.
 */
class KopoKopo {

    /** Access token cached for the life of the request (tokens last ~1 hour). */
    private static $token = null;

    private static function setting(string $key, string $default = ''): string {
        $res = DB::queryOne("SELECT value FROM system_settings WHERE `key` = ?", ["kk_$key"]);
        $val = trim((string)($res['value'] ?? ''));
        return $val !== '' ? $val : $default;
    }

    public static function baseUrl(): string {
        return rtrim(self::setting('base_url', 'https://api.kopokopo.com'), '/');
    }

    /** True when the admin has filled in enough of Settings → Payments to transact. */
    public static function isConfigured(): bool {
        return self::setting('client_id') !== ''
            && self::setting('client_secret') !== ''
            && self::setting('till_number') !== '';
    }

    /**
     * Public URL Kopo Kopo will POST the payment result to.
     *
     * Must live at the document root: .htaccess blocks /includes/ outright, so a
     * callback under includes/callbacks/ answers 403 and the payment is never
     * reconciled.
     */
    public static function callbackUrl(): string {
        $siteUrl = DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'site_url'")['value'] ?? '';

        if (trim((string)$siteUrl) === '') {
            $protocol = 'http';
            if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
                $protocol = 'https';
            }
            $host = $_SERVER['HTTP_HOST'] ?? 'sms.shanfixtechnology.com';
            // A localhost callback is unreachable from Kopo Kopo's servers.
            if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
                $host = 'sms.shanfixtechnology.com';
            }
            $siteUrl = "$protocol://$host";
        }

        $url   = rtrim($siteUrl, '/') . '/kopokopo_webhook.php';
        $token = self::setting('webhook_token');

        return $token !== '' ? $url . '?token=' . urlencode($token) : $url;
    }

    /**
     * Normalise any local format to the E.164 form Kopo Kopo requires (+2547…).
     */
    public static function normalisePhone(string $phoneNumber): string {
        $phone = preg_replace('/[^0-9]/', '', $phoneNumber);

        if (strlen($phone) === 9) {
            $phone = '254' . $phone;                       // 712345678
        } elseif (strlen($phone) === 10 && $phone[0] === '0') {
            $phone = '254' . substr($phone, 1);            // 0712345678
        } elseif (strlen($phone) === 12 && strpos($phone, '254') === 0) {
            // already 254712345678
        }

        return '+' . $phone;
    }

    /**
     * OAuth2 client-credentials token. Cached per request.
     *
     * @return string|false
     */
    public static function getToken() {
        if (self::$token !== null) return self::$token;

        $clientId     = self::setting('client_id');
        $clientSecret = self::setting('client_secret');
        if (!$clientId || !$clientSecret) return false;

        $ch = curl_init(self::baseUrl() . '/oauth/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'User-Agent: ShanfixBulkSMS/1.0',
            ],
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ]),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log('Kopo Kopo token cURL error: ' . $curlError);
            return false;
        }
        if ($httpCode !== 200) {
            error_log("Kopo Kopo token HTTP $httpCode: $response");
            return false;
        }

        $data  = json_decode($response, true);
        $token = $data['access_token'] ?? false;
        if ($token) self::$token = $token;

        return $token;
    }

    /**
     * Trigger an M-Pesa STK push for $amount against $phoneNumber.
     *
     * @param string $phoneNumber Any local or international format.
     * @param float  $amount      KES. Rounded to whole shillings — M-Pesa has no cents.
     * @param string $reference   Prefixed routing reference; echoed back to the webhook.
     * @return array{success:bool, data?:array, location?:string, error?:string}
     */
    public static function initiateSTKPush($phoneNumber, $amount, $reference): array {
        if (!self::isConfigured()) {
            return ['success' => false, 'error' => 'Kopo Kopo is not configured. Set the Client ID, Client Secret and Till Number in Settings → Payments.'];
        }

        $token = self::getToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Kopo Kopo authentication failed — check the Client ID and Client Secret.'];
        }

        $value = (int)round((float)$amount);
        if ($value < 1) {
            return ['success' => false, 'error' => 'Amount must be at least KES 1.'];
        }

        $body = [
            // Kopo Kopo matches this channel string exactly; "mpesa" is rejected.
            'payment_channel' => 'M-PESA STK Push',
            'till_number'     => self::setting('till_number'),
            'subscriber'      => [
                'first_name'   => 'Customer',
                'last_name'    => (string)$reference,
                'phone_number' => self::normalisePhone((string)$phoneNumber),
            ],
            'amount'          => [
                'currency' => 'KES',
                'value'    => $value,
            ],
            'metadata'        => [
                // Echoed back on the callback — this is how the webhook finds the row.
                'reference' => (string)$reference,
            ],
            // Kopo Kopo reads the callback from _links, not from the body root.
            '_links'          => [
                'callback_url' => self::callbackUrl(),
            ],
        ];

        $jsonBody = json_encode($body);

        $ch = curl_init(self::baseUrl() . '/api/v1/incoming_payments');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HEADER         => true, // the resource URL comes back in Location
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $token",
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: ShanfixBulkSMS/1.0',
            ],
            CURLOPT_POSTFIELDS     => $jsonBody,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $raw        = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            error_log("Kopo Kopo STK cURL error: $curlError");
            return ['success' => false, 'error' => "Could not reach Kopo Kopo: $curlError"];
        }

        $headers  = substr($raw, 0, $headerSize);
        $response = substr($raw, $headerSize);
        $data     = json_decode($response, true) ?: [];

        // A successful request is 201 Created with an empty body; the payment
        // resource URL is only in the Location header.
        if ($httpCode >= 200 && $httpCode < 300) {
            $location = '';
            if (preg_match('/^Location:\s*(.+)$/mi', $headers, $m)) {
                $location = trim($m[1]);
            }
            return ['success' => true, 'data' => $data, 'location' => $location];
        }

        error_log("Kopo Kopo STK failure: HTTP $httpCode ref=$reference body=$jsonBody response=$response");

        $errorMsg = $data['errors'][0]['message']
                 ?? $data['error_description']
                 ?? $data['message']
                 ?? 'Failed to initiate STK push.';

        return ['success' => false, 'error' => "Kopo Kopo: $errorMsg (HTTP $httpCode)"];
    }
}
