<?php
$pageTitle  = 'Site Branding';
$breadcrumb = [['label' => 'Account'], ['label' => 'Site Branding']];
require_once __DIR__ . '/layout.php';

$uid      = $user['id'];
$settings = DB::queryOne("SELECT * FROM reseller_settings WHERE reseller_id = ?", [$uid]);
if (!$settings) {
    DB::execute("INSERT IGNORE INTO reseller_settings (reseller_id, system_name) VALUES (?, ?)", [$uid, Branding::get('system_name')]);
    $settings = DB::queryOne("SELECT * FROM reseller_settings WHERE reseller_id = ?", [$uid]);
}

$sName    = htmlspecialchars($settings['system_name'] ?? '');
$sEmail   = htmlspecialchars($settings['support_email'] ?? '');
$sPhone   = htmlspecialchars($settings['support_phone'] ?? '');
$sLogo    = $settings['system_logo'] ?? '';
$sPrimary = $settings['primary_color'] ?? '#00c896';
$sSidebar = $settings['sidebar_color'] ?? '#0e1726';
$sHost    = htmlspecialchars($settings['smtp_host'] ?? '');
$sPort    = htmlspecialchars($settings['smtp_port'] ?? '587');
$sUser    = htmlspecialchars($settings['smtp_user'] ?? '');
$sEnc     = $settings['smtp_encryption'] ?? 'tls';
$sHasPass = !empty($settings['smtp_pass']);
$sPrice   = $settings['unit_price'] ?? '';
$sInstr   = htmlspecialchars($settings['payment_instructions'] ?? '');
$sUpdated = $settings['updated_at'] ?? null;
?>

