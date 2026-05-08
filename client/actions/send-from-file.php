<?php
/**
 * Action: Send File-Based SMS Campaign - Shanfix Technology
 *
 * Flow:
 *  1. Validate input and save file to disk.
 *  2. Create campaign record.
 *  3. Close the browser connection (user gets redirected instantly).
 *  4. PHP keeps running in the background and sends every message.
 *
 * No cron job required — messages are sent immediately regardless of file size.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/actions/sms.php';

$user = auth_user();
if (!$user || !in_array($user['role'], ['reseller', 'client'])) {
    json_response(['success' => false, 'error' => 'Unauthorized'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/client/send-from-file.php');
}

if (!csrf_verify()) {
    flash_set('danger', 'Security token mismatch. Please try again.');
    redirect('/client/send-from-file.php');
}

$senderId    = sanitize($_POST['sender_id'] ?? '');
$msgTemplate = $_POST['message'] ?? '';
$file        = $_FILES['csv_file'] ?? null;
$csvData     = $_POST['csv_data'] ?? '';

if (!$senderId || !$msgTemplate) {
    flash_set('danger', 'Sender ID and message are required.');
    redirect('/client/send-from-file.php');
}

// Validate sender ID is approved for this user
$validSender = DB::queryOne(
    "SELECT sender_id FROM sender_ids WHERE user_id = ? AND BINARY sender_id = ? AND status = 'approved'",
    [$user['id'], $senderId]
);
if (!$validSender) {
    flash_set('danger', "Sender ID '$senderId' is not approved for your account.");
    redirect('/client/send-from-file.php');
}

// Open data source: raw CSV file upload OR Excel-converted CSV string from JS
$sourceHandle = null;
$originalName = 'upload.csv';

if ($csvData) {
    $sourceHandle = fopen('php://temp', 'r+');
    fwrite($sourceHandle, $csvData);
    rewind($sourceHandle);
    $originalName = 'data-paste.csv';
} elseif ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $sourceHandle = fopen($file['tmp_name'], 'r');
    $originalName = basename($file['name'] ?? 'upload.csv');
}

if (!$sourceHandle) {
    flash_set('danger', 'Please upload a valid CSV or Excel file.');
    redirect('/client/send-from-file.php');
}

// Validate header row has a 'phone' column
$headerRow = fgetcsv($sourceHandle);
if (!$headerRow) {
    fclose($sourceHandle);
    flash_set('danger', 'File is empty or could not be read.');
    redirect('/client/send-from-file.php');
}
$cleanHeaders = array_map(fn($h) => strtolower(trim($h)), $headerRow);
if (!in_array('phone', $cleanHeaders)) {
    fclose($sourceHandle);
    flash_set('danger', "Your file must have a column header named 'phone'.");
    redirect('/client/send-from-file.php');
}

// Save file to a permanent server path (tmp_name gets deleted after the request)
$uploadDir = dirname(__DIR__, 2) . '/uploads/campaigns/' . $user['id'] . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$safeName  = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
$savePath  = $uploadDir . time() . '_' . $safeName;
$outHandle = fopen($savePath, 'w');
fputcsv($outHandle, $headerRow);
$totalRows = 0;
while (($row = fgetcsv($sourceHandle)) !== false) {
    fputcsv($outHandle, $row);
    $totalRows++;
}
fclose($sourceHandle);
fclose($outHandle);

if ($totalRows === 0) {
    @unlink($savePath);
    flash_set('danger', 'The file has no data rows. Please check and try again.');
    redirect('/client/send-from-file.php');
}

// Quick balance guard (exact deduction happens per-batch inside processCampaign)
$unitBalance = (float)DB::queryValue("SELECT sms_units FROM users WHERE id = ?", [$user['id']]);
if ($unitBalance < 1) {
    @unlink($savePath);
    flash_set('danger', 'You have no SMS units. Please top up before sending.');
    redirect('/client/send-from-file.php');
}

// Create the campaign record
$campaignName = 'File: ' . sanitize(pathinfo($originalName, PATHINFO_FILENAME)) . ' (' . date('d M Y H:i') . ')';
$campaignId   = DB::insert(
    "INSERT INTO campaigns (user_id, name, sender_id, message, file_path, total_count, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, 'queued', NOW())",
    [$user['id'], $campaignName, $senderId, $msgTemplate, $savePath, $totalRows]
);

if (!$campaignId) {
    @unlink($savePath);
    flash_set('danger', 'Failed to create campaign. Please try again.');
    redirect('/client/send-from-file.php');
}

// ------------------------------------------------------------------
// Send the HTTP redirect to the browser NOW so the user sees the
// campaigns page immediately, then continue processing in the background.
// ------------------------------------------------------------------
flash_set('success',
    'Sending ' . number_format($totalRows) . ' messages now. ' .
    'Live progress is visible on this page — refresh to update counts.'
);

session_write_close();                    // release session lock so other pages load
while (ob_get_level() > 0) ob_end_clean(); // discard any buffered output
header('Location: /client/campaigns.php', true, 302);
header('Connection: close');
header('Content-Encoding: none');
header('Content-Length: 0');

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();             // PHP-FPM: closes connection immediately
} else {
    flush();                              // mod_php / CGI fallback
}

// Browser is gone. Process every contact now.
ignore_user_abort(true);
set_time_limit(0);
ini_set('memory_limit', '512M');

SMS::processCampaign($campaignId);
