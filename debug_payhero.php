<?php
/**
 * Payhero Connection Debugger
 * Verifies if Payhero credentials and payload are correct.
 */
header('Content-Type: text/plain');

// Payhero Credentials from user
$apiUsername = 'qN3ovLV2V5HBRXRd3zZi';
$apiPassword = 'LneS0zAN6z16W11qqmS44vIIAWi3ALJreiveiRf8';
$channelId   = 3197;

echo "Payhero Connection Debugger\n";
echo "Target URL: https://backend.payhero.co.ke/api/v2/payments\n";
echo "Channel ID: $channelId\n";
echo "---------------------------------\n";

$phone = '0700000000'; // Test number
$amount = 10;
$reference = 'DEBUG-' . time();
$callbackUrl = 'https://shanfix.com/callback';

$body = [
    'amount' => (float)$amount,
    'phone_number' => $phone,
    'channel_id' => (int)$channelId,
    'provider' => 'm-pesa',
    'external_reference' => $reference,
    'callback_url' => $callbackUrl
];

$jsonBody = json_encode($body);
echo "Request Body: $jsonBody\n\n";

$ch = curl_init("https://backend.payhero.co.ke/api/v2/payments");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Basic " . base64_encode("$apiUsername:$apiPassword"),
    "Content-Type: application/json",
    "Accept: application/json",
    "User-Agent: ShanfixBulkSMS/1.0"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";
if ($curlError) echo "CURL Error: $curlError\n";
echo "Raw Response: $response\n";

$data = json_decode($response, true);
if ($httpCode >= 200 && $httpCode < 300 && isset($data['success']) && $data['success']) {
    echo "\nSUCCESS! Your Payhero credentials are valid and the request was accepted.\n";
} else {
    echo "\nFAILED! Please check the raw response above for errors.\n";
}
