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
        $header = fgetcsv($handle);
        if (!$header) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => "CSV file is empty or invalid."];
            redirect($_SERVER['HTTP_REFERER']);
        }
        
        // Clean and lowercase headers for matching
        $cleanHeaders = array_map(function($h) { return strtolower(trim($h)); }, $header);
        
        $phoneIdx = array_search('phone', $cleanHeaders);
        $nameIdx  = array_search('name',  $cleanHeaders);
        $emailIdx = array_search('email', $cleanHeaders);

        if ($phoneIdx === false) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => "CSV must contain a 'phone' column header."];
            redirect($_SERVER['HTTP_REFERER']);
        }

        $imported = 0; $skipped = 0;
        while (($data = fgetcsv($handle)) !== false) {
            $phone = trim($data[$phoneIdx] ?? '');
            if (!$phone) continue;

            // Robust Kenyan Phone Normalization
            $phone = preg_replace('/[^0-9]/', '', $phone); // Remove everything except numbers
            
            if (strlen($phone) === 9) { // 712345678 -> +254712345678
                $phone = '254' . $phone;
            } elseif (strpos($phone, '0') === 0 && strlen($phone) === 10) { // 0712345678 -> +254712345678
                $phone = '254' . substr($phone, 1);
            } elseif (strpos($phone, '254') !== 0 && strlen($phone) > 9) {
                // Not starting with 254 but long enough? might be another country or wrong
                // For now, assume it's just a raw number and try to prefix +
            }
            
            // Standardize with + prefix
            if (strpos($phone, '+') !== 0) $phone = '+' . $phone;

            $name  = $nameIdx !== false  ? sanitize($data[$nameIdx] ?? '') : '';
            $email = $emailIdx !== false ? sanitize($data[$emailIdx] ?? '') : '';

            // Check existence for current user
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
