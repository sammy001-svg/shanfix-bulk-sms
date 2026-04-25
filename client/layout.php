<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('client');

$pageTitle = $pageTitle ?? 'Dashboard';
$breadcrumb = $breadcrumb ?? [];
$user = current_user();

$navItems = [
  ['type'=>'section','label'=>'MAIN'],
  ['icon'=>'<i class="fa-solid fa-gauge-high"></i>',     'label'=>'Dashboard',       'url'=>'/client/index.php'],
  ['type'=>'section','label'=>'SMS'],
  ['icon'=>'<i class="fa-solid fa-paper-plane"></i>',    'label'=>'Send SMS',         'url'=>'/client/send-sms.php'],
  [
    'id'=>'campaigns','icon'=>'<i class="fa-solid fa-bullhorn"></i>','label'=>'Campaigns',
    'active'=> str_contains($_SERVER['PHP_SELF'], '/campaign'),
    'children'=>[
      ['icon'=>'<i class="fa-solid fa-list"></i>',          'label'=>'All Campaigns', 'url'=>'/client/campaigns.php'],
      ['icon'=>'<i class="fa-solid fa-plus"></i>',          'label'=>'New Campaign',  'url'=>'/client/campaigns.php?new=1'],
      ['icon'=>'<i class="fa-solid fa-calendar-check"></i>','label'=>'Scheduled',     'url'=>'/client/scheduled.php'],
    ]
  ],
  [
    'id'=>'contacts','icon'=>'<i class="fa-solid fa-address-book"></i>','label'=>'Contacts & Groups',
    'active'=> str_contains($_SERVER['PHP_SELF'], '/contact') || str_contains($_SERVER['PHP_SELF'], '/group'),
    'children'=>[
      ['icon'=>'<i class="fa-solid fa-users"></i>',        'label'=>'Contacts',     'url'=>'/client/contacts.php'],
      ['icon'=>'<i class="fa-solid fa-layer-group"></i>',  'label'=>'Groups',       'url'=>'/client/groups.php'],
      ['icon'=>'<i class="fa-solid fa-upload"></i>',       'label'=>'Import CSV',   'url'=>'/client/contacts-import.php'],
    ]
  ],
  ['type'=>'section','label'=>'ACCOUNT'],
  ['icon'=>'<i class="fa-solid fa-id-badge"></i>',       'label'=>'Sender IDs',       'url'=>'/client/sender-ids.php'],
  ['icon'=>'<i class="fa-solid fa-cart-shopping"></i>',  'label'=>'Buy Units',         'url'=>'/client/purchases.php'],
  ['icon'=>'<i class="fa-solid fa-chart-bar"></i>',      'label'=>'Reports',           'url'=>'/client/reports.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — BulkSMS Client</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <?php if (isset($extraHead)) echo $extraHead; ?>
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
