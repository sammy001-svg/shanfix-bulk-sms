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
        $value = sanitize($value);
        // Direct query update using backticks for the reserved word 'key'
        $res = DB::execute("UPDATE system_settings SET value = ? WHERE `key` = ?", [$value, $key]);
        if ($res === false) $errors++;
    }

    if ($errors === 0) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'System settings updated successfully.'];
    } else {
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Settings saved with some errors. Please check logs.'];
    }

    redirect('/admin/settings.php');
}
