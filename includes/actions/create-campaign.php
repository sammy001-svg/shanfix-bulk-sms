<?php
/**
 * Action: Create & Launch Campaign - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/actions/sms.php';

$user = current_user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(safe_referer('/'));
}

if (!csrf_verify()) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Security token mismatch. Please try again.'];
    redirect(safe_referer('/'));
}

$name        = sanitize($_POST['name'] ?? '');
$senderId    = sanitize($_POST['sender_id'] ?? 'SHANFIX');
$message     = sanitize($_POST['message'] ?? '');
$scheduledAt = $_POST['scheduled_at'] ?? null;
$groupId     = (int)($_POST['group_id'] ?? 0);
$rawNumbers  = sanitize($_POST['numbers'] ?? '');

// --- Basic validation ---
if ($name === '' || $message === '') {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Campaign name and message are required.'];
    redirect(safe_referer('/'));
}

// Validate sender ID belongs to this user and is approved — catch bad IDs before
// the background worker starts (avoids wasting a queued slot on a doomed campaign).
$validSender = DB::queryOne(
    "SELECT sender_id FROM sender_ids WHERE user_id = ? AND BINARY sender_id = ? AND status = 'approved'",
    [$user['id'], $senderId]
);
if (!$validSender) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => "Sender ID \"$senderId\" is not approved for your account."];
    redirect(safe_referer('/'));
}

// --- Resolve recipients ---
// For groups: only fetch the count; processCampaign reads contacts in paginated batches.
// For manual numbers: normalize and deduplicate here.
$groupCount  = 0;
$manualNums  = [];

if ($groupId) {
    $groupCount = (int)DB::queryValue(
        "SELECT COUNT(*) FROM contacts WHERE group_id = ? AND user_id = ?",
        [$groupId, $user['id']]
    );
}

if ($rawNumbers) {
    foreach (preg_split('/[\n,;]+/', $rawNumbers) as $raw) {
        $n = SMS::normalizePhone(trim($raw));
        if ($n) $manualNums[] = $n;
    }
    $manualNums = array_unique(array_filter($manualNums));
}

$totalCount = $groupCount + count($manualNums);
if ($totalCount === 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No valid recipients found.'];
    redirect(safe_referer('/'));
}

$status = $scheduledAt ? 'scheduled' : 'queued';

// Store group_id OR manual numbers — never both, or processCampaign double-sends
$dbGroupId    = $groupId ?: null;
$dbRecipients = (!$groupId && $manualNums) ? implode(',', $manualNums) : null;

$existingId = (int)($_POST['id'] ?? 0);

if ($existingId) {
    DB::execute(
        "UPDATE campaigns SET sender_id=?, name=?, message=?, group_id=?, recipients=?,
         total_count=?, status=?, scheduled_at=? WHERE id=? AND user_id=?",
        [$senderId, $name, $message, $dbGroupId, $dbRecipients,
         $totalCount, $status, $scheduledAt ?: null, $existingId, $user['id']]
    );
    $campaignId = $existingId;
} else {
    $campaignId = DB::insert(
        "INSERT INTO campaigns
         (user_id, sender_id, name, message, group_id, recipients, total_count, status, scheduled_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        [$user['id'], $senderId, $name, $message, $dbGroupId, $dbRecipients,
         $totalCount, $status, $scheduledAt ?: null]
    );
}

if (!$campaignId) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to save campaign. Please try again.'];
    redirect($_SESSION['user']['role'] === 'reseller' ? '/reseller/campaigns.php' : '/client/campaigns.php');
}

$redirectTo = $_SESSION['user']['role'] === 'reseller' ? '/reseller/campaigns.php' : '/client/campaigns.php';

if ($scheduledAt) {
    $_SESSION['flash'] = [
        'type'    => 'success',
        'message' => 'Campaign scheduled for ' . date('d M Y H:i', strtotime($scheduledAt)) . '.',
    ];
    redirect($redirectTo);
}

// Immediate send: redirect browser now, trigger detached background process.
$_SESSION['flash'] = [
    'type'    => 'success',
    'message' => 'Sending ' . number_format($totalCount) . ' messages now. Live progress on this page.',
];

session_write_close();
while (ob_get_level() > 0) ob_end_clean();
header('Location: ' . $redirectTo, true, 302);
header('Connection: close');
header('Content-Encoding: none');
header('Content-Length: 0');
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); else flush();

$spawned = SMS::spawnBackground();

if (!$spawned) {
    ignore_user_abort(true);
    set_time_limit(0);
    ini_set('memory_limit', '512M');
    SMS::processCampaign($campaignId);
}
