<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('reseller');

$pageTitle = $pageTitle ?? 'Dashboard';
$breadcrumb = $breadcrumb ?? [];
$user = current_user();

$navItems = [
  ['type'=>'section','label'=>'MAIN'],
  ['icon'=>'<i class="fa-solid fa-gauge-high"></i>',  'label'=>'Dashboard',     'url'=>'/reseller/index.php'],
  ['type'=>'section','label'=>'SMS'],
  [
    'id'=>'sms', 'icon'=>'<i class="fa-solid fa-paper-plane"></i>', 'label'=>'SMS & Campaigns',
    'active'=> str_contains($_SERVER['PHP_SELF'], '/campaign') || str_contains($_SERVER['PHP_SELF'], '/send'),
    'children'=>[
      ['icon'=>'<i class="fa-solid fa-plus-circle"></i>',    'label'=>'Send SMS',        'url'=>'/reseller/send-sms.php'],
      ['icon'=>'<i class="fa-solid fa-bullhorn"></i>',       'label'=>'Campaigns',        'url'=>'/reseller/campaigns.php'],
      ['icon'=>'<i class="fa-solid fa-calendar-check"></i>', 'label'=>'Scheduled',        'url'=>'/reseller/scheduled.php'],
    ]
  ],
  [
    'id'=>'contacts','icon'=>'<i class="fa-solid fa-address-book"></i>','label'=>'Contacts & Groups',
    'active'=> str_contains($_SERVER['PHP_SELF'], '/contact') || str_contains($_SERVER['PHP_SELF'], '/group'),
    'children'=>[
      ['icon'=>'<i class="fa-solid fa-users"></i>',  'label'=>'All Contacts', 'url'=>'/reseller/contacts.php'],
      ['icon'=>'<i class="fa-solid fa-layer-group"></i>','label'=>'Groups',  'url'=>'/reseller/groups.php'],
      ['icon'=>'<i class="fa-solid fa-upload"></i>',  'label'=>'Import CSV',  'url'=>'/reseller/contacts-import.php'],
    ]
  ],
  ['type'=>'section','label'=>'BUSINESS'],
  ['icon'=>'<i class="fa-solid fa-store"></i>',       'label'=>'My Clients',     'url'=>'/reseller/clients.php'],
  ['icon'=>'<i class="fa-solid fa-tags"></i>',        'label'=>'My Pricing',     'url'=>'/reseller/pricing.php'],
  ['icon'=>'<i class="fa-solid fa-id-badge"></i>',    'label'=>'Sender IDs',     'url'=>'/reseller/sender-ids.php'],
  ['icon'=>'<i class="fa-solid fa-cart-shopping"></i>','label'=>'Buy Units',     'url'=>'/reseller/purchases.php'],
  ['type'=>'section','label'=>'REPORTS'],
  ['icon'=>'<i class="fa-solid fa-chart-bar"></i>',   'label'=>'Reports',        'url'=>'/reseller/reports.php'],
  ['icon'=>'<i class="fa-solid fa-code"></i>',         'label'=>'API & Integration', 'url'=>'/reseller/api.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — BulkSMS Reseller</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script>
    (function() {
      const theme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      if (theme === 'dark') document.documentElement.classList.add('dark-mode');
    })();
  </script>
</head>
<body>
<div class="app-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content" id="mainContent">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content animate-in">
      <?php $flash = flash_get(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
      <?php endif; ?>
