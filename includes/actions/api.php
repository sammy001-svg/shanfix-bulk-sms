<?php
/**
 * AJAX API Endpoint — Shanfix Technology
 * Handles all AJAX requests from the frontend JS
 */
require_once __DIR__ . '/../auth.php';

if (!is_logged_in()) {
    json_response(['error' => 'Unauthorized'], 401);
}

$action = sanitize($_GET['action'] ?? $_POST['action'] ?? '');
$uid = current_user()['id'];

switch ($action) {
    case 'get_units': {
        $user = DB::queryOne("SELECT sms_units FROM users WHERE id=?", [$uid]);
        json_response(['units' => $user['sms_units'] ?? 0]);
        break;
    }
    case 'sms_stats': {
        $stats = DB::queryOne("
            SELECT
                COUNT(*) as total,
                SUM(status='sent') as sent,
                SUM(status='delivered') as delivered,
                SUM(status='failed') as failed
            FROM messages WHERE user_id=?
        ", [$uid]);
        json_response($stats ?? []);
        break;
    }
    case 'delete_contact': {
        if (!csrf_verify()) json_response(['error'=>'Invalid token'],403);
        $id = (int)($_POST['id'] ?? 0);
        DB::execute("DELETE FROM contacts WHERE id=? AND user_id=?",[$id,$uid]);
        json_response(['success'=>true]);
        break;
    }
    case 'check_sender_id': {
        $sid = sanitize($_GET['sender_id'] ?? '');
        $exists = DB::queryOne("SELECT id FROM sender_ids WHERE user_id=? AND sender_id=? AND status='approved'",[$uid,$sid]);
        json_response(['available' => !$exists, 'approved' => (bool)$exists]);
        break;
    }
    default:
        json_response(['error' => 'Unknown action'], 400);
}
