<?php
/**
 * Shanfix Technology - API v1: Check Balance
 * Endpoint: /api/v1/balance.php
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';

// Support both POST and JSON body, though GET is also supported for this endpoint
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$params = array_merge($_GET, $_POST, $input);

// Authentication
$clientId = $_SERVER['HTTP_X_CLIENT_ID'] ?? ($params['client_id'] ?? '');
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($params['api_key'] ?? '');

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

// Return Balance
echo json_encode([
    'success' => true,
    'client_name' => $user['name'],
    'sms_units' => (float)$user['sms_units'],
    'currency' => 'KES'
]);
