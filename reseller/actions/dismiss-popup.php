<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    echo json_encode(['ok' => false]);
    exit;
}

$uid = current_user()['id'];
$id  = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    dismiss_notification($uid, $id);
}

echo json_encode(['ok' => true]);
