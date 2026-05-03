<?php
try {
$pageTitle = 'WhatsApp Account Hub';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Account Hub']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } else {
        $action = $_POST['action'];
        
        if ($action === 'save_account') {
            $id = (int)($_POST['id'] ?? 0);
            $name = sanitize($_POST['account_name']);
            $phone = sanitize($_POST['phone_number']);
            $instanceId = sanitize($_POST['instance_id']);
            $token = sanitize($_POST['token']);

            if ($id > 0) {
                DB::execute("UPDATE whatsapp_accounts SET account_name = ?, phone_number = ?, instance_id = ?, token = ?, ai_enabled = ?, ai_api_key = ?, ai_prompt = ? WHERE id = ? AND user_id = ?", 
                    [$name, $phone, $instanceId, $token, (int)($_POST['ai_enabled'] ?? 0), $_POST['ai_api_key'] ?? '', $_POST['ai_prompt'] ?? '', $id, $uid]);
                flash_set('success', "Account '$name' updated.");
            } else {
                DB::execute("INSERT INTO whatsapp_accounts (user_id, account_name, phone_number, instance_id, token, status, ai_enabled, ai_api_key, ai_prompt) 
                    VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?)", 
                    [$uid, $name, $phone, $instanceId, $token, (int)($_POST['ai_enabled'] ?? 0), $_POST['ai_api_key'] ?? '', $_POST['ai_prompt'] ?? '']);
                flash_set('success', "Account '$name' added successfully.");
            }
        }

        if ($action === 'delete_account') {
            $id = (int)$_POST['id'];
            DB::execute("DELETE FROM whatsapp_accounts WHERE id = ? AND user_id = ?", [$id, $uid]);
            flash_set('success', 'WhatsApp account removed.');
        }

        if ($action === 'toggle_status') {
            $id = (int)$_POST['id'];
            $newStatus = $_POST['status'] === 'active' ? 'pending' : 'active';
            DB::execute("UPDATE whatsapp_accounts SET status = ? WHERE id = ? AND user_id = ?", [$newStatus, $id, $uid]);
            flash_set('success', 'Account status updated.');
        }
    }
    header("Location: whatsapp-connect.php");
    exit;
}

// Fetch all accounts
$accounts = DB::query("SELECT * FROM whatsapp_accounts WHERE user_id = ? ORDER BY created_at DESC", [$uid]) ?: [];
?>

<style>
.account-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 24px;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.account-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}
.account-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.account-icon {
    width: 48px;
    height: 48px;
    background: rgba(0, 200, 150, 0.1);
    color: var(--primary);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.account-info h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
}
.account-info p {
    margin: 2px 0 0;
    font-size: 13px;
    color: var(--text-muted);
}
.account-details {
    background: var(--bg-muted);
    border-radius: 12px;
    padding: 12px;
    font-size: 12px;
}
.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}
.detail-row:last-child { margin-bottom: 0; }
.detail-label { color: var(--text-muted); }
.detail-value { font-weight: 600; font-family: 'JetBrains Mono', monospace; }
</style>