<div class="page-header">
  <div>
    <h1>Site Branding</h1>
    <div class="subtitle">Customize how your platform looks for your clients.</div>
  </div>
  <?php if ($sUpdated): ?>
  <div style="font-size:12px;color:var(--text-secondary);display:flex;align-items:center;gap:6px">
    <i class="fa-solid fa-circle-check" style="color:var(--success)"></i>
    Last saved <?= date('d M Y · H:i', strtotime($sUpdated)) ?>
  </div>
  <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start">

  <!-- ── Left: Tabbed form ── -->
  <div>
    <div class="card">

      <!-- Tab strip -->
      <div style="display:flex;border-bottom:2px solid var(--border);background:var(--bg-dark);border-radius:var(--radius-md) var(--radius-md) 0 0;overflow:hidden">
        <?php
        $tabs = [
          'identity' => ['fa-solid fa-tag',    'Identity'],
          'colors'   => ['fa-solid fa-palette', 'Colors'],
          'contact'  => ['fa-solid fa-headset', 'Support'],
          'email'    => ['fa-solid fa-envelope','Email'],
          'billing'  => ['fa-solid fa-coins',   'Billing'],
        ];
        foreach ($tabs as $key => [$icon, $label]):
        ?>
          <button type="button"
            class="branding-tab <?= $key === 'identity' ? 'active' : '' ?>"
            data-tab="<?= $key ?>"
            onclick="switchTab('<?= $key ?>', this)">
            <i class="<?= $icon ?>"></i>
            <span><?= $label ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <!-- Single form wrapping all tabs -->
      <form method="POST" action="/reseller/actions/save-branding.php" enctype="multipart/form-data" id="brandingForm">
        <?= csrf_field() ?>
        <input type="hidden" name="remove_logo" id="removeLogoFlag" value="">

        <!-- ── Tab: Identity ── -->
        <div class="btab-panel" id="tab-identity">
          <div class="card-body" style="display:flex;flex-direction:column;gap:22px">

            <div class="form-group" style="margin:0">
              <label class="form-label">Platform Name <span class="required">*</span></label>
              <input type="text" name="system_name" class="form-control" value="<?= $sName ?>"
                     placeholder="e.g. Acme SMS Portal" required style="font-size:15px;font-weight:500">
              <div class="form-hint">Appears in page titles, the sidebar, and all client-facing emails.</div>
            </div>

            <!-- Logo upload zone -->
            <div class="form-group" style="margin:0">
              <label class="form-label">Platform Logo</label>
              <div id="logoDropZone"
                   style="border:2px dashed var(--border);border-radius:var(--radius-md);padding:28px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s"
                   onclick="document.getElementById('logoFileInput').click()"
                   ondragover="handleDragOver(event)"
                   ondragleave="handleDragLeave(event)"
                   ondrop="handleDrop(event)">

                <?php if ($sLogo): ?>
                  <div id="logoPreviewWrap">
                    <div style="background:var(--bg-muted);border-radius:var(--radius-sm);padding:16px;display:inline-block;margin-bottom:12px">
                      <img id="logoPreviewImg" src="<?= htmlspecialchars($sLogo) ?>" style="max-height:50px;max-width:200px;object-fit:contain">
                    </div>
                    <div style="font-size:12px;color:var(--text-secondary);margin-bottom:10px">Current logo</div>
                    <button type="button" class="btn btn-sm btn-danger" onclick="event.stopPropagation();removeLogo()">
                      <i class="fa-solid fa-trash"></i> Remove logo
                    </button>
                  </div>
                  <div id="logoUploadHint" style="display:none">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:28px;color:var(--text-secondary);margin-bottom:10px"></i>
                    <div style="font-size:13px;color:var(--text-secondary)">Drop a new logo here or <span style="color:var(--primary);font-weight:600">browse</span></div>
                  </div>
                <?php else: ?>
                  <div id="logoPreviewWrap" style="display:none">
                    <div style="background:var(--bg-muted);border-radius:var(--radius-sm);padding:16px;display:inline-block;margin-bottom:12px">
                      <img id="logoPreviewImg" src="" style="max-height:50px;max-width:200px;object-fit:contain">
                    </div>
                    <div style="font-size:12px;color:var(--text-secondary);margin-bottom:10px">New logo selected</div>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="event.stopPropagation();clearLogoSelect()">
                      <i class="fa-solid fa-xmark"></i> Clear selection
                    </button>
                  </div>
                  <div id="logoUploadHint">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:28px;color:var(--text-secondary);margin-bottom:10px"></i>
                    <div style="font-size:13px;color:var(--text-secondary)">Drop your logo here or <span style="color:var(--primary);font-weight:600">browse</span></div>
                  </div>
                <?php endif; ?>

                <input type="file" name="system_logo" id="logoFileInput" accept=".png,.jpg,.jpeg,.svg,.webp" style="display:none" onchange="previewLogo(this)">
              </div>
              <div class="form-hint">PNG, SVG, or WebP with transparent background recommended · Max 2 MB · Ideal: 200×50 px</div>
            </div>

          </div>
        </div>

        <!-- ── Tab: Colors ── -->
        <div class="btab-panel" id="tab-colors" style="display:none">
          <div class="card-body" style="display:flex;flex-direction:column;gap:24px">

            <div class="alert alert-info" style="font-size:12px;margin:0">
              <i class="fa-solid fa-circle-info"></i>
              Changes appear <strong>instantly</strong> in the preview on the right. Save to apply them to your clients' portal.
            </div>

            <!-- Primary color -->
            <div>
              <label class="form-label">Primary Accent Color</label>
              <div style="display:flex;align-items:center;gap:14px">
                <div style="position:relative">
                  <input type="color" name="primary_color" id="primaryPicker"
                         value="<?= $sPrimary ?>"
                         onchange="syncColor('primary', this.value)"
                         oninput="syncColor('primary', this.value)"
                         style="width:52px;height:52px;border:none;border-radius:var(--radius-sm);cursor:pointer;padding:2px;background:none">
                </div>
                <div style="flex:1">
                  <input type="text" id="primaryHex" class="form-control" value="<?= $sPrimary ?>"
                         onchange="syncHex('primary', this.value)"
                         style="font-family:monospace;letter-spacing:1px;text-transform:uppercase"
                         placeholder="#00c896" maxlength="7">
                </div>
              </div>
              <!-- Preset swatches -->
              <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
                <?php foreach (['#00c896','#3b82f6','#8b5cf6','#f59e0b','#ef4444','#10b981','#0ea5e9','#ec4899'] as $c): ?>
                  <button type="button"
                    class="color-swatch"
                    style="background:<?= $c ?>"
                    title="<?= $c ?>"
                    onclick="syncColor('primary', '<?= $c ?>')"></button>
                <?php endforeach; ?>
              </div>
              <div class="form-hint">Used for buttons, active nav items, and interactive accents.</div>
            </div>

            <!-- Sidebar color -->
            <div>
              <label class="form-label">Sidebar Background Color</label>
              <div style="display:flex;align-items:center;gap:14px">
                <input type="color" name="sidebar_color" id="sidebarPicker"
                       value="<?= $sSidebar ?>"
                       onchange="syncColor('sidebar', this.value)"
                       oninput="syncColor('sidebar', this.value)"
                       style="width:52px;height:52px;border:none;border-radius:var(--radius-sm);cursor:pointer;padding:2px;background:none">
                <div style="flex:1">
                  <input type="text" id="sidebarHex" class="form-control" value="<?= $sSidebar ?>"
                         onchange="syncHex('sidebar', this.value)"
                         style="font-family:monospace;letter-spacing:1px;text-transform:uppercase"
                         placeholder="#0e1726" maxlength="7">
                </div>
              </div>
              <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
                <?php foreach (['#0e1726','#1e293b','#111827','#1a1a2e','#0d1b2a','#18181b','#0f172a','#1c1917'] as $c): ?>
                  <button type="button"
                    class="color-swatch"
                    style="background:<?= $c ?>"
                    title="<?= $c ?>"
                    onclick="syncColor('sidebar', '<?= $c ?>')"></button>
                <?php endforeach; ?>
              </div>
              <div class="form-hint">Dark background of the navigation sidebar.</div>
            </div>

          </div>
        </div>

        <!-- ── Tab: Support Contact ── -->
        <div class="btab-panel" id="tab-contact" style="display:none">
          <div class="card-body" style="display:flex;flex-direction:column;gap:20px">

            <p style="font-size:13px;color:var(--text-secondary);margin:0">
              These details appear in the footer, help tooltips, and system emails sent to your clients.
            </p>

            <div class="form-group" style="margin:0">
              <label class="form-label">Support Email</label>
              <div style="position:relative">
                <i class="fa-solid fa-envelope" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-secondary);font-size:13px"></i>
                <input type="email" name="support_email" class="form-control" value="<?= $sEmail ?>"
                       placeholder="support@yourdomain.com" style="padding-left:36px">
              </div>
            </div>

            <div class="form-group" style="margin:0">
              <label class="form-label">Support Phone / WhatsApp</label>
              <div style="position:relative">
                <i class="fa-solid fa-phone" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-secondary);font-size:13px"></i>
                <input type="text" name="support_phone" class="form-control" value="<?= $sPhone ?>"
                       placeholder="+254 700 000 000" style="padding-left:36px">
              </div>
            </div>

          </div>
        </div>

        <!-- ── Tab: Email / SMTP ── -->
        <div class="btab-panel" id="tab-email" style="display:none">
          <div class="card-body" style="display:flex;flex-direction:column;gap:20px">

            <div class="alert alert-info" style="font-size:12px;margin:0">
              <i class="fa-solid fa-circle-info"></i>
              Configure your own SMTP server so transactional emails (welcome, low-balance, reports) are delivered from <strong>your own domain</strong>, not ours.
            </div>

            <div style="display:grid;grid-template-columns:1fr auto;gap:12px">
              <div class="form-group" style="margin:0">
                <label class="form-label">SMTP Host</label>
                <input type="text" name="smtp_host" class="form-control" value="<?= $sHost ?>" placeholder="smtp.gmail.com">
              </div>
              <div class="form-group" style="margin:0">
                <label class="form-label">Port</label>
                <input type="number" name="smtp_port" class="form-control" value="<?= $sPort ?>"
                       placeholder="587" style="width:100px">
              </div>
            </div>

            <div class="form-group" style="margin:0">
              <label class="form-label">Encryption</label>
              <div style="display:flex;gap:0;border:1px solid var(--border);border-radius:var(--radius-sm);overflow:hidden;width:fit-content">
                <?php foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None'] as $val => $label): ?>
                  <label style="margin:0;cursor:pointer">
                    <input type="radio" name="smtp_encryption" value="<?= $val ?>" <?= $sEnc === $val ? 'checked' : '' ?> style="display:none">
                    <div class="enc-opt <?= $sEnc === $val ? 'active' : '' ?>"><?= $label ?></div>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div class="form-group" style="margin:0">
                <label class="form-label">Username / From Email</label>
                <input type="text" name="smtp_user" class="form-control" value="<?= $sUser ?>"
                       placeholder="noreply@yourdomain.com">
              </div>
              <div class="form-group" style="margin:0">
                <label class="form-label">Password</label>
                <div style="position:relative">
                  <input type="password" name="smtp_pass" id="smtpPassField" class="form-control"
                         value="" placeholder="<?= $sHasPass ? '•••••••• (saved)' : 'Enter SMTP password' ?>">
                  <button type="button"
                    onclick="togglePass('smtpPassField', this)"
                    style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-secondary);cursor:pointer;padding:0">
                    <i class="fa-regular fa-eye"></i>
                  </button>
                </div>
                <div class="form-hint">Leave blank to keep the saved password.</div>
              </div>
            </div>

          </div>
        </div>

        <!-- ── Tab: Billing ── -->
        <div class="btab-panel" id="tab-billing" style="display:none">
          <div class="card-body" style="display:flex;flex-direction:column;gap:20px">

            <p style="font-size:13px;color:var(--text-secondary);margin:0">
              Set the default price your clients pay per SMS unit, and specify how they should pay.
            </p>

            <div class="form-group" style="margin:0">
              <label class="form-label">Unit Price (KES)</label>
              <div style="position:relative;max-width:200px">
                <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:13px;color:var(--text-secondary);font-weight:600">KES</span>
                <input type="number" name="unit_price" class="form-control" value="<?= $sPrice ?>"
                       step="0.0001" min="0" placeholder="0.8000" style="padding-left:48px">
              </div>
              <div class="form-hint">Price per SMS unit charged to your clients. Leave blank to use your reseller rate.</div>
            </div>

            <div class="form-group" style="margin:0">
              <label class="form-label">Payment Instructions</label>
              <textarea name="payment_instructions" class="form-control" rows="4"
                        placeholder="e.g. Pay to Till Number: 123456 · Business: Acme SMS&#10;After payment, share the M-Pesa confirmation code with us."><?= $sInstr ?></textarea>
              <div class="form-hint">Shown to your clients on the top-up/purchase page.</div>
            </div>

          </div>
        </div>

        <!-- Form footer -->
        <div class="card-footer" style="display:flex;justify-content:flex-end;gap:10px">
          <button type="reset" class="btn btn-secondary">Discard Changes</button>
          <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-floppy-disk"></i> Save Settings
          </button>
        </div>

      </form>
    </div>
  </div>

  <!-- ── Right: Sticky preview + Domain ── -->
  <div style="position:sticky;top:24px;display:flex;flex-direction:column;gap:20px">

    <!-- Live brand preview -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title" style="font-size:13px">
          <i class="fa-solid fa-eye" style="color:var(--primary)"></i> Brand Preview
        </h3>
      </div>
      <div class="card-body" style="padding:0">
        <!-- Mini browser chrome -->
        <div style="background:#2d2d2d;padding:8px 12px;display:flex;align-items:center;gap:6px">
          <div style="width:10px;height:10px;border-radius:50%;background:#ef4444"></div>
          <div style="width:10px;height:10px;border-radius:50%;background:#f59e0b"></div>
          <div style="width:10px;height:10px;border-radius:50%;background:#22c55e"></div>
          <div style="flex:1;background:#1a1a1a;border-radius:4px;padding:3px 10px;margin-left:6px;font-size:9px;color:#888">
            sms.yourdomain.com
          </div>
        </div>
        <!-- App viewport -->
        <div style="display:flex;height:210px;overflow:hidden">
          <!-- Preview sidebar -->
          <div id="previewSidebar" style="width:90px;padding:12px 8px;display:flex;flex-direction:column;gap:6px;flex-shrink:0;background:<?= $sSidebar ?>">
            <div id="previewLogoText"
                 style="font-size:9px;font-weight:800;color:<?= $sPrimary ?>;margin-bottom:8px;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              <?= $sName ?: 'MY BRAND' ?>
            </div>
            <?php
            $navItems = [['Dashboard','fa-chart-bar',true],['Messages','fa-message',false],['Reports','fa-file',false],['Contacts','fa-users',false]];
            foreach ($navItems as [$label, $icon, $active]):
            ?>
              <div id="<?= $active ? 'previewActiveNav' : '' ?>"
                   style="display:flex;align-items:center;gap:5px;padding:5px 6px;border-radius:5px;<?= $active ? 'background:rgba(0,200,150,0.18)' : '' ?>">
                <i class="fa-solid <?= $icon ?>" style="font-size:9px;color:<?= $active ? $sPrimary : '#9ca3af' ?>"></i>
                <span style="font-size:8px;color:<?= $active ? '#fff' : '#9ca3af' ?>;font-weight:<?= $active ? 600 : 400 ?>"><?= $label ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <!-- Preview content -->
          <div style="flex:1;background:#0f172a;padding:12px;overflow:hidden">
            <div style="font-size:10px;font-weight:700;color:#fff;margin-bottom:10px">Dashboard</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:10px">
              <?php foreach ([['SMS Units','1,250'],['Sent Today','342']] as [$lbl,$val]): ?>
                <div style="background:#1e293b;border-radius:5px;padding:8px">
                  <div style="font-size:7px;color:#64748b;margin-bottom:2px"><?= $lbl ?></div>
                  <div style="font-size:12px;font-weight:700;color:#fff"><?= $val ?></div>
                </div>
              <?php endforeach; ?>
            </div>
            <div id="previewBtn"
                 style="background:<?= $sPrimary ?>;border-radius:4px;padding:5px 10px;font-size:8px;font-weight:700;color:#000;display:inline-block;margin-bottom:8px">
              Send SMS
            </div>
            <div style="height:1px;background:#1e293b;margin-bottom:8px"></div>
            <div style="font-size:7px;color:#475569">Recent activity...</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Custom Domain card -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title" style="font-size:13px">
          <i class="fa-solid fa-globe" style="color:var(--primary)"></i> Custom Domain
        </h3>
      </div>
      <div class="card-body">
        <?php if (!empty($settings['custom_domain'])): ?>
          <div style="background:var(--bg-muted);border-radius:var(--radius-sm);padding:14px;margin-bottom:14px">
            <div style="font-size:10px;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Active Domain</div>
            <div style="font-weight:700;font-size:15px;color:var(--primary);margin-bottom:8px"><?= htmlspecialchars($settings['custom_domain']) ?></div>
            <?php if ($settings['ssl_enabled']): ?>
              <span class="badge badge-success"><i class="fa-solid fa-lock"></i> SSL Secured</span>
            <?php else: ?>
              <span class="badge badge-warning"><i class="fa-solid fa-lock-open"></i> No SSL</span>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="alert alert-warning" style="font-size:12px;margin-bottom:14px">
            <i class="fa-solid fa-triangle-exclamation"></i>
            No custom domain set yet.
          </div>
        <?php endif; ?>

        <div style="font-size:12px;color:var(--text-secondary);line-height:1.6">
          <strong style="color:var(--text-primary)">DNS Setup:</strong><br>
          Add a <code>CNAME</code> record pointing to:<br>
          <div style="display:flex;align-items:center;gap:8px;margin-top:6px;background:var(--bg-muted);padding:8px 10px;border-radius:var(--radius-sm)">
            <code id="serverIp" style="flex:1;font-size:11px"><?= htmlspecialchars($_SERVER['SERVER_ADDR'] ?? 'contact.support') ?></code>
            <button type="button" onclick="copyText('serverIp')" class="btn btn-sm btn-secondary" style="padding:2px 8px;font-size:11px">
              <i class="fa-regular fa-copy"></i>
            </button>
          </div>
          <div style="margin-top:8px;font-size:11px">Then contact support to activate your domain and SSL.</div>
        </div>
      </div>
    </div>

  </div><!-- /right column -->
