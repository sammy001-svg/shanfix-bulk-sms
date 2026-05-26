<?php
/**
 * Payhero Public Webhook Handler - Robust Version
 * Located in root to avoid firewall blocks on 'includes' folder.
 */
header("Content-Type: application/json");

try {
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/actions/purchases.php';
    require_once __DIR__ . '/includes/actions/ussd-wallet.php';

    // Logging setup — done first so even auth failures are traceable
    $logFile = __DIR__ . '/tmp/payhero_callback.log';
    if (!is_dir(__DIR__ . '/tmp')) @mkdir(__DIR__ . '/tmp', 0777, true);

    // Token-based webhook authentication.
    // If payhero_webhook_token is configured in system settings, the request
    // must include ?token=<value> matching it.  No token configured = open (legacy).
    $expectedToken = get_setting('payhero_webhook_token', '');
    $receivedToken = $_GET['token'] ?? '';
    if ($expectedToken !== '' && !hash_equals($expectedToken, $receivedToken)) {
        @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] AUTH_FAIL: invalid token\n", FILE_APPEND);
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Check Config & DB
    $configLoaded = defined('DB_NAME') ? 'YES' : 'NO';
    $currentDB = '';
    try {
        $currentDB = DB::queryOne("SELECT DATABASE()")['DATABASE()'] ?? 'UNKNOWN';
    } catch (Exception $e) { $currentDB = 'ERROR: ' . $e->getMessage(); }
    
    @file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] DIAG: ConfigLoaded=$configLoaded, DB=$currentDB" . PHP_EOL, FILE_APPEND);
    
    if (!$data) {
        $msg = "[".date('Y-m-d H:i:s')."] Webhook Access: No POST data received." . PHP_EOL;
        @file_put_contents($logFile, $msg, FILE_APPEND);
        echo json_encode(['status' => 'error', 'message' => 'No data received']);
        exit;
    }

    @file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] RAW_INPUT: " . $input . PHP_EOL, FILE_APPEND);

    /**
     * EXTRACT STATUS
     * Payhero can send 'status' at root or inside 'response' object.
     * Possible values: 'Successful', 'SUCCESSFUL', 'Failed', etc.
     */
    /**
     * EXTRACT STATUS & RESULT CODE
     * Payhero can send 'status' at root (often boolean) or inside 'response' object (string).
     * We prioritize the descriptive string from 'response'.
     */
    $resultCode = $data['ResultCode'] ?? $data['response']['ResultCode'] ?? null;
    $status = $data['response']['Status'] 
           ?? $data['response']['status'] 
           ?? $data['status'] 
           ?? ($data['success'] ? 'Successful' : 'Failed');

    /**
     * EXTRACT PURCHASE ID (External Reference)
     */
    $rawId = $data['external_reference'] 
          ?? $data['response']['ExternalReference'] 
          ?? $data['response']['external_reference'] 
          ?? $data['ExternalReference'] 
          ?? $data['reference']
          ?? $data['CheckoutRequestID'] 
          ?? null;

    // Strip Prefix
    $sitePrefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', SITE_NAME), 0, 3));
    $isUSSD = (strpos(strtoupper($rawId), 'USD') === 0);
    $purchaseId = $rawId;
    
    if ($rawId && !is_numeric($rawId)) {
        if ($isUSSD) {
            $purchaseId = (int)substr($rawId, 3);
        } elseif (strpos(strtoupper($rawId), $sitePrefix) === 0) {
            $purchaseId = (int)substr($rawId, strlen($sitePrefix));
        } else {
            // This belongs to another server!
            @file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] FOREIGN_PAYMENT: ID $rawId ignored (prefix mismatch)." . PHP_EOL, FILE_APPEND);
            $purchaseId = null; 
        }
    }

    /**
     * EXTRACT TRANSACTION REFERENCE (M-Pesa Code)
     */
    $mpesaCode = $data['reference'] 
              ?? $data['mpesa_reference'] 
              ?? $data['response']['MpesaReceiptNumber'] 
              ?? null;

    $logMsg = "[".date('Y-m-d H:i:s')."] PARSED: Status=$status, ResultCode=".($resultCode ?? 'N/A').", ID=$purchaseId, MpesaRef=$mpesaCode" . PHP_EOL;
    @file_put_contents($logFile, $logMsg, FILE_APPEND);

    // SUCCESSFUL PAYMENT CHECK
    $resultCode = $data['ResultCode'] ?? $data['response']['ResultCode'] ?? null;
    $isSuccessful = (in_array(strtoupper((string)$status), ['SUCCESSFUL', 'SUCCESS', 'OK'])) || 
                    ($resultCode !== null && (int)$resultCode === 0);

    @file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] CHECK: isSuccessful=" . ($isSuccessful ? 'YES' : 'NO') . PHP_EOL, FILE_APPEND);

    if ($isSuccessful && $purchaseId) {
        // Retry logic for DB lag
        $purchase = DB::queryOne("SELECT * FROM purchases WHERE id = ?", [$purchaseId]);
        if (!$purchase) {
            // Table Scan Diagnostic
            try {
                $recentIds = DB::query("SELECT id FROM purchases ORDER BY id DESC LIMIT 5");
                $idsList = implode(', ', array_column($recentIds, 'id'));
                @file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] TABLE_SCAN: Latest IDs in DB are: [$idsList]" . PHP_EOL, FILE_APPEND);
            } catch (Exception $e) {}

            @file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] DB_LAG: ID $purchaseId not found. Retrying in 1s..." . PHP_EOL, FILE_APPEND);
            sleep(1);
            $purchase = DB::queryOne("SELECT * FROM purchases WHERE id = ?", [$purchaseId]);
        }

        // Fallback Search by Phone and Amount (if ID lookup failed)
        if (!$purchase && !empty($data['response']['Phone'])) {
            $phone = (string)$data['response']['Phone'];
            $amount = $data['response']['Amount'];
            
            // Format-blind phone search (match last 9 digits)
            $phoneSuffix = substr($phone, -9);
            @file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] FALLBACK: Searching for Pending purchase (Phone Suffix: $phoneSuffix, Amount: $amount)" . PHP_EOL, FILE_APPEND);
            
            $purchase = DB::queryOne("SELECT * FROM purchases WHERE transaction_ref LIKE ? AND amount = ? AND status = 'pending' ORDER BY id DESC LIMIT 1", ["%$phoneSuffix", $amount]);
            
            if ($purchase) {
                $purchaseId = $purchase['id'];
                @file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] FALLBACK_FOUND: Matched ID #$purchaseId" . PHP_EOL, FILE_APPEND);
            }
        }

        // We found a successful payment and a purchase ID
        if ($mpesaCode && $purchaseId) {
            DB::execute("UPDATE purchases SET transaction_ref = ? WHERE id = ? AND (transaction_ref IS NULL OR transaction_ref = '' OR transaction_ref LIKE '254%')", [$mpesaCode, $purchaseId]);
        }

        if ($isUSSD) {
            $completed = USSD_Wallet::complete($purchaseId, $mpesaCode);
        } else {
            $completed = Purchase::complete($purchaseId);
        }
        
        if ($completed) {
            $res = ['status' => 'success', 'message' => 'Funds updated'];
            @file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] RESULT: Success - Wallet updated for #$purchaseId (USSD=$isUSSD)" . PHP_EOL, FILE_APPEND);
        } else {
            $res = ['status' => 'error', 'message' => 'Transaction already completed or not found'];
            @file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] RESULT: Ignored - Transaction #$purchaseId already done or missing" . PHP_EOL, FILE_APPEND);
        }
    } else {
        $res = ['status' => 'ignored', 'message' => 'Payment not successful or ID missing'];
        @file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] RESULT: Ignored - Status not successful" . PHP_EOL, FILE_APPEND);
    }

    echo json_encode($res);

} catch (Exception $e) {
    $err = "[".date('Y-m-d H:i:s')."] WEBHOOK_EXCEPTION: " . $e->getMessage() . PHP_EOL;
    @file_put_contents($logFile, $err, FILE_APPEND);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
