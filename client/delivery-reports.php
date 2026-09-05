<?php
/**
 * Client Delivery Reports — per-day carrier delivery status breakdown.
 * Scope: this client's own messages.
 * All the logic lives in includes/reports/ so every portal renders the same report.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('client');

$drScopeSql       = 'm.user_id = ?';
$drScopeParams    = [(int)current_user()['id']];
$drUserOptions    = [];   // a client only ever sees their own traffic
$drUserFilterLabel = 'User';

require __DIR__ . '/../includes/reports/delivery-report-data.php';

$pageTitle  = 'Delivery Reports';
$breadcrumb = [['label'=>'Client'],['label'=>'Reports'],['label'=>'Delivery Reports']];
require_once __DIR__ . '/layout.php';
require __DIR__ . '/../includes/reports/delivery-report-view.php';
include __DIR__ . '/../includes/layout-footer.php';
