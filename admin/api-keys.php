<?php
$pageTitle  = 'API Key Management';
$breadcrumb = [['label'=>'Admin'],['label'=>'API Keys']];
require_once __DIR__ . '/layout.php';

// ── KPIs ─────────────────────────────────────────────────────────
$withKey    = (int)(DB::queryOne("SELECT COUNT(*) as c FROM users WHERE role!='admin' AND api_client_id IS NOT NULL")['c'] ?? 0);
$withoutKey = (int)(DB::queryOne("SELECT COUNT(*) as c FROM users WHERE role!='admin' AND api_client_id IS NULL")['c'] ?? 0);

$callsToday = (int)(DB::queryOne(
    "SELECT COALESCE(SUM(hits),0) as t FROM api_rate_counters WHERE window_start >= CURDATE()"
)['t'] ?? 0);

$callsThisHour = (int)(DB::queryOne(
    "SELECT COALESCE(SUM(hits),0) as t FROM api_rate_counters WHERE window_start >= DATE_FORMAT(NOW(),'%Y-%m-%d %H:00:00')"
)['t'] ?? 0);

// ── Filters ───────────────────────────────────────────────────────
$filter  = in_array($_GET['filter'] ?? '', ['with','without']) ? $_GET['filter'] : '';
$search  = sanitize($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where  = "WHERE u.role != 'admin'";
$params = [];
if ($filter === 'with')    { $where .= ' AND u.api_client_id IS NOT NULL'; }
if ($filter === 'without') { $where .= ' AND u.api_client_id IS NULL'; }
if ($search) { $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.api_client_id LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$total  = (int)(DB::queryOne("SELECT COUNT(*) as c FROM users u $where", $params)['c'] ?? 0);
$pages  = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$users = DB::query(
    "SELECT u.id, u.name, u.email, u.role, u.status, u.api_client_id, u.api_key,
            (SELECT COALESCE(SUM(arc.hits),0) FROM api_rate_counters arc
             WHERE arc.bucket IN (CONCAT('api:', u.id), CONCAT('api_bulk:', u.id))
               AND arc.window_start >= CURDATE()) as calls_today,
            (SELECT COALESCE(SUM(arc2.hits),0) FROM api_rate_counters arc2
             WHERE arc2.bucket IN (CONCAT('api:', u.id), CONCAT('api_bulk:', u.id))) as calls_total
     FROM users u $where ORDER BY calls_today DESC, u.name ASC LIMIT $perPage OFFSET $offset",
    $params
);
?>

<div class="page-header">
  <div>
    <h1>API Key Management</h1>
    <div class="subtitle">View, generate, revoke, and monitor API credentials across all users</div>
  </div>
</div>

<!-- KPI Strip -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <div class="stat-card" style="margin:0">
    <div class="stat-icon green"><i class="fa-solid fa-key"></i></div>
    <div class="stat-info"><div class="stat-label">Users with Keys</div><div class="stat-value"><?= number_format($withKey) ?></div></div>
  </div>
  <div class="stat-card" style="margin:0">
    <div class="stat-icon orange"><i class="fa-solid fa-ban"></i></div>
    <div class="stat-info"><div class="stat-label">No API Key</div><div class="stat-value"><?= number_format($withoutKey) ?></div></div>
  </div>
  <div class="stat-card" style="margin:0">
    <div class="stat-icon blue"><i class="fa-solid fa-bolt"></i></div>
    <div class="stat-info"><div class="stat-label">API Calls Today</div><div class="stat-value"><?= number_format($callsToday) ?></div></div>
  </div>
  <div class="stat-card" style="margin:0">
    <div class="stat-icon blue"><i class="fa-solid fa-clock"></i></div>
    <div class="stat-info"><div class="stat-label">Calls This Hour</div><div class="stat-value"><?= number_format($callsThisHour) ?></div></div>
  </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:12px 18px">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <div class="tab-bar" style="flex:none">
        <a href="?filter=&q=<?= urlencode($search) ?>"       class="tab-item <?= $filter===''?'active':'' ?>">All <span class="badge badge-muted"><?= number_format($total) ?></span></a>
        <a href="?filter=with&q=<?= urlencode($search) ?>"   class="tab-item <?= $filter==='with'?'active':'' ?>">With Key <span class="badge badge-muted"><?= number_format($withKey) ?></span></a>
        <a href="?filter=without&q=<?= urlencode($search) ?>" class="tab-item <?= $filter==='without'?'active':'' ?>">No Key <span class="badge badge-muted"><?= number_format($withoutKey) ?></span></a>
      </div>
      <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <div class="input-group" style="flex:1">
          <div class="input-group-text input-addon-left"><i class="fa-solid fa-magnifying-glass"></i></div>
          <input type="text" name="q" class="form-control with-left" placeholder="Search name, email or client ID…" value="<?= htmlspecialchars($search) ?>">
        </div>
        <button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i></button>
        <?php if ($search) echo '<a href="?filter='.urlencode($filter).'" class="btn btn-secondary btn-sm">Clear</a>'; ?>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-key" style="color:var(--primary)"></i> API Credentials <span class="badge badge-muted"><?= number_format($total) ?></span></h3>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>User</th>
          <th>Role</th>
          <th>Client ID</th>
          <th>API Key</th>
          <th>Calls Today</th>
          <th>Calls Total</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No users found</td></tr>
        <?php else: foreach ($users as $u): ?>
          <tr>
            <td>
              <div style="font-weight:700"><?= htmlspecialchars($u['name']) ?></div>
              <div style="font-size:11px;color:var(--text-secondary)"><?= htmlspecialchars($u['email']) ?></div>
            </td>
            <td><span class="badge <?= $u['role']==='reseller'?'badge-success':'badge-info' ?>"><?= ucfirst($u['role']) ?></span></td>
            <td>
              <?php if ($u['api_client_id']): ?>
                <code style="font-size:11px;background:var(--bg-muted);padding:2px 6px;border-radius:4px"><?= htmlspecialchars($u['api_client_id']) ?></code>
              <?php else: ?>
                <span class="badge badge-muted">Not set</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($u['api_key']): ?>
                <span style="font-size:11px;color:var(--text-secondary);letter-spacing:.06em">sk_live_••••••••••••</span>
                <button class="btn btn-outline btn-sm btn-icon" style="margin-left:4px" title="View full credentials"
                        onclick="openViewModal(<?= htmlspecialchars(json_encode([
                          'name' => $u['name'],
                          'client_id' => $u['api_client_id'],
                          'api_key' => $u['api_key'],
                        ]), ENT_QUOTES) ?>)">
                  <i class="fa-solid fa-eye"></i>
                </button>
              <?php else: ?>
                <span class="badge badge-muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($u['calls_today'] > 0): ?>
                <span style="font-weight:700;color:var(--primary)"><?= number_format($u['calls_today']) ?></span>
              <?php else: ?>
                <span style="color:var(--text-secondary)">0</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--text-secondary)"><?= number_format($u['calls_total']) ?></td>
            <td>
              <div class="btn-group">
                <!-- Generate / Regenerate -->
                <form method="POST" action="/admin/actions/regenerate-api-key.php" style="display:inline"
                      onsubmit="return confirm('<?= $u['api_client_id'] ? 'Regenerate credentials? The existing key will stop working immediately.' : 'Generate API credentials for this user?' ?>')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" class="btn btn-<?= $u['api_client_id'] ? 'secondary' : 'primary' ?> btn-sm btn-icon"
                          title="<?= $u['api_client_id'] ? 'Regenerate Key' : 'Generate Key' ?>">
                    <i class="fa-solid fa-<?= $u['api_client_id'] ? 'rotate' : 'plus' ?>"></i>
                  </button>
                </form>
                <!-- Revoke (only if key exists) -->
                <?php if ($u['api_client_id']): ?>
                  <form method="POST" action="/admin/actions/revoke-api-key.php" style="display:inline"
                        onsubmit="return confirm('Revoke API key for <?= htmlspecialchars(addslashes($u['name'])) ?>? All integrations using this key will stop working.')">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-outline btn-sm btn-icon" style="color:var(--danger);border-color:var(--danger)" title="Revoke Key">
                      <i class="fa-solid fa-trash-can"></i>
                    </button>
                  </form>
                <?php endif; ?>
                <a href="/admin/user-detail.php?id=<?= $u['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="View Profile">
                  <i class="fa-solid fa-user"></i>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <div class="card-footer"><div class="pagination">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a href="?filter=<?= urlencode($filter) ?>&q=<?= urlencode($search) ?>&page=<?= $p ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div></div>
  <?php endif; ?>
