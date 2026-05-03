<?php
$pageTitle = 'Connect WhatsApp Account';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Connect']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// Handle Connection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['connect_account'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } else {
        $instanceId = sanitize($_POST['instance_id']);
        $token = sanitize($_POST['token']);

        $existing = DB::queryOne("SELECT id FROM whatsapp_accounts WHERE user_id = ?", [$uid]);
        if ($existing) {
            DB::execute("UPDATE whatsapp_accounts SET instance_id = ?, token = ?, status = 'pending' WHERE user_id = ?", [$instanceId, $token, $uid]);
        } else {
            DB::execute("INSERT INTO whatsapp_accounts (user_id, instance_id, token, status) VALUES (?, ?, ?, 'pending')", [$uid, $instanceId, $token]);
        }
        flash_set('success', 'WhatsApp account details updated. Testing connection...');
    }
}

$account = DB::queryOne("SELECT * FROM whatsapp_accounts WHERE user_id = ?", [$uid]);
?>

<div class="page-header">
  <div><h1>Connect WhatsApp</h1><div class="subtitle">Link your WhatsApp Business instance to start sending messages</div></div>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Instance Configuration</h3></div>
        <form method="POST">
            <div class="card-body" style="padding:24px">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <div class="form-group mb-16">
                    <label class="form-label">Instance ID</label>
                    <input type="text" name="instance_id" class="form-control" value="<?= htmlspecialchars($account['instance_id'] ?? '') ?>" placeholder="e.g. inst_12345" required>
                </div>
                <div class="form-group mb-16">
                    <label class="form-label">Access Token</label>
                    <input type="password" name="token" class="form-control" value="<?= htmlspecialchars($account['token'] ?? '') ?>" placeholder="••••••••••••••••" required>
                </div>
            </div>
            <div class="card-footer" style="padding:16px 24px">
                <button type="submit" name="connect_account" class="btn btn-primary">Save & Connect</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Status</h3></div>
        <div class="card-body" style="padding:24px; text-align:center">
            <div style="font-size:18px; font-weight:800;"><?= ($account['status'] ?? 'Not Connected') === 'active' ? 'Account Connected' : 'Disconnected' ?></div>
            <div class="badge badge-<?= ($account['status'] ?? '') === 'active' ? 'success' : 'muted' ?> mt-10"><?= strtoupper($account['status'] ?? 'INACTIVE') ?></div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
