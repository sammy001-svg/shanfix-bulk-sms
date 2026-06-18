<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');
csrf_verify();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/notifications.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    DB::execute("DELETE FROM notifications WHERE id = ?", [$id]);
    flash_set('success', 'Notification deleted.');
} else {
    flash_set('danger', 'Invalid notification.');
}

redirect('/admin/notifications.php');
