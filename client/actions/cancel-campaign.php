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
    flash_set('danger', 'Security token mismatch. Please try again.');
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

// Allow cancellation of any campaign that hasn't finished.
// completed/failed/cancelled are immutable.
$updated = DB::execute(
    "UPDATE campaigns SET status = 'cancelled'
     WHERE id = ? AND user_id = ? AND status IN ('queued', 'scheduled', 'sending', 'running')",
    [$id, $user['id']]
);

if ($updated) {
    flash_set('success', 'Campaign cancelled successfully.');
} else {
    // Could be: not owned by user, wrong status, or ID doesn't exist
    $campaign = DB::queryOne("SELECT status FROM campaigns WHERE id = ? AND user_id = ?", [$id, $user['id']]);
    flash_set(
        $campaign ? 'warning' : 'danger',
        $campaign ? 'Campaign cannot be cancelled — it is already ' . $campaign['status'] . '.' : 'Campaign not found.'
    );
}

redirect(safe_referer('/client/campaigns.php'));
