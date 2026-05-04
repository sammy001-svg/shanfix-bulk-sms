<?php
$pageTitle = 'USSD API Callbacks';
$breadcrumb = [['label'=>'USSD'],['label'=>'API Callbacks']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_callback'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } else {
        $codeId = (int)$_POST['code_id'];
        $url = sanitize($_POST['callback_url']);

        // Verify ownership
        $check = DB::queryOne("SELECT id FROM ussd_codes WHERE id = ? AND user_id = ?", [$codeId, $uid]);
        if ($check) {
            DB::execute("UPDATE ussd_codes SET callback_url = ? WHERE id = ?", [$url, $codeId]);
            flash_set('success', 'Callback URL updated successfully.');
        } else {
            flash_set('danger', 'Unauthorized action.');
        }
    }
}

// Fetch user's approved codes
$codes = DB::query("SELECT * FROM ussd_codes WHERE user_id = ? AND status = 'approved' ORDER BY requested_code ASC", [$uid]);
?>

<div class="page-header">
  <div><h1>API Callbacks</h1><div class="subtitle">Configure where we should forward USSD requests for your codes</div></div>
</div>

<div class="alert alert-info mb-24">
    <div style="display:flex; gap:15px; align-items:center">
        <i class="fa-solid fa-circle-info" style="font-size:24px"></i>
        <div>
            <strong>Integration Tip:</strong> When a user dials your USSD code, we will send an HTTP POST request to your callback URL with session details. 
            Your server should respond with the text you want to display to the user.
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>USSD Code</th>
                        <th>Type</th>
                        <th>Current Callback URL</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($codes)): ?>
                        <tr><td colspan="4" class="text-center" style="padding:40px; color:var(--text-muted)">You don't have any approved USSD codes yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($codes as $c): ?>
                        <tr>
                            <td><span class="badge badge-info" style="font-size:13px; padding:6px 12px"><?= htmlspecialchars($c['requested_code']) ?></span></td>
                            <td><?= ucfirst($c['type']) ?></td>
                            <td>
                                <code style="background:var(--bg-muted); padding:4px 8px; border-radius:4px; font-size:12px; color:var(--primary)">
                                    <?= $c['callback_url'] ?: '<i>No URL configured</i>' ?>
                                </code>
                            </td>
                            <td style="text-align:right">
                                <button class="btn btn-sm btn-primary" onclick="editCallback(<?= $c['id'] ?>, '<?= addslashes($c['requested_code']) ?>', '<?= addslashes($c['callback_url'] ?? '') ?>')">
                                    <i class="fa-solid fa-edit"></i> Configure
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="callbackModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(4px)">
  <div class="card" style="width:100%; max-width:500px; margin:20px; border:none; box-shadow: var(--shadow-lg)">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
      <h3 class="card-title">Configure Callback: <span id="modalCode"></span></h3>
      <button type="button" class="btn btn-icon" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
        <div class="card-body" style="padding:24px">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="code_id" id="modalCodeId">
            
            <div class="form-group mb-16">
                <label class="form-label">Callback URL (HTTPS recommended)</label>
                <input type="url" name="callback_url" id="modalUrl" class="form-control" placeholder="https://your-api.com/ussd" required>
                <div class="form-hint">Must be a publicly accessible endpoint that accepts POST requests.</div>
            </div>

            <div style="background:var(--bg-muted); padding:15px; border-radius:8px; font-size:11px; color:var(--text-muted)">
                <strong>Payload Format (JSON):</strong><br>
                <code>{ "sessionId": "...", "phoneNumber": "...", "text": "...", "serviceCode": "..." }</code>
            </div>
        </div>
        <div class="card-footer" style="padding:16px 24px; display:flex; gap:10px">
            <button type="button" class="btn btn-muted flex-1" onclick="closeModal()">Cancel</button>
            <button type="submit" name="update_callback" class="btn btn-primary flex-1">Save Changes</button>
        </div>
    </form>
  </div>
</div>

<script>
function editCallback(id, code, url) {
    document.getElementById('modalCodeId').value = id;
    document.getElementById('modalCode').innerText = code;
    document.getElementById('modalUrl').value = url;
    openModal('callbackModal');
}
</script>
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
