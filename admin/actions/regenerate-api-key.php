<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/api-keys.php');
}

$userId = (int)($_POST['user_id'] ?? 0);
$target = DB::queryOne("SELECT id, name, role FROM users WHERE id = ? AND role != 'admin'", [$userId]);

if (!$target) {
    flash_set('danger', 'User not found.');
    redirect('/admin/api-keys.php');
}

$ok = generate_user_api_keys($userId);

if ($ok) {
    flash_set('success', "API credentials generated for " . htmlspecialchars($target['name']) . ".");
} else {
    flash_set('danger', 'Failed to generate API credentials. Please try again.');
}

redirect(safe_referer('/admin/api-keys.php'));
