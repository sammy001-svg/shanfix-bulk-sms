<?php
/**
 * Shanfix Technology - API v1: Bulk SMS Send
 * Endpoint: /api/v1/bulksend.php
 *
 * POST fields (form-encoded or JSON body):
 *   client_id  — or HTTP header X-Client-Id
 *   api_key    — or HTTP header X-Api-Key
 *   to         — array of phone numbers OR comma-separated string (max 1000)
 *   message    — SMS body text
 *   sender_id  — approved sender ID (optional, defaults to account primary)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/_cors.php';
require_once __DIR__ . '/../../includes/actions/sms.php';

$input  = json_decode(file_get_contents('php://input'), true) ?: [];
$params = array_merge($_POST, $input);

$clientId = $_SERVER['HTTP_X_CLIENT_ID'] ?? ($params['client_id'] ?? '');
$apiKey   = $_SERVER['HTTP_X_API_KEY']   ?? ($params['api_key']   ?? '');

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

if ($user['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Account suspended']);
    exit;
}

// Rate limit: 10 bulk requests per minute (each may carry up to 1 000 recipients)
try {
    $bucket = 'api_bulk:' . $user['id'];
    $window = date('Y-m-d H:i:00');
    DB::execute(
        "INSERT INTO api_rate_counters (bucket, window_start, hits)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE hits = hits + 1",
        [$bucket, $window]
    );
    $hitCount = (int)DB::queryValue(
        "SELECT hits FROM api_rate_counters WHERE bucket = ? AND window_start = ?",
        [$bucket, $window]
    );
    if ($hitCount > 10) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Rate limit exceeded. Max 10 bulk requests per minute.']);
        exit;
    }
    if (random_int(1, 100) === 1) {
        DB::execute("DELETE FROM api_rate_counters WHERE window_start < DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
    }
} catch (Exception $e) {
    // api_rate_counters table not yet created — skip rate limiting (run phase13 migration)
}

// Validate required fields
$message  = trim($params['message'] ?? '');
$senderId = trim($params['sender_id'] ?? '');
$toRaw    = $params['to'] ?? null;

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required field: message']);
    exit;
}

if (mb_strlen($message) > 918) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message exceeds 918 characters (max 6 SMS segments).']);
    exit;
}
if ($toRaw === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required field: to']);
    exit;
}

// Parse recipients — accept JSON array or comma-separated string
$phones = is_array($toRaw)
    ? $toRaw
    : array_values(array_filter(array_map('trim', explode(',', (string)$toRaw))));

if (empty($phones)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No recipients provided in to field']);
    exit;
}
if (count($phones) > 1000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Maximum 1000 recipients per request']);
    exit;
}

// Resolve sender ID: use provided, else fall back to user's first approved sender
if ($senderId === '') {
    $firstSender = DB::queryOne(
        "SELECT sender_id FROM sender_ids WHERE user_id = ? AND status = 'approved' ORDER BY id LIMIT 1",
        [$user['id']]
    );
    if (!$firstSender) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No approved sender ID on your account. Please specify sender_id.']);
        exit;
    }
    $senderId = $firstSender['sender_id'];
}

$result = SMS::sendBulk($user['id'], $phones, $message, $senderId);

if (!$result['success']) {
    http_response_code(400);
}

echo json_encode($result);