</div>

<style>
.branding-tab {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 14px 18px;
  background: none;
  border: none;
  border-bottom: 3px solid transparent;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary);
  cursor: pointer;
  white-space: nowrap;
  transition: color .15s;
  margin-bottom: -2px;
}
.branding-tab:hover { color: var(--primary); }
.branding-tab.active { color: var(--primary); border-bottom-color: var(--primary); }

.color-swatch {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  border: 2px solid transparent;
  cursor: pointer;
  transition: transform .15s, border-color .15s;
}
.color-swatch:hover { transform: scale(1.2); border-color: rgba(255,255,255,.4); }

.enc-opt {
  padding: 8px 14px;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-secondary);
  cursor: pointer;
  border-right: 1px solid var(--border);
  transition: background .12s, color .12s;
}
.enc-opt:last-child { border-right: none; }
.enc-opt.active, label:has(input:checked) .enc-opt { background: var(--primary); color: #000; }

#logoDropZone:hover,
#logoDropZone.drag-over {
  border-color: var(--primary);
  background: rgba(var(--primary-rgb), .04);
}
</style>

<script>
/* ── Tab switching ── */
function switchTab(name, btn) {
  document.querySelectorAll('.branding-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.btab-panel').forEach(p => {
    p.style.display = p.id === 'tab-' + name ? 'block' : 'none';
  });
}

