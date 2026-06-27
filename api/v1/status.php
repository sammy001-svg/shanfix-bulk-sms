<?php
/**
 * Shanfix Technology - API v1: Check Message Status (single or batch)
 * Endpoint: /api/v1/status.php
 * Single:  ?message_id=123
 * Batch:   ?message_ids=123,456,789   OR  POST message_ids[] array
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/_cors.php';

$input      = json_decode(file_get_contents('php://input'), true) ?: [];
$authParams = array_merge($_POST, $input);
// message_id / message_ids may come from GET, but credentials must not
$params     = array_merge($_GET, $_POST, $input);

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

// --- Batch mode: message_ids (array or comma-separated string) ---
$rawIds = $params['message_ids'] ?? null;
if ($rawIds !== null) {
    $ids = is_array($rawIds)
        ? $rawIds
        : array_filter(array_map('trim', explode(',', (string)$rawIds)));

    $ids = array_values(array_unique(array_map('intval', $ids)));
    $ids = array_filter($ids); // remove zeros

    if (empty($ids)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'message_ids must not be empty']);
        exit;
    }
    if (count($ids) > 500) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Maximum 500 message IDs per request']);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = DB::query(
        "SELECT id, recipient, status, units_charged, gateway_msg_id,
                created_at, sent_at, delivered_at, failed_reason
         FROM messages
         WHERE id IN ($placeholders) AND user_id = ?",
        array_merge($ids, [$user['id']])
    );

    $indexed = [];
    foreach ($rows as $m) {
        $indexed[(int)$m['id']] = [
            'message_id'     => (int)$m['id'],
            'recipient'      => $m['recipient'],
            'status'         => $m['status'],
            'units_charged'  => (float)$m['units_charged'],
            'gateway_msg_id' => $m['gateway_msg_id'],
            'created_at'     => $m['created_at'],
            'sent_at'        => $m['sent_at'],
            'delivered_at'   => $m['delivered_at'],
            'failed_reason'  => $m['failed_reason'],
        ];
    }

    $results = [];
    foreach ($ids as $id) {
        $results[] = $indexed[$id] ?? ['message_id' => $id, 'error' => 'Not found'];
    }

    echo json_encode(['success' => true, 'count' => count($results), 'messages' => $results]);
    exit;
}

// --- Single mode: message_id (backwards-compatible) ---
$messageId = $params['message_id'] ?? '';

if (!$messageId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required field: message_id or message_ids']);
    exit;
}

$message = DB::queryOne(
    "SELECT id, recipient, status, units_charged, gateway_msg_id,
            created_at, sent_at, delivered_at, failed_reason
     FROM messages WHERE id = ? AND user_id = ? LIMIT 1",
    [$messageId, $user['id']]
);

if (!$message) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Message not found']);
    exit;
}

echo json_encode([
    'success'        => true,
    'message_id'     => (int)$message['id'],
    'recipient'      => $message['recipient'],
    'status'         => $message['status'],
    'units_charged'  => (float)$message['units_charged'],
    'gateway_msg_id' => $message['gateway_msg_id'],
    'created_at'     => $message['created_at'],
    'sent_at'        => $message['sent_at'],
    'delivered_at'   => $message['delivered_at'],
    'failed_reason'  => $message['failed_reason'],
]);
