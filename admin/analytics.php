<?php
$pageTitle  = 'Analytics';
$breadcrumb = [['label'=>'Admin'],['label'=>'Analytics']];
require_once __DIR__ . '/layout.php';

// ── Period ───────────────────────────────────────────────────────
$validPeriods = ['7'=>7, '30'=>30, '90'=>90, '365'=>365];
$period  = isset($validPeriods[$_GET['period'] ?? '']) ? (int)$_GET['period'] : 30;
$from    = date('Y-m-d', strtotime("-{$period} days"));
$to      = date('Y-m-d');
$prevFrom = date('Y-m-d', strtotime("-" . ($period * 2) . " days"));
$prevTo   = date('Y-m-d', strtotime("-{$period} days"));

// ── KPI helpers ──────────────────────────────────────────────────
function kpiPct($current, $prev): string {
    if ($prev == 0) return $current > 0 ? '+100%' : '—';
    $pct = round(($current - $prev) / $prev * 100, 1);
    return ($pct >= 0 ? '+' : '') . $pct . '%';
}
function kpiDir($current, $prev): string {
    if ($prev == 0) return $current > 0 ? 'up' : '';
    return $current >= $prev ? 'up' : 'down';
}

// ── Messages ────────────────────────────────────────────────────
$msgCur  = (int)(DB::queryOne("SELECT COUNT(*) as c FROM messages WHERE created_at >= ? AND created_at <= ?", [$from, $to . ' 23:59:59'])['c'] ?? 0);
$msgPrev = (int)(DB::queryOne("SELECT COUNT(*) as c FROM messages WHERE created_at >= ? AND created_at < ?",  [$prevFrom, $prevTo])['c'] ?? 0);

// ── Delivery breakdown (period) ───────────────────────────────────
$delivery = DB::queryOne(
    "SELECT COUNT(*) as total,
            SUM(status='sent') as sent,
            SUM(status='delivered') as delivered,
            SUM(status='failed') as failed_count,
            SUM(status='queued') as queued_count,
            SUM(status='undelivered') as undelivered_count
     FROM messages WHERE created_at >= ? AND created_at <= ?",
    [$from, $to . ' 23:59:59']
);

// ── Revenue ─────────────────────────────────────────────────────
$revCur  = (float)(DB::queryOne("SELECT COALESCE(SUM(amount),0) as t FROM purchases WHERE status='completed' AND created_at >= ? AND created_at <= ?", [$from, $to . ' 23:59:59'])['t'] ?? 0);
$revPrev = (float)(DB::queryOne("SELECT COALESCE(SUM(amount),0) as t FROM purchases WHERE status='completed' AND created_at >= ? AND created_at < ?",  [$prevFrom, $prevTo])['t'] ?? 0);

// ── Campaigns ───────────────────────────────────────────────────
$campCur  = (int)(DB::queryOne("SELECT COUNT(*) as c FROM campaigns WHERE created_at >= ? AND created_at <= ?", [$from, $to . ' 23:59:59'])['c'] ?? 0);
$campPrev = (int)(DB::queryOne("SELECT COUNT(*) as c FROM campaigns WHERE created_at >= ? AND created_at < ?",  [$prevFrom, $prevTo])['c'] ?? 0);

// ── New users ────────────────────────────────────────────────────
$newUsersCur  = (int)(DB::queryOne("SELECT COUNT(*) as c FROM users WHERE role!='admin' AND created_at >= ? AND created_at <= ?", [$from, $to . ' 23:59:59'])['c'] ?? 0);
$newUsersPrev = (int)(DB::queryOne("SELECT COUNT(*) as c FROM users WHERE role!='admin' AND created_at >= ? AND created_at < ?",  [$prevFrom, $prevTo])['c'] ?? 0);

// ── All-time totals ──────────────────────────────────────────────
$totals = [
    'users'     => (int)(DB::queryOne("SELECT COUNT(*) as c FROM users WHERE role!='admin'")['c'] ?? 0),
    'revenue'   => (float)(DB::queryOne("SELECT COALESCE(SUM(amount),0) as t FROM purchases WHERE status='completed'")['t'] ?? 0),
    'units_sold' => (float)(DB::queryOne("SELECT COALESCE(SUM(units),0) as t FROM purchases WHERE status='completed'")['t'] ?? 0),
    'active_users'=> (int)(DB::queryOne("SELECT COUNT(*) as c FROM users WHERE role!='admin' AND status='active'")['c'] ?? 0),
];

