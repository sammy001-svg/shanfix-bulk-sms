<?php
/**
 * Action: Handle Notifications Interactions (AJAX)
 */
require_once __DIR__ . '/../auth.php';

if (!is_logged_in()) {
    json_response(['error' => 'Unauthorized'], 401);
}

$user = current_user();
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if ($action === 'dismiss' && $id) {
    dismiss_notification($user['id'], $id);
    json_response(['success' => true]);
}

if ($action === 'read_all') {
    $notifs = get_unread_notifications($user['id']);
    foreach ($notifs as $n) {
        mark_notification_read($user['id'], $n['id']);
    }
    json_response(['success' => true]);
}

json_response(['error' => 'Invalid action'], 400);
