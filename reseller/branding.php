<?php
$pageTitle = 'Site Branding';
$breadcrumb = [['label'=>'Reseller'],['label'=>'Site Branding']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];
$settings = DB::queryOne("SELECT * FROM reseller_settings WHERE reseller_id = ?", [$uid]);

if (!$settings) {
    // Initialize default settings if none exist
    DB::execute("INSERT IGNORE INTO reseller_settings (reseller_id, system_name) VALUES (?, ?)", [$uid, Branding::get('system_name')]);
    $settings = DB::queryOne("SELECT * FROM reseller_settings WHERE reseller_id = ?", [$uid]);
}
?>

<div class="page-header">
  <div>
    <h1>Site Branding</h1>
    <div class="subtitle">Customize your platform's appearance for your clients.</div>
  </div>
</div>

<div class="row">
  <div class="col-md-8">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-palette" style="color:var(--primary)"></i> Visual Settings</h3>
      </div>
      <form method="POST" action="/reseller/actions/save-branding.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">System Name <span class="required">*</span></label>
            <input type="text" name="system_name" class="form-control" value="<?= htmlspecialchars($settings['system_name'] ?? '') ?>" placeholder="e.g. My SMS Portal" required>
            <div class="form-hint">This name will appear in page titles and navigation.</div>
          </div>
          
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Primary Brand Color</label>
                <div style="display:flex; gap:10px">
                  <input type="color" name="primary_color" class="form-control" value="<?= $settings['primary_color'] ?? '#00c896' ?>" style="width:60px; padding:4px; height:45px">
                  <input type="text" class="form-control" value="<?= $settings['primary_color'] ?? '#00c896' ?>" readonly style="background:var(--bg-muted); font-size:12px">
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">Sidebar Background</label>
                <div style="display:flex; gap:10px">
                  <input type="color" name="sidebar_color" class="form-control" value="<?= $settings['sidebar_color'] ?? '#0e1726' ?>" style="width:60px; padding:4px; height:45px">
                  <input type="text" class="form-control" value="<?= $settings['sidebar_color'] ?? '#0e1726' ?>" readonly style="background:var(--bg-muted); font-size:12px">
                </div>
              </div>
            </div>
            <div class="col-md-4">
               <div class="form-group">
                <label class="form-label">System Logo</label>
                <input type="file" name="system_logo" class="form-control" accept=".png,.jpg,.jpeg">
                <div class="form-hint">Recommended size: 200x50px (PNG with transparency).</div>
                <?php if (!empty($settings['system_logo'])): ?>
                  <div style="margin-top:10px; padding:10px; background:var(--bg-muted); border-radius:var(--radius-sm); display:inline-block">
                    <img src="<?= $settings['system_logo'] ?>" style="max-height:40px">
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="divider"></div>
          <h4 style="margin:20px 0 15px; font-size:15px"><i class="fa-solid fa-envelope" style="color:var(--primary)"></i> Support Contact Info</h4>
          
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Support Email</label>
                <input type="email" name="support_email" class="form-control" value="<?= htmlspecialchars($settings['support_email'] ?? '') ?>" placeholder="support@yourdomain.com">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Support Phone</label>
                <input type="text" name="support_phone" class="form-control" value="<?= htmlspecialchars($settings['support_phone'] ?? '') ?>" placeholder="+254...">
              </div>
            </div>
          </div>

          <div class="divider"></div>
          <h4 style="margin:20px 0 15px; font-size:15px"><i class="fa-solid fa-server" style="color:var(--primary)"></i> SMTP / Email Server</h4>
          <div class="alert alert-info" style="font-size:12px">
            Configure your own SMTP server to send notifications to your clients from your own domain.
          </div>
          
          <div class="row">
            <div class="col-md-8">
              <div class="form-group">
                <label class="form-label">SMTP Host</label>
                <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label class="form-label">SMTP Port</label>
                <input type="text" name="smtp_port" class="form-control" value="<?= htmlspecialchars($settings['smtp_port'] ?? '') ?>" placeholder="587">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">SMTP Username</label>
                <input type="text" name="smtp_user" class="form-control" value="<?= htmlspecialchars($settings['smtp_user'] ?? '') ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">SMTP Password</label>
                <input type="password" name="smtp_pass" class="form-control" value="<?= !empty($settings['smtp_pass']) ? '********' : '' ?>" placeholder="Leave blank to keep current">
              </div>
            </div>
          </div>
        </div>
        <div class="card-footer" style="text-align:right">
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Branding Settings</button>
        </div>
      </form>
    </div>

    <div class="card mt-24">
      <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-credit-card" style="color:var(--primary)"></i> Billing & Pricing</h3></div>
      <form method="POST" action="/reseller/actions/save-branding.php">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="billing">
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="form-label">Default Unit Price (KES)</label>
                <input type="number" step="0.0001" name="unit_price" class="form-control" value="<?= $settings['unit_price'] ?? '0.0000' ?>" placeholder="e.g. 0.80">
                <div class="form-hint">Price per unit charged to your clients.</div>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group">
                <label class="form-label">Payment Instructions (Till/Paybill)</label>
                <textarea name="payment_instructions" class="form-control" rows="3" placeholder="e.g. Pay to Till: 123456 (My Company)"><?= htmlspecialchars($settings['payment_instructions'] ?? '') ?></textarea>
                <div class="form-hint">These instructions will be shown to your clients when they buy units.</div>
              </div>
            </div>
          </div>
        </div>
        <div class="card-footer" style="text-align:right">
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Billing Settings</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-globe" style="color:var(--primary)"></i> Custom Domain</h3>
      </div>
      <div class="card-body">
        <?php if (empty($settings['custom_domain'])): ?>
          <div class="alert alert-warning" style="font-size:13px">
            <i class="fa-solid fa-triangle-exclamation"></i> No custom domain configured yet. 
            Contact support to link your own domain (e.g., sms.yourbrand.com).
          </div>
        <?php else: ?>
          <div style="background:var(--bg-muted); padding:15px; border-radius:var(--radius-md); margin-bottom:15px">
             <div style="font-size:12px; color:var(--text-secondary); margin-bottom:5px">Active Domain:</div>
             <div style="font-weight:700; font-size:16px; color:var(--primary)"><?= htmlspecialchars($settings['custom_domain']) ?></div>
             
             <div style="margin-top:10px">
                <?php if ($settings['ssl_enabled']): ?>
                  <span class="badge badge-success"><i class="fa-solid fa-lock"></i> SSL Secured</span>
                <?php else: ?>
                  <span class="badge badge-warning"><i class="fa-solid fa-lock-open"></i> No SSL</span>
                <?php endif; ?>
             </div>
          </div>
        <?php endif; ?>

        <div style="font-size:13px; color:var(--text-secondary); line-height:1.5">
          <strong>DNS Instructions:</strong><br>
          To use your own domain, point a CNAME or A record to our server IP: <code><?= $_SERVER['SERVER_ADDR'] ?? 'YOUR_SERVER_IP' ?></code>. 
          Once pointed, our team will enable your domain and SSL certificate.
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
