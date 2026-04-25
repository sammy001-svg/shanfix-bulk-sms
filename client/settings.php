<?php
$pageTitle = 'Settings';
$breadcrumb = [['label'=>'Client'],['label'=>'Settings']];
require_once __DIR__ . '/layout.php';
?>

<div class="page-header">
  <div><h1>Account Settings</h1><div class="subtitle">Customize your platform experience</div></div>
</div>

<div class="card" style="max-width:800px">
  <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-bell" style="color:var(--primary)"></i> Notification Preferences</h3></div>
  <form method="POST" action="/client/actions/update-settings.php">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="card-body">
      <div class="form-group" style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)">
        <div>
          <div style="font-weight:600">Email Notifications</div>
          <div style="font-size:12px;color:var(--text-secondary)">Receive weekly reports and transaction alerts</div>
        </div>
        <label class="switch"><input type="checkbox" name="notif_email" checked><span class="slider round"></span></label>
      </div>
      <div class="form-group" style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)">
        <div>
          <div style="font-weight:600">SMS Alerts</div>
          <div style="font-size:12px;color:var(--text-secondary)">Get notified via SMS when units are low</div>
        </div>
        <label class="switch"><input type="checkbox" name="notif_sms" checked><span class="slider round"></span></label>
      </div>
      <div class="form-group" style="display:flex;align-items:center;justify-content:space-between;padding:12px 0">
        <div>
          <div style="font-weight:600">Login Security Alerts</div>
          <div style="font-size:12px;color:var(--text-secondary)">Alert me of login attempts from new devices</div>
        </div>
        <label class="switch"><input type="checkbox" name="notif_security"><span class="slider round"></span></label>
      </div>
    </div>
    <div class="card-footer"><button type="submit" class="btn btn-primary">Save Settings</button></div>
  </form>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
