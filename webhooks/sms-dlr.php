<?php
/**
 * SMS Delivery Report (DLR) Webhook — Shanfix Technology
 *
 * Configure this URL in your Onfon Media portal under:
 *   Account → SMS Settings → Delivery Report URL
 *
 *   https://yourdomain.com/webhooks/sms-dlr.php
 *
 * Onfon POST fields (form-encoded):
 *   MessageId     — matches messages.gateway_msg_id
 *   Status        — 1=Delivered, 2=Failed, 0=Pending
 *   MobileNumber  — recipient number
 *   NetworkCode   — carrier code (optional)
 *   Timestamp     — delivery time string (optional)
 *
 * Onfon may also send JSON; both formats are handled below.
 */
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../includes/db.php';

    $logFile = __DIR__ . '/../tmp/dlr.log';
    if (!is_dir(__DIR__ . '/../tmp')) @mkdir(__DIR__ . '/../tmp', 0777, true);

    // Accept both form-encoded POST and JSON body
    $raw  = file_get_contents('php://input');
    $data = [];
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        $data = is_array($json) ? $json : [];
    }
    // Merge GET + POST on top so form-encoded fields win over JSON
    $data = array_merge($data, $_GET, $_POST);

    @file_put_contents($logFile,
        '[' . date('Y-m-d H:i:s') . '] RAW: ' . $raw . ' | POST: ' . json_encode($_POST) . "\n",
        FILE_APPEND
    );

    // Normalise field names — Onfon uses PascalCase; guard against variations
    $msgId    = $data['MessageId']    ?? ($data['messageId']    ?? ($data['msgid']   ?? ''));
    $status   = $data['Status']       ?? ($data['status']       ?? ($data['dlr_status'] ?? ''));
    $mobile   = $data['MobileNumber'] ?? ($data['mobileNumber'] ?? ($data['mobile']  ?? ''));
    $tsRaw    = $data['Timestamp']    ?? ($data['timestamp']    ?? null);

    if (!$msgId) {
        @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] SKIP: no MessageId\n", FILE_APPEND);
        echo json_encode(['status' => 'ignored', 'reason' => 'no MessageId']);
        exit;
    }

    // Map Onfon status codes → our ENUM
    // 1 = Delivered, anything else = failed (0=Pending treated as unknown, 2=Failed)
    $statusInt = (int)$status;
    if ($statusInt === 1) {
        $newStatus   = 'delivered';
        $ts          = $tsRaw ? @strtotime($tsRaw) : false;
        $deliveredAt = ($ts !== false) ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
        $failedReason = null;
    } elseif ($statusInt === 2) {
        $newStatus    = 'failed';
        $deliveredAt  = null;
        $failedReason = 'Undelivered (carrier rejection)';
    } else {
        // 0 or unknown — not actionable yet
        @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] SKIP: status=$status not terminal\n", FILE_APPEND);
        echo json_encode(['status' => 'ignored', 'reason' => 'non-terminal status']);
        exit;
    }

    // Update the message row — only if currently 'sent' to avoid overwriting 'failed' set at send time
    $affected = DB::execute(
        "UPDATE messages
         SET status = ?, delivered_at = ?, failed_reason = COALESCE(?, failed_reason)
         WHERE gateway_msg_id = ? AND status = 'sent'",
        [$newStatus, $deliveredAt, $failedReason, $msgId]
    );

    @file_put_contents($logFile,
        '[' . date('Y-m-d H:i:s') . "] msgId=$msgId status=$newStatus updated=$affected mobile=$mobile\n",
        FILE_APPEND
    );

    echo json_encode(['status' => 'ok', 'updated' => $affected]);

} catch (Throwable $e) {
    @file_put_contents(
        __DIR__ . '/../tmp/dlr.log',
        '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $e->getMessage() . "\n",
        FILE_APPEND
    );
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
