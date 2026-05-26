<?php
/**
 * Action: Cancel Scheduled Campaign - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(safe_referer('/client/campaigns.php'));
}

if (!csrf_verify()) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Security token mismatch. Please try again.'];
    redirect(safe_referer('/client/campaigns.php'));
}

$user = current_user();
if (!$user) {
    redirect('/login.php');
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    redirect(safe_referer('/client/campaigns.php'));
}

// Only campaigns that have not started sending can be cancelled.
// 'sending' / 'completed' / 'failed' campaigns are immutable.
$updated = DB::execute(
    "UPDATE campaigns SET status = 'cancelled'
     WHERE id = ? AND user_id = ? AND status IN ('queued', 'scheduled')",
    [$id, $user['id']]
);

if ($updated) {
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Campaign cancelled successfully.'];
} else {
    // Could be: not owned by user, wrong status, or ID doesn't exist
    $campaign = DB::queryOne("SELECT status FROM campaigns WHERE id = ? AND user_id = ?", [$id, $user['id']]);
    if (!$campaign) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Campaign not found.'];
    } else {
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Campaign cannot be cancelled — it is already ' . $campaign['status'] . '.'];
    }
}

redirect(safe_referer('/client/campaigns.php'));
