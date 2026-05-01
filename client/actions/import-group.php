<?php
/**
 * Action: Import CSV to Contact Group - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

$user = auth_user();
if (!$user || !in_array($user['role'], ['reseller', 'client'])) {
    json_response(['success' => false, 'error' => 'Unauthorized'], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'CSRF Token mismatch.');
        redirect('/client/send-from-file.php');
    }

    $groupName = sanitize($_POST['group_name'] ?? '');
    $file      = $_FILES['csv_file'] ?? null;

    if (!$groupName || !$file || $file['error'] !== UPLOAD_ERR_OK) {
        flash_set('danger', 'Please provide a group name and a valid CSV file.');
        redirect('/client/send-from-file.php');
    }

    $handle = fopen($file['tmp_name'], "r");
    $header = fgetcsv($handle);
    if (!$header) {
        flash_set('danger', 'CSV file is empty or invalid.');
        redirect('/client/send-from-file.php');
    }

    // Standardize headers for mapping
    $cleanHeaders = array_map(function($h) { return strtolower(trim($h)); }, $header);
    $phoneIdx = array_search('phone', $cleanHeaders);
    $nameIdx  = array_search('name', $cleanHeaders);

    if ($phoneIdx === false) {
        flash_set('danger', "CSV must contain a 'phone' column header.");
        redirect('/client/send-from-file.php');
    }

    try {
        DB::beginTransaction();

        // 1. Create Group
        $groupId = DB::insert(
            "INSERT INTO contact_groups (user_id, name, created_at) VALUES (?, ?, NOW())",
            [$user['id'], $groupName]
        );

        $imported = 0;
        $failed = 0;

        // 2. Import Contacts
        while (($row = fgetcsv($handle)) !== false) {
            $phone = trim($row[$phoneIdx] ?? '');
            if (!$phone) continue;

            $name = ($nameIdx !== false) ? trim($row[$nameIdx] ?? '') : null;
            
            // Build metadata from all columns
            $metadata = [];
            foreach ($cleanHeaders as $idx => $h) {
                $metadata[$h] = trim($row[$idx] ?? '');
            }

            DB::execute(
                "INSERT INTO contacts (user_id, group_id, name, phone, metadata, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                [$user['id'], $groupId, $name, $phone, json_encode($metadata)]
            );
            $imported++;
        }

        DB::commit();
        fclose($handle);

        flash_set('success', "Successfully created group '$groupName' and imported $imported contacts.");
        redirect('/client/send-sms.php');

    } catch (Exception $e) {
        DB::rollback();
        if (isset($handle)) fclose($handle);
        flash_set('danger', 'Import failed: ' . $e->getMessage());
        redirect('/client/send-from-file.php');
    }
}
