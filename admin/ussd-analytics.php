<?php
$pageTitle = 'USSD Usage Analytics';
$breadcrumb = [['label'=>'Admin'],['label'=>'USSD'],['label'=>'Analytics']];
require_once __DIR__ . '/layout.php';
?>

<div class="page-header">
  <div><h1>USSD Analytics</h1><div class="subtitle">Comprehensive overview of USSD performance across all users</div></div>
</div>

<div class="alert alert-info">
    <i class="fa-solid fa-person-digging"></i> This module is under construction. Detailed analytics for specific codes will be available here soon.
</div>

<div class="card">
    <div class="card-body">
        <p class="text-muted">Currently tracking sessions and requests across all active USSD services.</p>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