// ── Chart: Message volume per day ────────────────────────────────
$msgTrend = DB::query(
    "SELECT DATE(created_at) as day, COUNT(*) as total,
            SUM(status='sent') as sent,
            SUM(status='delivered') as delivered,
            SUM(status='failed') as failed_count
     FROM messages WHERE created_at >= ? AND created_at <= ?
     GROUP BY day ORDER BY day",
    [$from, $to . ' 23:59:59']
);
$trendDays  = json_encode(array_column($msgTrend, 'day'));
$trendTotal = json_encode(array_column($msgTrend, 'total'));
$trendSent  = json_encode(array_column($msgTrend, 'sent'));
$trendFail  = json_encode(array_column($msgTrend, 'failed_count'));

// ── Chart: Revenue per day ────────────────────────────────────────
$revTrend = DB::query(
    "SELECT DATE(created_at) as day, SUM(amount) as total
     FROM purchases WHERE status='completed' AND created_at >= ? AND created_at <= ?
     GROUP BY day ORDER BY day",
    [$from, $to . ' 23:59:59']
);
$revDays = json_encode(array_column($revTrend, 'day'));
$revAmts = json_encode(array_column($revTrend, 'total'));

// ── Chart: User signups per week ──────────────────────────────────
$userGrowth = DB::query(
    "SELECT DATE(created_at) as day, COUNT(*) as total
     FROM users WHERE role!='admin' AND created_at >= ? AND created_at <= ?
     GROUP BY day ORDER BY day",
    [$from, $to . ' 23:59:59']
);
$growthDays   = json_encode(array_column($userGrowth, 'day'));
$growthCounts = json_encode(array_column($userGrowth, 'total'));

// ── Campaign status breakdown ─────────────────────────────────────
$campStatus = DB::queryOne(
    "SELECT SUM(status='completed') as completed, SUM(status='failed') as failed_count,
            SUM(status IN ('queued','running','sending')) as active,
            SUM(status='draft') as draft, SUM(status='scheduled') as scheduled
     FROM campaigns WHERE created_at >= ? AND created_at <= ?",
    [$from, $to . ' 23:59:59']
);

// ── Top senders ───────────────────────────────────────────────────
$topSenders = DB::query(
    "SELECT u.name, u.role, COUNT(m.id) as msgs, COALESCE(SUM(m.units_charged),0) as units
     FROM messages m JOIN users u ON m.user_id = u.id
     WHERE m.created_at >= ? AND m.created_at <= ?
     GROUP BY m.user_id ORDER BY msgs DESC LIMIT 8",
    [$from, $to . ' 23:59:59']
);

// ── Top revenue earners ──────────────────────────────────────────
$topRevenue = DB::query(
    "SELECT u.name, u.role, COUNT(p.id) as purchases, COALESCE(SUM(p.amount),0) as revenue, COALESCE(SUM(p.units),0) as units
     FROM purchases p JOIN users u ON p.user_id = u.id
     WHERE p.status = 'completed' AND p.created_at >= ? AND p.created_at <= ?
     GROUP BY p.user_id ORDER BY revenue DESC LIMIT 8",
    [$from, $to . ' 23:59:59']
);

// ── Hourly message distribution ───────────────────────────────────
$hourly = DB::query(
    "SELECT HOUR(created_at) as hr, COUNT(*) as total
     FROM messages WHERE created_at >= ? AND created_at <= ?
     GROUP BY hr ORDER BY hr",
    [$from, $to . ' 23:59:59']
);
$hourlyHrs  = array_fill(0, 24, 0);
foreach ($hourly as $h) { $hourlyHrs[(int)$h['hr']] = (int)$h['total']; }
$hourlyLabels = json_encode(array_map(fn($h) => str_pad($h,2,'0',STR_PAD_LEFT).':00', range(0,23)));
$hourlyValues = json_encode(array_values($hourlyHrs));

