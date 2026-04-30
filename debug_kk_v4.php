<?php
/**
 * IMPROVED DEBUGGER (v4) - Testing Channel & Till Variations
 */
require_once 'includes/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Kopo Kopo Connection Debugger (v4)</h2>";

$clientId = DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'kk_client_id'")['value'] ?? '';
$clientSecret = DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'kk_client_secret'")['value'] ?? '';
$baseUrl = DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'kk_base_url'")['value'] ?? 'https://api.kopokopo.com';
$till = DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'kk_till_number'")['value'] ?? '';

echo "<h4>Step 1: Getting Access Token</h4>";
$ch = curl_init("$baseUrl/oauth/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded", "Accept: application/json", "User-Agent: ShanfixBulkSMS/1.0"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'client_credentials', 'client_id' => $clientId, 'client_secret' => $clientSecret]));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$data = json_decode($response, true);
$token = $data['access_token'] ?? null;

if ($token) {
    echo "<span style='color:green'>SUCCESS: Token obtained.</span><br>";
} else {
    die("<span style='color:red'>FAILED to get token.</span>");
}

function testSTK($token, $baseUrl, $channel, $tillNumber) {
    echo "Testing Channel: <b>$channel</b> | Till: <b>$tillNumber</b>... ";
    $testBody = [
        'payment_channel' => $channel,
        'till_number' => $tillNumber,
        'subscriber' => ['first_name' => 'Debug', 'last_name' => 'Test', 'phone_number' => '+254700000000'],
        'amount' => ['currency' => 'KES', 'value' => '10.00'],
        'callback_url' => 'https://shanfix.com/callback',
        'metadata' => [
            'purchase_id' => '123'
        ]
    ];

    $ch = curl_init("$baseUrl/api/v1/incoming_payments");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: application/json", "Accept: application/json", "User-Agent: ShanfixBulkSMS/1.0"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testBody));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        echo "<span style='color:green'>SUCCESS (HTTP $httpCode)</span><br>";
    } elseif ($httpCode == 429) {
        echo "<span style='color:orange'>ACCEPTED (Rate Limited $httpCode)</span><br>";
    } else {
        echo "<span style='color:red'>FAILED (HTTP $httpCode)</span>. Response: $response<br>";
    }
}

echo "<h4>Step 2: Testing Variations</h4>";
testSTK($token, $baseUrl, 'm_pesa', $till);
testSTK($token, $baseUrl, 'mpesa', $till);
testSTK($token, $baseUrl, 'M-PESA STK Push', $till);
if (strpos($till, 'K') !== 0) {
    testSTK($token, $baseUrl, 'm_pesa', 'K' . $till);
}
?>