<div class="page-header">
  <div>
    <h1>WhatsApp Account Hub</h1>
    <div class="subtitle">Manage your connected WhatsApp numbers and API instances</div>
  </div>
  <button class="btn btn-primary" onclick="openAccountModal()">
    <i class="fa-solid fa-plus"></i> Add New Account
  </button>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-24">
    <?php if (empty($accounts)): ?>
        <div class="card col-span-full p-40 text-center">
            <div style="font-size:48px; color:var(--bg-muted); margin-bottom:20px"><i class="fa-brands fa-whatsapp"></i></div>
            <h3>No accounts connected</h3>
            <p class="text-muted">Connect your first WhatsApp instance to start sending bulk messages and using the chatbot.</p>
            <button class="btn btn-primary mt-20" onclick="openAccountModal()">Connect Now</button>
        </div>
    <?php endif; ?>

    <?php foreach ($accounts as $acc): ?>
        <div class="account-card">
            <div class="account-header">
                <div style="display:flex; gap:12px; align-items:center">
                    <div class="account-icon">
                        <i class="fa-brands fa-whatsapp"></i>
                    </div>
                    <div class="account-info">
                        <h3><?= htmlspecialchars($acc['account_name'] ?: 'WhatsApp Account') ?></h3>
                        <p><?= htmlspecialchars($acc['phone_number'] ?: 'No number set') ?></p>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-icon btn-sm" onclick="toggleDropdown(this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                    <div class="dropdown-menu" style="right:0">
                        <a href="javascript:void(0)" onclick='openAccountModal(<?= json_encode($acc) ?>)'><i class="fa-solid fa-edit"></i> Edit</a>
                        <form method="POST" onsubmit="return confirm('Disconnect this account?')">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <input type="hidden" name="action" value="delete_account">
                            <input type="hidden" name="id" value="<?= $acc['id'] ?>">
                            <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-trash"></i> Delete</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="account-details">
                <div class="detail-row">
                    <span class="detail-label">Instance ID</span>
                    <span class="detail-value"><?= htmlspecialchars($acc['instance_id']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="badge <?= $acc['status'] === 'active' ? 'badge-success' : 'badge-muted' ?>"><?= strtoupper($acc['status']) ?></span>
                </div>
            </div>

            <div style="display:flex; gap:10px">
                <form method="POST" style="flex:1">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="id" value="<?= $acc['id'] ?>">
                    <input type="hidden" name="status" value="<?= $acc['status'] ?>">
                    <button type="submit" class="btn btn-sm btn-muted btn-full">
                        <?= $acc['status'] === 'active' ? '<i class="fa-solid fa-pause"></i> Deactivate' : '<i class="fa-solid fa-play"></i> Activate' ?>
                    </button>
                </form>
                <button class="btn btn-sm btn-outline flex-1" onclick="testConnection(<?= $acc['id'] ?>)">
                    <i class="fa-solid fa-vial"></i> Test
                </button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Add/Edit Account Modal -->
<div id="accountModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="modalTitle">Connect WhatsApp Account</h3>
      <button type="button" class="btn btn-icon" onclick="closeModal('accountModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save_account">
            <input type="hidden" name="id" id="acc_id">
            
            <div class="form-group mb-16">
                <label class="form-label">Account Label (Sender Name)</label>
                <input type="text" name="account_name" id="acc_name" class="form-control" placeholder="e.g. Sales Department" required>
            </div>

            <div class="form-group mb-16">
                <label class="form-label">WhatsApp Phone Number</label>
                <input type="text" name="phone_number" id="acc_phone" class="form-control" placeholder="e.g. 254712345678" required>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px">
                <div class="form-group">
                    <label class="form-label">Instance ID</label>
                    <input type="text" name="instance_id" id="acc_instance" class="form-control" placeholder="inst_12345" required>
                </div>
                <div class="form-group mb-16">
                    <label class="form-label">WhatsApp Token</label>
                    <input type="text" name="token" id="acc_token" class="form-control" placeholder="API Token" required>
                </div>
            </div>

            <div style="margin-top:20px; padding-top:20px; border-top:1px dashed var(--border)">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px">
                    <h4 style="margin:0; font-size:14px"><i class="fa-solid fa-brain" style="color:var(--primary)"></i> AI Smart Fallback</h4>
                    <label class="switch">
                        <input type="checkbox" name="ai_enabled" value="1" id="acc_ai_enabled">
                        <span class="slider round"></span>
                    </label>
                </div>
                
                <div id="ai_settings_fields" style="display:none">
                    <div class="form-group mb-16">
                        <label class="form-label">Google Gemini API Key</label>
                        <input type="password" name="ai_api_key" id="acc_ai_api_key" class="form-control" placeholder="Enter your Gemini API Key">
                    </div>
                    <div class="form-group mb-16">
                        <label class="form-label">Business Personality / Prompt</label>
                        <textarea name="ai_prompt" id="acc_ai_prompt" class="form-control" rows="3" placeholder="e.g. You are a professional customer support..."></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-muted flex-1" onclick="closeModal('accountModal')">Cancel</button>
            <button type="submit" class="btn btn-primary flex-1">Save Configuration</button>
        </div>
    </form>
  </div>
</div>

<script>
function openAccountModal(data = null) {
    const modal = document.getElementById('accountModal');
    const form = document.getElementById('accountForm');
    
    if (data) {
        document.getElementById('modalTitle').innerText = 'Edit WhatsApp Account';
        document.getElementById('acc_id').value = data.id;
        document.getElementById('acc_name').value = data.account_name;
        document.getElementById('acc_phone').value = data.phone_number;
        document.getElementById('acc_instance').value = data.instance_id;
        document.getElementById('acc_token').value = data.token;
        document.getElementById('acc_ai_enabled').checked = parseInt(data.ai_enabled) === 1;
        document.getElementById('acc_ai_api_key').value = data.ai_api_key || '';
        document.getElementById('acc_ai_prompt').value = data.ai_prompt || '';
    } else {
        document.getElementById('modalTitle').innerText = 'Add New WhatsApp Account';
        form.reset();
        document.getElementById('acc_id').value = 0;
    }
    
    toggleAISettings();
    modal.classList.add('active');
}

function toggleAISettings() {
    const enabled = document.getElementById('acc_ai_enabled').checked;
    document.getElementById('ai_settings_fields').style.display = enabled ? 'block' : 'none';
}

document.getElementById('acc_ai_enabled').addEventListener('change', toggleAISettings);

function testConnection(id) {
    alert('Initiating connection test for account ID: ' + id + '\nPlease wait...');
}

function toggleDropdown(btn) {
    const menu = btn.nextElementSibling;
    menu.classList.toggle('show');
    document.querySelectorAll('.dropdown-menu').forEach(m => {
        if (m !== menu) m.classList.remove('show');
    });
}

window.onclick = function(e) {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
    }
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
