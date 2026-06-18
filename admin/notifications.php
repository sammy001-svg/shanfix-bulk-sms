<?php
$pageTitle  = 'Broadcast & Announcements';
$breadcrumb = [['label'=>'Admin'],['label'=>'Notifications']];
require_once __DIR__ . '/layout.php';

// ── KPIs ────────────────────────────────────────────────────────
$kpis = DB::queryOne(
    "SELECT COUNT(*) as total,
            SUM(user_id IS NULL) as broadcasts,
            SUM(user_id IS NOT NULL) as individual,
            SUM(is_banner = 1) as banners,
            SUM(is_popup  = 1) as popups
     FROM notifications"
);
$engagedTotal = DB::queryOne(
    "SELECT COUNT(DISTINCT notification_id) as n FROM notification_interactions WHERE is_read = 1"
)['n'] ?? 0;

// ── Filters ─────────────────────────────────────────────────────
$tab    = in_array($_GET['tab'] ?? '', ['broadcast','individual']) ? ($_GET['tab'] ?? '') : '';
$search = sanitize($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage= 20;

$where  = '1=1';
$params = [];
if ($tab === 'broadcast')  { $where .= ' AND n.user_id IS NULL'; }
if ($tab === 'individual') { $where .= ' AND n.user_id IS NOT NULL'; }
if ($search) { $where .= ' AND (n.title LIKE ? OR n.message LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

$total    = (int)(DB::queryOne("SELECT COUNT(*) as c FROM notifications n WHERE $where", $params)['c'] ?? 0);
$pages    = max(1, (int)ceil($total / $perPage));
$offset   = ($page - 1) * $perPage;

$notifs = DB::query(
    "SELECT n.*, u.name as user_name, u.role as user_role,
            (SELECT COUNT(*) FROM notification_interactions ni WHERE ni.notification_id = n.id AND ni.is_read = 1) as read_count
     FROM notifications n LEFT JOIN users u ON n.user_id = u.id
     WHERE $where ORDER BY n.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

// ── User list for dropdown ──────────────────────────────────────
$allUsers = DB::query("SELECT id, name, role FROM users WHERE status='active' AND role != 'admin' ORDER BY role, name");
?>

<div class="page-header">
  <div>
    <h1>Broadcast & Announcements</h1>
    <div class="subtitle">Send notifications, banners, and popups to users</div>
  </div>
  <button class="btn btn-primary" onclick="openModal('newNotifModal')">
    <i class="fa-solid fa-plus"></i> Create Notification
  </button>
</div>

<!-- KPI Strip -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:24px">
  <div class="stat-card" style="margin:0">
    <div class="stat-icon blue"><i class="fa-solid fa-bell"></i></div>
    <div class="stat-info"><div class="stat-label">Total Sent</div><div class="stat-value"><?= number_format($kpis['total'] ?? 0) ?></div></div>
  </div>
  <div class="stat-card" style="margin:0">
    <div class="stat-icon orange"><i class="fa-solid fa-globe"></i></div>
    <div class="stat-info"><div class="stat-label">Broadcasts</div><div class="stat-value"><?= number_format($kpis['broadcasts'] ?? 0) ?></div></div>
  </div>
  <div class="stat-card" style="margin:0">
    <div class="stat-icon blue"><i class="fa-solid fa-user"></i></div>
    <div class="stat-info"><div class="stat-label">Individual</div><div class="stat-value"><?= number_format($kpis['individual'] ?? 0) ?></div></div>
  </div>
  <div class="stat-card" style="margin:0">
    <div class="stat-icon green"><i class="fa-solid fa-rectangle-ad"></i></div>
    <div class="stat-info"><div class="stat-label">Banners</div><div class="stat-value"><?= number_format($kpis['banners'] ?? 0) ?></div></div>
  </div>
  <div class="stat-card" style="margin:0">
    <div class="stat-icon green"><i class="fa-solid fa-eye"></i></div>
    <div class="stat-info"><div class="stat-label">Notifications Read</div><div class="stat-value"><?= number_format($engagedTotal) ?></div></div>
  </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:12px 18px">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <div class="tab-bar" style="flex:none">
        <a href="?tab=&q=<?=urlencode($search)?>" class="tab-item <?= $tab===''?'active':'' ?>">All <span class="badge badge-muted"><?=number_format($total)?></span></a>
        <a href="?tab=broadcast&q=<?=urlencode($search)?>" class="tab-item <?= $tab==='broadcast'?'active':'' ?>">Broadcasts</a>
        <a href="?tab=individual&q=<?=urlencode($search)?>" class="tab-item <?= $tab==='individual'?'active':'' ?>">Individual</a>
      </div>
      <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px">
        <input type="hidden" name="tab" value="<?=htmlspecialchars($tab)?>">
        <div class="input-group" style="flex:1">
          <div class="input-group-text input-addon-left"><i class="fa-solid fa-magnifying-glass"></i></div>
          <input type="text" name="q" class="form-control with-left" placeholder="Search title or message..." value="<?=htmlspecialchars($search)?>">
        </div>
        <button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i></button>
        <?php if ($search) echo '<a href="?tab='.urlencode($tab).'" class="btn btn-secondary btn-sm">Clear</a>'; ?>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-bell" style="color:var(--primary)"></i> Notifications <span class="badge badge-muted"><?=number_format($total)?></span></h3>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Title & Message</th><th>Audience</th><th>Type</th><th>Mode</th><th>Engagement</th><th>Sent At</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($notifs)): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:40px">No notifications found</td></tr>
        <?php else: foreach ($notifs as $n):
          $tc = ['info'=>'info','success'=>'success','warning'=>'warning','danger'=>'danger'][$n['type']] ?? 'muted';
        ?>
          <tr>
            <td style="max-width:280px">
              <div style="font-weight:700;color:var(--text-primary)"><?= htmlspecialchars($n['title']) ?></div>
              <div style="font-size:12px;color:var(--text-secondary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                <?= htmlspecialchars(mb_strimwidth($n['message'], 0, 90, '…')) ?>
              </div>
            </td>
            <td>
              <?php if ($n['user_id']): ?>
                <span class="badge badge-muted"><i class="fa-solid fa-user"></i> <?= htmlspecialchars($n['user_name']) ?></span>
                <div style="font-size:10px;color:var(--text-secondary);margin-top:2px"><?= ucfirst($n['user_role']) ?></div>
              <?php else: ?>
                <span class="badge badge-success"><i class="fa-solid fa-globe"></i> All Users</span>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-<?= $tc ?>"><?= strtoupper($n['type']) ?></span></td>
            <td>
              <div style="display:flex;gap:4px;flex-wrap:wrap">
                <?php if ($n['is_banner']): ?><span class="badge badge-info" title="Dashboard banner"><i class="fa-solid fa-rectangle-ad"></i> Banner</span><?php endif; ?>
                <?php if ($n['is_popup']): ?><span class="badge badge-warning" title="Popup modal"><i class="fa-solid fa-window-maximize"></i> Popup</span><?php endif; ?>
                <?php if (!$n['is_banner'] && !$n['is_popup']): ?><span class="badge badge-muted">Bell only</span><?php endif; ?>
              </div>
            </td>
            <td>
              <?php if ($n['user_id']): ?>
                <span class="badge badge-<?= $n['read_count'] > 0 ? 'success' : 'muted' ?>"><?= $n['read_count'] > 0 ? 'Read' : 'Unread' ?></span>
              <?php else: ?>
                <span class="badge badge-<?= $n['read_count'] > 0 ? 'success' : 'muted' ?>"><?= number_format($n['read_count']) ?> reads</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;color:var(--text-secondary)"><?= date('d M Y H:i', strtotime($n['created_at'])) ?></td>
            <td>
              <form method="POST" action="/admin/actions/delete-notification.php" style="display:inline"
                    onsubmit="return confirm('Delete this notification? It will be removed from all users.')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="id" value="<?= $n['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm btn-icon" style="color:var(--danger);border-color:var(--danger)" title="Delete">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <div class="card-footer"><div class="pagination">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a href="?tab=<?=urlencode($tab)?>&q=<?=urlencode($search)?>&page=<?=$p?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a>
      <?php endfor; ?>
    </div></div>
  <?php endif; ?>
</div>

<!-- Create Notification Modal -->
<div class="modal-overlay" id="newNotifModal">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-bullhorn" style="color:var(--primary)"></i> Create Notification</h3>
      <button class="modal-close" onclick="closeModal('newNotifModal')">×</button>
    </div>
    <form method="POST" action="/admin/actions/create-notification.php" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="modal-body">

        <div class="form-row">
          <div class="form-group" style="flex:2">
            <label class="form-label">Title <span class="required">*</span></label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Scheduled Maintenance" required>
          </div>
          <div class="form-group" style="flex:1">
            <label class="form-label">Alert Type</label>
            <select name="type" class="form-control">
              <option value="info">Info</option>
              <option value="success">Success</option>
              <option value="warning">Warning</option>
              <option value="danger">Danger</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Message <span class="required">*</span></label>
          <textarea name="message" class="form-control" rows="4" placeholder="Write your message..." required></textarea>
        </div>

        <div class="form-row">
          <div class="form-group" style="flex:1">
            <label class="form-label">Audience <span class="required">*</span></label>
            <select name="audience" id="audienceSelect" class="form-control" onchange="toggleUserPicker(this.value)">
              <option value="all">All Users (Broadcast)</option>
              <option value="resellers">All Resellers</option>
              <option value="clients">All Clients</option>
              <option value="user">Specific User</option>
            </select>
          </div>
          <div class="form-group" style="flex:1" id="userPickerWrap" style="display:none">
            <label class="form-label">Select User</label>
            <select name="user_id" class="form-control" id="userPicker">
              <option value="">— pick a user —</option>
              <?php foreach ($allUsers as $u): ?>
                <option value="<?= $u['id'] ?>">[<?= ucfirst($u['role']) ?>] <?= htmlspecialchars($u['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Display Mode</label>
          <div style="display:flex;gap:20px;padding:10px 14px;background:var(--bg-muted);border-radius:var(--radius-sm);border:1px solid var(--border)">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
              <input type="checkbox" name="is_banner" value="1">
              <span><strong>Dashboard Banner</strong> — shows in the carousel at the top of the dashboard</span>
            </label>
          </div>
          <div style="display:flex;gap:20px;padding:10px 14px;background:var(--bg-muted);border-radius:var(--radius-sm);border:1px solid var(--border);margin-top:6px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
              <input type="checkbox" name="is_popup" value="1">
              <span><strong>Popup Modal</strong> — shows as a blocking modal when the user logs in</span>
            </label>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Banner Image <span style="color:var(--text-secondary);font-weight:400">(optional, for banners/popups)</span></label>
          <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/gif">
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('newNotifModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Send</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function toggleUserPicker(val) {
    const wrap = document.getElementById('userPickerWrap');
    const picker = document.getElementById('userPicker');
    if (val === 'user') {
        wrap.style.display = 'block';
        picker.required = true;
    } else {
        wrap.style.display = 'none';
        picker.required = false;
    }
}
toggleUserPicker(document.getElementById('audienceSelect').value);
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
