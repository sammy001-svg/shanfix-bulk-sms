<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('reseller');
$user = current_user();

if (isset($_POST['regenerate']) && csrf_verify()) {
    generate_user_api_keys($user['id']);
    flash_set('success', 'New API credentials generated successfully!');
    header('Location: /reseller/api.php');
    exit;
}

$pageTitle = 'API & Integration';
$breadcrumb = [['label' => 'Account'], ['label' => 'API & Integration']];
require_once __DIR__ . '/layout.php';

$u = DB::queryOne("SELECT api_client_id, api_key FROM users WHERE id = ?", [$user['id']]);
if (empty($u['api_client_id'])) {
    generate_user_api_keys($user['id']);
    $u = DB::queryOne("SELECT api_client_id, api_key FROM users WHERE id = ?", [$user['id']]);
}

$exampleSender = DB::queryValue(
    "SELECT sender_id FROM sender_ids WHERE user_id = ? AND status = 'approved' ORDER BY id LIMIT 1",
    [$user['id']]
) ?: 'YOUR_SENDER_ID';

$cid    = htmlspecialchars($u['api_client_id']);
$akey   = htmlspecialchars($u['api_key']);
$sender = htmlspecialchars($exampleSender);
$base   = rtrim(SITE_URL, '/');
$sname  = htmlspecialchars(get_setting('site_name', 'Shanfix'));
?>

<div class="page-header">
  <div>
    <h1>API &amp; Integration</h1>
    <div class="subtitle">Send SMS programmatically from any language or platform.</div>
  </div>
</div>

<!-- Credentials Card -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-key" style="color:var(--primary)"></i> Your API Credentials</h3>
  </div>
  <div class="card-body">
    <div style="background:var(--bg-muted);padding:24px;border-radius:var(--radius-md);border:1px dashed var(--border)">
      <div class="form-row">
        <div class="form-group" style="flex:1">
          <label class="form-label">Client ID</label>
          <div style="display:flex;gap:10px">
            <input type="text" class="form-control" value="<?= $cid ?>" readonly id="clientIdField">
            <button class="btn btn-secondary btn-icon" onclick="copyField('clientIdField')" title="Copy"><i class="fa-regular fa-copy"></i></button>
          </div>
        </div>
        <div class="form-group" style="flex:1">
          <label class="form-label">API Key</label>
          <div style="display:flex;gap:10px">
            <input type="password" class="form-control" value="<?= $akey ?>" readonly id="apiKeyField">
            <button class="btn btn-secondary btn-icon" onclick="toggleVis('apiKeyField',this)" title="Show/Hide"><i class="fa-regular fa-eye"></i></button>
            <button class="btn btn-secondary btn-icon" onclick="copyField('apiKeyField')" title="Copy"><i class="fa-regular fa-copy"></i></button>
          </div>
        </div>
      </div>
      <div style="margin-top:18px;display:flex;align-items:center;gap:14px;padding:14px;background:rgba(239,68,68,.05);border-radius:var(--radius-sm);border:1px solid rgba(239,68,68,.15)">
        <i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;font-size:18px;flex-shrink:0"></i>
        <div style="flex:1;font-size:13px;color:var(--text-secondary)">
          <strong style="color:#ef4444">Keep this secret.</strong> Never expose your API key in client-side code, commit it to git, or share it publicly. Regenerate immediately if compromised.
        </div>
        <form method="POST" onsubmit="return confirm('This will invalidate your current key. All existing integrations will break until updated. Continue?')">
          <?= csrf_field() ?>
          <button type="submit" name="regenerate" class="btn btn-danger btn-sm">Regenerate Key</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Quick Start -->
<div class="card" style="margin-bottom:24px" id="docs">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-rocket" style="color:var(--primary)"></i> Quick Start</h3>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
      <div style="background:var(--bg-dark);border-radius:var(--radius-md);padding:18px">
        <div style="width:28px;height:28px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#000;margin-bottom:12px">1</div>
        <div style="font-weight:600;margin-bottom:6px">Copy your credentials</div>
        <div style="font-size:13px;color:var(--text-secondary)">Use the <strong>Client ID</strong> and <strong>API Key</strong> above. Pass them as HTTP headers — not in the URL.</div>
      </div>
      <div style="background:var(--bg-dark);border-radius:var(--radius-md);padding:18px">
        <div style="width:28px;height:28px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#000;margin-bottom:12px">2</div>
        <div style="font-weight:600;margin-bottom:6px">Ensure you have a Sender ID</div>
        <div style="font-size:13px;color:var(--text-secondary)">Go to <a href="/reseller/sender-ids.php">Sender IDs</a> and request one. Without an approved Sender ID you cannot send messages.</div>
      </div>
      <div style="background:var(--bg-dark);border-radius:var(--radius-md);padding:18px">
        <div style="width:28px;height:28px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#000;margin-bottom:12px">3</div>
        <div style="font-weight:600;margin-bottom:6px">Send your first SMS</div>
        <div style="font-size:13px;color:var(--text-secondary)">POST to <code>/api/v1/sendsms.php</code> with <code>to</code>, <code>message</code>, and optionally <code>sender_id</code>. See examples below.</div>
      </div>
    </div>
  </div>
