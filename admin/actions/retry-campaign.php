<?php
/**
 * Retry the failed recipients of a campaign.
 *
 * Re-running the original campaign is not an option: processCampaign() skips
 * the first (sent_count + failed_count) recipients on resume, and for group /
 * file sources it would re-send to people who already received the message.
 * Instead we clone the campaign into a new one whose recipient list is exactly
 * the numbers that failed, and let the normal cron worker pick it up.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/actions/sms.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    flash_set('danger', 'Invalid request.');
    redirect('/admin/campaigns.php');
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    flash_set('danger', 'Campaign ID missing.');
    redirect('/admin/campaigns.php');
}

$back = '/admin/campaign-detail.php?id=' . $id;

$campaign = DB::queryOne(
    "SELECT c.*, u.name AS user_name, u.status AS user_status, u.sms_units
     FROM campaigns c JOIN users u ON c.user_id = u.id WHERE c.id = ?",
    [$id]
);
if (!$campaign) {
    flash_set('danger', 'Campaign not found.');
    redirect('/admin/campaigns.php');
}

// A live worker is still writing to this campaign — retrying now would double-send.
if (in_array($campaign['status'], ['queued','scheduled','running','sending'], true)) {
    flash_set('warning', 'Campaign is still ' . $campaign['status'] . '. Wait for it to finish before retrying.');
    redirect($back);
}

if ($campaign['user_status'] !== 'active') {
    flash_set('danger', 'Owner account is ' . $campaign['user_status'] . ' — retry blocked.');
    redirect($back);
}

// The worker fails the whole campaign if the sender ID is no longer approved,
// so catch it here where the admin can actually see why.
$senderOk = DB::queryOne(
    "SELECT id FROM sender_ids WHERE user_id = ? AND BINARY sender_id = ? AND status = 'approved'",
    [$campaign['user_id'], $campaign['sender_id']]
);
if (!$senderOk) {
    flash_set('danger', 'Sender ID "' . htmlspecialchars($campaign['sender_id']) . '" is no longer approved for this user — retry blocked.');
    redirect($back);
}

$recipients = array_column(DB::query(
    "SELECT DISTINCT recipient FROM messages
     WHERE campaign_id = ? AND status IN ('failed','undelivered') AND recipient <> ''",
    [$id]
), 'recipient');

if (empty($recipients)) {
    flash_set('warning', 'No failed recipients to retry on this campaign.');
    redirect($back);
}

// Warn rather than block — dispatchBatch marks the batch failed if the balance
// runs out mid-run, so the admin should top the account up first.
$segment  = SMS::isUnicode($campaign['message']) ? 70 : 160;
$parts    = max(1, (int)ceil(mb_strlen($campaign['message']) / $segment));
$required = count($recipients) * $parts;
if ((float)$campaign['sms_units'] < $required) {
    flash_set('danger', sprintf(
        'Retry blocked: %s has %s units but %s are needed for %d recipient(s).',
        htmlspecialchars($campaign['user_name']),
        number_format((float)$campaign['sms_units'], 2),
        number_format($required),
        count($recipients)
    ));
    redirect($back);
}

$name = mb_substr(preg_replace('/ \(Retry(?: \d+)?\)$/', '', $campaign['name']), 0, 165) . ' (Retry)';

$newId = (int)DB::insert(
    "INSERT INTO campaigns (user_id, name, sender_id, message, recipients, total_count, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, 'queued', NOW())",
    [
        $campaign['user_id'], $name, $campaign['sender_id'], $campaign['message'],
        implode(',', $recipients), count($recipients),
    ]
);

notify(
    (int)$campaign['user_id'],
    'Campaign retry queued',
    sprintf('An administrator queued a retry of "%s" for %d recipient(s) that failed.', $campaign['name'], count($recipients)),
    'info'
);

flash_set('success', sprintf(
    'Queued retry campaign #%d for %d failed recipient(s). It will start on the next worker tick.',
    $newId, count($recipients)
));
redirect('/admin/campaign-detail.php?id=' . $newId);
