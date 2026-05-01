<?php
/**
 * Payhero Public Webhook Handler - Robust Version
 * Located in root to avoid firewall blocks on 'includes' folder.
 */
header("Content-Type: application/json");

try {
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/actions/purchases.php';

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Logging setup
    $logFile = __DIR__ . '/tmp/payhero_callback.log';
    if (!is_dir(__DIR__ . '/tmp')) @mkdir(__DIR__ . '/tmp', 0777, true);
    
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
    $status = $data['status'] 
           ?? $data['response']['Status'] 
           ?? $data['response']['status'] 
           ?? ($data['success'] ? 'Successful' : 'Failed');

    /**
     * EXTRACT PURCHASE ID (External Reference)
     * We pass our internal Purchase ID as 'external_reference'.
     */
    $purchaseId = $data['external_reference'] 
               ?? $data['ExternalReference'] 
               ?? $data['response']['ExternalReference'] 
               ?? $data['response']['external_reference'] 
               ?? $data['CheckoutRequestID'] // Last resort
               ?? null;

    /**
     * EXTRACT TRANSACTION REFERENCE (M-Pesa Code)
     */
    $mpesaCode = $data['reference'] 
              ?? $data['mpesa_reference'] 
              ?? $data['response']['MpesaReceiptNumber'] 
              ?? null;

    $logMsg = "[".date('Y-m-d H:i:s')."] PARSED: Status=$status, PurchaseID=$purchaseId, MpesaRef=$mpesaCode" . PHP_EOL;
    @file_put_contents($logFile, $logMsg, FILE_APPEND);

    // SUCCESSFUL PAYMENT CHECK
    $isSuccessful = in_array(strtoupper((string)$status), ['SUCCESSFUL', 'SUCCESS', '0']);

    if ($isSuccessful && $purchaseId) {
        // We found a successful payment and a purchase ID
        // Note: Purchase::complete needs to handle the purchase update.
        // We'll update the purchase with the M-Pesa code if we have it.
        if ($mpesaCode) {
            DB::execute("UPDATE purchases SET transaction_ref = ? WHERE id = ? AND (transaction_ref IS NULL OR transaction_ref = '' OR transaction_ref LIKE '254%')", [$mpesaCode, $purchaseId]);
        }

        $completed = Purchase::complete($purchaseId);
        
        if ($completed) {
            $res = ['status' => 'success', 'message' => 'Units updated'];
            @file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] RESULT: Success - Units added for #$purchaseId" . PHP_EOL, FILE_APPEND);
        } else {
            $res = ['status' => 'error', 'message' => 'Purchase already completed or not found'];
            @file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] RESULT: Ignored - Purchase #$purchaseId already done or missing" . PHP_EOL, FILE_APPEND);
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
