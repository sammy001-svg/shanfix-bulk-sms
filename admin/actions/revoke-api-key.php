<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/api-keys.php');
}

$userId = (int)($_POST['user_id'] ?? 0);
$target = DB::queryOne("SELECT id, name FROM users WHERE id = ? AND role != 'admin'", [$userId]);

if (!$target) {
    flash_set('danger', 'User not found.');
    redirect('/admin/api-keys.php');
}

DB::execute(
    "UPDATE users SET api_client_id = NULL, api_key = NULL WHERE id = ?",
    [$userId]
);

// Also clear their rate-counter history so the bucket is clean if they get a new key
DB::execute(
    "DELETE FROM api_rate_counters WHERE bucket = ?",
    ['api:' . $userId]
);

flash_set('success', "API key revoked for " . htmlspecialchars($target['name']) . ". All integrations using the old key will no longer work.");
redirect(safe_referer('/admin/api-keys.php'));
