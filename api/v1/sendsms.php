<?php
/**
 * Shanfix Technology - API v1: Send SMS
 * Endpoint: /api/v1/sendsms.php
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/_cors.php';
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

// Rate limit: 60 requests per minute per user
try {
    $bucket = 'api:' . $user['id'];
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
    if ($hitCount > 60) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Rate limit exceeded. Max 60 requests per minute.']);
        exit;
    }
    if (random_int(1, 100) === 1) {
        DB::execute("DELETE FROM api_rate_counters WHERE window_start < DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
    }
} catch (Exception $e) {
    // api_rate_counters table not yet created — skip rate limiting (run phase13 migration)
}

// Request Data
$toRaw    = trim($params['to'] ?? '');
$message  = $params['message'] ?? '';
$senderIdRaw = trim($params['sender_id'] ?? '');

if (!$toRaw || !$message) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields: to, message']);
    exit;
}

if (mb_strlen($message) > 918) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message exceeds 918 characters (max 6 SMS segments).']);
    exit;
}

// Normalize phone number (handles 07XX, +254XX, 254XX formats)
$to = SMS::normalizePhone($toRaw);
if ($to === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "Invalid phone number: $toRaw"]);
    exit;
}

// Resolve sender ID: use provided value or fall back to user's first approved sender
if ($senderIdRaw !== '') {
    $senderId = $senderIdRaw;
} else {
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

// Send SMS
$result = SMS::send($user['id'], $to, $message, $senderId);

if ($result['success']) {
    $freshBalance = (float)DB::queryValue("SELECT sms_units FROM users WHERE id = ?", [$user['id']]);
    echo json_encode([
        'success'         => true,
        'message_id'      => $result['id'],
        'units_charged'   => $result['cost'],
        'remaining_units' => number_format($freshBalance, 2, '.', ''),
    ]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $result['error']]);
}
