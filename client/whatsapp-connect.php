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

        // Check if exists
        $existing = DB::queryOne("SELECT id FROM whatsapp_accounts WHERE user_id = ?", [$uid]);
        if ($existing) {
            DB::execute("UPDATE whatsapp_accounts SET instance_id = ?, token = ?, status = 'pending' WHERE user_id = ?", [$instanceId, $token, $uid]);
        } else {
            DB::execute("INSERT INTO whatsapp_accounts (user_id, instance_id, token, status) VALUES (?, ?, ?, 'pending')", [$uid, $instanceId, $token]);
        }
        flash_set('success', 'WhatsApp account details updated. Testing connection...');
    }
}

// Fetch current account
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
                    <div class="form-hint">Your unique WhatsApp instance identifier.</div>
                </div>

                <div class="form-group mb-16">
                    <label class="form-label">Access Token</label>
                    <input type="password" name="token" class="form-control" value="<?= htmlspecialchars($account['token'] ?? '') ?>" placeholder="••••••••••••••••" required>
                    <div class="form-hint">Your secure API access token.</div>
                </div>

                <div class="alert alert-info" style="font-size:12px; margin:0">
                    <i class="fa-solid fa-circle-info"></i> Make sure your WhatsApp instance is active and has a valid subscription with the provider.
                </div>
            </div>
            <div class="card-footer" style="padding:16px 24px">
                <button type="submit" name="connect_account" class="btn btn-primary">Save & Connect</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Status Overview</h3></div>
        <div class="card-body" style="padding:24px; text-align:center">
            <div style="width:80px; height:80px; background:var(--bg-muted); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; color:<?= ($account['status'] ?? '') === 'active' ? 'var(--success)' : 'var(--text-muted)' ?>">
                <i class="fa-brands fa-whatsapp" style="font-size:40px"></i>
            </div>
            <div style="font-size:18px; font-weight:800; margin-bottom:5px">
                <?= ($account['status'] ?? 'Not Connected') === 'active' ? 'Account Connected' : 'Disconnected' ?>
            </div>
            <div class="text-muted" style="font-size:12px; margin-bottom:20px">
                <?= ($account['status'] ?? '') === 'active' ? 'Ready to send messages' : 'Please configure your instance details' ?>
            </div>
            
            <?php if (($account['status'] ?? '') === 'active'): ?>
                <div class="badge badge-success">ACTIVE</div>
            <?php else: ?>
                <div class="badge badge-muted">INACTIVE</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card mt-24">
    <div class="card-header"><h3 class="card-title">Setup Instructions</h3></div>
    <div class="card-body">
        <div style="display:flex; flex-direction:column; gap:15px">
            <div style="display:flex; gap:15px">
                <div style="width:24px; height:24px; background:var(--primary); color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0">1</div>
                <div>Get your <strong>Instance ID</strong> and <strong>Token</strong> from your WhatsApp API provider dashboard.</div>
            </div>
            <div style="display:flex; gap:15px">
                <div style="width:24px; height:24px; background:var(--primary); color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0">2</div>
                <div>Enter the credentials in the form above and click <strong>Save & Connect</strong>.</div>
            </div>
            <div style="display:flex; gap:15px">
                <div style="width:24px; height:24px; background:var(--primary); color:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0">3</div>
                <div>Ensure your phone is linked to the instance via a <strong>QR Code</strong> (if required by your provider).</div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
