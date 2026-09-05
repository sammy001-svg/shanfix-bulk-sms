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

    /**
     * The API Key from the Kopo Kopo dashboard. Kopo Kopo signs webhook bodies
     * with it, so it is what verifies X-KopoKopo-Signature.
     *
     * kk_webhook_secret is the legacy name for the same value and is still
     * honoured so existing installations keep working.
     */
    public static function apiKey(): string {
        return self::setting('api_key') ?: self::setting('webhook_secret');
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

    /**
     * Pull the first usable value from a list of dot-paths in a decoded payload.
     * Kopo Kopo nests results differently between webhook callbacks and direct
     * resource reads, so every field is looked for in both shapes.
     */
    private static function pick(array $data, array $paths) {
        foreach ($paths as $path) {
            $node = $data;
            foreach (explode('.', $path) as $segment) {
                if (!is_array($node) || !isset($node[$segment])) { $node = null; break; }
                $node = $node[$segment];
            }
            if ($node !== null && $node !== '' && !is_array($node)) return $node;
        }
        return null;
    }

    /**
     * Normalise a Kopo Kopo payment payload — webhook body or resource read —
     * into one shape. Single parser so the webhook and the reconciler can never
     * disagree about whether a payment succeeded.
     *
     * @return array{status:?string, resource_status:?string, reference:string,
     *               mpesa_ref:?string, successful:bool, failed:bool}
     */
    public static function extract(array $payload): array {
        $status = self::pick($payload, [
            'data.attributes.status',
            'attributes.status',
            'data.attributes.event.resource.status',
            'attributes.event.resource.status',
            'status',
        ]);

        $resourceStatus = self::pick($payload, [
            'data.attributes.event.resource.status',
            'attributes.event.resource.status',
        ]);

        $reference = (string)(self::pick($payload, [
            'data.attributes.metadata.reference',
            'attributes.metadata.reference',
            'metadata.reference',
        ]) ?? '');

        $mpesaRef = self::pick($payload, [
            'data.attributes.event.resource.reference',
            'attributes.event.resource.reference',
            'data.attributes.event.resource.receipt_number',
        ]);

        $s  = strtoupper((string)$status);
        $rs = strtoupper((string)$resourceStatus);

        $successful = in_array($s,  ['SUCCESS', 'SUCCESSFUL', 'RECEIVED'], true)
                   || in_array($rs, ['SUCCESS', 'RECEIVED'], true);

        // Only a terminal failure counts — "Pending"/"Processing" must not
        // cause the reconciler to give up on a payment still in flight.
        $failed = !$successful && in_array($s, ['FAILED', 'CANCELLED', 'CANCELED', 'REJECTED'], true);

        return [
            'status'          => $status !== null ? (string)$status : null,
            'resource_status' => $resourceStatus !== null ? (string)$resourceStatus : null,
            'reference'       => trim($reference),
            'mpesa_ref'       => $mpesaRef !== null ? (string)$mpesaRef : null,
            'successful'      => $successful,
            'failed'          => $failed,
        ];
    }

    /**
     * Read a payment resource back from Kopo Kopo.
     *
     * This is what makes crediting independent of the webhook: if the callback
     * is delayed, blocked or misconfigured, the outcome can still be pulled on
     * demand and the account credited straight away.
     *
     * @param string $resourceUrl The Location URL captured from the STK push.
     * @return array{ok:bool, error?:string, ...extract() fields}
     */
    public static function getPaymentStatus(string $resourceUrl): array {
        $resourceUrl = trim($resourceUrl);
        if ($resourceUrl === '') {
            return ['ok' => false, 'error' => 'No gateway reference stored for this payment.'];
        }

        // Only ever call the configured Kopo Kopo host — the URL comes from the
        // database and must not be able to point this request anywhere else.
        if (strpos($resourceUrl, self::baseUrl() . '/') !== 0) {
            return ['ok' => false, 'error' => 'Gateway reference does not belong to the configured Kopo Kopo host.'];
        }

        $token = self::getToken();
        if (!$token) {
            return ['ok' => false, 'error' => 'Kopo Kopo authentication failed.'];
        }

        $ch = curl_init($resourceUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $token",
                'Accept: application/json',
                'User-Agent: ShanfixBulkSMS/1.0',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => "Could not reach Kopo Kopo: $curlErr"];
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return ['ok' => false, 'error' => "Kopo Kopo returned HTTP $httpCode"];
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'Kopo Kopo returned a non-JSON response.'];
        }

        return array_merge(['ok' => true], self::extract($data));
    }
}
