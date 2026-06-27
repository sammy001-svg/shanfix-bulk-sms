<?php
/**
 * Shanfix Technology - API v1: List Messages
 * Endpoint: /api/v1/messages.php
 *
 * GET parameters (all optional):
 *   page       — page number, default 1
 *   per_page   — results per page, 1-100, default 50
 *   status     — filter: queued | sent | delivered | failed
 *   from       — start date YYYY-MM-DD (inclusive, based on created_at)
 *   to         — end date   YYYY-MM-DD (inclusive)
 *   campaign_id — restrict to a specific campaign
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/_cors.php';

$input      = json_decode(file_get_contents('php://input'), true) ?: [];
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

// --- Parse & validate query parameters ---
$params    = array_merge($_GET, $input);
$page      = max(1, (int)($params['page']    ?? 1));
$perPage   = min(100, max(1, (int)($params['per_page'] ?? 50)));
$offset    = ($page - 1) * $perPage;

$validStatuses = ['queued', 'sent', 'delivered', 'failed'];
$statusFilter  = ($params['status'] ?? '') !== '' && in_array($params['status'], $validStatuses, true)
    ? $params['status'] : null;

$fromDate   = ($params['from'] ?? '') !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['from'])
    ? $params['from'] : null;
$toDate     = ($params['to']   ?? '') !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['to'])
    ? $params['to']   : null;
$campaignId = isset($params['campaign_id']) && (int)$params['campaign_id'] > 0
    ? (int)$params['campaign_id'] : null;

// --- Build query ---
$where  = 'WHERE user_id = ?';
$qParams = [$user['id']];

if ($statusFilter !== null) {
    $where    .= ' AND status = ?';
    $qParams[] = $statusFilter;
}
if ($fromDate !== null) {
    $where    .= ' AND DATE(created_at) >= ?';
    $qParams[] = $fromDate;
}
if ($toDate !== null) {
    $where    .= ' AND DATE(created_at) <= ?';
    $qParams[] = $toDate;
}
if ($campaignId !== null) {
    $where    .= ' AND campaign_id = ?';
    $qParams[] = $campaignId;
}

$total = (int)DB::queryValue("SELECT COUNT(*) FROM messages $where", $qParams);
$rows  = DB::query(
    "SELECT id, campaign_id, sender_id, recipient, message,
            units_charged, status, gateway_msg_id,
            created_at, sent_at, delivered_at, failed_reason
     FROM messages $where
     ORDER BY created_at DESC
     LIMIT $perPage OFFSET $offset",
    $qParams
);

$messages = [];
foreach ($rows as $m) {
    $messages[] = [
        'message_id'     => (int)$m['id'],
        'campaign_id'    => $m['campaign_id'] ? (int)$m['campaign_id'] : null,
        'sender_id'      => $m['sender_id'],
        'recipient'      => $m['recipient'],
        'message'        => $m['message'],
        'units_charged'  => (float)$m['units_charged'],
        'status'         => $m['status'],
        'gateway_msg_id' => $m['gateway_msg_id'],
        'created_at'     => $m['created_at'],
        'sent_at'        => $m['sent_at'],
        'delivered_at'   => $m['delivered_at'],
        'failed_reason'  => $m['failed_reason'],
    ];
}

echo json_encode([
    'success'    => true,
    'total'      => $total,
    'page'       => $page,
    'per_page'   => $perPage,
    'pages'      => (int)ceil($total / $perPage),
    'messages'   => $messages,
]);
