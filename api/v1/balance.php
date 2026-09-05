<?php
/**
 * Shanfix Technology - API v1: Check Balance
 * Endpoint: /api/v1/balance.php
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/_cors.php';

$input = json_decode(file_get_contents('php://input'), true) ?: [];
// Credentials must come from HTTP headers or request body — never URL query string
// (GET params end up in server access logs and browser history).
$authParams = array_merge($_POST, $input);

$clientId = $_SERVER['HTTP_X_CLIENT_ID'] ?? ($authParams['client_id'] ?? '');
$apiKey   = $_SERVER['HTTP_X_API_KEY']   ?? ($authParams['api_key']   ?? '');

if (!$clientId || !$apiKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Missing Client ID or API Key']);
    exit;
}

$user = validate_api_credentials($clientId, $apiKey);
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid credentials']);
    exit;
}

$freshUnits = (float)DB::queryValue("SELECT sms_units FROM users WHERE id = ?", [$user['id']]);
echo json_encode([
    'success'     => true,
    'client_name' => $user['name'],
    'sms_units'   => $freshUnits,
    'currency'    => 'KES',
]);