</div>

<!-- Full Reference -->
<div class="card">
  <div class="card-header" style="border-bottom:none;padding-bottom:0">
    <h3 class="card-title"><i class="fa-solid fa-terminal" style="color:var(--primary)"></i> API Reference</h3>
  </div>

  <div style="display:flex;gap:2px;border-bottom:2px solid var(--border);padding:0 24px;margin-bottom:0" id="langTabs">
    <button class="api-lang-btn active" onclick="switchLang('php',this)">PHP</button>
    <button class="api-lang-btn" onclick="switchLang('curl',this)">cURL</button>
    <button class="api-lang-btn" onclick="switchLang('python',this)">Python</button>
    <button class="api-lang-btn" onclick="switchLang('node',this)">Node.js</button>
  </div>

  <div style="padding:28px;display:flex;flex-direction:column;gap:40px">

    <!-- ── Authentication ── -->
    <section>
      <h4 class="api-section-title">Authentication</h4>
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:14px">
        Pass your credentials as HTTP headers on every request. Header auth is preferred over body auth because headers are not logged by web servers.
      </p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start">
        <div>
          <div style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;margin-bottom:8px">Required Headers</div>
          <table class="data-table" style="font-size:13px">
            <thead><tr><th>Header</th><th>Value</th></tr></thead>
            <tbody>
              <tr><td><code>X-Client-ID</code></td><td>Your Client ID</td></tr>
              <tr><td><code>X-API-Key</code></td><td>Your API Key</td></tr>
            </tbody>
          </table>
          <div class="alert alert-info" style="margin-top:12px;font-size:12px">
            <i class="fa-solid fa-circle-info"></i>
            Credentials can also be sent as POST body fields (<code>client_id</code>, <code>api_key</code>) or JSON body — but <strong>never</strong> as URL query parameters.
          </div>
        </div>
        <div>
          <div style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;margin-bottom:8px">Error Responses</div>
          <table class="data-table" style="font-size:13px">
            <thead><tr><th>HTTP</th><th>Meaning</th></tr></thead>
            <tbody>
              <tr><td><span class="badge badge-warning">401</span></td><td>Missing or invalid credentials</td></tr>
              <tr><td><span class="badge badge-danger">400</span></td><td>Bad request (validation error)</td></tr>
              <tr><td><span class="badge badge-danger">404</span></td><td>Resource not found</td></tr>
              <tr><td><span class="badge badge-danger">429</span></td><td>Rate limit exceeded</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <hr class="api-divider">

    <!-- ── Send Single SMS ── -->
    <section>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <span class="method-badge post">POST</span>
        <h4 style="margin:0;font-size:16px">Send Single SMS</h4>
        <code class="endpoint-url"><?= $base ?>/api/v1/sendsms.php</code>
      </div>
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">
        Send a single SMS to one recipient. Returns a <code>message_id</code> you can use to poll delivery status.
        Rate limit: <strong>60 requests/minute</strong>.
      </p>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
          <div class="code-label">Request</div>
          <div class="lang-panel" data-lang="php">
<pre class="code-block"><?php ?><span class="c-kw">&lt;?php</span>
<span class="c-cm">// Recommended: credentials in headers</span>
$ch = curl_init(<span class="c-str">'<?= $base ?>/api/v1/sendsms.php'</span>);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => <span class="c-kw">true</span>,
    CURLOPT_HTTPHEADER     => [
        <span class="c-str">'X-Client-ID: <?= $cid ?>'</span>,
        <span class="c-str">'X-API-Key: <?= $akey ?>'</span>,
        <span class="c-str">'Content-Type: application/json'</span>,
    ],
    CURLOPT_POSTFIELDS => json_encode([
        <span class="c-str">'to'</span>        => <span class="c-str">'254712345678'</span>,
        <span class="c-str">'message'</span>   => <span class="c-str">'Hello from <?= $sname ?>!'</span>,
        <span class="c-str">'sender_id'</span> => <span class="c-str">'<?= $sender ?>'</span>,
    ]),
]);
$res = json_decode(curl_exec($ch), <span class="c-kw">true</span>);
curl_close($ch);
print_r($res);</pre>
          </div>
          <div class="lang-panel" data-lang="curl" style="display:none">
