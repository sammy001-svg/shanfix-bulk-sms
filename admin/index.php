<?php
$pageTitle  = 'Dashboard';
$breadcrumb = [['label'=>'Admin'],['label'=>'Dashboard']];

require_once __DIR__ . '/layout.php';

// ── Stats — split by cache lifetime ───────────────────────────
// Stable counts (users, campaigns, revenue) refresh every hour.
// Today's messages refresh every 5 minutes since it updates throughout the day.
$_now = time();

if (!isset($_SESSION['_dash_stable_ts']) || ($_now - $_SESSION['_dash_stable_ts']) > 3600) {
    $_SESSION['_dash_stable'] = [
        'totalUsers'     => (int)(DB::queryOne("SELECT COUNT(*) as c FROM users WHERE role != 'admin'")['c'] ?? 0),
        'totalResellers' => (int)(DB::queryOne("SELECT COUNT(*) as c FROM users WHERE role = 'reseller'")['c'] ?? 0),
        'totalClients'   => (int)(DB::queryOne("SELECT COUNT(*) as c FROM users WHERE role = 'client'")['c'] ?? 0),
        'totalCampaigns' => (int)(DB::queryOne("SELECT COUNT(*) as c FROM campaigns")['c'] ?? 0),
        'totalRevenue'   => (float)(DB::queryOne("SELECT COALESCE(SUM(amount),0) as s FROM purchases WHERE status='completed'")['s'] ?? 0),
        'chartLabels'    => null,
        'chartValues'    => null,
        'topResellers'   => null,
    ];
    // Chart data (last 7 days) — historical, cache with stable data
    $chartData = DB::query("
        SELECT DATE(created_at) as day, COUNT(*) as total
        FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at) ORDER BY day ASC
    ");
    $_SESSION['_dash_stable']['chartLabels'] = json_encode(array_column($chartData, 'day'));
    $_SESSION['_dash_stable']['chartValues'] = json_encode(array_column($chartData, 'total'));

    // Top resellers — correlated subqueries instead of joining campaigns ×
    // messages, which multiplied into (campaigns × messages) rows per user
    // and could freeze the page for minutes on a large messages table.
    // (The old SUM(m.id) also summed message IDs instead of counting them.)
    $_SESSION['_dash_stable']['topResellers'] = DB::query("
        SELECT u.name, u.email, u.sms_units,
               (SELECT COUNT(*) FROM campaigns c WHERE c.user_id = u.id) as campaigns,
               (SELECT COUNT(*) FROM messages  m WHERE m.user_id = u.id) as messages_sent
        FROM users u
        WHERE u.role = 'reseller'
        ORDER BY messages_sent DESC LIMIT 5
    ");
    $_SESSION['_dash_stable_ts'] = $_now;
}

$totalUsers     = $_SESSION['_dash_stable']['totalUsers'];
$totalResellers = $_SESSION['_dash_stable']['totalResellers'];
$totalClients   = $_SESSION['_dash_stable']['totalClients'];
$totalCampaigns = $_SESSION['_dash_stable']['totalCampaigns'];
$totalRevenue   = $_SESSION['_dash_stable']['totalRevenue'];
$chartLabels    = $_SESSION['_dash_stable']['chartLabels'];
$chartValues    = $_SESSION['_dash_stable']['chartValues'];
$topResellers   = $_SESSION['_dash_stable']['topResellers'];

// Today's send count — refreshes every 5 minutes
if (!isset($_SESSION['_dash_today_ts']) || ($_now - $_SESSION['_dash_today_ts']) > 300) {
    $_SESSION['_dash_today']    = (int)(DB::queryOne("SELECT COUNT(*) as c FROM messages WHERE created_at >= CURDATE()")['c'] ?? 0);
    $_SESSION['_dash_today_ts'] = $_now;
}
$sentToday = $_SESSION['_dash_today'];

$pendingIds = $pendingSenderIds; // already fetched by layout.php badge cache

// ── Onfon Live Balance ─────────────────────────────────────────
require_once __DIR__ . '/../includes/gateways/onfon.php';
$adminUser  = DB::queryOne("SELECT id, sms_units FROM users WHERE role = 'admin' LIMIT 1");
$localUnits = $adminUser['sms_units'] ?? 0;

// ── Recent Campaigns (short-lived — refreshes every 2 minutes) ─
if (!isset($_SESSION['_dash_campaigns_ts']) || ($_now - $_SESSION['_dash_campaigns_ts']) > 120) {
    $_SESSION['_dash_campaigns'] = DB::query("
        SELECT c.*, u.name as user_name
        FROM campaigns c JOIN users u ON c.user_id = u.id
        ORDER BY c.created_at DESC LIMIT 8
    ");
    $_SESSION['_dash_campaigns_ts'] = $_now;
}
$recentCampaigns = $_SESSION['_dash_campaigns'];
?>

<!-- Stats Grid -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-users"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Users</div>
      <div class="stat-value"><?= number_format($totalUsers) ?></div>
      <div class="stat-trend up"><i class="fa-solid fa-arrow-trend-up"></i> <?= $totalResellers ?> Resellers · <?= $totalClients ?> Clients</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-paper-plane"></i></div>
    <div class="stat-info">
      <div class="stat-label">Sent Today</div>
      <div class="stat-value"><?= number_format($sentToday) ?></div>
      <div class="stat-trend up"><i class="fa-solid fa-arrow-trend-up"></i> Messages dispatched</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fa-solid fa-bullhorn"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Campaigns</div>
      <div class="stat-value"><?= number_format($totalCampaigns) ?></div>
      <div class="stat-trend up"><i class="fa-solid fa-arrow-trend-up"></i> All time</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-circle-dollar-to-slot"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Revenue</div>
      <div class="stat-value">KES <?= number_format($totalRevenue) ?></div>
      <div class="stat-trend up"><i class="fa-solid fa-arrow-trend-up"></i> Completed purchases</div>
    </div>
  </div>
  <!-- Provider Balance Card -->
  <div class="stat-card">
    <div class="stat-icon blue" style="position:relative; z-index:2;"><i class="fa-solid fa-cloud-arrow-down"></i></div>
    <div class="stat-info" style="position:relative; z-index:2;">
      <div class="stat-label">Onfon Live Balance</div>
      <div class="stat-value" id="providerBalance"><?= number_format($localUnits, 2) ?></div>
      <div class="stat-trend">
        <form method="POST" action="/admin/actions/sync-balance.php" id="syncForm" style="display:inline">
            <?= csrf_field() ?>
            <button type="button" class="btn btn-link btn-sm" style="padding:0; font-size:12px; text-decoration:none; color:var(--primary); cursor:pointer;" onclick="this.innerHTML='<i class=\'fa-solid fa-spinner fa-spin\'></i> Syncing...'; this.style.opacity='0.6'; this.form.submit();">
                <i class="fa-solid fa-rotate"></i> Sync now
            </button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Charts + Pending Sender IDs -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">
  <!-- Messages Chart -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-chart-line" style="color:var(--primary)"></i> Messages Sent (Last 7 Days)</h3>
      <button class="btn btn-secondary btn-sm" onclick="exportChart()"><i class="fa-solid fa-download"></i> Export</button>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:260px">
        <canvas id="messagesChart"></canvas>
      </div>
    </div>
  </div>

  <!-- Pending Sender IDs -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-id-badge" style="color:var(--warning)"></i> Pending Sender IDs</h3>
      <a href="/admin/sender-ids.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="card-body">
      <?php
      $pending = DB::query("
        SELECT s.*, u.name, u.email
        FROM sender_ids s JOIN users u ON s.user_id = u.id
        WHERE s.status = 'pending' ORDER BY s.created_at DESC LIMIT 5
      ");
      if (empty($pending)): ?>
        <div class="empty-state" style="padding:30px 10px">
          <div class="empty-icon">✅</div>
          <p>No pending requests</p>
        </div>
      <?php else: ?>
        <?php foreach ($pending as $sid): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)">
            <div>
              <div style="font-weight:700;font-size:13px"><?= htmlspecialchars($sid['sender_id']) ?></div>
              <div style="font-size:11px;color:var(--text-secondary)"><?= htmlspecialchars($sid['name']) ?></div>
            </div>
            <div style="display:flex;gap:6px">
              <form method="POST" action="/admin/actions/approve-sender-id.php" style="display:inline">
                <input type="hidden" name="id" value="<?= $sid['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <button class="btn btn-primary btn-sm" title="Approve">✓</button>
              </form>
              <a href="/admin/sender-ids.php?reject=<?= $sid['id'] ?>" class="btn btn-danger btn-sm" title="Reject">✕</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Recent Campaigns Table -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-list" style="color:var(--primary)"></i> Recent Campaigns</h3>
    <a href="/admin/reports.php" class="btn btn-outline btn-sm">Full Report</a>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Campaign</th><th>User</th><th>Sender ID</th>
          <th>Total</th><th>Sent</th><th>Failed</th>
          <th>Units Used</th><th>Status</th><th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recentCampaigns)): ?>
          <tr><td colspan="9" class="text-center text-muted" style="padding:30px">No campaigns yet</td></tr>
        <?php else: ?>
          <?php foreach ($recentCampaigns as $c): ?>
            <tr>
              <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
              <td><?= htmlspecialchars($c['user_name']) ?></td>
              <td><code><?= htmlspecialchars($c['sender_id']) ?></code></td>
              <td><?= number_format($c['total_count']) ?></td>
              <td style="color:var(--success)"><?= number_format($c['sent_count']) ?></td>
              <td style="color:var(--danger)"><?= number_format($c['failed_count']) ?></td>
              <td><?= number_format($c['units_used'],2) ?></td>
              <td>
                <?php $statusColor = ['completed'=>'success','sending'=>'info','queued'=>'warning','failed'=>'danger','draft'=>'muted'][$c['status']] ?? 'muted'; ?>
                <span class="badge badge-<?= $statusColor ?>"><?= ucfirst($c['status']) ?></span>
              </td>
              <td style="font-size:12px"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Top Resellers -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-trophy" style="color:var(--warning)"></i> Top Resellers</h3>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>#</th><th>Name</th><th>Email</th><th>Units</th><th>Campaigns</th><th>Messages Sent</th></tr>
      </thead>
      <tbody>
        <?php if (empty($topResellers)): ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:30px">No resellers yet</td></tr>
        <?php else: ?>
          <?php foreach ($topResellers as $i => $r): ?>
            <tr>
              <td><strong>#<?= $i+1 ?></strong></td>
              <td><?= htmlspecialchars($r['name']) ?></td>
              <td style="color:var(--text-secondary)"><?= htmlspecialchars($r['email']) ?></td>
              <td><strong style="color:var(--primary)"><?= number_format($r['sms_units'],2) ?></strong></td>
              <td><?= number_format($r['campaigns']) ?></td>
              <td><?= number_format($r['messages_sent']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$extraScript = <<<JS
<script>
(function() {
  const ctx = document.getElementById('messagesChart');
  if (!ctx) return;
  const labels = {$chartLabels} || ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  const values = {$chartValues} || [0,0,0,0,0,0,0];
  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Messages Sent',
        data: values,
        borderColor: '#00c896',
        backgroundColor: 'rgba(0,200,150,0.08)',
        borderWidth: 2.5,
        pointBackgroundColor: '#00c896',
        pointRadius: 4,
        pointHoverRadius: 7,
        fill: true, tension: 0.45
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color:'rgba(0,0,0,0.05)' }, ticks: { font:{size:11} } },
        y: { grid: { color:'rgba(0,0,0,0.05)' }, beginAtZero: true, ticks: { font:{size:11} } }
      }
    }
  });
})();

function exportChart() {
  const canvas = document.getElementById('messagesChart');
  if (!canvas) return;
  const link = document.createElement('a');
  link.download = 'messages-last-7-days.png';
  link.href = canvas.toDataURL('image/png');
  link.click();
}
</script>
JS;

include __DIR__ . '/../includes/layout-footer.php';
?>
