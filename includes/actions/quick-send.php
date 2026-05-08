<?php
/**
 * Action: Quick Send SMS - Shanfix Technology
 *
 * Single number  → sent immediately in this request (fast).
 * Scheduled      → stored as 'scheduled', cron fires it at the right time.
 * Multi-recipient / group → browser is redirected instantly, then PHP
 *                           keeps running to send every message in the background.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/actions/sms.php';

$user = current_user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($_SERVER['HTTP_REFERER'] ?? '/');
}

$senderId    = sanitize($_POST['sender_id'] ?? 'SHANFIX');
$message     = sanitize($_POST['message'] ?? '');
$scheduledAt = $_POST['scheduled_at'] ?? null;
$groupId     = (int)($_POST['group_id'] ?? 0);
$manualInput = trim($_POST['numbers'] ?? '') ?: trim($_POST['recipient'] ?? '');

if (!$message) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Message content is required.'];
    redirect($_SERVER['HTTP_REFERER'] ?? '/');
}

// --- Resolve recipients without loading them all into memory ---
$recipients = [];
$groupCount = 0;

if ($groupId) {
    // Only fetch the count — processCampaign reads contacts in paginated batches
    $groupCount = (int)DB::queryValue(
        "SELECT COUNT(*) FROM contacts WHERE group_id = ? AND user_id = ?",
        [$groupId, $user['id']]
    );
}

if ($manualInput) {
    foreach (preg_split('/[\n,;]+/', $manualInput) as $raw) {
        $n = SMS::normalizePhone(trim($raw));
        if ($n) $recipients[] = $n;
    }
    $recipients = array_unique(array_filter($recipients));
}

$totalCount = $groupCount + count($recipients);

if ($totalCount === 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No valid recipients found.'];
    redirect($_SERVER['HTTP_REFERER'] ?? '/');
}

// --- Single number, no schedule: send inline and return immediately ---
if (!$groupId && count($recipients) === 1 && !$scheduledAt) {
    $result = SMS::send($user['id'], $recipients[0], $message, $senderId);
    if ($result['success']) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Message sent successfully!'];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to send: ' . $result['error']];
    }
    redirect($_SERVER['HTTP_REFERER'] ?? '/');
}

// --- Scheduled send: store and let cron fire it at the right time ---
if ($scheduledAt) {
    $campaignId = DB::insert(
        "INSERT INTO campaigns
         (user_id, sender_id, name, message, group_id, recipients, total_count, status, scheduled_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW())",
        [
            $user['id'], $senderId,
            'Scheduled Broadcast ' . date('Y-m-d H:i'),
            $message,
            $groupId ?: null,
            (!$groupId && $recipients) ? implode(',', $recipients) : null,
            $totalCount,
            $scheduledAt,
        ]
    );
    $_SESSION['flash'] = $campaignId
        ? ['type' => 'success', 'message' => 'Broadcast scheduled for ' . date('d M Y H:i', strtotime($scheduledAt)) . '.']
        : ['type' => 'danger',  'message' => 'Failed to schedule broadcast. Please try again.'];
    redirect($_SERVER['HTTP_REFERER'] ?? '/');
}

// --- Multi-recipient immediate send ---
// Create the campaign record, redirect the browser, then send everything in the background.
$campaignId = DB::insert(
    "INSERT INTO campaigns
     (user_id, sender_id, name, message, group_id, recipients, total_count, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'queued', NOW())",
    [
        $user['id'], $senderId,
        'Quick Broadcast ' . date('Y-m-d H:i'),
        $message,
        $groupId ?: null,
        (!$groupId && $recipients) ? implode(',', $recipients) : null,
        $totalCount,
    ]
);

if (!$campaignId) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to initiate broadcast. Please try again.'];
    redirect($_SERVER['HTTP_REFERER'] ?? '/');
}

$_SESSION['flash'] = [
    'type'    => 'success',
    'message' => 'Sending ' . number_format($totalCount) . ' messages now. Track live progress in Campaigns.',
];

// Redirect the browser immediately, then trigger background processing.
// SMS::spawnBackground() launches a detached PHP process (not part of PHP-FPM),
// so it won't be killed by request_terminate_timeout — safe for any campaign size.
session_write_close();
while (ob_get_level() > 0) ob_end_clean();
$returnTo = $_SERVER['HTTP_REFERER'] ?? '/';
header('Location: ' . $returnTo, true, 302);
header('Connection: close');
header('Content-Encoding: none');
header('Content-Length: 0');
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); else flush();

$spawned = SMS::spawnBackground();

// Fallback: process inline if exec() is disabled (cron will rescue if FPM kills us)
if (!$spawned) {
    ignore_user_abort(true);
    set_time_limit(0);
    ini_set('memory_limit', '512M');
    SMS::processCampaign($campaignId);
}