// ── JS vars ──────────────────────────────────────────────────────
$dSent        = (int)($delivery['sent']        ?? 0);
$dDelivered   = (int)($delivery['delivered']   ?? 0);
$dFailed      = (int)($delivery['failed_count'] ?? 0);
$dUndelivered = (int)($delivery['undelivered_count'] ?? 0);
$dQueued      = (int)($delivery['queued_count'] ?? 0);
?>

<div class="page-header">
  <div>
    <h1>Platform Analytics</h1>
    <div class="subtitle">Updated <?= date('d M Y, H:i') ?></div>
  </div>
  <div style="display:flex;gap:8px;align-items:center">
    <span style="font-size:12px;color:var(--text-secondary)">Period:</span>
    <?php foreach ([7=>'7 Days', 30=>'30 Days', 90=>'90 Days', 365=>'1 Year'] as $d => $label): ?>
      <a href="?period=<?=$d?>" class="btn btn-sm <?=$period===$d?'btn-primary':'btn-secondary'?>"><?=$label?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- All-time strip -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;padding:14px 16px;background:var(--bg-muted);border-radius:var(--radius-md);border:1px solid var(--border)">
  <?php foreach ([
    ['label'=>'Total Users',    'value'=> number_format($totals['users']),    'sub'=> $totals['active_users'].' active'],
    ['label'=>'Total Revenue',  'value'=> 'KES '.number_format($totals['revenue'],0), 'sub'=>'All time'],
    ['label'=>'Units Sold',     'value'=> number_format($totals['units_sold'],0), 'sub'=>'All time'],
    ['label'=>'Total Messages', 'value'=> number_format($msgCur + $msgPrev),  'sub'=>'Lifetime (approx)'],
  ] as $s): ?>
    <div style="text-align:center">
      <div style="font-size:11px;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px"><?=$s['label']?></div>
      <div style="font-size:20px;font-weight:800;color:var(--text-primary)"><?=$s['value']?></div>
      <div style="font-size:11px;color:var(--text-secondary)"><?=$s['sub']?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Period KPI Cards -->
<div style="margin-bottom:6px;font-size:11px;color:var(--text-secondary);font-weight:600;text-transform:uppercase;letter-spacing:.06em">
  Last <?=$period?> days vs previous <?=$period?> days
</div>
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <?php
  $kpiCards = [
    ['icon'=>'<i class="fa-solid fa-paper-plane"></i>',  'color'=>'blue',   'label'=>'Messages Sent',  'cur'=>$msgCur,      'prev'=>$msgPrev,      'fmt'=>'number'],
    ['icon'=>'<i class="fa-solid fa-money-bill-wave"></i>','color'=>'green', 'label'=>'Revenue (KES)',  'cur'=>$revCur,      'prev'=>$revPrev,      'fmt'=>'currency'],
    ['icon'=>'<i class="fa-solid fa-bullhorn"></i>',     'color'=>'orange', 'label'=>'Campaigns',      'cur'=>$campCur,     'prev'=>$campPrev,     'fmt'=>'number'],
    ['icon'=>'<i class="fa-solid fa-user-plus"></i>',    'color'=>'blue',   'label'=>'New Users',      'cur'=>$newUsersCur, 'prev'=>$newUsersPrev, 'fmt'=>'number'],
  ];
  foreach ($kpiCards as $k):
    $pct = kpiPct($k['cur'], $k['prev']);
    $dir = kpiDir($k['cur'], $k['prev']);
    $val = $k['fmt'] === 'currency' ? 'KES '.number_format($k['cur'], 0) : number_format($k['cur']);
  ?>
    <div class="stat-card" style="margin:0">
      <div class="stat-icon <?=$k['color']?>"><?=$k['icon']?></div>
      <div class="stat-info">
        <div class="stat-label"><?=$k['label']?></div>
        <div class="stat-value"><?=$val?></div>
        <div class="stat-trend <?=$dir?>">
          <i class="fa-solid fa-arrow-<?=$dir==='up'?'up':'down'?>"></i> <?=$pct?> vs prev period
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Row 1: Message trend + Delivery donut -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-chart-area" style="color:var(--primary)"></i> Message Volume (Last <?=$period?> Days)</h3>
    </div>
    <div class="card-body"><div class="chart-container" style="height:250px"><canvas id="msgTrendChart"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-chart-pie" style="color:var(--primary)"></i> Delivery Breakdown</h3></div>
    <div class="card-body">
      <div class="chart-container" style="height:200px"><canvas id="donutChart"></canvas></div>
      <?php
      $dTotal = $dSent + $dDelivered + $dFailed + $dUndelivered + $dQueued;
      $delivRate = $dTotal > 0 ? round(($dDelivered + $dSent) / $dTotal * 100, 1) : 0;
      ?>
      <div style="text-align:center;margin-top:10px">
        <div style="font-size:24px;font-weight:800;color:var(--primary)"><?=$delivRate?>%</div>
        <div style="font-size:11px;color:var(--text-secondary)">delivery success rate</div>
      </div>
    </div>
  </div>
