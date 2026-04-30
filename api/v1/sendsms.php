<?php
/**
 * Shanfix Technology - API v1: Send SMS
 * Endpoint: /api/v1/sendsms.php
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/actions/sms.php';

// Support both POST and JSON body
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$params = array_merge($_POST, $input);

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

// Request Data
$to = sanitize($params['to'] ?? '');
$message = $params['message'] ?? '';
$senderId = sanitize($params['sender_id'] ?? 'SHANFIX');

if (!$to || !$message) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields: to, message']);
    exit;
}

// Send SMS
$result = SMS::send($user['id'], $to, $message, $senderId);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message_id' => $result['id'],
        'units_charged' => $result['cost'],
        'remaining_units' => number_format($user['sms_units'] - $result['cost'], 2, '.', '')
    ]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $result['error']]);
}
