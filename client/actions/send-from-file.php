<?php
/**
 * Action: Send Personalized SMS from CSV File - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/actions/sms.php';

$user = auth_user();
if (!$user || !in_array($user['role'], ['reseller', 'client'])) {
    json_response(['success' => false, 'error' => 'Unauthorized'], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'CSRF Token mismatch.');
        redirect('/client/send-from-file.php');
    }

    $senderId   = sanitize($_POST['sender_id'] ?? '');
    $msgTemplate = $_POST['message'] ?? '';
    $file       = $_FILES['csv_file'] ?? null;
    $csvData = $_POST['csv_data'] ?? '';
    $handle = null;

    if ($csvData) {
        // Use the data sent from the client (Excel converted to CSV)
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csvData);
        rewind($handle);
    } elseif ($file && ($file['error'] ?? null) === UPLOAD_ERR_OK) {
        // Use the uploaded CSV file
        $handle = fopen($file['tmp_name'], "r");
    }

    if (!$handle) {
        flash_set('danger', 'Please provide a valid CSV or Excel file.');
        redirect('/client/send-from-file.php');
    }

    $header = fgetcsv($handle);
    if (!$header) {
        flash_set('danger', 'File data is empty or invalid.');
        redirect('/client/send-from-file.php');
    }

    // Standardize headers
    $cleanHeaders = array_map(function($h) { return strtolower(trim($h)); }, $header);
    $phoneIdx = array_search('phone', $cleanHeaders);

    if ($phoneIdx === false) {
        flash_set('danger', "CSV must contain a 'phone' column header.");
        redirect('/client/send-from-file.php');
    }

    $sent = 0; $failed = 0; $errors = [];
    $totalRows = 0;

    // We'll create a campaign record to group these messages
    $fileName = $file['name'] ?? 'Data Paste';
    $campaignName = "File Upload: " . sanitize($fileName) . " (" . date('Y-m-d H:i') . ")";
    $campaignId = DB::insert(
        "INSERT INTO campaigns (user_id, name, sender_id, message, status, created_at) VALUES (?, ?, ?, ?, 'sending', NOW())",
        [$user['id'], $campaignName, $senderId, $msgTemplate]
    );

    while (($row = fgetcsv($handle)) !== false) {
        $totalRows++;
        $phone = trim($row[$phoneIdx] ?? '');
        if (!$phone) continue;

        // Personalized message replacement
        $personalizedMsg = $msgTemplate;
        foreach ($cleanHeaders as $idx => $headerName) {
            $value = trim($row[$idx] ?? '');
            // Support both ##TitleCase## and {lowercase} for compatibility
            $label = ucfirst($headerName);
            $personalizedMsg = str_replace('##' . $label . '##', $value, $personalizedMsg);
            $personalizedMsg = str_replace('{' . $headerName . '}', $value, $personalizedMsg);
        }

        // Send SMS
        $res = SMS::send($user['id'], $phone, $personalizedMsg, $senderId, $campaignId);
        
        if ($res['success']) {
            $sent++;
        } else {
            $failed++;
            $errors[] = "Line $totalRows ($phone): " . ($res['error'] ?? 'Unknown error');
        }
    }
    fclose($handle);

    // Update campaign status
    DB::execute(
        "UPDATE campaigns SET status = 'completed', total_count = ?, sent_count = ?, failed_count = ?, sent_at = NOW() WHERE id = ?",
        [$totalRows, $sent, $failed, $campaignId]
    );

    $msg = "Campaign complete: $sent sent successfully, $failed failed.";
    if ($failed > 0) {
        $msg .= " Errors: " . implode(', ', array_slice($errors, 0, 3)) . (count($errors) > 3 ? '...' : '');
    }
    
    flash_set($failed == 0 ? 'success' : 'warning', $msg);
    redirect('/client/campaigns.php');
}
