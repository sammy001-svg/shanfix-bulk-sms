<?php
/**
 * Reseller Delivery Reports — per-day carrier delivery status breakdown.
 * Scope: the reseller's own messages plus those of every client under them.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('reseller');

$resellerId = (int)current_user()['id'];

$drScopeSql    = 'm.user_id = ? OR m.user_id IN (SELECT id FROM users WHERE parent_id = ?)';
$drScopeParams = [$resellerId, $resellerId];

// Filter dropdown: the reseller themself plus their own clients.
$drUserOptions = array_merge(
    [['id' => $resellerId, 'name' => current_user()['name'] . ' (me)']],
    DB::query("SELECT id, name FROM users WHERE parent_id = ? ORDER BY name", [$resellerId])
);
$drUserFilterLabel = 'Client';

require __DIR__ . '/../includes/reports/delivery-report-data.php';

$pageTitle  = 'Delivery Reports';
$breadcrumb = [['label'=>'Reseller'],['label'=>'Reports'],['label'=>'Delivery Reports']];
require_once __DIR__ . '/layout.php';
require __DIR__ . '/../includes/reports/delivery-report-view.php';
include __DIR__ . '/../includes/layout-footer.php';
