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
require_once __DIR__ . '/../../includes/helpers/dlr-status.php';

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

// ── Classify the carrier status ───────────────────────────────────────────────
//
// DlrStatus is shared with webhooks/sms-dlr.php and the Delivery Reports page,
// so whichever endpoint Onfon is pointed at produces identical reporting.
//
// The granular label is preserved in messages.dlr_status. That is the column
// the report pivots on — collapsing straight to delivered/undelivered here is
// what previously made AbsentSubscriber, DeliveryImpossible and the rest
// impossible to report on.

// Prefer a descriptive string over a bare numeric code; the description field
// often carries the most specific state ("Absent Subscriber", "Expired").
$dlrSource = '';
foreach ([$description, $rawStatus, $statusCode] as $candidate) {
    if (trim((string)$candidate) !== '' && !is_numeric(trim((string)$candidate))) {
        $dlrSource = $candidate;
        break;
    }
}
if ($dlrSource === '') {
    $dlrSource = $statusCode !== '' ? $statusCode : $rawStatus;
}

$dlrLabel  = DlrStatus::normalise($dlrSource);
$newStatus = DlrStatus::toEnum($dlrLabel);

// Always record the carrier state, even for a non-terminal one such as
// Submitted — the report shows those as their own column.
DB::execute(
    "UPDATE messages SET dlr_status = ? WHERE gateway_msg_id = ?",
    [$dlrLabel, $gatewayMsgId]
);

if ($newStatus === null) {
    // Still in transit: dlr_status is updated above, messages.status untouched.
    echo json_encode([
        'status'     => 'ok',
        'terminal'   => false,
        'dlr_status' => $dlrLabel,
        'msg_id'     => $gatewayMsgId,
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
         dlr_status   = ?,
         failed_reason = CASE WHEN ? = 'undelivered' THEN ? ELSE failed_reason END
     WHERE gateway_msg_id = ?
       AND status IN ('sent', 'queued')",
    [
        $newStatus,
        $deliveredAt,
        $dlrLabel,
        $newStatus,
        $description ?: ('Undelivered: ' . $dlrLabel),
        $gatewayMsgId,
    ]
);

echo json_encode([
    'status'       => 'ok',
    'terminal'     => true,
    'msg_id'       => $gatewayMsgId,
    'new_status'   => $newStatus,
    'dlr_status'   => $dlrLabel,
    'delivered_at' => $deliveredAt,
    'rows_updated' => $updated,
]);
