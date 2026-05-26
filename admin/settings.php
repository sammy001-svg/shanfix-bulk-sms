<?php
$pageTitle = 'System Settings';
$breadcrumb = [['label'=>'Admin'],['label'=>'Settings']];
require_once __DIR__ . '/layout.php';

$settings = [];
$rows = DB::query("SELECT `key`, `value` FROM system_settings");
foreach ($rows as $r) { $settings[$r['key']] = $r['value']; }

$s = fn(string $key, string $default='') => htmlspecialchars($settings[$key] ?? $default);
?>
<div class="page-header">
  <div><h1>System Settings</h1><div class="subtitle">Configure platform-wide settings</div></div>
</div>

<div style="display:grid;grid-template-columns:200px 1fr;gap:24px;align-items:start">
  <!-- Tabs sidebar -->
  <div class="card">
    <div style="padding:8px 0">
      <?php
      $tabs = ['general'=>['icon'=>'fa-gear','label'=>'General'],'sms'=>['icon'=>'fa-paper-plane','label'=>'SMS Gateway'],'billing'=>['icon'=>'fa-credit-card','label'=>'Billing'],'email'=>['icon'=>'fa-envelope','label'=>'Email SMTP'],'security'=>['icon'=>'fa-shield-halved','label'=>'Security']];
      $activeTab = sanitize($_GET['tab']??'general');
      foreach ($tabs as $key=>$tab): ?>
        <a href="?tab=<?=$key?>" class="nav-link <?=$activeTab===$key?'active':''?>" style="color:var(--text-primary)">
          <span class="nav-icon"><i class="fa-solid <?=$tab['icon']?>"></i></span>
          <span class="nav-text"><?=$tab['label']?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Settings form -->
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-sliders" style="color:var(--primary)"></i> <?=$tabs[$activeTab]['label']?> Settings</h3></div>
    <form method="POST" action="/admin/actions/save-settings.php">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="tab" value="<?=$activeTab?>">
      <div class="card-body">
        <?php if ($activeTab==='general'): ?>
          <div class="form-group"><label class="form-label">Platform Name</label><input type="text" name="site_name" class="form-control" value="<?=$s('site_name','Shanfix Technology')?>"></div>
          <div class="form-group"><label class="form-label">Platform URL</label><input type="url" name="site_url" class="form-control" value="<?=$s('site_url','http://localhost:8080')?>"></div>
          <div class="form-group"><label class="form-label">Support Email</label><input type="email" name="support_email" class="form-control" value="<?=$s('support_email','support@bulksmspro.com')?>"></div>
          <div class="form-group"><label class="form-label">Support Phone</label><input type="text" name="support_phone" class="form-control" value="<?=$s('support_phone','+254700000000')?>"></div>
          <div class="form-group"><label class="form-label">Default Currency</label><select name="default_currency" class="form-control"><option <?=$s('default_currency','KES')==='KES'?'selected':''?>>KES</option><option <?=$s('default_currency')==='USD'?'selected':''?>>USD</option></select></div>
        <?php elseif ($activeTab==='sms'): ?>
          <div class="alert alert-info"><i class="fa-solid fa-info-circle"></i> Configure your SMS gateway API credentials. Supported: Africa's Talking, Infobip, Twilio.</div>
          <div class="form-group"><label class="form-label">SMS Gateway</label><select name="sms_gateway" class="form-control">
            <option value="onfon" <?=$s('sms_gateway')==='onfon'?'selected':''?>>Onfon Media</option>
            <option value="at" <?=$s('sms_gateway')==='at'?'selected':''?>>Africa's Talking</option>
            <option value="infobip" <?=$s('sms_gateway')==='infobip'?'selected':''?>>Infobip</option>
            <option value="twilio" <?=$s('sms_gateway')==='twilio'?'selected':''?>>Twilio</option>
          </select></div>
          <div id="onfon_fields" style="display: <?=$s('sms_gateway')==='onfon'?'block':'none'?>">
            <div class="form-group"><label class="form-label">Onfon API Key</label><input type="text" name="onfon_api_key" class="form-control" value="<?=$s('onfon_api_key')?>"></div>
            <div class="form-group"><label class="form-label">Onfon User ID / Client ID</label><input type="text" name="onfon_user_id" class="form-control" value="<?=$s('onfon_user_id')?>"></div>
            <div class="form-group"><label class="form-label">Onfon Access Key (Optional)</label><input type="text" name="onfon_access_key" class="form-control" value="<?=$s('onfon_access_key')?>"></div>
            <div class="form-group">
              <label class="form-label">Batch Delay <span style="font-size:11px;color:var(--text-muted)">(ms between API calls)</span></label>
              <input type="number" name="onfon_batch_delay_ms" class="form-control" min="0" max="5000" value="<?=$s('onfon_batch_delay_ms','100')?>">
              <div style="font-size:11px;color:var(--text-secondary);margin-top:4px">Pause between each batch of 20 messages sent to Onfon. Increase (e.g. 300–500 ms) if you see high failure rates from Onfon throttling. Default: 100 ms.</div>
            </div>
          </div>
          <div id="other_sms_fields" style="display: <?=$s('sms_gateway')!=='onfon'?'block':'none'?>">
            <div class="form-group"><label class="form-label">API Key / Username</label><input type="text" name="sms_api_key" class="form-control" value="<?=$s('sms_api_key')?>"></div>
            <div class="form-group"><label class="form-label">API Secret / Password</label><input type="password" name="sms_api_secret" class="form-control" placeholder="Leave blank to keep current"></div>
          </div>
          <div class="form-group"><label class="form-label">Short Code / Sender ID</label><input type="text" name="sms_shortcode" class="form-control" value="<?=$s('sms_shortcode')?>"></div>
        <?php elseif ($activeTab==='billing'): ?>
          <div class="alert alert-info"><i class="fa-solid fa-credit-card"></i> Configure your Payhero Kenya M-Pesa STK Push credentials.</div>
          <div class="form-group"><label class="form-label">Payhero API Username</label><input type="text" name="payhero_api_username" class="form-control" value="<?=$s('payhero_api_username')?>" placeholder="Found in Payhero Developer section"></div>
          <div class="form-group"><label class="form-label">Payhero API Password</label><input type="password" name="payhero_api_password" class="form-control" placeholder="Leave blank to keep current"></div>
          <div class="form-group"><label class="form-label">Payhero Channel ID</label><input type="text" name="payhero_channel_id" class="form-control" value="<?=$s('payhero_channel_id')?>" placeholder="Found under Payment Channels"></div>
          <hr style="margin:24px 0;border-color:var(--border)">
          <div class="form-group"><label class="form-label">Default Unit Price (KES)</label><input type="number" step="0.0001" name="unit_price" class="form-control" value="<?=$s('unit_price','0.50')?>"></div>
        <?php elseif ($activeTab==='email'): ?>
          <div class="alert alert-info" style="margin-bottom:20px"><i class="fa-solid fa-info-circle"></i> Used for password reset emails. Works with Gmail (App Password), Mailgun, or any SMTP provider.</div>
          <div class="form-group"><label class="form-label">SMTP Host</label><input type="text" name="smtp_host" class="form-control" value="<?=$s('smtp_host','smtp.gmail.com')?>"></div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">SMTP Port</label><input type="number" name="smtp_port" class="form-control" value="<?=$s('smtp_port','587')?>"></div>
            <div class="form-group"><label class="form-label">Encryption</label><select name="smtp_encryption" class="form-control"><option value="tls" <?=$s('smtp_encryption','tls')==='tls'?'selected':''?>>TLS (port 587)</option><option value="ssl" <?=$s('smtp_encryption')==='ssl'?'selected':''?>>SSL (port 465)</option></select></div>
          </div>
          <div class="form-group"><label class="form-label">SMTP Username / From Address</label><input type="text" name="smtp_user" class="form-control" value="<?=$s('smtp_user')?>" placeholder="you@gmail.com"></div>
          <div class="form-group"><label class="form-label">SMTP Password</label><input type="password" name="smtp_pass" class="form-control" placeholder="Leave blank to keep current"></div>
          <div class="form-group"><label class="form-label">From Name <span style="font-size:11px;color:var(--text-muted)">(defaults to platform name)</span></label><input type="text" name="smtp_from_name" class="form-control" value="<?=$s('smtp_from_name')?>" placeholder="<?=$s('site_name','Shanfix Technology')?>"></div>
          <hr style="margin:24px 0;border-color:var(--border)">
          <div class="form-group">
            <label class="form-label">Low Balance Alert Threshold <span style="font-size:11px;color:var(--text-muted)">(units — set 0 to disable)</span></label>
            <input type="number" name="low_balance_threshold" class="form-control" min="0" value="<?=$s('low_balance_threshold','0')?>" placeholder="e.g. 100">
            <div style="font-size:11px;color:var(--text-secondary);margin-top:4px">Users receive an email alert when their balance drops below this number after a campaign finishes. Alerts are rate-limited to once per 24 hours per user.</div>
          </div>
          <hr style="margin:24px 0;border-color:var(--border)">
          <div class="form-group">
            <label class="form-label">SMS Delivery Report (DLR) Webhook URL</label>
            <div class="input-group">
              <input type="text" class="form-control" value="<?= htmlspecialchars(rtrim($settings['site_url']??'', '/') . '/webhooks/sms-dlr.php') ?>" readonly onclick="this.select()">
              <div class="input-group-text" style="cursor:pointer" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)" title="Copy"><i class="fa-regular fa-copy"></i></div>
            </div>
            <div style="font-size:11px;color:var(--text-secondary);margin-top:4px">Paste this URL into Onfon Media → Account → SMS Settings → Delivery Report URL to receive real-time delivery confirmations.</div>
          </div>
        <?php elseif ($activeTab==='security'): ?>
          <div class="form-group"><label class="form-label">Session Timeout (seconds)</label><input type="number" name="session_timeout" class="form-control" value="<?=$s('session_timeout','3600')?>"></div>
          <div class="form-group"><label class="form-label">Max Login Attempts</label><input type="number" name="max_login_attempts" class="form-control" value="<?=$s('max_login_attempts','5')?>"></div>
          <div class="form-group">
            <label class="form-label">Max Concurrent Campaigns</label>
            <input type="number" name="max_concurrent_campaigns" class="form-control" min="1" max="50" value="<?=$s('max_concurrent_campaigns','5')?>">
            <div style="font-size:11px;color:var(--text-secondary);margin-top:4px">How many campaigns may send at the same time across all users. Rule of thumb: 1 per 256 MB available RAM. Default: 5.</div>
          </div>
          <div class="form-group"><label class="form-label">Registration</label><select name="allow_registration" class="form-control"><option value="1" <?=$s('allow_registration','0')==='1'?'selected':''?>>Open</option><option value="0" <?=$s('allow_registration','0')==='0'?'selected':''?>>Invite Only</option></select></div>
          <div class="form-group"><label class="form-label">Maintenance Mode</label><select name="maintenance_mode" class="form-control"><option value="0">Off</option><option value="1" <?=$s('maintenance_mode')==='1'?'selected':''?>>On</option></select></div>
        <?php endif; ?>
      </div>
      <div class="card-footer" style="padding:16px 20px">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Settings</button>
      </div>
    </form>
  </div>
</div>
<script>
document.querySelector('select[name="sms_gateway"]').addEventListener('change', function() {
  const isOnfon = this.value === 'onfon';
  document.getElementById('onfon_fields').style.display = isOnfon ? 'block' : 'none';
  document.getElementById('other_sms_fields').style.display = isOnfon ? 'none' : 'block';
});
</script>
<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
