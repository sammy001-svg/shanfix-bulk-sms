<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pageTitle = $pageTitle ?? 'Dashboard';
$breadcrumb = $breadcrumb ?? [];
$user = current_user();

// Fetch pending counts for badges
$pendingSenderIds = DB::queryValue("SELECT COUNT(*) FROM sender_ids WHERE status = 'pending'");
$pendingUssdRequests = DB::queryValue("SELECT COUNT(*) FROM ussd_codes WHERE status = 'pending'");

$navItems = [
  ['type'=>'section','label'=>'MAIN'],
  ['icon'=>'<i class="fa-solid fa-gauge-high"></i>',    'label'=>'Dashboard',   'url'=>'/admin/index.php'],
  ['type'=>'section','label'=>'MANAGEMENT'],
  [
    'id'=>'users', 'icon'=>'<i class="fa-solid fa-users"></i>', 'label'=>'Users',
    'active'=> (strpos($_SERVER['PHP_SELF'], '/users') !== false),
    'children'=>[
      ['icon'=>'<i class="fa-solid fa-store"></i>',      'label'=>'Resellers',       'url'=>'/admin/resellers.php'],
      ['icon'=>'<i class="fa-solid fa-user"></i>',       'label'=>'Clients',          'url'=>'/admin/clients.php'],
      ['icon'=>'<i class="fa-solid fa-user-plus"></i>',  'label'=>'Create User',      'url'=>'/admin/users-create.php'],
    ]
  ],
  ['icon'=>'<i class="fa-solid fa-id-badge"></i>',  'label'=>'Sender IDs',     'url'=>'/admin/sender-ids.php',
   'badge'=> ($pendingSenderIds ?? 0) > 0 ? ($pendingSenderIds ?? 0) : null
  ],
  ['icon'=>'<i class="fa-solid fa-coins"></i>',     'label'=>'Units',          'url'=>'/admin/units.php'],
  ['icon'=>'<i class="fa-solid fa-tags"></i>',      'label'=>'Pricing Plans',  'url'=>'/admin/pricing.php'],
  ['type'=>'section','label'=>'REPORTS'],
  ['icon'=>'<i class="fa-solid fa-chart-line"></i>','label'=>'Analytics',      'url'=>'/admin/analytics.php'],
  ['icon'=>'<i class="fa-solid fa-file-lines"></i>','label'=>'Reports',        'url'=>'/admin/reports.php'],
  ['icon'=>'<i class="fa-solid fa-envelope-open-text"></i>','label'=>'Message Logs', 'url'=>'/admin/logs.php'],
  [
    'id'=>'ussd','icon'=>'<i class="fa-solid fa-hashtag"></i>','label'=>'USSD',
    'active'=> (strpos($_SERVER['PHP_SELF'], '/ussd') !== false),
    'badge'=> ($pendingUssdRequests ?? 0) > 0 ? ($pendingUssdRequests ?? 0) : null,
    'children'=>[
      ['icon'=>'<i class="fa-solid fa-plus"></i>',          'label'=>'Request USSD Code', 'url'=>'/admin/ussd-requests.php'],
      ['icon'=>'<i class="fa-solid fa-list-ol"></i>',       'label'=>'USSD Codes',        'url'=>'/admin/ussd-codes.php'],
      ['icon'=>'<i class="fa-solid fa-chart-line"></i>',    'label'=>'Usage Analytics',   'url'=>'/admin/ussd-analytics.php'],
      ['icon'=>'<i class="fa-solid fa-clock-rotate-left"></i>','label'=>'USSD Sessions',    'url'=>'/admin/ussd-sessions.php'],
      ['icon'=>'<i class="fa-solid fa-wallet"></i>',        'label'=>'Wallet Balance',    'url'=>'/admin/ussd-wallet.php'],
    ]
  ],
  [
    'id'=>'whatsapp','icon'=>'<i class="fa-brands fa-whatsapp"></i>','label'=>'WhatsApp Hub',
    'active'=> (strpos($_SERVER['PHP_SELF'], '/whatsapp') !== false),
    'children'=>[
      ['icon'=>'<i class="fa-solid fa-list"></i>',          'label'=>'Service Overview',  'url'=>'/admin/whatsapp-management.php'],
      ['icon'=>'<i class="fa-solid fa-tags"></i>',          'label'=>'Global Pricing',    'url'=>'/admin/whatsapp-pricing.php'],
    ]
  ],
  ['type'=>'section','label'=>'SYSTEM'],
  ['icon'=>'<i class="fa-solid fa-bell"></i>',      'label'=>'Notifications',  'url'=>'/admin/notifications.php'],
  ['icon'=>'<i class="fa-solid fa-gear"></i>',      'label'=>'Settings',       'url'=>'/admin/settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — BulkSMS Admin</title>
  <link rel="stylesheet" href="/assets/css/style.css?v=1.1">
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
      <?php
      $flash = flash_get();
      if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>">
          <?= htmlspecialchars($flash['message']) ?>
        </div>
      <?php endif; ?>
