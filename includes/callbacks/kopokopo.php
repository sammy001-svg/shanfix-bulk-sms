<?php
/**
 * Kopo Kopo Callback Handler - Shanfix Technology
 * Verifies X-KopoKopo-Signature HMAC before processing any payment.
 */
header("Content-Type: application/json");

try {
    require_once __DIR__ . '/../db.php';
    require_once __DIR__ . '/../actions/purchases.php';

    $logFile = __DIR__ . '/../../tmp/kk_callback.log';
    if (!is_dir(__DIR__ . '/../../tmp')) @mkdir(__DIR__ . '/../../tmp', 0777, true);

    $input = file_get_contents('php://input');

    // HMAC-SHA256 signature verification.
    // Set kk_webhook_secret in Settings > Payments to enable.
    // KoPokoPo sends: X-KopoKopo-Signature: sha256=<hmac>
    $expectedSecret = get_setting('kk_webhook_secret', '');
    if ($expectedSecret !== '') {
        $receivedSig = $_SERVER['HTTP_X_KOPOKOPO_SIGNATURE'] ?? '';
        $computedSig = 'sha256=' . hash_hmac('sha256', $input, $expectedSecret);
        if (!hash_equals($computedSig, $receivedSig)) {
            @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] AUTH_FAIL: signature mismatch\n", FILE_APPEND);
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
            exit;
        }
    }

    $data = json_decode($input, true);

    if (!$data) {
        @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR: empty or non-JSON body\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'No data received']);
        exit;
    }

    @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] RAW: " . $input . "\n", FILE_APPEND);

    $status = $data['data']['attributes']['status'] ?? '';
    $ref    = $data['data']['attributes']['metadata']['reference'] ?? '';

    // Reference format: "<purchaseId>-<timestamp>" (see KopoKopo::initiateSTKPush)
    $purchaseId = (int)explode('-', $ref)[0];

    @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] PARSED: Status=$status, ID=$purchaseId\n", FILE_APPEND);

    if ($status === 'Success' && $purchaseId > 0) {
        $completed = Purchase::complete($purchaseId);
        if ($completed) {
            @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] RESULT: Wallet updated for #$purchaseId\n", FILE_APPEND);
            echo json_encode(['status' => 'success', 'message' => 'Payment completed']);
        } else {
            @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] RESULT: Ignored — already completed or not found (#$purchaseId)\n", FILE_APPEND);
            echo json_encode(['status' => 'ignored', 'message' => 'Already processed or not found']);
        }
    } else {
        @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] RESULT: Ignored — status not successful ($status)\n", FILE_APPEND);
        echo json_encode(['status' => 'ignored', 'message' => 'Payment not successful']);
    }

} catch (Exception $e) {
    @file_put_contents($logFile ?? sys_get_temp_dir() . '/kk_callback.log',
        "[" . date('Y-m-d H:i:s') . "] EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
