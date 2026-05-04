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
  ['icon'=>'<i class="fa-solid fa-paper-plane"></i>',    'label'=>'Send SMS',         'url'=>'/client/send-sms.php', 'no_ajax'=>true],
  ['icon'=>'<i class="fa-solid fa-note-sticky"></i>',    'label'=>'SMS Templates',     'url'=>'/client/templates.php'],
  ['icon'=>'<i class="fa-solid fa-file-import"></i>',   'label'=>'Send from File',   'url'=>'/client/send-from-file.php', 'no_ajax'=>true],
  [
    'id'=>'campaigns','icon'=>'<i class="fa-solid fa-bullhorn"></i>','label'=>'Campaigns',
    'active'=> (strpos($_SERVER['PHP_SELF'], '/campaign') !== false),
    'children'=>[
      ['icon'=>'<i class="fa-solid fa-list"></i>',          'label'=>'All Campaigns', 'url'=>'/client/campaigns.php'],
      ['icon'=>'<i class="fa-solid fa-plus"></i>',          'label'=>'New Campaign',  'url'=>'/client/campaigns.php?new=1'],
      ['icon'=>'<i class="fa-solid fa-calendar-check"></i>','label'=>'Scheduled',     'url'=>'/client/scheduled.php'],
    ]
  ],
  [
    'id'=>'contacts','icon'=>'<i class="fa-solid fa-address-book"></i>','label'=>'Contacts & Groups',
    'active'=> (strpos($_SERVER['PHP_SELF'], '/contact') !== false) || (strpos($_SERVER['PHP_SELF'], '/group') !== false),
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
  [
    'id'=>'ussd','icon'=>'<i class="fa-solid fa-hashtag"></i>','label'=>'USSD',
    'active'=> (strpos($_SERVER['PHP_SELF'], '/ussd') !== false),
    'children'=>[
      ['icon'=>'<i class="fa-solid fa-plus"></i>',          'label'=>'Request USSD Code', 'url'=>'/client/ussd-request.php'],
      ['icon'=>'<i class="fa-solid fa-list-ol"></i>',       'label'=>'USSD Codes',        'url'=>'/client/ussd-codes.php'],
      ['icon'=>'<i class="fa-solid fa-chart-line"></i>',    'label'=>'Usage Analytics',   'url'=>'/client/ussd-analytics.php'],
      ['icon'=>'<i class="fa-solid fa-clock-rotate-left"></i>','label'=>'USSD Sessions',    'url'=>'/client/ussd-sessions.php'],
      ['icon'=>'<i class="fa-solid fa-wallet"></i>',        'label'=>'Wallet Balance',    'url'=>'/client/ussd-wallet.php'],
      ['icon'=>'<i class="fa-solid fa-code-branch"></i>',   'label'=>'API Callbacks',      'url'=>'/client/ussd-callbacks.php'],
    ]
  ],
  [
    'id'=>'whatsapp','icon'=>'<i class="fa-brands fa-whatsapp"></i>','label'=>'WhatsApp Hub',
    'active'=> (strpos($_SERVER['PHP_SELF'], '/whatsapp') !== false),
    'children'=>[
      ['icon'=>'<i class="fa-solid fa-link"></i>',          'label'=>'Connect Account',   'url'=>'/client/whatsapp-connect.php'],
      ['icon'=>'<i class="fa-solid fa-paper-plane"></i>',   'label'=>'Bulk Messaging',    'url'=>'/client/whatsapp-bulk.php'],
      ['icon'=>'<i class="fa-solid fa-address-book"></i>',   'label'=>'Contacts & Groups', 'url'=>'/client/whatsapp-contacts.php'],
      ['icon'=>'<i class="fa-solid fa-robot"></i>',         'label'=>'Chatbot Builder',   'url'=>'/client/whatsapp-chatbot.php'],
      ['icon'=>'<i class="fa-solid fa-database"></i>',      'label'=>'Dynamic Data Hub',  'url'=>'/client/whatsapp-data.php'],
      ['icon'=>'<i class="fa-solid fa-gears"></i>',         'label'=>'Self-Service',      'url'=>'/client/whatsapp-self-service.php'],
      ['icon'=>'<i class="fa-solid fa-message"></i>',       'label'=>'Message Logs',      'url'=>'/client/whatsapp-logs.php'],
      ['icon'=>'<i class="fa-solid fa-wallet"></i>',        'label'=>'Top-up Wallet',     'url'=>'/client/whatsapp-topup.php'],
      ['icon'=>'<i class="fa-solid fa-inbox"></i>',         'label'=>'Conversations',     'url'=>'/client/whatsapp-inbox.php'],
    ]
  ],
  ['icon'=>'<i class="fa-solid fa-code"></i>',           'label'=>'API & Integration', 'url'=>'/client/api.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars(Branding::get('system_name')) ?></title>
  <meta name="view-transition" content="same-origin">
  <link rel="stylesheet" href="/assets/css/style.css?v=1.1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <?php Branding::renderStyles(); ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script>
    (function() {
      const theme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      if (theme === 'dark') document.documentElement.classList.add('dark-mode');
    })();

    // GLOBAL CONFIG INJECTION
    <?php
    $effectiveSmsRate = 1.00;
    if (($user['custom_unit_price'] ?? 0) > 0) {
        $effectiveSmsRate = (float)$user['custom_unit_price'];
    } elseif (!empty($user['parent_id'])) {
        $rs = DB::queryOne("SELECT unit_price FROM reseller_settings WHERE reseller_id = ?", [$user['parent_id']]);
        if ($rs && ($rs['unit_price'] ?? 0) > 0) {
            $effectiveSmsRate = (float)$rs['unit_price'];
        }
    }
    ?>
    window.ShanfixConfig = {
        smsRate: <?= json_encode($effectiveSmsRate) ?>,
        whatsappRate: <?= json_encode((float)($user['whatsapp_rate'] ?? 1.00)) ?>,
        siteUrl: <?= json_encode(SITE_URL) ?>
    };

    // STK PUSH GLOBAL HANDLER
    window.ShanfixSTK = {
        checkStatus: function(purchaseId, type) {
            // type can be 'sms', 'whatsapp', or 'ussd'
            const isUssd = type === 'ussd';
            const ajaxType = isUssd ? 'ussd' : 'purchase';
            
            Swal.fire({
                title: 'Waiting Confirmation',
                text: 'Please enter your M-Pesa PIN on your phone...',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            let attempts = 0;
            const maxAttempts = 30; // 60 seconds (2s interval)
            const interval = setInterval(async () => {
                attempts++;
                try {
                    const response = await fetch(`/client/ajax-check-payment.php?id=${purchaseId}&type=${ajaxType}`);
                    const data = await response.json();

                    if (data.status === 'completed') {
                        clearInterval(interval);
                        Swal.fire({
                            title: 'Congratulations! 🎉',
                            text: 'Payment successful! Your account has been topped up.',
                            icon: 'success',
                            confirmButtonColor: 'var(--primary)'
                        }).then(() => {
                            // Update ALL elements with 'current-balance-value' class intelligently
                            const balanceEls = document.querySelectorAll('.current-balance-value');
                            
                            balanceEls.forEach(el => {
                                const bType = el.dataset.balanceType;
                                if (!bType) return;

                                let newVal = 0;
                                let prefix = '';
                                let suffix = '';
                                let decimals = 2;

                                if (bType === 'sms') {
                                    newVal = data.balances.sms;
                                    // Some elements might want " Units" suffix, others not.
                                    // If it's the sidebar (units-value), no suffix.
                                    if (!el.classList.contains('units-value')) {
                                        // Check if it's the one in purchases.php which has " units" outside the tag
                                        // or if we should add it. For now, let's look at the existing content.
                                        if (el.innerText.toLowerCase().includes('units')) suffix = ' Units';
                                    }
                                } else if (bType === 'whatsapp') {
                                    newVal = data.balances.whatsapp;
                                    prefix = 'KES ';
                                } else if (bType === 'ussd') {
                                    newVal = data.balances.ussd;
                                    prefix = 'KES ';
                                }

                                const formatted = prefix + parseFloat(newVal).toLocaleString(undefined, {
                                    minimumFractionDigits: decimals, 
                                    maximumFractionDigits: decimals
                                }) + suffix;
                                
                                el.innerText = formatted;
                            });

                            // If no elements were updated, fallback to reload
                            if (balanceEls.length === 0) {
                                location.reload();
                            }
                        });
                    } else if (data.status === 'failed') {
                        clearInterval(interval);
                        Swal.fire({
                            title: 'Payment Declined 😔',
                            text: 'The transaction was cancelled or failed. Please try again.',
                            icon: 'error',
                            confirmButtonColor: '#ff4d4d'
                        });
                    } else if (attempts >= maxAttempts) {
                        clearInterval(interval);
                        Swal.fire({
                            title: 'Timeout',
                            text: 'We haven\'t received the payment confirmation yet. Please check your history in a moment.',
                            icon: 'warning'
                        });
                    }
                } catch (e) {
                    console.error('Polling error:', e);
                }
            }, 2000);
        }
    };
  </script>
</head>
<body>
<div id="page-loader"></div>
<div class="app-wrapper">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="main-content" id="mainContent">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <div class="page-content animate-in">
      <?php $flash = flash_get(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
      <?php endif; ?>