</div>

<!-- Row 2: Revenue + Hourly pattern -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-chart-bar" style="color:var(--primary)"></i> Revenue Trend</h3></div>
    <div class="card-body"><div class="chart-container" style="height:200px"><canvas id="revChart"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-clock" style="color:var(--primary)"></i> Hourly Message Pattern</h3></div>
    <div class="card-body"><div class="chart-container" style="height:200px"><canvas id="hourlyChart"></canvas></div></div>
  </div>
</div>

<!-- Row 3: User growth + Campaign breakdown -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-chart-line" style="color:var(--primary)"></i> User Sign-ups</h3></div>
    <div class="card-body"><div class="chart-container" style="height:200px"><canvas id="growthChart"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-bullhorn" style="color:var(--primary)"></i> Campaign Outcomes</h3></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:10px;padding-top:12px">
      <?php foreach ([
        ['Completed', (int)($campStatus['completed'] ?? 0), 'success'],
        ['Active',    (int)($campStatus['active'] ?? 0),    'info'],
        ['Scheduled', (int)($campStatus['scheduled'] ?? 0), 'warning'],
        ['Draft',     (int)($campStatus['draft'] ?? 0),     'muted'],
        ['Failed',    (int)($campStatus['failed_count'] ?? 0), 'danger'],
      ] as [$label, $count, $color]):
        $pct = $campCur > 0 ? round($count / $campCur * 100) : 0;
      ?>
        <div>
          <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
            <span style="font-weight:600"><?=$label?></span>
            <span><?=number_format($count)?> <span style="color:var(--text-secondary)">(<?=$pct?>%)</span></span>
          </div>
          <div style="height:6px;background:var(--bg-muted);border-radius:3px">
            <div style="height:6px;background:var(--<?=$color==='muted'?'text-secondary':$color?>);border-radius:3px;width:<?=$pct?>%;transition:width .4s"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Row 4: Top senders + Top revenue -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-trophy" style="color:var(--primary)"></i> Top Senders</h3>
      <span style="font-size:11px;color:var(--text-secondary)">Last <?=$period?> days</span>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>#</th><th>User</th><th>Role</th><th>Messages</th><th>Units</th></tr></thead>
        <tbody>
          <?php if (empty($topSenders)): ?>
            <tr><td colspan="5" class="text-center text-muted" style="padding:20px">No data yet</td></tr>
          <?php else: foreach ($topSenders as $i => $s): ?>
            <tr>
              <td><strong style="color:var(--primary)"><?=$i+1?></strong></td>
              <td style="font-weight:600"><?=htmlspecialchars($s['name'])?></td>
              <td><span class="badge <?=$s['role']==='reseller'?'badge-success':'badge-info'?>"><?=ucfirst($s['role'])?></span></td>
              <td><?=number_format($s['msgs'])?></td>
              <td><?=number_format($s['units'],2)?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-money-bill-wave" style="color:var(--primary)"></i> Top Revenue</h3>
      <span style="font-size:11px;color:var(--text-secondary)">Last <?=$period?> days</span>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>#</th><th>User</th><th>Role</th><th>Purchases</th><th>KES</th></tr></thead>
        <tbody>
          <?php if (empty($topRevenue)): ?>
            <tr><td colspan="5" class="text-center text-muted" style="padding:20px">No data yet</td></tr>
          <?php else: foreach ($topRevenue as $i => $r): ?>
            <tr>
              <td><strong style="color:var(--primary)"><?=$i+1?></strong></td>
              <td style="font-weight:600"><?=htmlspecialchars($r['name'])?></td>
              <td><span class="badge <?=$r['role']==='reseller'?'badge-success':'badge-info'?>"><?=ucfirst($r['role'])?></span></td>
              <td><?=number_format($r['purchases'])?></td>
              <td style="font-weight:700;color:var(--primary)"><?=number_format($r['revenue'],0)?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$extraScript = <<<JS
