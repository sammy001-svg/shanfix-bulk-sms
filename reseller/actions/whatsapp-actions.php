<?php
/**
 * WhatsApp Hub - Contact & Group Actions for Reseller
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$user = auth_user();
if (!$user || $user['role'] !== 'reseller') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$action = $_GET['action'] ?? '';
$uid = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
        redirect($_SERVER['HTTP_REFERER'] ?? '/reseller/whatsapp-contacts.php');
    }
}

switch ($action) {
    case 'add_contact':
        $phone = sanitize($_POST['phone']);
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $groupId = (int)($_POST['group_id'] ?? 0);
        if ($phone) {
            $phone = preg_replace('/[^0-9]/', '', $phone);
            DB::execute("INSERT INTO whatsapp_contacts (user_id, group_id, phone, name, email) VALUES (?, ?, ?, ?, ?)", [$uid, $groupId ?: null, $phone, $name, $email]);
            flash_set('success', 'Contact added.');
        }
        break;

    case 'add_group':
        $name = sanitize($_POST['name']);
        if ($name) {
            DB::execute("INSERT INTO whatsapp_contact_groups (user_id, name) VALUES (?, ?)", [$uid, $name]);
            flash_set('success', 'Group created.');
        }
        break;

    case 'delete_contact':
        $id = (int)$_POST['id'];
        DB::execute("DELETE FROM whatsapp_contacts WHERE id = ? AND user_id = ?", [$id, $uid]);
        flash_set('success', 'Contact removed.');
        break;

    case 'delete_group':
        if (csrf_verify()) {
            $id = (int)$_GET['id'];
            DB::execute("DELETE FROM whatsapp_contact_groups WHERE id = ? AND user_id = ?", [$id, $uid]);
            flash_set('success', 'Group removed.');
        }
        break;
}

redirect('/reseller/whatsapp-contacts.php');
