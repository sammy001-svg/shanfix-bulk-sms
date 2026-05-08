<?php
/**
 * Action: Queue File-Based SMS Campaign - Shanfix Technology
 * Saves the uploaded file to disk and creates a queued campaign.
 * The cron job picks it up and sends in fast bulk batches.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

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

// Validate sender ID belongs to this user and is approved
$validSender = DB::queryOne(
    "SELECT sender_id FROM sender_ids WHERE user_id = ? AND BINARY sender_id = ? AND status = 'approved'",
    [$user['id'], $senderId]
);
if (!$validSender) {
    flash_set('danger', "Sender ID '$senderId' is not approved for your account.");
    redirect('/client/send-from-file.php');
}

// Open data source: either posted CSV string (from JS Excel parser) or raw file upload
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

// Validate the header row contains a 'phone' column
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

// Save file to a permanent location on disk (not /tmp which gets wiped)
$uploadDir = dirname(__DIR__, 2) . '/uploads/campaigns/' . $user['id'] . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$safeName  = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
$savePath  = $uploadDir . time() . '_' . $safeName;
$outHandle = fopen($savePath, 'w');

fputcsv($outHandle, $headerRow); // preserve header
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

// Check user has at least some units (rough guard — exact deduction happens in cron)
$unitBalance = (float)DB::queryValue("SELECT sms_units FROM users WHERE id = ?", [$user['id']]);
if ($unitBalance < 1) {
    @unlink($savePath);
    flash_set('danger', 'You have no SMS units. Please top up before sending.');
    redirect('/client/send-from-file.php');
}

// Create campaign as 'queued' — the cron will process it in batches
$campaignName = 'File: ' . sanitize(pathinfo($originalName, PATHINFO_FILENAME)) . ' (' . date('d M Y H:i') . ')';
$campaignId = DB::insert(
    "INSERT INTO campaigns (user_id, name, sender_id, message, file_path, total_count, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, 'queued', NOW())",
    [$user['id'], $campaignName, $senderId, $msgTemplate, $savePath, $totalRows]
);

if (!$campaignId) {
    @unlink($savePath);
    flash_set('danger', 'Failed to create campaign. Please try again.');
    redirect('/client/send-from-file.php');
}

flash_set('success',
    number_format($totalRows) . ' contacts queued successfully. ' .
    'Messages will be sent in the background — you can track progress on the Campaigns page.'
);
redirect('/client/campaigns.php');
