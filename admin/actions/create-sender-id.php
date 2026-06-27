<?php
/**
 * Admin Action: Create & Approve Sender ID - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
        redirect('/admin/sender-ids.php');
    }

    $admin        = current_user();
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $senderId     = sanitize($_POST['sender_id'] ?? '');
    $purpose      = sanitize($_POST['purpose'] ?? 'Assigned by Administrator');

    if (!$targetUserId || !$senderId) {
        flash_set('danger', 'User and Sender ID are required.');
        redirect('/admin/sender-ids.php');
    }

    // Check if it already exists for this user
    $exists = DB::queryOne("SELECT id FROM sender_ids WHERE user_id = ? AND sender_id = ?", [$targetUserId, $senderId]);

    if ($exists) {
        DB::execute("UPDATE sender_ids SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ?", [$admin['id'], $exists['id']]);
        flash_set('info', "Sender ID '$senderId' already existed; it has now been approved.");
    } else {
        DB::insert("INSERT INTO sender_ids (user_id, sender_id, purpose, status, approved_at, approved_by, created_at)
                   VALUES (?, ?, ?, 'approved', NOW(), ?, NOW())",
                   [$targetUserId, $senderId, $purpose, $admin['id']]);
        flash_set('success', "Sender ID '$senderId' successfully assigned and approved.");
    }

    redirect('/admin/sender-ids.php');
}