<pre class="code-block">curl -s -X POST '<?= $base ?>/api/v1/sendsms.php' \
  -H 'X-Client-ID: <?= $cid ?>' \
  -H 'X-API-Key: <?= $akey ?>' \
  -H 'Content-Type: application/json' \
  -d '{
    "to": "254712345678",
    "message": "Hello from <?= $sname ?>!",
    "sender_id": "<?= $sender ?>"
  }'</pre>
          </div>
          <div class="lang-panel" data-lang="python" style="display:none">
<pre class="code-block"><span class="c-kw">import</span> requests

res = requests.post(
    <span class="c-str">'<?= $base ?>/api/v1/sendsms.php'</span>,
    headers={
        <span class="c-str">'X-Client-ID'</span>: <span class="c-str">'<?= $cid ?>'</span>,
        <span class="c-str">'X-API-Key'</span>:   <span class="c-str">'<?= $akey ?>'</span>,
    },
    json={
        <span class="c-str">'to'</span>:        <span class="c-str">'254712345678'</span>,
        <span class="c-str">'message'</span>:   <span class="c-str">'Hello from <?= $sname ?>!'</span>,
        <span class="c-str">'sender_id'</span>: <span class="c-str">'<?= $sender ?>'</span>,
    }
)
print(res.json())</pre>
          </div>
          <div class="lang-panel" data-lang="node" style="display:none">
<pre class="code-block"><span class="c-kw">const</span> res = <span class="c-kw">await</span> fetch(
  <span class="c-str">'<?= $base ?>/api/v1/sendsms.php'</span>,
  {
    method: <span class="c-str">'POST'</span>,
    headers: {
      <span class="c-str">'X-Client-ID'</span>:   <span class="c-str">'<?= $cid ?>'</span>,
      <span class="c-str">'X-API-Key'</span>:     <span class="c-str">'<?= $akey ?>'</span>,
      <span class="c-str">'Content-Type'</span>:  <span class="c-str">'application/json'</span>,
    },
    body: JSON.stringify({
      to:        <span class="c-str">'254712345678'</span>,
      message:   <span class="c-str">'Hello from <?= $sname ?>!'</span>,
      sender_id: <span class="c-str">'<?= $sender ?>'</span>,
    }),
  }
);
console.log(<span class="c-kw">await</span> res.json());</pre>
          </div>
        </div>
        <div>
          <div class="code-label">Response (success)</div>
<pre class="code-block">{
  <span class="c-key">"success"</span>:         <span class="c-kw">true</span>,
  <span class="c-key">"message_id"</span>:      <span class="c-num">4821</span>,
  <span class="c-key">"units_charged"</span>:   <span class="c-num">1</span>,
  <span class="c-key">"remaining_units"</span>: <span class="c-str">"48.00"</span>
}

<span class="c-cm">// Error example (400)</span>
{
  <span class="c-key">"success"</span>: <span class="c-kw">false</span>,
  <span class="c-key">"error"</span>:   <span class="c-str">"Invalid phone number: 123"</span>
}</pre>
          <div style="margin-top:14px">
            <div style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;margin-bottom:8px">Parameters</div>
            <table class="data-table" style="font-size:12px">
              <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Notes</th></tr></thead>
              <tbody>
                <tr><td><code>to</code></td><td>string</td><td><span class="badge badge-danger">Yes</span></td><td>Phone number — see formats below</td></tr>
                <tr><td><code>message</code></td><td>string</td><td><span class="badge badge-danger">Yes</span></td><td>Max 918 chars (6 SMS parts)</td></tr>
                <tr><td><code>sender_id</code></td><td>string</td><td><span class="badge badge-muted">No</span></td><td>Defaults to your first approved Sender ID</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>

    <hr class="api-divider">

    <!-- ── Bulk Send ── -->
    <section>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <span class="method-badge post">POST</span>
        <h4 style="margin:0;font-size:16px">Bulk Send SMS</h4>
        <code class="endpoint-url"><?= $base ?>/api/v1/bulksend.php</code>
      </div>
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">
        Send the same message to up to <strong>1,000 recipients</strong> in a single request. Duplicate numbers are deduplicated automatically.
        Rate limit: <strong>10 requests/minute</strong>.
      </p>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
          <div class="code-label">Request</div>
          <div class="lang-panel" data-lang="php">
<pre class="code-block"><span class="c-kw">&lt;?php</span>
$ch = curl_init(<span class="c-str">'<?= $base ?>/api/v1/bulksend.php'</span>);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => <span class="c-kw">true</span>,
    CURLOPT_HTTPHEADER     => [
        <span class="c-str">'X-Client-ID: <?= $cid ?>'</span>,
        <span class="c-str">'X-API-Key: <?= $akey ?>'</span>,
        <span class="c-str">'Content-Type: application/json'</span>,
    ],
    CURLOPT_POSTFIELDS => json_encode([
        <span class="c-str">'to'</span>        => [<span class="c-str">'254712345678'</span>, <span class="c-str">'254798765432'</span>],
        <span class="c-str">'message'</span>   => <span class="c-str">'Hello everyone from <?= $sname ?>!'</span>,
        <span class="c-str">'sender_id'</span> => <span class="c-str">'<?= $sender ?>'</span>,
    ]),
]);
$res = json_decode(curl_exec($ch), <span class="c-kw">true</span>);
curl_close($ch);</pre>
          </div>
          <div class="lang-panel" data-lang="curl" style="display:none">
