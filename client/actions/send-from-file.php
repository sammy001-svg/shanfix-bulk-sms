<?php
/**
 * Action: Send File-Based SMS Campaign — Shanfix Technology
 *
 * Deliberately minimal — the only work done here is:
 *   1. Validate inputs (sender ID, message, file type).
 *   2. move_uploaded_file() — stores the raw file in < 1 second.
 *   3. Create the campaign record (status = 'queued').
 *   4. Redirect the browser to /client/campaigns.php immediately.
 *
 * ALL heavy work (XLSX→CSV conversion, row counting, phone-column
 * validation, actual SMS sending) happens inside the background worker.
 * This avoids HTTP/2 ERR_HTTP2_PROTOCOL_ERROR caused by long PHP
 * execution on slow shared-hosting servers.
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

// ── Validate sender ID ────────────────────────────────────────────────────────
$validSender = DB::queryOne(
    "SELECT sender_id FROM sender_ids WHERE user_id = ? AND BINARY sender_id = ? AND status = 'approved'",
    [$user['id'], $senderId]
);
if (!$validSender) {
    flash_set('danger', "Sender ID '$senderId' is not approved for your account.");
    redirect('/client/send-from-file.php');
}

// ── File validation (type/size only — no content parsing) ─────────────────────
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $errMsg = match ($file['error'] ?? UPLOAD_ERR_NO_FILE) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
            'File is too large. Max allowed: ' . ini_get('upload_max_filesize') . '. Use CSV or split the Excel file.',
        UPLOAD_ERR_NO_FILE => 'No file uploaded. Please select a CSV or Excel (.xlsx) file.',
        default            => 'Upload failed (error ' . ($file['error'] ?? 0) . '). Please try again.',
    };
    flash_set('danger', $errMsg);
    redirect('/client/send-from-file.php');
}

$originalName = basename($file['name'] ?? 'upload.csv');
$ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if ($ext === 'xls') {
    flash_set('danger', 'Old Excel format (.xls) is not supported. Save as .xlsx or .csv and re-upload.');
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

// ── Save raw file (FAST — just a file move, no parsing) ───────────────────────
$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
$rawPath  = $uploadDir . time() . '_' . $safeName;

if (!move_uploaded_file($file['tmp_name'], $rawPath)) {
    flash_set('danger', 'Failed to save uploaded file. Check server permissions on /uploads/.');
    redirect('/client/send-from-file.php');
}

// ── Balance guard ──────────────────────────────────────────────────────────────
$unitBalance = (float)DB::queryValue("SELECT sms_units FROM users WHERE id = ?", [$user['id']]);
if ($unitBalance < 1) {
    @unlink($rawPath);
    flash_set('danger', 'You have no SMS units. Please top up before sending.');
    redirect('/client/send-from-file.php');
}

// ── Create campaign record ─────────────────────────────────────────────────────
// total_count is 0 now; the background worker updates it after counting rows.
$campaignName = 'File: ' . sanitize(pathinfo($originalName, PATHINFO_FILENAME)) . ' (' . date('d M Y H:i') . ')';
$campaignId   = DB::insert(
    "INSERT INTO campaigns (user_id, name, sender_id, message, file_path, total_count, status, created_at)
     VALUES (?, ?, ?, ?, ?, 0, 'queued', NOW())",
    [$user['id'], $campaignName, $senderId, $msgTemplate, $rawPath]
);

if (!$campaignId) {
    @unlink($rawPath);
    flash_set('danger', 'Failed to create campaign. Please try again.');
    redirect('/client/send-from-file.php');
}

// ── Respond to browser IMMEDIATELY ────────────────────────────────────────────
flash_set('success', 'Campaign queued — file is being processed in the background. Track live progress on the Campaigns page.');

session_write_close();
while (ob_get_level() > 0) ob_end_clean();
header('Location: /client/campaigns.php', true, 302);
header('Connection: close');
header('Content-Encoding: none');
header('Content-Length: 0');
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); else flush();

// ── Spawn background worker ───────────────────────────────────────────────────
// The worker handles: XLSX→CSV conversion, row counting, phone-column
// validation, and batch SMS sending — none of which block the browser.
$spawned = SMS::spawnBackground();

if (!$spawned) {
    // Fallback: run inline if exec/popen are all disabled.
    // PHP-FPM may kill this after request_terminate_timeout, but the
    // cron rescues stuck campaigns automatically every minute.
    ignore_user_abort(true);
    set_time_limit(0);
    ini_set('memory_limit', '512M');
    SMS::processCampaign($campaignId);
}
