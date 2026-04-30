<?php
/**
 * IMPROVED DEBUGGER (v3) - Includes STK Push Test
 */
require_once 'includes/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Kopo Kopo Connection Debugger (v3)</h2>";

$clientId = DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'kk_client_id'")['value'] ?? '';
$clientSecret = DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'kk_client_secret'")['value'] ?? '';
$baseUrl = DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'kk_base_url'")['value'] ?? 'https://api.kopokopo.com';
$till = DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'kk_till_number'")['value'] ?? '';

if (!$clientId || !$clientSecret) {
    die("<span style='color:red'>ERROR: Credentials missing in system_settings table!</span>");
}

echo "<h4>Step 1: Getting Access Token</h4>";
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
curl_close($ch);

$data = json_decode($response, true);
$token = $data['access_token'] ?? null;

if ($httpCode == 200 && $token) {
    echo "<span style='color:green'>SUCCESS: Token obtained.</span><br>";
} else {
    die("<span style='color:red'>FAILED to get token. Status: $httpCode, Response: $response</span>");
}

echo "<h4>Step 2: Testing STK Push Initiation</h4>";
$testBody = [
    'payment_channel' => 'm_pesa',
    'till_number' => $till,
    'subscriber' => [
        'first_name' => 'Debug',
        'last_name' => 'Test',
        'phone_number' => '+254700000000'
    ],
    'amount' => [
        'currency' => 'KES',
        'value' => '1.00'
    ],
    'callback_url' => 'https://shanfix.com/callback',
    'metadata' => [
        'debug' => 'true'
    ]
];

$ch = curl_init("$baseUrl/api/v1/incoming_payments");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $token",
    "Content-Type: application/json",
    "Accept: application/json",
    "User-Agent: ShanfixBulkSMS/1.0"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testBody));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "STK Push Target: <b>$baseUrl/api/v1/incoming_payments</b><br>";
echo "HTTP Status Code: <b>$httpCode</b><br>";
if ($curlError) echo "CURL Error: <span style='color:red'>$curlError</span><br>";
echo "Raw Response: <pre>" . htmlspecialchars($response) . "</pre>";

if ($httpCode >= 200 && $httpCode < 300) {
    echo "<span style='color:green;font-size:20px'>SUCCESS! STK Push was initiated.</span>";
} elseif ($httpCode == 429) {
    echo "<span style='color:orange;font-size:20px'>ACCEPTED (Rate Limited): The request is valid, but M-Pesa is busy.</span>";
} else {
    echo "<span style='color:red;font-size:20px'>FAILED!</span><br>";
    echo "If response is empty, check if your firewall blocks outgoing requests to port 443.";
}
?>
