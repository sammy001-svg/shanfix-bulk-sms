<?php
/**
 * Action: Get Reseller White-Label Settings (JSON)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$settings = DB::queryOne("SELECT custom_domain, ssl_enabled FROM reseller_settings WHERE reseller_id = ?", [$id]);

echo json_encode($settings ?: ['custom_domain' => '', 'ssl_enabled' => 0]);
exit();
