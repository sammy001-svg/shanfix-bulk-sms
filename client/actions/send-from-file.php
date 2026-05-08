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

// Validate header row has a phone column (accept 'phone', 'mobile', 'number', or any partial match)
$headerRow = fgetcsv($sourceHandle);
if (!$headerRow) {
    fclose($sourceHandle);
    flash_set('danger', 'File is empty or could not be read.');
    redirect('/client/send-from-file.php');
}
$cleanHeaders = array_map(fn($h) => strtolower(trim($h)), $headerRow);
$phoneColIdx  = -1;
foreach ($cleanHeaders as $i => $h) {
    if ($h === 'phone' || $h === 'mobile' || $h === 'number' || $h === 'contact' ||
        strpos($h, 'phone') !== false || strpos($h, 'mobile') !== false) {
        $phoneColIdx = $i;
        // Normalise header to 'phone' so processCampaign always finds it
        $headerRow[$i] = 'phone';
        break;
    }
}
if ($phoneColIdx === -1) {
    fclose($sourceHandle);
    flash_set('danger', "Your file must have a column named 'phone' or 'mobile'.");
    redirect('/client/send-from-file.php');
}

// Save file to a permanent server path (tmp_name gets deleted after the request)
$uploadDir = dirname(__DIR__, 2) . '/uploads/campaigns/' . $user['id'] . '/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    fclose($sourceHandle);
    flash_set('danger', 'Server storage error: could not create upload directory. Contact support.');
    redirect('/client/send-from-file.php');
}
$safeName  = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
$savePath  = $uploadDir . time() . '_' . $safeName;
$outHandle = fopen($savePath, 'w');
if ($outHandle === false) {
    fclose($sourceHandle);
    flash_set('danger', 'Server storage error: could not write upload file. Contact support.');
    redirect('/client/send-from-file.php');
}
fputcsv($outHandle, $headerRow);
$totalRows = 0;
while (($row = fgetcsv($sourceHandle)) !== false) {
    // Skip rows where the phone cell is completely empty
    if (trim($row[$phoneColIdx] ?? '') === '') continue;
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
// Redirect the browser immediately, then spawn a detached PHP process
// to handle all sending. The spawned process is outside PHP-FPM's
// request pool so it will NOT be killed by request_terminate_timeout,
// making it safe for files with millions of contacts.
// ------------------------------------------------------------------
flash_set('success',
    'Campaign queued: sending ' . number_format($totalRows) . ' messages. ' .
    'Live progress is visible on this page — refresh to update counts.'
);

session_write_close();
while (ob_get_level() > 0) ob_end_clean();
header('Location: /client/campaigns.php', true, 302);
header('Connection: close');
header('Content-Encoding: none');
header('Content-Length: 0');
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); else flush();

$spawned = SMS::spawnBackground();

// Fallback: inline processing if exec() is unavailable.
// The cron safety-net will rescue the campaign if FPM kills this process.
if (!$spawned) {
    ignore_user_abort(true);
    set_time_limit(0);
    ini_set('memory_limit', '512M');
    SMS::processCampaign($campaignId);
}