<pre class="code-block">curl -s -X POST '<?= $base ?>/api/v1/bulksend.php' \
  -H 'X-Client-ID: <?= $cid ?>' \
  -H 'X-API-Key: <?= $akey ?>' \
  -H 'Content-Type: application/json' \
  -d '{
    "to": ["254712345678", "254798765432"],
    "message": "Hello everyone from <?= $sname ?>!",
    "sender_id": "<?= $sender ?>"
  }'</pre>
          </div>
          <div class="lang-panel" data-lang="python" style="display:none">
<pre class="code-block">res = requests.post(
    <span class="c-str">'<?= $base ?>/api/v1/bulksend.php'</span>,
    headers={<span class="c-str">'X-Client-ID'</span>: <span class="c-str">'<?= $cid ?>'</span>, <span class="c-str">'X-API-Key'</span>: <span class="c-str">'<?= $akey ?>'</span>},
    json={
        <span class="c-str">'to'</span>:        [<span class="c-str">'254712345678'</span>, <span class="c-str">'254798765432'</span>],
        <span class="c-str">'message'</span>:   <span class="c-str">'Hello everyone from <?= $sname ?>!'</span>,
        <span class="c-str">'sender_id'</span>: <span class="c-str">'<?= $sender ?>'</span>,
    }
)
print(res.json())</pre>
          </div>
          <div class="lang-panel" data-lang="node" style="display:none">
<pre class="code-block"><span class="c-kw">const</span> res = <span class="c-kw">await</span> fetch(<span class="c-str">'<?= $base ?>/api/v1/bulksend.php'</span>, {
  method: <span class="c-str">'POST'</span>,
  headers: {
    <span class="c-str">'X-Client-ID'</span>: <span class="c-str">'<?= $cid ?>'</span>,
    <span class="c-str">'X-API-Key'</span>:   <span class="c-str">'<?= $akey ?>'</span>,
    <span class="c-str">'Content-Type'</span>: <span class="c-str">'application/json'</span>,
  },
  body: JSON.stringify({
    to: [<span class="c-str">'254712345678'</span>, <span class="c-str">'254798765432'</span>],
    message: <span class="c-str">'Hello everyone from <?= $sname ?>!'</span>,
    sender_id: <span class="c-str">'<?= $sender ?>'</span>,
  }),
});
console.log(<span class="c-kw">await</span> res.json());</pre>
          </div>
        </div>
        <div>
          <div class="code-label">Response (success)</div>
<pre class="code-block">{
  <span class="c-key">"success"</span>:         <span class="c-kw">true</span>,
  <span class="c-key">"total_submitted"</span>: <span class="c-num">2</span>,
  <span class="c-key">"sent"</span>:            <span class="c-num">2</span>,
  <span class="c-key">"failed"</span>:          <span class="c-num">0</span>,
  <span class="c-key">"invalid_numbers"</span>: [],
  <span class="c-key">"units_charged"</span>:   <span class="c-num">2</span>,
  <span class="c-key">"remaining_units"</span>: <span class="c-str">"46.00"</span>
}</pre>
          <div class="alert alert-info" style="margin-top:12px;font-size:12px">
            <i class="fa-solid fa-circle-info"></i>
            <code>to</code> accepts a JSON array <em>or</em> a comma-separated string of numbers. Max 1,000 per request.
          </div>
        </div>
      </div>
    </section>

    <hr class="api-divider">

    <!-- ── Check Balance ── -->
    <section>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <span class="method-badge get">GET</span>
        <h4 style="margin:0;font-size:16px">Check Balance</h4>
        <code class="endpoint-url"><?= $base ?>/api/v1/balance.php</code>
      </div>
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">
        Returns your current SMS unit balance. Credentials must be in headers (not the URL).
      </p>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
          <div class="code-label">Request</div>
          <div class="lang-panel" data-lang="php">
<pre class="code-block"><span class="c-kw">&lt;?php</span>
$ch = curl_init(<span class="c-str">'<?= $base ?>/api/v1/balance.php'</span>);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => <span class="c-kw">true</span>,
    CURLOPT_HTTPHEADER     => [
        <span class="c-str">'X-Client-ID: <?= $cid ?>'</span>,
        <span class="c-str">'X-API-Key: <?= $akey ?>'</span>,
    ],
]);
$res = json_decode(curl_exec($ch), <span class="c-kw">true</span>);
echo $res[<span class="c-str">'sms_units'</span>];</pre>
          </div>
          <div class="lang-panel" data-lang="curl" style="display:none">
