<?php
/**
 * Client: API Documentation & Integration - Shanfix Technology
 */
require_once __DIR__ . '/../includes/auth.php';
require_role('client');
$user = current_user();

// Handle Regeneration
if (isset($_POST['regenerate'])) {
    if (csrf_verify()) {
        generate_user_api_keys($user['id']);
        flash_set('success', 'New API credentials generated successfully!');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

$pageTitle = 'API & Integration';
$breadcrumb = [['label' => 'Account'], ['label' => 'API & Integration']];
require_once __DIR__ . '/layout.php';

$u = DB::queryOne("SELECT api_client_id, api_key FROM users WHERE id = ?", [$user['id']]);
?>


<div class="page-header">
    <div>
        <h1>API & Integration</h1>
        <div class="subtitle">Integrate your applications with our robust SMS gateway.</div>
    </div>
    <a href="#docs" class="btn btn-secondary">
        <i class="fa-solid fa-book"></i> View Docs
    </a>
</div>

<div class="grid" style="--grid-cols: 1; gap: 24px;">
    <!-- Credentials Card -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-key" style="color:var(--primary)"></i> Your API Credentials</h3>
        </div>
        <div class="card-body">
            <div style="background: var(--bg-muted); padding: 24px; border-radius: var(--radius-md); border: 1px dashed var(--border);">
                <div class="form-row">
                    <div class="form-group" style="flex:1">
                        <label class="form-label">Client ID</label>
                        <div style="display:flex; gap:10px">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($u['api_client_id']) ?>" readonly id="clientIdField">
                            <button class="btn btn-secondary" onclick="copyToClipboard('clientIdField')" title="Copy">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label class="form-label">API Key</label>
                        <div style="display:flex; gap:10px">
                            <input type="password" class="form-control" value="<?= htmlspecialchars($u['api_key']) ?>" readonly id="apiKeyField">
                            <button class="btn btn-secondary" onclick="toggleVisibility('apiKeyField', this)" title="Show/Hide">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                            <button class="btn btn-secondary" onclick="copyToClipboard('apiKeyField')" title="Copy">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top:20px; display:flex; align-items:center; gap:15px; padding:15px; background:rgba(239, 68, 68, 0.05); border-radius:var(--radius-sm); border:1px solid rgba(239, 68, 68, 0.1)">
                    <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444; font-size:20px"></i>
                    <div style="flex:1; font-size:13px; color:var(--text-secondary)">
                        <strong style="color:#ef4444">Security Warning:</strong> Never share your API key or commit it to version control. If compromised, regenerate it immediately.
                    </div>
                    <form method="POST" onsubmit="return confirm('Are you sure? Existing integrations using the old key will stop working.')">
                        <?= csrf_field() ?>
                        <button type="submit" name="regenerate" class="btn btn-danger btn-sm">Regenerate Key</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Documentation Section -->
    <div class="card" id="docs">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-terminal" style="color:var(--primary)"></i> Developer Documentation</h3>
        </div>
        <div class="card-body" style="padding:0">
            <!-- Documentation Tabs -->
            <div style="display:flex; border-bottom:1px solid var(--border)">
                <button class="tab-btn active" onclick="switchApiTab(this, 'php_example')" style="padding:15px 25px; background:none; border:none; color:var(--text-secondary); cursor:pointer; font-weight:600; font-size:14px">PHP</button>
                <button class="tab-btn" onclick="switchApiTab(this, 'curl_example')" style="padding:15px 25px; background:none; border:none; color:var(--text-secondary); cursor:pointer; font-weight:600; font-size:14px">cURL</button>
                <button class="tab-btn" onclick="switchApiTab(this, 'python_example')" style="padding:15px 25px; background:none; border:none; color:var(--text-secondary); cursor:pointer; font-weight:600; font-size:14px">Python</button>
            </div>

            <div style="padding:30px">
                <!-- Send SMS Section -->
                <div class="api-section">
                    <h4 style="margin-bottom:15px; display:flex; align-items:center; gap:10px">
                        <span style="background:var(--primary); color:#000; font-size:11px; padding:2px 8px; border-radius:4px; font-weight:800">POST</span>
                        Send SMS
                        <code style="background:var(--bg-muted); padding:5px 10px; border-radius:4px; color:var(--primary); font-size:12px; margin-left:auto"><?= SITE_URL ?>/api/v1/sendsms.php</code>
                    </h4>

                
                <div id="php_example" class="api-tab-panel active">
<pre style="background:#0f172a; color:#f8fafc; padding:20px; border-radius:12px; font-size:13px; line-height:1.6; overflow-x:auto">
<span style="color:#94a3b8">&lt;?php</span>
<span style="color:#64748b">// Shanfix Technology SMS API Example</span>
$url = <span style="color:#00c896">"<?= SITE_URL ?>/api/v1/sendsms.php"</span>;
$data = [
    <span style="color:#e2e8f0">'client_id'</span> => <span style="color:#00c896">'<?= $u['api_client_id'] ?>'</span>,
    <span style="color:#e2e8f0">'api_key'</span>   => <span style="color:#00c896">'<?= $u['api_key'] ?>'</span>,
    <span style="color:#e2e8f0">'to'</span>        => <span style="color:#00c896">'2547XXXXXXXX'</span>,
    <span style="color:#e2e8f0">'message'</span>   => <span style="color:#00c896">'Hello from Shanfix API!'</span>,
    <span style="color:#e2e8f0">'sender_id'</span> => <span style="color:#00c896">'SHANFIX'</span>
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, <span style="color:#00c896">true</span>);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
$response = curl_exec($ch);
curl_close($ch);

echo $response;
</pre>
                </div>

                <div id="curl_example" class="api-tab-panel" style="display:none">
<pre style="background:#0f172a; color:#f8fafc; padding:20px; border-radius:12px; font-size:13px; line-height:1.6; overflow-x:auto">
curl -X POST <?= SITE_URL ?>/api/v1/sendsms.php \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": "<?= $u['api_client_id'] ?>",
    "api_key": "<?= $u['api_key'] ?>",
    "to": "2547XXXXXXXX",
    "message": "Hello from Shanfix API!",
    "sender_id": "SHANFIX"
  }'
</pre>
                </div>

                <div id="python_example" class="api-tab-panel" style="display:none">
<pre style="background:#0f172a; color:#f8fafc; padding:20px; border-radius:12px; font-size:13px; line-height:1.6; overflow-x:auto">
<span style="color:#94a3b8">import</span> requests

url = <span style="color:#00c896">"<?= SITE_URL ?>/api/v1/sendsms.php"</span>
payload = {
    <span style="color:#00c896">"client_id"</span>: <span style="color:#00c896">"<?= $u['api_client_id'] ?>"</span>,
    <span style="color:#00c896">"api_key"</span>: <span style="color:#00c896">"<?= $u['api_key'] ?>"</span>,
    <span style="color:#00c896">"to"</span>: <span style="color:#00c896">"2547XXXXXXXX"</span>,
    <span style="color:#00c896">"message"</span>: <span style="color:#00c896">"Hello from Shanfix API!"</span>,
    <span style="color:#00c896">"sender_id"</span>: <span style="color:#00c896">"SHANFIX"</span>
}

response = requests.post(url, json=payload)
print(response.json())
</pre>
                </div>

                <hr style="border:none; border-top:1px solid var(--border); margin:40px 0">

                <!-- Balance Check Section -->
                <div class="api-section">
                    <h4 style="margin-bottom:15px; display:flex; align-items:center; gap:10px">
                        <span style="background:#3b82f6; color:#fff; font-size:11px; padding:2px 8px; border-radius:4px; font-weight:800">GET</span>
                        Check Balance
                        <code style="background:var(--bg-muted); padding:5px 10px; border-radius:4px; color:var(--primary); font-size:12px; margin-left:auto"><?= SITE_URL ?>/api/v1/balance.php</code>
                    </h4>
                    <p style="font-size:13px; color:var(--text-secondary); margin-bottom:15px">Retrieve your current account balance and remaining SMS units.</p>
                    <pre style="background:#0f172a; color:#f8fafc; padding:20px; border-radius:12px; font-size:13px; line-height:1.6; overflow-x:auto">
curl -X GET <?= SITE_URL ?>/api/v1/balance.php \
  -H "X-Client-ID: <?= $u['api_client_id'] ?>" \
  -H "X-API-Key: <?= $u['api_key'] ?>"
</pre>
                </div>

                <hr style="border:none; border-top:1px solid var(--border); margin:40px 0">

                <!-- Message Status Section -->
                <div class="api-section">
                    <h4 style="margin-bottom:15px; display:flex; align-items:center; gap:10px">
                        <span style="background:#3b82f6; color:#fff; font-size:11px; padding:2px 8px; border-radius:4px; font-weight:800">GET</span>
                        Check Status
                        <code style="background:var(--bg-muted); padding:5px 10px; border-radius:4px; color:var(--primary); font-size:12px; margin-left:auto"><?= SITE_URL ?>/api/v1/status.php</code>
                    </h4>
                    <p style="font-size:13px; color:var(--text-secondary); margin-bottom:15px">Check the delivery status of a specific message using its ID.</p>
                    <pre style="background:#0f172a; color:#f8fafc; padding:20px; border-radius:12px; font-size:13px; line-height:1.6; overflow-x:auto">
curl -X GET "<?= SITE_URL ?>/api/v1/status.php?message_id=MESSAGE_ID" \
  -H "X-Client-ID: <?= $u['api_client_id'] ?>" \
  -H "X-API-Key: <?= $u['api_key'] ?>"
</pre>
                </div>


                <div style="margin-top:40px">
                    <h4 style="margin-bottom:20px">Parameter Reference</h4>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Parameter</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>client_id</code></td>
                                <td>String</td>
                                <td>Yes</td>
                                <td>Your unique application Client ID.</td>
                            </tr>
                            <tr>
                                <td><code>api_key</code></td>
                                <td>String</td>
                                <td>Yes</td>
                                <td>Your secret API Key.</td>
                            </tr>
                            <tr>
                                <td><code>to</code></td>
                                <td>String</td>
                                <td>Yes</td>
                                <td>Recipient phone number (International format, e.g., 254...).</td>
                            </tr>
                            <tr>
                                <td><code>message</code></td>
                                <td>String</td>
                                <td>Yes</td>
                                <td>The text content of your message.</td>
                            </tr>
                            <tr>
                                <td><code>sender_id</code></td>
                                <td>String</td>
                                <td>No</td>
                                <td>Your approved Sender ID (Default: SHANFIX).</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(id) {
    const el = document.getElementById(id);
    el.select();
    document.execCommand('copy');
    alert('Copied to clipboard!');
}

function toggleVisibility(id, btn) {
    const el = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (el.type === 'password') {
        el.type = 'text';
        icon.className = 'fa-regular fa-eye-slash';
    } else {
        el.type = 'password';
        icon.className = 'fa-regular fa-eye';
    }
}

function switchApiTab(btn, panelId) {
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('active');
        b.style.color = 'var(--text-secondary)';
        b.style.borderBottom = 'none';
    });
    document.querySelectorAll('.api-tab-panel').forEach(p => p.style.display = 'none');
    
    btn.classList.add('active');
    btn.style.color = 'var(--primary)';
    btn.style.borderBottom = '2px solid var(--primary)';
    
    document.getElementById(panelId).style.display = 'block';
}

// Initial tab state
document.addEventListener('DOMContentLoaded', () => {
    const activeTab = document.querySelector('.tab-btn.active');
    activeTab.style.color = 'var(--primary)';
    activeTab.style.borderBottom = '2px solid var(--primary)';
});
</script>

<style>
.tab-btn:hover { color: var(--primary) !important; }
.tab-btn.active { color: var(--primary) !important; border-bottom: 2px solid var(--primary) !important; }
code { font-family: 'Fira Code', monospace; font-size: 13px; }
pre { margin: 0; }
</style>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
