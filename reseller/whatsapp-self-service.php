<?php
$pageTitle = 'WhatsApp Self-Service';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Self-Service']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// Handle configuration updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } else {
        $module = $_POST['module_type'];
        $keyword = sanitize($_POST['trigger_keyword']);
        $template = sanitize($_POST['response_template']);
        $enabled = isset($_POST['is_enabled']) ? 1 : 0;
        
        DB::execute("
            INSERT INTO whatsapp_self_service (user_id, module_type, trigger_keyword, response_template, is_enabled) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                trigger_keyword = VALUES(trigger_keyword),
                response_template = VALUES(response_template),
                is_enabled = VALUES(is_enabled)
        ", [$uid, $module, $keyword, $template, $enabled]);
        
        flash_set('success', ucfirst(str_replace('_', ' ', $module)) . ' updated.');
    }
}

$configs = DB::query("SELECT * FROM whatsapp_self_service WHERE user_id = ?", [$uid]);
$activeModules = [];
foreach ($configs as $c) $activeModules[$c['module_type']] = $c;

$modules = [
    'order_status' => [
        'title' => 'Order Status', 'desc' => 'Instant order progress updates.', 'icon' => 'fa-box-open', 'color' => '#3b82f6',
        'default_keyword' => 'ORDER', 'default_template' => "Order #{order_no} status: {status}."
    ],
    'account' => [
        'title' => 'Account Queries', 'desc' => 'Balance and profile details.', 'icon' => 'fa-user-gear', 'color' => '#8b5cf6',
        'default_keyword' => 'MYACC', 'default_template' => "Name: {name}\nBalance: {balance}"
    ]
];
?>

<div class="page-header">
  <div><h1>Self-Service</h1><div class="subtitle">Enable 24/7 automated customer interaction</div></div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-24">
    <?php foreach ($modules as $type => $m): 
        $active = isset($activeModules[$type]) && $activeModules[$type]['is_enabled'];
        $config = $activeModules[$type] ?? null;
    ?>
        <div class="card p-24">
            <div style="display:flex; justify-content:space-between">
                <div style="color:<?= $m['color'] ?>; font-size:24px"><i class="fa-solid <?= $m['icon'] ?>"></i></div>
                <span class="badge badge-<?= $active ? 'success' : 'muted' ?>"><?= $active ? 'Enabled' : 'Disabled' ?></span>
            </div>
            <h3 class="mt-15"><?= $m['title'] ?></h3>
            <p class="text-muted mb-20"><?= $m['desc'] ?></p>
            <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--border); pt-15">
                <button class="btn btn-muted btn-sm" onclick="openConfig('<?= $type ?>')">Configure</button>
                <div style="font-size:12px">Trigger: <strong><?= htmlspecialchars($config['trigger_keyword'] ?? $m['default_keyword']) ?></strong></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Config Modal Reused -->

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
