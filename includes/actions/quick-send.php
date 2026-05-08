<?php
/**
 * Action: Quick Send SMS - Shanfix Technology
 * Single-number sends go immediately; multi-recipient campaigns are queued
 * so the cron processes them in fast bulk batches without HTTP timeouts.
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
$manualInput = $_POST['numbers'] ?? ($_POST['recipient'] ?? '');

if (!$message) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Message content is required.'];
    redirect($_SERVER['HTTP_REFERER'] ?? '/');
}

// --- Resolve recipients without loading them all into memory ---

// For a single manual number (most common case) send immediately
$recipients  = [];
$groupCount  = 0;

if ($groupId) {
    // Just get the count — processCampaign reads contacts itself via paginated queries
    $groupCount = (int)DB::queryValue(
        "SELECT COUNT(*) FROM contacts WHERE group_id = ? AND user_id = ?",
        [$groupId, $user['id']]
    );
}

if ($manualInput) {
    $nums = preg_split('/[\n,;]+/', $manualInput);
    foreach ($nums as $raw) {
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

// Single manual number with no group and no schedule: send immediately
if (!$groupId && count($recipients) === 1 && !$scheduledAt) {
    $result = SMS::send($user['id'], $recipients[0], $message, $senderId);
    if ($result['success']) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Message sent successfully!'];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to send: ' . $result['error']];
    }
    redirect($_SERVER['HTTP_REFERER'] ?? '/');
}

// Everything else: queue a campaign and let the cron dispatch it in bulk
$status     = $scheduledAt ? 'scheduled' : 'queued';
$campaignId = DB::insert(
    "INSERT INTO campaigns
     (user_id, sender_id, name, message, group_id, recipients, total_count, status, scheduled_at, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
    [
        $user['id'],
        $senderId,
        'Quick Broadcast ' . date('Y-m-d H:i'),
        $message,
        $groupId ?: null,
        // Store manual numbers only when there's no group
        (!$groupId && $recipients) ? implode(',', $recipients) : null,
        $totalCount,
        $status,
        $scheduledAt ?: null,
    ]
);

if ($campaignId) {
    if ($scheduledAt) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Broadcast scheduled for " . date('d M Y H:i', strtotime($scheduledAt)) . '.'];
    } else {
        $_SESSION['flash'] = ['type' => 'success', 'message' => number_format($count) . " recipients queued. Messages will be sent shortly — track progress in Campaigns."];
    }
} else {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to initiate broadcast. Please try again.'];
}

redirect($_SERVER['HTTP_REFERER'] ?? '/');
