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
 *   Status        — 1=Delivered, 2=Failed, 0=Pending, or a carrier status string
 *   MobileNumber  — recipient number
 *   NetworkCode   — carrier code (optional)
 *   Timestamp     — delivery time string (optional)
 *
 * Onfon may also send JSON; both formats are handled below.
 *
 * TWO STATUSES ARE RECORDED PER MESSAGE
 * -------------------------------------
 * messages.status     — the 5-value ENUM the rest of the app reasons about.
 * messages.dlr_status — the raw carrier state as sent (DELIVRD, Submitted,
 *                       AbsentSubscriber, DeliveryImpossible, REJECTD, ...).
 * The Delivery Reports pages pivot on dlr_status, so a non-terminal state such
 * as "Submitted" is still recorded even though it leaves the ENUM untouched.
 */
header('Content-Type: application/json');

/**
 * Classify a raw carrier status into ['enum' => ?string, 'label' => string].
 *
 * enum === null means the state is not terminal: record the label for
 * reporting but leave messages.status alone.
 */
function dlr_classify($raw): array {
    $trimmed = trim((string)$raw);

    // Numeric Onfon codes — the documented default.
    if ($trimmed !== '' && is_numeric($trimmed)) {
        switch ((int)$trimmed) {
            case 1:  return ['enum' => 'delivered', 'label' => 'DELIVRD'];
            case 2:  return ['enum' => 'failed',    'label' => 'REJECTD'];
            default: return ['enum' => null,        'label' => 'Submitted'];
        }
    }

    // Carrier status strings, matched case-insensitively and space-insensitively.
    $key = strtolower(preg_replace('/[^a-z]/i', '', $trimmed));

    $delivered = ['delivrd', 'delivered', 'delivredtoterminal', 'deliveredtoterminal', 'success'];
    $failed    = [
        'rejectd', 'rejected', 'failed', 'undeliv', 'undelivered', 'undeliverable',
        'absentsubscriber', 'deliveryimpossible', 'expired', 'deleted',
        'sendernameblacklisted', 'blacklisted', 'unknownsubscriber', 'invalidnumber',
    ];
    $pending   = ['submitted', 'acceptd', 'accepted', 'enroute', 'buffered', 'pending', 'queued'];

    if (in_array($key, $delivered, true)) return ['enum' => 'delivered',   'label' => $trimmed];
    if (in_array($key, $failed, true))    return ['enum' => 'undelivered', 'label' => $trimmed];
    if (in_array($key, $pending, true))   return ['enum' => null,          'label' => $trimmed];

    // Unrecognised but non-empty — keep it for the report, don't guess the ENUM.
    return ['enum' => null, 'label' => $trimmed !== '' ? $trimmed : 'Unknown'];
}

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

    // A descriptive status, when present, is richer than the numeric code and
    // is what the Onfon portal reports on — prefer it for dlr_status.
    $descriptive = $data['StatusDescription'] ?? ($data['statusDescription']
                ?? ($data['DeliveryStatus']   ?? ($data['deliveryStatus']
                ?? ($data['StatusText']       ?? ($data['ErrorCode'] ?? null)))));

    if (!$msgId) {
        @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] SKIP: no MessageId\n", FILE_APPEND);
        echo json_encode(['status' => 'ignored', 'reason' => 'no MessageId']);
        exit;
    }

    // Classify on the descriptive value when it is usable, else the code.
    $source     = ($descriptive !== null && trim((string)$descriptive) !== '') ? $descriptive : $status;
    $classified = dlr_classify($source);
    $dlrLabel   = substr($classified['label'], 0, 60);
    $newStatus  = $classified['enum'];

    if ($newStatus === 'delivered') {
        $ts           = $tsRaw ? @strtotime($tsRaw) : false;
        $deliveredAt  = ($ts !== false) ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');
        $failedReason = null;
    } elseif ($newStatus !== null) {
        $deliveredAt  = null;
        $failedReason = 'Undelivered: ' . $dlrLabel;
    } else {
        // Non-terminal: record the carrier state for reporting, leave status as is.
        $affected = DB::execute(
            "UPDATE messages SET dlr_status = ? WHERE gateway_msg_id = ?",
            [$dlrLabel, $msgId]
        );
        @file_put_contents($logFile,
            '[' . date('Y-m-d H:i:s') . "] NON_TERMINAL: msgId=$msgId dlr=$dlrLabel updated=$affected\n",
            FILE_APPEND
        );
        echo json_encode(['status' => 'ok', 'terminal' => false, 'updated' => $affected]);
        exit;
    }

    // Update the message row — only if currently 'sent' to avoid overwriting 'failed' set at send time
    $affected = DB::execute(
        "UPDATE messages
         SET status = ?, delivered_at = ?, failed_reason = COALESCE(?, failed_reason), dlr_status = ?
         WHERE gateway_msg_id = ? AND status = 'sent'",
        [$newStatus, $deliveredAt, $failedReason, $dlrLabel, $msgId]
    );

    // The message had already left 'sent' (e.g. a duplicate DLR). Still keep the
    // carrier status current so the report reflects the latest carrier state.
    if (!$affected) {
        DB::execute(
            "UPDATE messages SET dlr_status = ? WHERE gateway_msg_id = ?",
            [$dlrLabel, $msgId]
        );
    }

    @file_put_contents($logFile,
        '[' . date('Y-m-d H:i:s') . "] msgId=$msgId status=$newStatus dlr=$dlrLabel updated=$affected mobile=$mobile\n",
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
