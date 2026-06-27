<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/reseller/scheduled.php');
}

if (!csrf_verify()) {
    flash_set('danger', 'Security token mismatch. Please try again.');
    redirect(safe_referer('/reseller/scheduled.php'));
}

$user = current_user();
$id   = (int)($_POST['id'] ?? 0);

if (!$id) {
    redirect('/reseller/scheduled.php');
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
    $campaign = DB::queryOne(
        "SELECT status FROM campaigns WHERE id = ? AND user_id = ?",
        [$id, $user['id']]
    );
    if (!$campaign) {
        flash_set('danger', 'Campaign not found.');
    } else {
        flash_set('warning', 'Campaign cannot be cancelled — it is already ' . $campaign['status'] . '.');
    }
}

redirect('/reseller/scheduled.php');
