<?php
/**
 * Action: Save Reseller Domain/SSL Settings (Admin)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid CSRF token.');
        redirect('/admin/resellers.php');
    }

    $id = (int)($_POST['reseller_id'] ?? 0);
    $domain = sanitize($_POST['custom_domain'] ?? '');
    $ssl = (int)($_POST['ssl_enabled'] ?? 0);

    if (!$id) {
        flash_set('danger', 'Reseller ID missing.');
        redirect('/admin/resellers.php');
    }

    // Upsert settings
    $exists = DB::queryOne("SELECT reseller_id FROM reseller_settings WHERE reseller_id = ?", [$id]);
    
    if ($exists) {
        $sql = "UPDATE reseller_settings SET custom_domain = ?, ssl_enabled = ? WHERE reseller_id = ?";
        $params = [$domain ?: null, $ssl, $id];
    } else {
        $sql = "INSERT INTO reseller_settings (reseller_id, custom_domain, ssl_enabled, system_name) VALUES (?, ?, ?, ?)";
        $params = [$id, $domain ?: null, $ssl, Branding::get('system_name')];
    }

    try {
        DB::execute($sql, $params);
        flash_set('success', 'Reseller domain and SSL settings updated.');
    } catch (Exception $e) {
        flash_set('danger', 'Error: ' . $e->getMessage());
    }

    redirect('/admin/resellers.php');
}