<pre class="code-block">curl -s '<?= $base ?>/api/v1/balance.php' \
  -H 'X-Client-ID: <?= $cid ?>' \
  -H 'X-API-Key: <?= $akey ?>'</pre>
          </div>
          <div class="lang-panel" data-lang="python" style="display:none">
<pre class="code-block">res = requests.get(
    <span class="c-str">'<?= $base ?>/api/v1/balance.php'</span>,
    headers={<span class="c-str">'X-Client-ID'</span>: <span class="c-str">'<?= $cid ?>'</span>, <span class="c-str">'X-API-Key'</span>: <span class="c-str">'<?= $akey ?>'</span>},
)
print(res.json()[<span class="c-str">'sms_units'</span>])</pre>
          </div>
          <div class="lang-panel" data-lang="node" style="display:none">
<pre class="code-block"><span class="c-kw">const</span> res = <span class="c-kw">await</span> fetch(<span class="c-str">'<?= $base ?>/api/v1/balance.php'</span>, {
  headers: {
    <span class="c-str">'X-Client-ID'</span>: <span class="c-str">'<?= $cid ?>'</span>,
    <span class="c-str">'X-API-Key'</span>:   <span class="c-str">'<?= $akey ?>'</span>,
  },
});
<span class="c-kw">const</span> { sms_units } = <span class="c-kw">await</span> res.json();
console.log(sms_units);</pre>
          </div>
        </div>
        <div>
          <div class="code-label">Response</div>
<pre class="code-block">{
  <span class="c-key">"success"</span>:     <span class="c-kw">true</span>,
  <span class="c-key">"client_name"</span>: <span class="c-str">"Jane Doe"</span>,
  <span class="c-key">"sms_units"</span>:   <span class="c-num">48</span>,
  <span class="c-key">"currency"</span>:    <span class="c-str">"KES"</span>
}</pre>
        </div>
      </div>
    </section>

    <hr class="api-divider">

    <!-- ── Message Status ── -->
    <section>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <span class="method-badge get">GET</span>
        <h4 style="margin:0;font-size:16px">Check Message Status</h4>
        <code class="endpoint-url"><?= $base ?>/api/v1/status.php</code>
      </div>
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">
        Check delivery status for one message (<code>message_id</code>) or up to 500 at once (<code>message_ids</code>, comma-separated or array).
      </p>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
          <div class="code-label">Single &amp; batch</div>
          <div class="lang-panel" data-lang="php">
<pre class="code-block"><span class="c-kw">&lt;?php</span>
<span class="c-cm">// Single</span>
$ch = curl_init(<span class="c-str">'<?= $base ?>/api/v1/status.php?message_id=4821'</span>);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => <span class="c-kw">true</span>,
    CURLOPT_HTTPHEADER => [
        <span class="c-str">'X-Client-ID: <?= $cid ?>'</span>,
        <span class="c-str">'X-API-Key: <?= $akey ?>'</span>,
    ],
]);
$res = json_decode(curl_exec($ch), <span class="c-kw">true</span>);

<span class="c-cm">// Batch (up to 500)</span>
$ch2 = curl_init(
    <span class="c-str">'<?= $base ?>/api/v1/status.php?message_ids=4821,4822,4823'</span>
);
<span class="c-cm">// same headers...</span></pre>
          </div>
          <div class="lang-panel" data-lang="curl" style="display:none">
<pre class="code-block"><span class="c-cm"># Single</span>
curl -s '<?= $base ?>/api/v1/status.php?message_id=4821' \
  -H 'X-Client-ID: <?= $cid ?>' \
  -H 'X-API-Key: <?= $akey ?>'

<span class="c-cm"># Batch (up to 500)</span>
curl -s '<?= $base ?>/api/v1/status.php?message_ids=4821,4822,4823' \
  -H 'X-Client-ID: <?= $cid ?>' \
  -H 'X-API-Key: <?= $akey ?>'</pre>
          </div>
          <div class="lang-panel" data-lang="python" style="display:none">
<pre class="code-block">hdrs = {<span class="c-str">'X-Client-ID'</span>: <span class="c-str">'<?= $cid ?>'</span>, <span class="c-str">'X-API-Key'</span>: <span class="c-str">'<?= $akey ?>'</span>}

<span class="c-cm"># Single</span>
r = requests.get(
    <span class="c-str">'<?= $base ?>/api/v1/status.php'</span>,
    params={<span class="c-str">'message_id'</span>: <span class="c-num">4821</span>},
    headers=hdrs
)

<span class="c-cm"># Batch</span>
r = requests.get(
    <span class="c-str">'<?= $base ?>/api/v1/status.php'</span>,
    params={<span class="c-str">'message_ids'</span>: <span class="c-str">'4821,4822,4823'</span>},
    headers=hdrs
)
print(r.json())</pre>
          </div>
          <div class="lang-panel" data-lang="node" style="display:none">
