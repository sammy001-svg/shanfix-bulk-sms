<?php
/**
 * WhatsApp Hub - Contact & Group Actions (Reseller)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$user = auth_user();
if (!$user || $user['role'] !== 'reseller') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$action = $_GET['action'] ?? '';
$uid = $user['id'];

// CSRF check for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
        redirect(safe_referer('/reseller/whatsapp-contacts.php'));
    }
}

switch ($action) {
    case 'add_contact':
        $phone = sanitize($_POST['phone']);
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $groupId = (int)($_POST['group_id'] ?? 0);

        if (!$phone) {
            flash_set('danger', 'Phone number is required.');
        } else {
            $phone = preg_replace('/[^0-9]/', '', $phone);
            $res = DB::execute("INSERT INTO whatsapp_contacts (user_id, group_id, phone, name, email) VALUES (?, ?, ?, ?, ?)", 
                        [$uid, $groupId ?: null, $phone, $name, $email]);
            if ($res) flash_set('success', 'Contact added successfully.');
            else flash_set('danger', 'Failed to add contact.');
        }
        break;

    case 'edit_contact':
        $id = (int)$_POST['id'];
        $phone = sanitize($_POST['phone']);
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $groupId = (int)($_POST['group_id'] ?? 0);

        $res = DB::execute("UPDATE whatsapp_contacts SET phone = ?, name = ?, email = ?, group_id = ? WHERE id = ? AND user_id = ?", 
                    [$phone, $name, $email, $groupId ?: null, $id, $uid]);
        if ($res) flash_set('success', 'Contact updated.');
        else flash_set('warning', 'No changes made or update failed.');
        break;

    case 'delete_contact':
        $id = (int)$_POST['id'];
        DB::execute("DELETE FROM whatsapp_contacts WHERE id = ? AND user_id = ?", [$id, $uid]);
        flash_set('success', 'Contact removed.');
        break;

    case 'add_group':
        $name = sanitize($_POST['name']);
        if (!$name) {
            flash_set('danger', 'Group name is required.');
        } else {
            DB::execute("INSERT INTO whatsapp_contact_groups (user_id, name) VALUES (?, ?)", [$uid, $name]);
            flash_set('success', 'Group created.');
        }
        break;

    case 'delete_group':
        if (!csrf_verify()) {
            flash_set('danger', 'Invalid security token.');
        } else {
            $id = (int)$_GET['id'];
            DB::execute("DELETE FROM whatsapp_contact_groups WHERE id = ? AND user_id = ?", [$id, $uid]);
            flash_set('success', 'Group removed.');
        }
        break;

    case 'import_contacts':
        $groupId = (int)$_POST['group_id'];
        $contactsData = $_POST['contacts_json'] ?? '';
        
        if (!$contactsData) {
            flash_set('danger', 'No contact data found for import.');
        } else {
            $data = json_decode($contactsData, true);
            if (!is_array($data)) {
                flash_set('danger', 'Invalid data format.');
            } else {
                $count = 0;
                foreach ($data as $row) {
                    $phone = preg_replace('/[^0-9]/', '', $row['phone'] ?? $row['Phone'] ?? $row['mobile'] ?? '');
                    if (!$phone) continue;

                    $name = $row['name'] ?? $row['Name'] ?? $row['full_name'] ?? '';
                    $email = $row['email'] ?? $row['Email'] ?? '';

                    DB::execute("INSERT IGNORE INTO whatsapp_contacts (user_id, group_id, phone, name, email) VALUES (?, ?, ?, ?, ?)", 
                        [$uid, $groupId ?: null, $phone, $name, $email]);
                    $count++;
                }
                flash_set('success', "Successfully imported $count contacts.");
            }
        }
        break;

    default:
        flash_set('danger', 'Unknown action.');
}

redirect('/reseller/whatsapp-contacts.php' . ($groupId ? "?group=$groupId" : ""));
