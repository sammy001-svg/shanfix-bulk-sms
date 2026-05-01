<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$user = current_user();
if (!$user) {
    json_response(['success' => false, 'error' => 'Unauthorized'], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'CSRF Token mismatch.');
        redirect('/client/templates.php');
    }

    $id      = (int)($_POST['id'] ?? 0);
    $title   = sanitize($_POST['title'] ?? '');
    $message = $_POST['message'] ?? ''; // Message content preserved with tags/placeholders

    if (!$title || !$message) {
        flash_set('danger', 'Please provide both a title and message.');
        redirect('/client/templates.php');
    }

    if ($id > 0) {
        // Update existing
        $res = DB::execute(
            "UPDATE sms_templates SET title = ?, message = ? WHERE id = ? AND user_id = ?",
            [$title, $message, $id, $user['id']]
        );
        flash_set('success', 'Template updated successfully.');
    } else {
        // Create new
        $res = DB::insert(
            "INSERT INTO sms_templates (user_id, title, message, created_at) VALUES (?, ?, ?, NOW())",
            [$user['id'], $title, $message]
        );
        flash_set('success', 'Template created successfully.');
    }

    redirect('/client/templates.php');
}
