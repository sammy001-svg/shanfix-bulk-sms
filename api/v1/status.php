<?php
/**
 * Shanfix Technology - API v1: Check Message Status
 * Endpoint: /api/v1/status.php
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';

// Support both POST and JSON body
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

$messageId = $params['message_id'] ?? '';

if (!$messageId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required field: message_id']);
    exit;
}

// Query message status
$message = DB::queryOne(
    "SELECT id, recipient, status, units_charged, created_at, sent_at FROM messages WHERE id = ? AND user_id = ? LIMIT 1",
    [$messageId, $user['id']]
);

if (!$message) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Message not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'message_id' => $message['id'],
    'recipient' => $message['recipient'],
    'status' => $message['status'],
    'units_charged' => (float)$message['units_charged'],
    'created_at' => $message['created_at'],
    'sent_at' => $message['sent_at']
]);