<script>
(function(){
  const green  = '#00c896', blue = '#3b82f6', orange = '#f97316', red = '#ef4444', purple = '#8b5cf6';
  const gridC  = 'rgba(128,128,128,0.08)';
  const defOpts = {responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}},
    scales:{x:{grid:{display:false}, ticks:{maxTicksLimit:10, font:{size:10}}}, y:{beginAtZero:true, grid:{color:gridC}, ticks:{font:{size:10}}}}};

  // 1. Message volume (multi-line)
  new Chart(document.getElementById('msgTrendChart'), {
    type:'line',
    data:{
      labels: {$trendDays}||[],
      datasets:[
        {label:'Total',    data:{$trendTotal}||[], borderColor:blue,   backgroundColor:'rgba(59,130,246,0.08)',  fill:true, tension:.4, pointRadius:2, borderWidth:2},
        {label:'Sent',     data:{$trendSent}||[],  borderColor:green,  backgroundColor:'rgba(0,200,150,0.06)',   fill:false, tension:.4, pointRadius:2, borderWidth:1.5, borderDash:[]},
        {label:'Failed',   data:{$trendFail}||[],  borderColor:red,    backgroundColor:'rgba(239,68,68,0.05)',   fill:false, tension:.4, pointRadius:2, borderWidth:1.5},
      ]
    },
    options:{...defOpts, plugins:{legend:{display:true, position:'top', labels:{boxWidth:10, font:{size:11}}}}}
  });

  // 2. Delivery donut
  new Chart(document.getElementById('donutChart'), {
    type:'doughnut',
    data:{labels:['Sent','Delivered','Failed','Undelivered','Queued'],
      datasets:[{data:[{$dSent},{$dDelivered},{$dFailed},{$dUndelivered},{$dQueued}],
        backgroundColor:[blue, green, red, orange, purple], borderWidth:0, hoverOffset:4}]},
    options:{responsive:true, maintainAspectRatio:false, cutout:'68%',
      plugins:{legend:{position:'bottom', labels:{boxWidth:10, font:{size:11}}}}}
  });

  // 3. Revenue bar
  new Chart(document.getElementById('revChart'), {
    type:'bar',
    data:{labels:{$revDays}||[], datasets:[{label:'KES', data:{$revAmts}||[], backgroundColor:'rgba(0,200,150,0.75)', borderRadius:5, borderSkipped:false}]},
    options:defOpts
  });

  // 4. Hourly heatmap-style bar
  new Chart(document.getElementById('hourlyChart'), {
    type:'bar',
    data:{labels:{$hourlyLabels}, datasets:[{label:'Messages', data:{$hourlyValues},
      backgroundColor: ({chart, dataIndex}) => {
        const max = Math.max(...chart.data.datasets[0].data);
        const v = chart.data.datasets[0].data[dataIndex];
        const alpha = max > 0 ? 0.15 + (v/max)*0.8 : 0.15;
        return 'rgba(0,200,150,' + alpha + ')';
      },
      borderRadius:3, borderSkipped:false}]},
    options:{...defOpts, scales:{...defOpts.scales, x:{...defOpts.scales.x, ticks:{...defOpts.scales.x.ticks, maxTicksLimit:8}}}}
  });

  // 5. User growth
  new Chart(document.getElementById('growthChart'), {
    type:'line',
    data:{labels:{$growthDays}||[], datasets:[{label:'Sign-ups', data:{$growthCounts}||[],
      borderColor:purple, backgroundColor:'rgba(139,92,246,0.08)', fill:true, tension:.4, pointRadius:3, borderWidth:2}]},
    options:defOpts
  });
})();
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
