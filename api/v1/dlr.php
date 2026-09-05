<?php
/**
 * Shanfix Technology — Onfon Delivery Report (DLR) Webhook
 *
 * Configure this URL in Onfon's portal under:
 *   Account Settings → SMS → Callback URL / DLR URL
 *
 * URL to register:  https://yourdomain.com/api/v1/dlr.php
 *
 * Onfon calls this endpoint (GET or POST) whenever a message reaches a
 * terminal state: Delivered, Undelivered, Expired, etc.
 *
 * On receipt we update messages.status → 'delivered' | 'undelivered'
 * and stamp delivered_at so clients polling /api/v1/status.php see the
 * real delivery time rather than staying at 'sent' forever.
 *
 * No API credentials are required — this is an inbound push from Onfon.
 * The gateway_msg_id (stored when we sent the message) is the join key.
 */

require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

// ── Accept GET, POST form-encoded, or JSON body ──────────────────────────────
$rawBody = file_get_contents('php://input');
$json    = json_decode($rawBody, true);
$params  = array_merge($_GET, $_POST, is_array($json) ? $json : []);

// ── Debug log — record every inbound DLR for diagnosis ───────────────────────
$logPath = __DIR__ . '/../../includes/gateways/dlr_debug.log';
@file_put_contents(
    $logPath,
    '[' . date('Y-m-d H:i:s') . '] '
    . $_SERVER['REQUEST_METHOD']
    . ' IP:' . ($_SERVER['REMOTE_ADDR'] ?? '-')
    . ' | ' . json_encode($params)
    . "\n",
    FILE_APPEND | LOCK_EX
);

// ── Extract fields — Onfon uses various casing depending on account type ──────
//
// Known field names across different Onfon API versions:
//   MessageId / messageid / msg_id / MsgId
//   Status / status / MsgStatus / DeliveryStatus
//   StatusCode / status_code / Code
//   MobileNumber / mobilenumber / PhoneNumber / MSISDN / To
//   Description / description / Reason
//   Timestamp / timestamp / SentDateTime / DlrTime

function dlr_pick(array $p, array $keys, $default = ''): string {
    foreach ($keys as $k) {
        if (isset($p[$k]) && $p[$k] !== '') return (string)$p[$k];
        // Try lowercase variant too
        $kl = strtolower($k);
        if (isset($p[$kl]) && $p[$kl] !== '') return (string)$p[$kl];
    }
    return (string)$default;
}

$gatewayMsgId = dlr_pick($params, ['MessageId', 'MsgId', 'msg_id', 'MessageID', 'msgid', 'id']);
$rawStatus    = dlr_pick($params, ['Status', 'MsgStatus', 'DeliveryStatus', 'status', 'StatusDescription']);
$statusCode   = dlr_pick($params, ['StatusCode', 'status_code', 'Code', 'ErrorCode', 'DlrStatus']);
$phone        = dlr_pick($params, ['MobileNumber', 'PhoneNumber', 'MSISDN', 'To', 'mobilenumber', 'to']);
$description  = dlr_pick($params, ['Description', 'Reason', 'description', 'MessageErrorDescription']);
$dlrTime      = dlr_pick($params, ['Timestamp', 'SentDateTime', 'DlrTime', 'DeliveredAt', 'timestamp']);

if (!$gatewayMsgId) {
    // Nothing we can act on — acknowledge but do nothing
    echo json_encode(['status' => 'ignored', 'reason' => 'No message ID in payload']);
    exit;
}

// ── Map Onfon status → our internal status ────────────────────────────────────
//
// Onfon status codes (when present):
//   1 or 2 = Delivered
//   3      = Not delivered (network error, handset off, etc.)
//   4      = Expired (message TTL elapsed)
//   5      = Unknown / buffered
//
// Onfon status strings (when present):
//   "DeliveredToTerminal", "Delivered", "DELIVERED"   → delivered
//   "DeliveryFailed", "UnDeliverable", "FAILED",
//   "NotDelivered", "UNDELIVERED", "Expired"          → undelivered
//   "DeliveredToNetwork", "Sent", "SENT", "Buffered"  → still in transit (ignore)

$newStatus = null;

// Try numeric code first
if ($statusCode !== '') {
    $code = (int)$statusCode;
    if ($code === 1 || $code === 2) {
        $newStatus = 'delivered';
    } elseif ($code === 3 || $code === 4) {
        $newStatus = 'undelivered';
    }
    // code 5 / unknown = still in transit → leave as 'sent'
}

// Fall back to string matching
if ($newStatus === null && $rawStatus !== '') {
    $sl = strtolower($rawStatus);
    if (
        str_contains($sl, 'deliveredtoterm') ||
        str_contains($sl, 'delivered')       ||
        $sl === '1' || $sl === '2'           ||
        $sl === 'success' || $sl === 'ok'
    ) {
        $newStatus = 'delivered';
    } elseif (
        str_contains($sl, 'fail')         ||
        str_contains($sl, 'undeliver')    ||
        str_contains($sl, 'notdeliver')   ||
        str_contains($sl, 'expired')      ||
        str_contains($sl, 'rejected')     ||
        $sl === '3' || $sl === '4'
    ) {
        $newStatus = 'undelivered';
    }
    // "deliveredtonetwork", "sent", "buffered" → still in transit → ignore
}

if ($newStatus === null) {
    // Transit status or unrecognised — do not update the record yet
    echo json_encode([
        'status'  => 'ignored',
        'reason'  => 'Transit or unrecognised status: ' . $rawStatus . ' (code: ' . $statusCode . ')',
        'msg_id'  => $gatewayMsgId,
    ]);
    exit;
}

// ── Resolve delivered_at timestamp ────────────────────────────────────────────
$deliveredAt = null;
if ($newStatus === 'delivered') {
    if ($dlrTime !== '') {
        // Accept ISO 8601 or common date strings; fall back to NOW()
        $ts = strtotime($dlrTime);
        $deliveredAt = ($ts !== false) ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
    } else {
        $deliveredAt = date('Y-m-d H:i:s');
    }
}

// ── Update the message record ─────────────────────────────────────────────────
// Only update if currently 'sent' or 'queued' — do not overwrite an already
// 'delivered' record with 'undelivered' if a second DLR fires out of order.
$updated = DB::execute(
    "UPDATE messages
     SET status       = ?,
         delivered_at = ?,
         failed_reason = CASE WHEN ? = 'undelivered' THEN ? ELSE failed_reason END
     WHERE gateway_msg_id = ?
       AND status IN ('sent', 'queued')",
    [
        $newStatus,
        $deliveredAt,
        $newStatus,
        $description ?: 'Delivery failed — carrier returned undeliverable status',
        $gatewayMsgId,
    ]
);

echo json_encode([
    'status'       => 'ok',
    'msg_id'       => $gatewayMsgId,
    'new_status'   => $newStatus,
    'delivered_at' => $deliveredAt,
    'rows_updated' => $updated,
]);