</div>

<!-- View Credentials Modal -->
<div class="modal-overlay" id="viewCredModal">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-key" style="color:var(--primary)"></i> API Credentials</h3>
      <button class="modal-close" onclick="closeModal('viewCredModal')">×</button>
    </div>
    <div class="modal-body">
      <div style="background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:var(--radius-sm);padding:10px 14px;font-size:12px;color:var(--danger);margin-bottom:16px">
        <i class="fa-solid fa-triangle-exclamation"></i> Treat these as passwords. Do not share or expose in logs.
      </div>
      <div style="font-weight:700;margin-bottom:12px" id="vcUserName"></div>
      <div class="form-group">
        <label class="form-label" style="font-size:11px">Client ID</label>
        <div style="display:flex;gap:8px">
          <input type="text" id="vcClientId" class="form-control" readonly style="font-family:monospace;font-size:13px">
          <button class="btn btn-secondary btn-sm" onclick="copyField('vcClientId')" title="Copy"><i class="fa-regular fa-copy"></i></button>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" style="font-size:11px">API Key</label>
        <div style="display:flex;gap:8px">
          <input type="password" id="vcApiKey" class="form-control" readonly style="font-family:monospace;font-size:13px">
          <button class="btn btn-secondary btn-sm" onclick="toggleVis('vcApiKey',this)" title="Show/Hide"><i class="fa-regular fa-eye"></i></button>
          <button class="btn btn-secondary btn-sm" onclick="copyField('vcApiKey')" title="Copy"><i class="fa-regular fa-copy"></i></button>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('viewCredModal')">Close</button>
    </div>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function openViewModal(cred) {
    document.getElementById('vcUserName').textContent = cred.name;
    document.getElementById('vcClientId').value = cred.client_id;
    document.getElementById('vcApiKey').value = cred.api_key;
    document.getElementById('vcApiKey').type = 'password';
    const eyeIcon = document.querySelector('#viewCredModal .fa-eye, #viewCredModal .fa-eye-slash');
    if (eyeIcon) eyeIcon.className = 'fa-regular fa-eye';
    openModal('viewCredModal');
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
function copyField(id) {
    const el = document.getElementById(id);
    const prev = el.type;
    el.type = 'text';
    const val = el.value;
    el.type = prev;
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(val).then(function() {
            el.style.outline = '2px solid var(--success)';
            setTimeout(function() { el.style.outline = ''; }, 1200);
        });
    } else {
        el.type = 'text'; el.select(); document.execCommand('copy'); el.type = prev;
    }
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
