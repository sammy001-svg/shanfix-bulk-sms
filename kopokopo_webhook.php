<?php
/**
 * Kopo Kopo Public Webhook Handler - Shanfix Technology
 *
 * Lives in the document root on purpose: .htaccess blocks /includes/ with
 * [F,L], so a handler under includes/callbacks/ answers 403 and Kopo Kopo can
 * never reconcile the payment.
 *
 * Routing is driven by the reference set when the STK push was initiated
 * (see KopoKopo::initiateSTKPush):
 *   "USD<id>"          → ussd_transactions row  (USSD_Wallet::complete)
 *   "<SITEPREFIX><id>" → purchases row          (Purchase::complete)
 * Anything else belongs to a different deployment sharing the same Kopo Kopo
 * till and is ignored rather than applied to the wrong account.
 */
header('Content-Type: application/json');

$logFile = __DIR__ . '/tmp/kk_callback.log';

/** Append one line to the callback log, best-effort. */
function kk_log(string $msg): void {
    global $logFile;
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

/**
 * Pull the first non-empty value found at any of the given dot-paths.
 * Kopo Kopo nests the result differently for per-request callbacks and for
 * subscription webhooks, so every field is looked up in both shapes.
 */
function kk_pick(array $data, array $paths) {
    foreach ($paths as $path) {
        $node = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($node) || !isset($node[$segment])) {
                $node = null;
                break;
            }
            $node = $node[$segment];
        }
        if ($node !== null && $node !== '' && !is_array($node)) return $node;
    }
    return null;
}

try {
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/actions/purchases.php';
    require_once __DIR__ . '/includes/actions/ussd-wallet.php';

    if (!is_dir(__DIR__ . '/tmp')) @mkdir(__DIR__ . '/tmp', 0777, true);

    $input = file_get_contents('php://input');

    // ── Authentication ────────────────────────────────────────────────────────
    // Two optional guards; whichever is configured is enforced. With neither
    // set the endpoint is open (legacy behaviour) and the reference prefix
    // check below is the only thing keeping foreign payments out.

    // 1. HMAC-SHA256 over the raw body, when Kopo Kopo signs the request.
    $secret = get_setting('kk_webhook_secret', '');
    if ($secret !== '') {
        $receivedSig = $_SERVER['HTTP_X_KOPOKOPO_SIGNATURE'] ?? '';
        if ($receivedSig === '') {
            kk_log('AUTH_FAIL: kk_webhook_secret is set but no X-KopoKopo-Signature header was sent');
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
            exit;
        }
        // Kopo Kopo sends either "sha256=<hmac>" or a bare hex digest.
        $computed = hash_hmac('sha256', $input, $secret);
        $ok = hash_equals('sha256=' . $computed, $receivedSig) || hash_equals($computed, $receivedSig);
        if (!$ok) {
            kk_log('AUTH_FAIL: signature mismatch');
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
            exit;
        }
    }

    // 2. Shared secret in the query string, for tills that do not sign.
    $expectedToken = get_setting('kk_webhook_token', '');
    if ($expectedToken !== '' && !hash_equals($expectedToken, (string)($_GET['token'] ?? ''))) {
        kk_log('AUTH_FAIL: invalid token');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }

    $data = json_decode($input, true);
    if (!$data) {
        kk_log('ERROR: empty or non-JSON body');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'No data received']);
        exit;
    }

    kk_log('RAW: ' . $input);

    // ── Extract status ────────────────────────────────────────────────────────
    // attributes.status is the request outcome ("Success"/"Failed"); the nested
    // event resource carries the money-movement status ("Received").
    $status = kk_pick($data, [
        'data.attributes.status',
        'attributes.status',
        'data.attributes.event.resource.status',
        'attributes.event.resource.status',
        'status',
    ]);

    $resourceStatus = kk_pick($data, [
        'data.attributes.event.resource.status',
        'attributes.event.resource.status',
    ]);

    // ── Extract the routing reference ─────────────────────────────────────────
    $rawRef = kk_pick($data, [
        'data.attributes.metadata.reference',
        'attributes.metadata.reference',
        'metadata.reference',
        'data.attributes.event.resource.reference',
    ]);

    // ── Extract the M-Pesa receipt number ─────────────────────────────────────
    $mpesaCode = kk_pick($data, [
        'data.attributes.event.resource.reference',
        'attributes.event.resource.reference',
        'data.attributes.event.resource.receipt_number',
    ]);

    // ── Route by prefix ───────────────────────────────────────────────────────
    $sitePrefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', SITE_NAME), 0, 3));
    $rawRef     = trim((string)$rawRef);
    $isUSSD     = (stripos($rawRef, 'USD') === 0);
    $recordId   = null;

    if ($rawRef === '') {
        kk_log('ERROR: no reference in payload — cannot route payment');
    } elseif (is_numeric($rawRef)) {
        // Unprefixed numeric reference — treat as a purchase id (legacy rows).
        $recordId = (int)$rawRef;
    } elseif ($isUSSD) {
        $recordId = (int)substr($rawRef, 3);
    } elseif (stripos($rawRef, $sitePrefix) === 0) {
        $recordId = (int)substr($rawRef, strlen($sitePrefix));
    } else {
        kk_log("FOREIGN_PAYMENT: reference '$rawRef' ignored (prefix mismatch, expected '$sitePrefix')");
    }

    $isSuccessful = in_array(strtoupper((string)$status), ['SUCCESS', 'SUCCESSFUL', 'RECEIVED'], true)
                 || in_array(strtoupper((string)$resourceStatus), ['SUCCESS', 'RECEIVED'], true);

    kk_log(sprintf(
        'PARSED: status=%s resource=%s ref=%s id=%s ussd=%s mpesa=%s success=%s',
        $status ?? 'N/A',
        $resourceStatus ?? 'N/A',
        $rawRef !== '' ? $rawRef : 'N/A',
        $recordId ?? 'N/A',
        $isUSSD ? 'YES' : 'NO',
        $mpesaCode ?? 'N/A',
        $isSuccessful ? 'YES' : 'NO'
    ));

    if (!$isSuccessful || !$recordId) {
        kk_log('RESULT: ignored — payment not successful or reference unusable');
        echo json_encode(['status' => 'ignored', 'message' => 'Payment not successful or reference missing']);
        exit;
    }

    if ($isUSSD) {
        $completed = USSD_Wallet::complete($recordId, $mpesaCode);
    } else {
        // Record the M-Pesa receipt before crediting, but never overwrite a real
        // receipt — at this point transaction_ref still holds the payer's phone.
        if ($mpesaCode) {
            DB::execute(
                "UPDATE purchases SET transaction_ref = ?
                 WHERE id = ? AND (transaction_ref IS NULL OR transaction_ref = '' OR transaction_ref LIKE '%254%' OR transaction_ref LIKE '0%')",
                [$mpesaCode, $recordId]
            );
        }
        $completed = Purchase::complete($recordId);
    }

    if ($completed) {
        kk_log("RESULT: wallet updated for #$recordId (ussd=" . ($isUSSD ? 'YES' : 'NO') . ')');
        echo json_encode(['status' => 'success', 'message' => 'Funds updated']);
    } else {
        kk_log("RESULT: ignored — #$recordId already completed or not found");
        echo json_encode(['status' => 'ignored', 'message' => 'Already processed or not found']);
    }

} catch (Throwable $e) {
    kk_log('EXCEPTION: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal error']);
}