<pre class="code-block"><span class="c-kw">const</span> hdrs = {
  <span class="c-str">'X-Client-ID'</span>: <span class="c-str">'<?= $cid ?>'</span>,
  <span class="c-str">'X-API-Key'</span>:   <span class="c-str">'<?= $akey ?>'</span>,
};

<span class="c-cm">// Batch</span>
<span class="c-kw">const</span> r = <span class="c-kw">await</span> fetch(
  <span class="c-str">'<?= $base ?>/api/v1/status.php?message_ids=4821,4822,4823'</span>,
  { headers: hdrs }
);
console.log(<span class="c-kw">await</span> r.json());</pre>
          </div>
        </div>
        <div>
          <div class="code-label">Response — single</div>
<pre class="code-block">{
  <span class="c-key">"success"</span>:        <span class="c-kw">true</span>,
  <span class="c-key">"message_id"</span>:     <span class="c-num">4821</span>,
  <span class="c-key">"recipient"</span>:      <span class="c-str">"254712345678"</span>,
  <span class="c-key">"status"</span>:         <span class="c-str">"delivered"</span>,
  <span class="c-key">"units_charged"</span>:  <span class="c-num">1</span>,
  <span class="c-key">"gateway_msg_id"</span>: <span class="c-str">"MID-abc123"</span>,
  <span class="c-key">"created_at"</span>:     <span class="c-str">"2026-06-27 09:00:00"</span>,
  <span class="c-key">"sent_at"</span>:        <span class="c-str">"2026-06-27 09:00:03"</span>,
  <span class="c-key">"delivered_at"</span>:   <span class="c-str">"2026-06-27 09:00:08"</span>,
  <span class="c-key">"failed_reason"</span>:  <span class="c-kw">null</span>
}</pre>
          <div style="margin-top:12px">
            <div class="code-label">Response — batch</div>
<pre class="code-block">{
  <span class="c-key">"success"</span>:  <span class="c-kw">true</span>,
  <span class="c-key">"count"</span>:    <span class="c-num">3</span>,
  <span class="c-key">"messages"</span>: [
    { <span class="c-key">"message_id"</span>: <span class="c-num">4821</span>, <span class="c-key">"status"</span>: <span class="c-str">"delivered"</span>, <span class="c-cm">…</span> },
    { <span class="c-key">"message_id"</span>: <span class="c-num">4822</span>, <span class="c-key">"error"</span>: <span class="c-str">"Not found"</span> },
    { <span class="c-key">"message_id"</span>: <span class="c-num">4823</span>, <span class="c-key">"status"</span>: <span class="c-str">"sent"</span>, <span class="c-cm">…</span> }
  ]
}</pre>
          </div>
          <div class="alert alert-info" style="margin-top:10px;font-size:12px">
            <i class="fa-solid fa-circle-info"></i>
            Possible <code>status</code> values: <code>queued</code>, <code>sent</code>, <code>delivered</code>, <code>failed</code>
          </div>
        </div>
      </div>
    </section>

    <hr class="api-divider">

    <!-- ── List Messages ── -->
    <section>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <span class="method-badge get">GET</span>
        <h4 style="margin:0;font-size:16px">List Messages</h4>
        <code class="endpoint-url"><?= $base ?>/api/v1/messages.php</code>
      </div>
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px">
        Paginated list of all messages you have sent. Useful for reconciliation and building delivery reports.
      </p>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
          <div class="code-label">Request (with filters)</div>
          <div class="lang-panel" data-lang="php">
<pre class="code-block"><span class="c-kw">&lt;?php</span>
$qs = http_build_query([
    <span class="c-str">'status'</span>   => <span class="c-str">'delivered'</span>,
    <span class="c-str">'from'</span>     => <span class="c-str">'2026-06-01'</span>,
    <span class="c-str">'to'</span>       => <span class="c-str">'2026-06-30'</span>,
    <span class="c-str">'per_page'</span> => <span class="c-num">50</span>,
    <span class="c-str">'page'</span>     => <span class="c-num">1</span>,
]);
$ch = curl_init(<span class="c-str">"<?= $base ?>/api/v1/messages.php?$qs"</span>);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => <span class="c-kw">true</span>,
    CURLOPT_HTTPHEADER => [
        <span class="c-str">'X-Client-ID: <?= $cid ?>'</span>,
        <span class="c-str">'X-API-Key: <?= $akey ?>'</span>,
    ],
]);
$res = json_decode(curl_exec($ch), <span class="c-kw">true</span>);</pre>
          </div>
          <div class="lang-panel" data-lang="curl" style="display:none">
