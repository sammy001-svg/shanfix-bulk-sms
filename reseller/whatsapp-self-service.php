<?php
try {
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
        
        flash_set('success', ucfirst(str_replace('_', ' ', $module)) . ' configuration updated.');
    }
}

// Fetch current configurations
$configs = DB::query("SELECT * FROM whatsapp_self_service WHERE user_id = ?", [$uid]) ?: [];
$activeModules = [];
foreach ($configs as $c) {
    $activeModules[$c['module_type']] = $c;
}

$modules = [
    'order_status' => [
        'title' => 'Order Status',
        'desc' => 'Allow clients to check their order progress by sending an Order ID.',
        'icon' => 'fa-box-open',
        'color' => '#3b82f6',
        'default_keyword' => 'ORDER',
        'default_template' => "Hello! Your Order #{order_no} status is: {status}.\nEstimated Delivery: {delivery_date}\nAmount: {amount}"
    ],
    'appointment' => [
        'title' => 'Book Appointments',
        'desc' => 'Let customers view and book available time slots for your services.',
        'icon' => 'fa-calendar-check',
        'color' => '#10b981',
        'default_keyword' => 'BOOK',
        'default_template' => "You have an appointment for {service_type} on {appointment_at}.\nStatus: {status}\nThank you for booking with us!"
    ],
    'delivery' => [
        'title' => 'Track Deliveries',
        'desc' => 'Provide real-time tracking updates for dispatched items.',
        'icon' => 'fa-truck-fast',
        'color' => '#f59e0b',
        'default_keyword' => 'TRACK',
        'default_template' => "Tracking update for {order_no}:\nCurrent Location: {location}\nStatus: {status}"
    ],
    'account' => [
        'title' => 'Account Queries',
        'desc' => 'Give clients instant access to their account balance or profile details.',
        'icon' => 'fa-user-gear',
        'color' => '#8b5cf6',
        'default_keyword' => 'MYACC',
        'default_template' => "Account Details:\nName: {name}\nBalance: {balance}\nStatus: {status}"
    ]
];
?>

<style>
.module-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 24px;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    gap: 20px;
    position: relative;
    overflow: hidden;
}
.module-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}
.module-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.module-status {
    position: absolute;
    top: 24px;
    right: 24px;
}
.module-title {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
}
.module-desc {
    font-size: 14px;
    color: var(--text-muted);
    line-height: 1.6;
    flex-grow: 1;
}

/* Toggle Switch */
.switch {
  position: relative;
  display: inline-block;
  width: 44px;
  height: 24px;
}
.switch input { opacity: 0; width: 0; height: 0; }
.slider {
  position: absolute;
  cursor: pointer;
  top: 0; left: 0; right: 0; bottom: 0;
  background-color: #ccc;
  transition: .4s;
  border-radius: 34px;
}
.slider:before {
  position: absolute;
  content: "";
  height: 18px; width: 18px;
  left: 3px; bottom: 3px;
  background-color: white;
  transition: .4s;
  border-radius: 50%;
}
input:checked + .slider { background-color: var(--primary); }
input:checked + .slider:before { transform: translateX(20px); }
</style>

<div class="page-header">
  <div>
    <h1>WhatsApp Self-Service</h1>
    <div class="subtitle">Enable powerful automated features for your customers to interact with your business 24/7</div>
  </div>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:24px">
    <?php foreach ($modules as $type => $m): 
        $active = isset($activeModules[$type]) && $activeModules[$type]['is_enabled'];
        $config = $activeModules[$type] ?? null;
    ?>
        <div class="module-card">
            <div class="module-status">
                <span class="badge <?= $active ? 'badge-success' : 'badge-muted' ?>">
                    <?= $active ? 'Enabled' : 'Disabled' ?>
                </span>
            </div>
            <div class="module-icon" style="background: <?= $m['color'] ?>20; color: <?= $m['color'] ?>">
                <i class="fa-solid <?= $m['icon'] ?>"></i>
            </div>
            <div>
                <h3 class="module-title"><?= $m['title'] ?></h3>
                <p class="module-desc"><?= $m['desc'] ?></p>
            </div>
            <div style="display:flex; justify-content: space-between; align-items: center; padding-top:15px; border-top:1px solid var(--border)">
                <button class="btn btn-muted btn-sm" onclick="openConfig('<?= $type ?>')">
                    <i class="fa-solid fa-cog"></i> Configure
                </button>
                <div style="font-size:12px; font-weight:700; color:var(--text-muted)">
                    Trigger: <span class="text-primary"><?= htmlspecialchars($config['trigger_keyword'] ?? $m['default_keyword']) ?></span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Configuration Modal -->
