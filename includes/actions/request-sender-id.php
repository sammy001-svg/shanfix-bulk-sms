<?php
/**
 * Action: Request Sender ID - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
$user = auth_user();
if (!$user) redirect('/login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senderId = sanitize($_POST['sender_id'] ?? '');
    $purpose  = sanitize($_POST['purpose'] ?? '');

    // Basic Validation
    if (strlen($senderId) < 3 || strlen($senderId) > 11) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Sender ID must be between 3 and 11 characters.'];
        redirect($_SERVER['HTTP_REFERER']);
    }

    $id = DB::insert("INSERT INTO sender_ids (user_id, sender_id, purpose, status, created_at) VALUES (?, ?, ?, 'pending', NOW())", 
                     [$user['id'], $senderId, $purpose]);

    if ($id) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Sender ID request submitted. Pending review.'];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Database error. Duplicate Sender ID requested?'];
    }

    redirect($_SERVER['HTTP_REFERER']);
}
