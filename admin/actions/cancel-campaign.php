<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
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

$campaign = DB::queryOne("SELECT id, name, status FROM campaigns WHERE id = ?", [$id]);
if (!$campaign) {
    flash_set('danger', 'Campaign not found.');
    redirect('/admin/campaigns.php');
}

$cancellable = ['queued', 'scheduled'];
if (!in_array($campaign['status'], $cancellable, true)) {
    flash_set('warning', 'Campaign "' . htmlspecialchars($campaign['name']) . '" cannot be cancelled — it is ' . $campaign['status'] . '.');
    redirect(safe_referer('/admin/campaigns.php'));
}

DB::execute(
    "UPDATE campaigns SET status = 'failed', sent_at = NOW() WHERE id = ? AND status IN ('queued','scheduled')",
    [$id]
);

flash_set('success', 'Campaign "' . htmlspecialchars($campaign['name']) . '" has been cancelled.');
redirect(safe_referer('/admin/campaigns.php'));