<div id="configModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="modalTitle" style="margin:0">Configure Module</h3>
                <p class="text-muted" style="font-size:12px; margin:0">Set up triggers and response logic for this self-service module</p>
            </div>
            <button type="button" class="btn btn-icon" onclick="closeModal('configModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="module_type" id="modalModuleType">
                <input type="hidden" name="save_config" value="1">

                <div style="display:flex; justify-content: space-between; align-items: center;" class="mb-24">
                    <div>
                        <label class="form-label" style="margin:0">Enable Module</label>
                        <p class="text-muted" style="font-size:11px">Turn this self-service feature on/off</p>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="is_enabled" id="modalEnabled" checked>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="form-group mb-20">
                    <label class="form-label">Trigger Keyword</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-bolt"></i></span>
                        <input type="text" name="trigger_keyword" id="modalKeyword" class="form-control" placeholder="e.g. ORDER" required>
                    </div>
                    <div class="form-hint">The command clients use to start this flow (e.g. "ORDER #1001")</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Response Template</label>
                    <textarea name="response_template" id="modalTemplate" class="form-control" rows="6" required></textarea>
                    <div class="form-hint" id="modalHints">
                        Available Placeholders: {order_no}, {status}, {delivery_date}, {amount}
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-muted flex-1" onclick="closeModal('configModal')">Cancel</button>
                <button type="submit" class="btn btn-primary flex-1">Save Configuration</button>
            </div>
        </form>
    </div>
</div>

<script>
const moduleData = <?= json_encode($modules) ?>;
const allConfigs = <?= json_encode($activeModules) ?>;
const currentConfigs = Array.isArray(allConfigs) ? {} : allConfigs;

function openConfig(type) {
    const m = moduleData[type];
    const c = currentConfigs[type] || {};
    
    document.getElementById('modalTitle').innerText = 'Configure ' + m.title;
    document.getElementById('modalModuleType').value = type;
    document.getElementById('modalEnabled').checked = c.is_enabled !== undefined ? parseInt(c.is_enabled) : true;
    document.getElementById('modalKeyword').value = c.trigger_keyword || m.default_keyword;
    document.getElementById('modalTemplate').value = c.response_template || m.default_template;
    
    // Update hints based on type
    let hints = "";
    if (type === 'order_status') hints = "Placeholders: {order_no}, {status}, {delivery_date}, {amount}";
    if (type === 'appointment') hints = "Placeholders: {service_type}, {appointment_at}, {status}";
    if (type === 'delivery') hints = "Placeholders: {order_no}, {status}, {location}";
    if (type === 'account') hints = "Placeholders: {name}, {balance}, {status}";
    document.getElementById('modalHints').innerText = hints;

    openModal('configModal');
}
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
<?php
} catch (Throwable $e) {
    echo "<div style='padding:20px; border:2px solid red; background:#fff1f1; color:red; font-family:monospace; margin:20px; border-radius:10px; z-index:9999; position:relative;'>";
    echo "<h3>⚠️ PHP Execution Error Caught</h3>";
    echo "<b>Message:</b> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<b>File:</b> " . htmlspecialchars($e->getFile()) . "<br>";
    echo "<b>Line:</b> " . $e->getLine() . "<br>";
    echo "<hr><b>Trace:</b> <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}
?>
