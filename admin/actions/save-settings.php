<?php
/**
 * Admin Action: Save System Settings - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/settings.php');
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
    redirect('/admin/settings.php');
}

// Explicit whitelist — only these keys may be written to system_settings.
// Adding a new settings field to the UI requires adding it here first.
const ALLOWED_SETTING_KEYS = [
    // Site identity
    'site_name', 'site_url', 'support_email', 'support_phone', 'default_currency',
    // SMS gateway
    'sms_gateway', 'onfon_api_key', 'onfon_user_id', 'onfon_access_key',
    'sms_api_key', 'sms_api_secret', 'sms_shortcode',
    // Payments
    'payhero_api_username', 'payhero_api_password', 'payhero_channel_id', 'payhero_webhook_token',
    'kk_client_id', 'kk_client_secret', 'kk_till_number', 'kk_base_url', 'kk_webhook_secret',
    // Pricing
    'unit_price',
    // SMTP
    'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_pass', 'smtp_from_name',
    // Security & access
    'session_timeout', 'max_login_attempts', 'allow_registration', 'maintenance_mode',
    // Performance
    'max_concurrent_campaigns',
];

// Secrets that must not be blanked out if the field is submitted empty
// (the form renders these as password inputs with placeholder "Leave blank to keep current")
const PRESERVE_IF_EMPTY = [
    'sms_api_secret', 'payhero_api_password', 'payhero_webhook_token', 'smtp_pass',
    'kk_client_secret', 'kk_webhook_secret',
];

$errors = 0;
foreach ($_POST as $key => $value) {
    if ($key === 'csrf_token' || $key === 'tab') continue;

    if (!in_array($key, ALLOWED_SETTING_KEYS, true)) {
        // Unknown key — skip silently (don't expose which keys are rejected)
        continue;
    }

    if (in_array($key, PRESERVE_IF_EMPTY, true) && $value === '') {
        continue;
    }

    $value = sanitize($value);

    $res = DB::execute(
        "INSERT INTO system_settings (`key`, `value`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
        [$key, $value]
    );

    if ($res === false) $errors++;
}

$_SESSION['flash'] = $errors === 0
    ? ['type' => 'success', 'message' => 'System settings updated successfully.']
    : ['type' => 'warning', 'message' => 'Settings saved with some errors. Check server logs.'];

redirect('/admin/settings.php');