<pre class="code-block">curl -s \
  '<?= $base ?>/api/v1/messages.php?status=delivered&from=2026-06-01&per_page=50' \
  -H 'X-Client-ID: <?= $cid ?>' \
  -H 'X-API-Key: <?= $akey ?>'</pre>
          </div>
          <div class="lang-panel" data-lang="python" style="display:none">
<pre class="code-block">res = requests.get(
    <span class="c-str">'<?= $base ?>/api/v1/messages.php'</span>,
    headers={<span class="c-str">'X-Client-ID'</span>: <span class="c-str">'<?= $cid ?>'</span>, <span class="c-str">'X-API-Key'</span>: <span class="c-str">'<?= $akey ?>'</span>},
    params={
        <span class="c-str">'status'</span>:   <span class="c-str">'delivered'</span>,
        <span class="c-str">'from'</span>:     <span class="c-str">'2026-06-01'</span>,
        <span class="c-str">'per_page'</span>: <span class="c-num">50</span>,
    }
)
data = res.json()
<span class="c-kw">for</span> msg <span class="c-kw">in</span> data[<span class="c-str">'messages'</span>]:
    print(msg[<span class="c-str">'recipient'</span>], msg[<span class="c-str">'status'</span>])</pre>
          </div>
          <div class="lang-panel" data-lang="node" style="display:none">
<pre class="code-block"><span class="c-kw">const</span> params = <span class="c-kw">new</span> URLSearchParams({
  status:   <span class="c-str">'delivered'</span>,
  from:     <span class="c-str">'2026-06-01'</span>,
  per_page: <span class="c-str">'50'</span>,
});
<span class="c-kw">const</span> res = <span class="c-kw">await</span> fetch(
  <span class="c-str">`<?= $base ?>/api/v1/messages.php?${params}`</span>,
  { headers: { <span class="c-str">'X-Client-ID'</span>: <span class="c-str">'<?= $cid ?>'</span>, <span class="c-str">'X-API-Key'</span>: <span class="c-str">'<?= $akey ?>'</span> } }
);
<span class="c-kw">const</span> { messages, total, pages } = <span class="c-kw">await</span> res.json();
console.log(<span class="c-str">`Page 1 of ${pages} — ${total} total`</span>);</pre>
          </div>

          <div style="margin-top:14px">
            <div style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;margin-bottom:8px">Query Parameters (all optional)</div>
            <table class="data-table" style="font-size:12px">
              <thead><tr><th>Param</th><th>Default</th><th>Notes</th></tr></thead>
              <tbody>
                <tr><td><code>status</code></td><td>—</td><td><code>queued</code> / <code>sent</code> / <code>delivered</code> / <code>failed</code></td></tr>
                <tr><td><code>from</code></td><td>—</td><td>Start date, <code>YYYY-MM-DD</code></td></tr>
                <tr><td><code>to</code></td><td>—</td><td>End date, <code>YYYY-MM-DD</code></td></tr>
                <tr><td><code>campaign_id</code></td><td>—</td><td>Filter to a specific campaign</td></tr>
                <tr><td><code>page</code></td><td>1</td><td>Page number</td></tr>
                <tr><td><code>per_page</code></td><td>50</td><td>Max 100</td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div>
          <div class="code-label">Response</div>
