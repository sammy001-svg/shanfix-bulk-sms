<?php
// CSV export must happen before layout.php emits headers
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

$search   = sanitize($_GET['q']      ?? '');
$status   = sanitize($_GET['status'] ?? '');
$userId   = (int)($_GET['user_id']   ?? 0);
$from     = sanitize($_GET['from']   ?? date('Y-m-01'));
$to       = sanitize($_GET['to']     ?? date('Y-m-d'));
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;

$validStatuses = ['draft','scheduled','queued','sending','running','completed','failed'];
if (!in_array($status, $validStatuses, true)) $status = '';

// Build WHERE
$where  = "WHERE c.created_at >= ? AND c.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
$params = [$from, $to];
if ($status) { $where .= " AND c.status = ?";        $params[] = $status; }
if ($userId) { $where .= " AND c.user_id = ?";       $params[] = $userId; }
if ($search) { $where .= " AND (c.name LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
               $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

// CSV export
if (($_GET['export'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="campaigns_' . $from . '_to_' . $to . '.csv"');
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['ID','Campaign','Owner','Role','Sender ID','Total','Sent','Failed','Units Used','Status','Scheduled','Created']);
    $rows = DB::query(
        "SELECT c.*, u.name as user_name, u.role as user_role
         FROM campaigns c JOIN users u ON c.user_id = u.id
         $where ORDER BY c.created_at DESC", $params
    );
    foreach ($rows as $r) {
        fputcsv($fh, [
            $r['id'], $r['name'], $r['user_name'], $r['user_role'],
            $r['sender_id'], $r['total_count'], $r['sent_count'], $r['failed_count'],
            $r['units_used'], $r['status'],
            $r['scheduled_at'] ?? '', $r['created_at'],
        ]);
    }
    fclose($fh);
    exit;
}

// KPI summary
$kpis = DB::queryOne(
    "SELECT COUNT(*) as total,
            SUM(c.status IN ('queued','running','sending')) as active,
            SUM(c.status='completed') as completed,
            SUM(c.status='failed') as failed_count,
            COALESCE(SUM(c.total_count),0) as recipients,
            COALESCE(SUM(c.units_used),0) as units
     FROM campaigns c JOIN users u ON c.user_id = u.id $where",
    $params
);

$total      = (int)(DB::queryOne("SELECT COUNT(*) as c FROM campaigns c JOIN users u ON c.user_id=u.id $where", $params)['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$campaigns = DB::query(
    "SELECT c.*, u.name as user_name, u.email as user_email, u.role as user_role
     FROM campaigns c JOIN users u ON c.user_id = u.id
     $where ORDER BY c.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

$users = DB::query("SELECT id, name FROM users WHERE role != 'admin' ORDER BY name");

// Build query string helper
function adminCmpQs(array $ov = []): string {
    global $search, $status, $userId, $from, $to, $perPage;
    $base = ['from'=>$from,'to'=>$to];
    if ($search)         $base['q']       = $search;
    if ($status)         $base['status']  = $status;
    if ($userId)         $base['user_id'] = $userId;
    return http_build_query(array_filter(array_merge($base, $ov), fn($v) => $v !== null && $v !== ''));
}
$exportUrl = '?' . adminCmpQs(['export'=>'1','page'=>null]);

$defaults = ['from'=>date('Y-m-01'),'to'=>date('Y-m-d'),'q'=>'','status'=>'','user_id'=>0];
$changed  = ($from !== $defaults['from'] || $to !== $defaults['to'] || $search !== $defaults['q'] ||
             $status !== $defaults['status'] || $userId !== $defaults['user_id']);

$pageTitle  = 'Campaigns';
$breadcrumb = [['label'=>'Admin'],['label'=>'Campaigns']];
require_once __DIR__ . '/layout.php';
?>

<div class="page-header">
  <div><h1>Campaign Management</h1><div class="subtitle">View and manage all campaigns across all users</div></div>
  <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-secondary"><i class="fa-solid fa-download"></i> Export CSV</a>
</div>

<!-- KPI Grid -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-bullhorn"></i></div>
    <div class="stat-info"><div class="stat-label">Total Campaigns</div><div class="stat-value"><?= number_format($kpis['total'] ?? 0) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fa-solid fa-circle-notch fa-spin"></i></div>
    <div class="stat-info"><div class="stat-label">Active / Sending</div><div class="stat-value"><?= number_format($kpis['active'] ?? 0) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
    <div class="stat-info"><div class="stat-label">Completed</div><div class="stat-value"><?= number_format($kpis['completed'] ?? 0) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fa-solid fa-users"></i></div>
    <div class="stat-info"><div class="stat-label">Total Recipients</div><div class="stat-value"><?= number_format($kpis['recipients'] ?? 0) ?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-coins"></i></div>
    <div class="stat-info"><div class="stat-label">Units Consumed</div><div class="stat-value"><?= number_format($kpis['units'] ?? 0, 2) ?></div></div>
  </div>
</div>

<!-- Status tabs -->
<div class="tabs" style="margin-bottom:12px">
  <?php foreach ([''=>'All','queued'=>'Queued','sending'=>'Sending','scheduled'=>'Scheduled','completed'=>'Completed','failed'=>'Failed','draft'=>'Draft'] as $k=>$v): ?>
    <a href="?<?= adminCmpQs(['status'=>$k,'page'=>1]) ?>" class="tab-btn <?= $status===$k?'active':'' ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="input-group" style="flex:1;min-width:200px">
        <div class="input-group-text input-addon-left"><i class="fa-solid fa-magnifying-glass"></i></div>
        <input type="text" name="q" class="form-control with-left" placeholder="Campaign name or user…" value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">Owner</label>
        <select name="user_id" class="form-control" style="width:200px">
          <option value="">All Users</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $userId == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">From</label>
        <input type="date" name="from" class="form-control" value="<?= $from ?>" style="width:145px">
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">To</label>
        <input type="date" name="to" class="form-control" value="<?= $to ?>" style="width:145px">
      </div>
      <?php if ($status): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>"><?php endif; ?>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
      <?php if ($changed): ?><a href="/admin/campaigns.php" class="btn btn-secondary">Clear</a><?php endif; ?>
    </form>
  </div>
</div>

<!-- Campaigns Table -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-bullhorn" style="color:var(--primary)"></i> Campaigns <span class="badge badge-muted"><?= number_format($total) ?></span></h3>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Campaign</th>
          <th>Owner</th>
          <th>Sender ID</th>
          <th style="min-width:200px">Progress</th>
          <th>Units</th>
          <th>Status</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($campaigns)): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:40px">No campaigns found for selected filters</td></tr>
        <?php else: ?>
          <?php foreach ($campaigns as $c):
            $sc         = ['draft'=>'muted','scheduled'=>'warning','queued'=>'warning','running'=>'info','sending'=>'info','completed'=>'success','failed'=>'danger'][$c['status']] ?? 'muted';
            $isActive   = in_array($c['status'], ['queued','running','sending']);
            $isDone     = in_array($c['status'], ['completed','failed']);
            $tcnt       = (int)($c['total_count']  ?? 0);
            $scnt       = (int)($c['sent_count']   ?? 0);
            $fcnt       = (int)($c['failed_count'] ?? 0);
            $done       = $scnt + $fcnt;
            $pct        = $tcnt > 0 ? min(100, round($done / $tcnt * 100)) : ($isDone ? 100 : 0);
            $sentPct    = $tcnt > 0 ? min(100, (int)round($scnt / $tcnt * 100)) : ($isDone && $fcnt === 0 ? 100 : 0);
            $failPct    = $tcnt > 0 ? min(100 - $sentPct, (int)round($fcnt  / $tcnt * 100)) : 0;
            $canCancel  = in_array($c['status'], ['queued','scheduled']);
          ?>
          <tr data-campaign-id="<?= $c['id'] ?>" data-status="<?= htmlspecialchars($c['status']) ?>">
            <td style="max-width:240px">
              <a href="/admin/campaign-detail.php?id=<?= $c['id'] ?>"><strong><?= htmlspecialchars($c['name']) ?></strong></a>
              <div style="font-size:11px;color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($c['message']) ?>">
                <?= htmlspecialchars(mb_strimwidth($c['message'], 0, 70, '…')) ?>
              </div>
            </td>
            <td>
              <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($c['user_name']) ?></div>
              <div style="font-size:11px;color:var(--text-secondary)"><?= htmlspecialchars($c['user_email']) ?></div>
              <span class="badge <?= $c['user_role']==='reseller'?'badge-success':'badge-info' ?>" style="font-size:10px"><?= ucfirst($c['user_role']) ?></span>
            </td>
            <td><code style="font-size:12px"><?= htmlspecialchars($c['sender_id']) ?></code></td>
            <td class="progress-cell">
              <?php if ($tcnt > 0 || $isActive || $isDone): ?>
                <div class="campaign-counts" style="display:flex;gap:10px;font-size:12px;font-weight:600;margin-bottom:4px">
                  <span class="cnt-sent" style="color:var(--success)"><i class="fa-solid fa-check"></i> <span class="cnt-val"><?= number_format($scnt) ?></span></span>
                  <span class="cnt-failed" style="color:var(--danger)<?= $fcnt===0?';display:none':'' ?>"><i class="fa-solid fa-xmark"></i> <span class="cnt-val"><?= number_format($fcnt) ?></span></span>
                </div>
                <div class="prog-wrap" style="background:var(--border-color);border-radius:4px;height:6px;overflow:hidden;display:flex" title="<?= $pct ?>% complete">
                  <div class="prog-sent" style="height:100%;width:<?= $sentPct ?>%;background:var(--success);transition:width .4s ease"></div>
                  <div class="prog-fail" style="height:100%;width:<?= $failPct ?>%;background:var(--danger);transition:width .4s ease"></div>
                </div>
                <div style="font-size:10px;color:var(--text-secondary);margin-top:2px">
                  <span class="cnt-pct"><?= $pct ?></span>% of <span class="cnt-total"><?= number_format($tcnt) ?></span>
                </div>
              <?php else: ?>
                <span style="font-size:12px;color:var(--text-secondary)"><?= number_format($tcnt) ?> contacts</span>
              <?php endif; ?>
            </td>
            <td style="font-weight:600;color:var(--primary)"><?= number_format($c['units_used'], 2) ?></td>
            <td class="status-cell">
              <span class="badge badge-<?= $sc ?> campaign-status-badge"><?= ucfirst($c['status']) ?></span>
              <?php if ($isActive): ?>
                <div class="sending-spinner" style="font-size:10px;color:var(--text-secondary);margin-top:2px"><i class="fa-solid fa-circle-notch fa-spin" style="font-size:9px"></i> Sending…</div>
              <?php endif; ?>
            </td>
            <td style="font-size:12px">
              <?= date('d M Y', strtotime($c['created_at'])) ?>
              <?php if ($c['scheduled_at']): ?>
                <div style="font-size:10px;color:var(--warning)"><i class="fa-solid fa-clock"></i> <?= date('d M H:i', strtotime($c['scheduled_at'])) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="btn-group">
                <a href="/admin/campaign-detail.php?id=<?= $c['id'] ?>"
                   class="btn btn-outline btn-sm btn-icon" title="View Campaign"><i class="fa-solid fa-magnifying-glass-chart"></i></a>
                <?php if ($canCancel): ?>
                  <form method="POST" action="/admin/actions/cancel-campaign.php" style="display:inline"
                        onsubmit="return confirm('Cancel campaign \'<?= htmlspecialchars(addslashes($c['name'])) ?>\'?')">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Cancel Campaign"><i class="fa-solid fa-ban"></i></button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
    <div style="font-size:12px;color:var(--text-secondary)">
      <?php if ($total > 0): ?>
        Showing <?= number_format(($page-1)*$perPage+1) ?> – <?= number_format(min($page*$perPage,$total)) ?> of <?= number_format($total) ?>
      <?php else: ?>
        No campaigns found
      <?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?>
      <div style="display:flex;gap:6px">
        <?php if ($page > 1): ?>
          <a href="?<?= adminCmpQs(['page'=>$page-1]) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i> Prev</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?<?= adminCmpQs(['page'=>$page+1]) ?>" class="btn btn-primary btn-sm">Next <i class="fa-solid fa-chevron-right"></i></a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
// Live polling for active campaigns — fast when sending, slow when idle
let pollTimer = null;

function allCampaignIds() {
    return [...document.querySelectorAll('tr[data-campaign-id]')]
        .map(r => r.dataset.campaignId).filter(Boolean);
}
function hasActiveCampaigns() {
    return !!document.querySelector('tr[data-status="queued"],tr[data-status="running"],tr[data-status="sending"]');
}
function scheduleNextPoll() {
    pollTimer = setTimeout(doPoll, hasActiveCampaigns() ? 3000 : 12000);
}

function doPoll() {
    clearTimeout(pollTimer);
    if (document.hidden) return;
    const ids = allCampaignIds();
    if (!ids.length) { scheduleNextPoll(); return; }
    fetch('/admin/api/campaign-status.php?ids=' + ids.join(','))
        .then(r => r.ok ? r.json() : null)
        .then(data => { if (data) data.campaigns.forEach(applyUpdate); })
        .catch(() => {})
        .finally(scheduleNextPoll);
}

function applyUpdate(c) {
    const row = document.querySelector(`tr[data-campaign-id="${c.id}"]`);
    if (!row) return;

    const sent    = parseInt(c.sent_count)   || 0;
    const failed  = parseInt(c.failed_count) || 0;
    const total   = parseInt(c.total_count)  || 0;
    const done    = sent + failed;
    const pct     = total > 0 ? Math.min(100, Math.round(done   / total * 100)) : 0;
    const sentPct = total > 0 ? Math.min(100, Math.round(sent   / total * 100)) : 0;
    const failPct = total > 0 ? Math.min(100 - sentPct, Math.round(failed / total * 100)) : 0;
    const isActive = ['queued','running','sending'].includes(c.status);

    // Inject progress bar if it wasn't there on load
    const cell = row.querySelector('.progress-cell');
    if (cell && !cell.querySelector('.prog-wrap') && (total > 0 || isActive)) {
        cell.innerHTML =
            '<div class="campaign-counts" style="display:flex;gap:10px;font-size:12px;font-weight:600;margin-bottom:4px">' +
            '<span class="cnt-sent" style="color:var(--success)"><i class="fa-solid fa-check"></i> <span class="cnt-val">0</span></span>' +
            '<span class="cnt-failed" style="color:var(--danger);display:none"><i class="fa-solid fa-xmark"></i> <span class="cnt-val">0</span></span>' +
            '</div>' +
            '<div class="prog-wrap" style="background:var(--border-color);border-radius:4px;height:6px;overflow:hidden;display:flex">' +
            '<div class="prog-sent" style="height:100%;width:0%;background:var(--success);transition:width .4s ease"></div>' +
            '<div class="prog-fail" style="height:100%;width:0%;background:var(--danger);transition:width .4s ease"></div>' +
            '</div>' +
            '<div style="font-size:10px;color:var(--text-secondary);margin-top:2px"><span class="cnt-pct">0</span>% of <span class="cnt-total">0</span></div>';
    }

    const sentVal  = row.querySelector('.cnt-sent .cnt-val');
    const failVal  = row.querySelector('.cnt-failed .cnt-val');
    const failSpan = row.querySelector('.cnt-failed');
    const pctEl    = row.querySelector('.cnt-pct');
    const totalEl  = row.querySelector('.cnt-total');
    const progSent = row.querySelector('.prog-sent');
    const progFail = row.querySelector('.prog-fail');

    if (sentVal)  sentVal.textContent  = sent.toLocaleString();
    if (failVal)  failVal.textContent  = failed.toLocaleString();
    if (pctEl)    pctEl.textContent    = pct;
    if (totalEl)  totalEl.textContent  = total.toLocaleString();
    if (failSpan) failSpan.style.display = failed > 0 ? '' : 'none';
    if (progSent) progSent.style.width   = sentPct + '%';
    if (progFail) progFail.style.width   = failPct + '%';

    if (row.dataset.status !== c.status) {
        row.dataset.status = c.status;
        const badge = row.querySelector('.campaign-status-badge');
        if (badge) {
            const cls = {draft:'muted',scheduled:'warning',queued:'warning',running:'info',sending:'info',completed:'success',failed:'danger'};
            badge.className = `badge badge-${cls[c.status]||'muted'} campaign-status-badge`;
            badge.textContent = c.status.charAt(0).toUpperCase() + c.status.slice(1);
        }
    }

    const statusCell = row.querySelector('.status-cell');
    if (statusCell) {
        const spinner = statusCell.querySelector('.sending-spinner');
        if (isActive && !spinner) {
            const d = document.createElement('div');
            d.className = 'sending-spinner';
            d.style.cssText = 'font-size:10px;color:var(--text-secondary);margin-top:2px';
            d.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin" style="font-size:9px"></i> Sending…';
            statusCell.appendChild(d);
        } else if (!isActive && spinner) {
            spinner.remove();
        }
    }
}

document.addEventListener('visibilitychange', () => { if (!document.hidden) doPoll(); });
doPoll();
</script>
JS;

include __DIR__ . '/../includes/layout-footer.php';
?>