/* ── Color syncing ── */
function syncColor(which, val) {
  val = val.length === 4
    ? '#' + val[1]+val[1] + val[2]+val[2] + val[3]+val[3]
    : val;

  if (which === 'primary') {
    document.getElementById('primaryPicker').value = val;
    document.getElementById('primaryHex').value    = val.toUpperCase();
    // Live preview
    document.getElementById('previewBtn').style.background = val;
    document.getElementById('previewLogoText').style.color = val;
    document.querySelectorAll('#previewActiveNav i').forEach(el => el.style.color = val);
    document.getElementById('previewActiveNav').style.background = hexToRgba(val, 0.18);
  } else {
    document.getElementById('sidebarPicker').value = val;
    document.getElementById('sidebarHex').value    = val.toUpperCase();
    document.getElementById('previewSidebar').style.background = val;
  }
}

function syncHex(which, raw) {
  const val = raw.startsWith('#') ? raw : '#' + raw;
  if (/^#[0-9a-fA-F]{6}$/.test(val)) syncColor(which, val);
}

function hexToRgba(hex, alpha) {
  const r = parseInt(hex.slice(1,3), 16);
  const g = parseInt(hex.slice(3,5), 16);
  const b = parseInt(hex.slice(5,7), 16);
  return `rgba(${r},${g},${b},${alpha})`;
}

/* Update preview logo text when system_name changes */
document.querySelector('[name="system_name"]').addEventListener('input', function() {
  document.getElementById('previewLogoText').textContent = this.value || 'MY BRAND';
});

/* ── SMTP encryption toggle (visual radio) ── */
document.querySelectorAll('input[name="smtp_encryption"]').forEach(radio => {
  radio.addEventListener('change', () => {
    document.querySelectorAll('.enc-opt').forEach(el => el.classList.remove('active'));
    radio.closest('label').querySelector('.enc-opt').classList.add('active');
  });
});

/* ── Logo upload ── */
function previewLogo(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  if (file.size > 2 * 1024 * 1024) {
    alert('File too large. Maximum size is 2 MB.');
    input.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('logoPreviewImg').src = e.target.result;
    document.getElementById('logoPreviewWrap').style.display = '';
    document.getElementById('logoUploadHint').style.display  = 'none';
  };
  reader.readAsDataURL(file);
}

function removeLogo() {
  document.getElementById('removeLogoFlag').value = '1';
  document.getElementById('logoFileInput').value  = '';
  document.getElementById('logoPreviewWrap').style.display = 'none';
  document.getElementById('logoUploadHint').style.display  = '';
}

function clearLogoSelect() {
  document.getElementById('logoFileInput').value  = '';
  document.getElementById('logoPreviewWrap').style.display = 'none';
  document.getElementById('logoUploadHint').style.display  = '';
}

/* ── Drag & drop ── */
function handleDragOver(e) {
  e.preventDefault();
  document.getElementById('logoDropZone').classList.add('drag-over');
}
function handleDragLeave(e) {
  document.getElementById('logoDropZone').classList.remove('drag-over');
}
function handleDrop(e) {
  e.preventDefault();
  document.getElementById('logoDropZone').classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file) {
    const dt  = new DataTransfer();
    dt.items.add(file);
    const inp = document.getElementById('logoFileInput');
    inp.files = dt.files;
    previewLogo(inp);
  }
}

/* ── Password show/hide ── */
function togglePass(id, btn) {
  const inp = document.getElementById(id);
  const ico = btn.querySelector('i');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'fa-regular fa-eye-slash';
  } else {
    inp.type = 'password';
    ico.className = 'fa-regular fa-eye';
  }
}

/* ── Copy to clipboard ── */
function copyText(id) {
  const text = document.getElementById(id).textContent.trim();
  navigator.clipboard?.writeText(text).then(() => {
    const btn = document.querySelector(`#${id} + button, button[onclick="copyText('${id}')"]`);
    if (btn) { const orig = btn.innerHTML; btn.innerHTML = '<i class="fa-solid fa-check"></i>'; setTimeout(()=>btn.innerHTML=orig,1200); }
  });
}
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