<pre class="code-block">{
  <span class="c-key">"success"</span>:  <span class="c-kw">true</span>,
  <span class="c-key">"total"</span>:    <span class="c-num">342</span>,
  <span class="c-key">"page"</span>:     <span class="c-num">1</span>,
  <span class="c-key">"per_page"</span>: <span class="c-num">50</span>,
  <span class="c-key">"pages"</span>:    <span class="c-num">7</span>,
  <span class="c-key">"messages"</span>: [
    {
      <span class="c-key">"message_id"</span>:     <span class="c-num">4821</span>,
      <span class="c-key">"campaign_id"</span>:    <span class="c-kw">null</span>,
      <span class="c-key">"sender_id"</span>:      <span class="c-str">"<?= $sender ?>"</span>,
      <span class="c-key">"recipient"</span>:      <span class="c-str">"254712345678"</span>,
      <span class="c-key">"message"</span>:        <span class="c-str">"Hello from <?= $sname ?>!"</span>,
      <span class="c-key">"units_charged"</span>:  <span class="c-num">1</span>,
      <span class="c-key">"status"</span>:         <span class="c-str">"delivered"</span>,
      <span class="c-key">"gateway_msg_id"</span>: <span class="c-str">"MID-abc123"</span>,
      <span class="c-key">"created_at"</span>:     <span class="c-str">"2026-06-27 09:00:00"</span>,
      <span class="c-key">"sent_at"</span>:        <span class="c-str">"2026-06-27 09:00:03"</span>,
      <span class="c-key">"delivered_at"</span>:   <span class="c-str">"2026-06-27 09:00:08"</span>,
      <span class="c-key">"failed_reason"</span>:  <span class="c-kw">null</span>
    }
    <span class="c-cm">// … more messages</span>
  ]
}</pre>
        </div>
      </div>
    </section>

    <hr class="api-divider">

    <!-- ── Phone Number Formats ── -->
    <section>
      <h4 class="api-section-title">Phone Number Formats</h4>
      <p style="font-size:13px;color:var(--text-secondary);margin-bottom:14px">
        All of the following are accepted and normalized to <code>254XXXXXXXXX</code> internally:
      </p>
      <table class="data-table" style="font-size:13px;max-width:560px">
        <thead><tr><th>Format</th><th>Example</th><th>Accepted?</th></tr></thead>
        <tbody>
          <tr><td>International with +</td><td><code>+254712345678</code></td><td><span class="badge badge-success">Yes</span></td></tr>
          <tr><td>International without +</td><td><code>254712345678</code></td><td><span class="badge badge-success">Yes</span></td></tr>
          <tr><td>Local (0-prefix)</td><td><code>0712345678</code></td><td><span class="badge badge-success">Yes</span></td></tr>
          <tr><td>Short (9 digits)</td><td><code>712345678</code></td><td><span class="badge badge-success">Yes</span></td></tr>
          <tr><td>Other country codes</td><td><code>+1-800-555-0199</code></td><td><span class="badge badge-warning">Passed through</span></td></tr>
        </tbody>
      </table>
    </section>

    <hr class="api-divider">

    <!-- ── Rate Limits ── -->
    <section>
      <h4 class="api-section-title">Rate Limits</h4>
      <table class="data-table" style="font-size:13px;max-width:560px">
        <thead><tr><th>Endpoint</th><th>Limit</th><th>Window</th></tr></thead>
        <tbody>
          <tr><td>/api/v1/sendsms.php</td><td>60 requests</td><td>per minute</td></tr>
          <tr><td>/api/v1/bulksend.php</td><td>10 requests</td><td>per minute (each up to 1,000 recipients)</td></tr>
          <tr><td>/api/v1/balance.php</td><td>Unlimited</td><td>—</td></tr>
          <tr><td>/api/v1/status.php</td><td>Unlimited</td><td>—</td></tr>
          <tr><td>/api/v1/messages.php</td><td>Unlimited</td><td>—</td></tr>
        </tbody>
      </table>
      <p style="font-size:12px;color:var(--text-secondary);margin-top:10px">
        When the rate limit is exceeded the API returns <code>HTTP 429</code> with <code>{"success":false,"error":"Rate limit exceeded…"}</code>.
      </p>
    </section>

  </div>
</div>

<style>
.api-lang-btn {
  background: none;
  border: none;
  border-bottom: 3px solid transparent;
  padding: 13px 22px;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
  cursor: pointer;
  transition: color .15s;
  margin-bottom: -2px;
}
.api-lang-btn:hover { color: var(--primary); }
.api-lang-btn.active { color: var(--primary); border-bottom-color: var(--primary); }

.method-badge {
  display: inline-block;
  font-size: 11px;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 4px;
  letter-spacing: .3px;
}
.method-badge.post { background: var(--primary); color: #000; }
.method-badge.get  { background: #3b82f6; color: #fff; }

.endpoint-url {
  margin-left: auto;
  background: var(--bg-muted);
  padding: 4px 10px;
  border-radius: 4px;
  color: var(--primary);
  font-size: 12px;
  font-family: 'Fira Code', monospace;
}

.api-section-title { font-size: 15px; margin: 0 0 14px; }
.api-divider { border: none; border-top: 1px solid var(--border); margin: 0; }

.code-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: .5px;
  margin-bottom: 8px;
}

.code-block {
  background: #0f172a;
  color: #f8fafc;
  padding: 18px 20px;
  border-radius: 10px;
  font-size: 12.5px;
  line-height: 1.65;
  overflow-x: auto;
  margin: 0;
  font-family: 'Fira Code', 'Consolas', monospace;
}
.c-kw  { color: #7dd3fc; }
.c-str { color: #86efac; }
.c-num { color: #fbbf24; }
.c-key { color: #93c5fd; }
.c-cm  { color: #475569; }
</style>

<script>
function switchLang(lang, btn) {
  document.querySelectorAll('.api-lang-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.lang-panel').forEach(p => {
    p.style.display = p.dataset.lang === lang ? '' : 'none';
  });
}

function copyField(id) {
  const el = document.getElementById(id);
  const val = el.value;
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(val).then(() => flashCopied(el));
  } else {
    el.select();
    document.execCommand('copy');
    flashCopied(el);
  }
}
function flashCopied(el) {
  const orig = el.style.outline;
  el.style.outline = '2px solid var(--success)';
  setTimeout(() => el.style.outline = orig, 1200);
}

function toggleVis(id, btn) {
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
</script>

<?php require_once __DIR__ . '/../includes/layout-footer.php'; ?>
