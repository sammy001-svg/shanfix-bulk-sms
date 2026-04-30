<?php
/**
 * Admin Action: Save System Settings - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = $_POST;
    unset($settings['csrf_token']);

    $errors = 0;
    foreach ($settings as $key => $value) {
        if ($key === 'tab') continue;

        // Special handling for secrets: don't overwrite if empty
        if (in_array($key, ['sms_api_secret', 'kk_client_secret', 'payhero_api_password', 'smtp_pass']) && empty($value)) {
            continue;
        }

        $value = sanitize($value);
        
        // UPSERT: Insert if new, update if exists
        $sql = "INSERT INTO system_settings (`key`, `value`) VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";
        $res = DB::execute($sql, [$key, $value]);
        
        if ($res === false) $errors++;
    }

    if ($errors === 0) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'System settings updated successfully.'];
    } else {
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Settings saved with some errors. Please check logs.'];
    }

    redirect('/admin/settings.php');
}
