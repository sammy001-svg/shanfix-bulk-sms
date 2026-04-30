<?php
/**
 * IMPROVED DEBUGGER - Upload to cPanel root
 */
require_once 'includes/db.php';

// Enable error reporting to screen for this script
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Kopo Kopo Connection Debugger (v2)</h2>";

$clientId = DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'kk_client_id'")['value'] ?? '';
$clientSecret = DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'kk_client_secret'")['value'] ?? '';
$baseUrl = DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'kk_base_url'")['value'] ?? 'https://api.kopokopo.com';

if (!$clientId || !$clientSecret) {
    die("<span style='color:red'>ERROR: Credentials missing in system_settings table!</span>");
}

echo "Target URL: <b>$baseUrl/oauth/token</b><br>";
echo "Client ID: <b>" . substr($clientId, 0, 10) . "...</b><br>";

// Manual CURL test to see exact error
$ch = curl_init("$baseUrl/oauth/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/x-www-form-urlencoded",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'client_credentials',
    'client_id' => $clientId,
    'client_secret' => $clientSecret
]));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<h4>CURL Result:</h4>";
if ($response === false) {
    echo "<span style='color:red'>CURL FAILED: $curlError</span><br>";
} else {
    echo "HTTP Status Code: <b>$httpCode</b><br>";
    echo "Raw Response: <pre>" . htmlspecialchars($response) . "</pre>";
}

if ($httpCode == 200) {
    echo "<span style='color:green;font-size:20px'>SUCCESS! Your credentials are valid.</span>";
} else {
    echo "<span style='color:red'>FAILED! Kopo Kopo rejected the request.</span><br>";
    echo "Possible reasons:<br>";
    echo "1. Client ID/Secret are for Sandbox but URL is Live.<br>";
    echo "2. Your Kopo Kopo App is not 'Approved' yet.<br>";
    echo "3. IP address of this server is blocked (unlikely).";
}
?>
