<?php
/**
 * Action: Import Contacts from CSV - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
if (!in_array($user['role'], ['reseller', 'client'])) redirect('/login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $groupId    = (int)($_POST['group_id'] ?? 0);
    $duplicates = $_POST['duplicates'] ?? 'skip';
    $file       = $_FILES['csv_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], "r");
        $header = fgetcsv($handle); // Assuming first row is header
        
        $phoneIdx = array_search('phone', array_map('strtolower', $header));
        $nameIdx  = array_search('name',  array_map('strtolower', $header));
        $emailIdx = array_search('email', array_map('strtolower', $header));

        if ($phoneIdx === false) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => "CSV must contain a 'phone' column."];
            redirect($_SERVER['HTTP_REFERER']);
        }

        $imported = 0; $skipped = 0;
        while (($data = fgetcsv($handle)) !== false) {
            $phone = sanitize($data[$phoneIdx] ?? '');
            if (!$phone) continue;

            // Normalize phone
            $phone = preg_replace('/[^0-9+]/', '', $phone);
            if (strpos($phone, '0') === 0) $phone = '+254' . substr($phone, 1);
            if (strpos($phone, '+') !== 0) $phone = '+' . $phone;

            $name  = $nameIdx !== false  ? sanitize($data[$nameIdx] ?? '') : '';
            $email = $emailIdx !== false ? sanitize($data[$emailIdx] ?? '') : '';

            $exists = DB::queryOne("SELECT id FROM contacts WHERE user_id = ? AND phone = ?", [$user['id'], $phone]);
            
            if ($exists && $duplicates === 'skip') {
                $skipped++;
                continue;
            }

            if ($exists && $duplicates === 'update') {
                DB::execute("UPDATE contacts SET name = ?, email = ?, group_id = ? WHERE id = ?", [$name, $email, $groupId ?: null, $exists['id']]);
                $imported++;
            } else {
                DB::insert("INSERT INTO contacts (user_id, group_id, phone, name, email, created_at) VALUES (?, ?, ?, ?, ?, NOW())", 
                           [$user['id'], $groupId ?: null, $phone, $name, $email]);
                $imported++;
            }
        }
        fclose($handle);

        $_SESSION['flash'] = ['type' => 'success', 'message' => "Import complete: $imported imported, $skipped skipped."];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => "File upload error code: " . $file['error']];
    }

    redirect($_SERVER['HTTP_REFERER']);
}
