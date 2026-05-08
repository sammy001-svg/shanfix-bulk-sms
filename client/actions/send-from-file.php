<?php
/**
 * Action: Send File-Based SMS Campaign — Shanfix Technology
 *
 * Accepts CSV or Excel (.xlsx) uploads and converts them to a permanent
 * server-side CSV for streaming processing. No browser-side conversion
 * needed — handles files of any size (1 M+ rows).
 *
 * Flow:
 *  1. Validate sender ID and message.
 *  2. Detect file type → save/convert to permanent CSV on server.
 *  3. Validate the CSV has a recognisable phone column.
 *  4. Create campaign record.
 *  5. Redirect browser to campaigns page instantly.
 *  6. Spawn detached background process (not subject to PHP-FPM timeout).
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

// ── File validation ────────────────────────────────────────────────────────────
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $errMsg = match ($file['error'] ?? UPLOAD_ERR_NO_FILE) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
            'File is too large. Max upload size is ' . ini_get('upload_max_filesize') . '. Use a CSV file or split your Excel into smaller parts.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded. Please select a CSV or Excel file.',
        default            => 'File upload failed (error ' . ($file['error'] ?? 0) . '). Please try again.',
    };
    flash_set('danger', $errMsg);
    redirect('/client/send-from-file.php');
}

$originalName = basename($file['name'] ?? 'upload.csv');
$ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if ($ext === 'xls') {
    flash_set('danger', 'Old Excel format (.xls) is not supported. Please save the file as .xlsx or .csv and re-upload.');
    redirect('/client/send-from-file.php');
}
if (!in_array($ext, ['csv', 'xlsx'])) {
    flash_set('danger', 'Unsupported file type. Please upload a .csv or .xlsx file.');
    redirect('/client/send-from-file.php');
}

// ── Prepare upload directory ───────────────────────────────────────────────────
$uploadDir = dirname(__DIR__, 2) . '/uploads/campaigns/' . $user['id'] . '/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    flash_set('danger', 'Server storage error: cannot create upload directory. Contact support.');
    redirect('/client/send-from-file.php');
}

$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
$csvName  = time() . '_' . preg_replace('/\.(xlsx)$/i', '.csv', $safeName);
$savePath = $uploadDir . $csvName;

// ── Convert or copy to permanent CSV ──────────────────────────────────────────
$totalRows = 0;

if ($ext === 'xlsx') {
    // ── Excel path: server-side streaming conversion (no browser XLSX.js) ──
    require_once dirname(__DIR__, 2) . '/includes/helpers/XlsxReader.php';

    // Move the raw XLSX to a temp location so we can parse it
    $tmpXlsx = $uploadDir . time() . '_raw_' . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $tmpXlsx)) {
        flash_set('danger', 'Failed to save uploaded file. Check server permissions on /uploads/.');
        redirect('/client/send-from-file.php');
    }

    try {
        $totalRows = XlsxReader::toCsv($tmpXlsx, $savePath);
    } catch (\RuntimeException $e) {
        @unlink($tmpXlsx);
        flash_set('danger', 'Cannot read Excel file: ' . $e->getMessage());
        redirect('/client/send-from-file.php');
    } finally {
        @unlink($tmpXlsx); // Always remove the raw XLSX; we keep only the CSV
    }

} else {
    // ── CSV path: stream row-by-row, no memory spike ──
    $srcHandle = fopen($file['tmp_name'], 'r');
    if (!$srcHandle) {
        flash_set('danger', 'Cannot read uploaded CSV file.');
        redirect('/client/send-from-file.php');
    }

    $outHandle = fopen($savePath, 'w');
    if (!$outHandle) {
        fclose($srcHandle);
        flash_set('danger', 'Server storage error: cannot write to upload directory.');
        redirect('/client/send-from-file.php');
    }

    // Copy header row first
    $headerRow = fgetcsv($srcHandle);
    if (!$headerRow) {
        fclose($srcHandle); fclose($outHandle); @unlink($savePath);
        flash_set('danger', 'CSV file is empty or could not be read.');
        redirect('/client/send-from-file.php');
    }
    fputcsv($outHandle, $headerRow);

    while (($row = fgetcsv($srcHandle)) !== false) {
        fputcsv($outHandle, $row);
        $totalRows++;
    }
    fclose($srcHandle);
    fclose($outHandle);
}

if ($totalRows === 0) {
    @unlink($savePath);
    flash_set('danger', 'The file has no data rows. Please check your file and try again.');
    redirect('/client/send-from-file.php');
}

// ── Validate that the saved CSV has a recognised phone column ──────────────────
$checkHandle = fopen($savePath, 'r');
$headerRow   = $checkHandle ? fgetcsv($checkHandle) : false;
if ($checkHandle) fclose($checkHandle);

if (!$headerRow) {
    @unlink($savePath);
    flash_set('danger', 'Could not read the file header row. Please re-upload.');
    redirect('/client/send-from-file.php');
}

$cleanHeaders = array_map(fn($h) => strtolower(trim($h)), $headerRow);
$phoneColIdx  = -1;
foreach ($cleanHeaders as $i => $h) {
    if ($h === 'phone' || $h === 'mobile' || $h === 'number' || $h === 'contact' ||
        strpos($h, 'phone') !== false || strpos($h, 'mobile') !== false) {
        $phoneColIdx = $i;
        break;
    }
}

if ($phoneColIdx === -1) {
    @unlink($savePath);
    flash_set('danger', "Your file must have a column named 'phone' or 'mobile'. Columns found: " . implode(', ', $cleanHeaders));
    redirect('/client/send-from-file.php');
}

// If the detected phone header isn't exactly 'phone', rewrite the header row in the CSV
// so processCampaign always finds it by the canonical name.
if ($cleanHeaders[$phoneColIdx] !== 'phone') {
    self_rewrite_phone_header($savePath, $phoneColIdx);
}

// ── Balance guard ──────────────────────────────────────────────────────────────
$unitBalance = (float)DB::queryValue("SELECT sms_units FROM users WHERE id = ?", [$user['id']]);
if ($unitBalance < 1) {
    @unlink($savePath);
    flash_set('danger', 'You have no SMS units. Please top up before sending.');
    redirect('/client/send-from-file.php');
}

// ── Create campaign record ─────────────────────────────────────────────────────
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

// ── Respond to browser and trigger background processing ──────────────────────
flash_set('success',
    'Campaign queued: ' . number_format($totalRows) . ' contacts. ' .
    'Sending starts immediately — track live progress on the Campaigns page.'
);

session_write_close();
while (ob_get_level() > 0) ob_end_clean();
header('Location: /client/campaigns.php', true, 302);
header('Connection: close');
header('Content-Encoding: none');
header('Content-Length: 0');
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); else flush();

// Spawn a detached process that is NOT part of PHP-FPM's request pool.
// It can run for hours without being killed by request_terminate_timeout.
$spawned = SMS::spawnBackground();

// Fallback: inline processing (cron rescues if FPM kills this process)
if (!$spawned) {
    ignore_user_abort(true);
    set_time_limit(0);
    ini_set('memory_limit', '512M');
    SMS::processCampaign($campaignId);
}

// ── Helper ────────────────────────────────────────────────────────────────────

/**
 * Rewrite the first row of a CSV to set column $idx header to 'phone'.
 * Used when the detected phone column has a different name (e.g. 'mobile', 'Phone No').
 */
function self_rewrite_phone_header(string $csvPath, int $phoneIdx): void {
    $tmp = $csvPath . '.tmp';
    $in  = fopen($csvPath, 'r');
    $out = fopen($tmp,     'w');
    if (!$in || !$out) return;

    $header = fgetcsv($in);
    if ($header) {
        $header[$phoneIdx] = 'phone';
        fputcsv($out, $header);
        while (($row = fgetcsv($in)) !== false) {
            fputcsv($out, $row);
        }
    }
    fclose($in);
    fclose($out);
    rename($tmp, $csvPath);
}
