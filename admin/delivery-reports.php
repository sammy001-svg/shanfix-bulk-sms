<?php
/**
 * Admin Delivery Reports — per-day carrier delivery status breakdown.
 * Scope: every message on the platform, filterable to one account.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

$drScopeSql        = '1 = 1';
$drScopeParams     = [];
$drUserOptions     = DB::query("SELECT id, name FROM users WHERE role != 'admin' ORDER BY name");
$drUserFilterLabel = 'User';

require __DIR__ . '/../includes/reports/delivery-report-data.php';

$pageTitle  = 'Delivery Reports';
$breadcrumb = [['label'=>'Admin'],['label'=>'Reports'],['label'=>'Delivery Reports']];
require_once __DIR__ . '/layout.php';
require __DIR__ . '/../includes/reports/delivery-report-view.php';
include __DIR__ . '/../includes/layout-footer.php';
