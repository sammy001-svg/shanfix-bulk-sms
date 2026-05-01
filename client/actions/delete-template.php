<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$user = current_user();
if (!$user) {
    json_response(['success' => false, 'error' => 'Unauthorized'], 403);
}

$id = (int)($_GET['id'] ?? 0);
$token = $_GET['csrf_token'] ?? '';

if (!validate_csrf($token)) {
    flash_set('danger', 'Invalid CSRF Token.');
    redirect('/client/templates.php');
}

if ($id > 0) {
    DB::execute("DELETE FROM sms_templates WHERE id = ? AND user_id = ?", [$id, $user['id']]);
    flash_set('success', 'Template deleted successfully.');
}

redirect('/client/templates.php');
