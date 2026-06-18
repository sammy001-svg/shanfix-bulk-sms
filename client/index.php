<?php
$pageTitle  = 'Dashboard';
$breadcrumb = [['label'=>'Client'],['label'=>'Dashboard']];
require_once __DIR__ . '/layout.php';

$uid  = $user['id'];
$rate = (float)($user['custom_unit_price'] ?? 1.00);

// ── Period stats (current + previous month) ──────────────────────
$thisMonthStart = date('Y-m-01');
$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd   = date('Y-m-t', strtotime('-1 month'));

$statsCur = DB::queryOne(
    "SELECT COUNT(*) as total,
            SUM(status IN ('sent','delivered')) as sent_ok,
            SUM(status='delivered') as delivered,
            SUM(status='failed') as failed,
            COALESCE(SUM(units_charged),0) as units_used
     FROM messages WHERE user_id=? AND created_at >= ?",
    [$uid, $thisMonthStart]
);
$statsPrev = DB::queryOne(
    "SELECT COUNT(*) as total,
            SUM(status IN ('sent','delivered')) as sent_ok
     FROM messages WHERE user_id=? AND created_at >= ? AND created_at <= ?",
    [$uid, $lastMonthStart, $lastMonthEnd . ' 23:59:59']
);

$deliveryRate  = ($statsCur['total'] ?? 0) > 0
    ? round(($statsCur['sent_ok'] ?? 0) / $statsCur['total'] * 100, 1) : 0;
$prevRate      = ($statsPrev['total'] ?? 0) > 0
    ? round(($statsPrev['sent_ok'] ?? 0) / $statsPrev['total'] * 100, 1) : 0;

$unitsBalance = (float)$user['sms_units'];
$totalCampaigns = (int)(DB::queryOne("SELECT COUNT(*) as c FROM campaigns WHERE user_id=?", [$uid])['c'] ?? 0);
$activeCampaigns = (int)(DB::queryOne(
    "SELECT COUNT(*) as c FROM campaigns WHERE user_id=? AND status IN ('queued','running','sending')", [$uid]
)['c'] ?? 0);

// ── Spending this month ───────────────────────────────────────────
$spentThisMonth = (float)(DB::queryOne(
    "SELECT COALESCE(SUM(amount),0) as t FROM purchases WHERE user_id=? AND status='completed' AND created_at >= ?",
    [$uid, $thisMonthStart]
)['t'] ?? 0);

// ── 14-day chart ─────────────────────────────────────────────────
$chartData = DB::query(
    "SELECT DATE(created_at) as day,
            COUNT(*) as total,
            SUM(status IN ('sent','delivered')) as sent_ok,
            SUM(status='failed') as failed_count
     FROM messages WHERE user_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
     GROUP BY day ORDER BY day",
    [$uid]
);
$chartDays   = json_encode(array_column($chartData, 'day'));
$chartTotal  = json_encode(array_column($chartData, 'total'));
$chartSent   = json_encode(array_column($chartData, 'sent_ok'));
$chartFailed = json_encode(array_column($chartData, 'failed_count'));

// ── Recent campaigns ─────────────────────────────────────────────
$recentCampaigns = DB::query(
    "SELECT * FROM campaigns WHERE user_id=? ORDER BY created_at DESC LIMIT 6", [$uid]
);

// ── Pending sender IDs ───────────────────────────────────────────
$pendingSenderIds = (int)(DB::queryOne(
    "SELECT COUNT(*) as c FROM sender_ids WHERE user_id=? AND status='pending'", [$uid]
)['c'] ?? 0);

// ── Dashboard banners ────────────────────────────────────────────
$banners = get_dashboard_banners($uid);
?>

<?php if (!empty($banners)): ?>
<div class="banner-carousel" id="notifCarousel">
  <div class="banner-container" id="carouselContainer">
    <?php foreach ($banners as $b): ?>
      <div class="banner-slide <?= $b['type'] ?>">
        <div class="banner-text">
          <div class="banner-title"><?= htmlspecialchars($b['title']) ?></div>
          <div class="banner-msg"><?= htmlspecialchars($b['message']) ?></div>
          <div class="banner-actions">
            <a href="/client/purchases.php" class="banner-action-link"><i class="fa-solid fa-cart-shopping"></i> Buy Units</a>
            <a href="/client/send-sms.php" class="banner-action-link"><i class="fa-solid fa-paper-plane"></i> Send SMS</a>
            <a href="/client/sender-ids.php" class="banner-action-link"><i class="fa-solid fa-id-badge"></i> Sender IDs</a>
          </div>
        </div>
        <?php if ($b['image_url']): ?>
          <div class="banner-img" style="height:80px;width:120px;border-radius:8px;background:url('<?= $b['image_url'] ?>') center/cover"></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (count($banners) > 1): ?><div class="banner-nav" id="carouselNav"></div><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($totalCampaigns == 0 && $unitsBalance == 0 && empty($banners)): ?>
<div class="welcome-banner" style="margin-bottom:28px">
  <div class="banner-content">
    <p style="color:var(--primary);font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;margin-bottom:8px">Getting Started</p>
    <h2>Welcome, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</h2>
    <p>Request a Sender ID and top up your units to start sending SMS campaigns.</p>
    <div class="banner-actions">
      <a href="/client/sender-ids.php?new=1" class="banner-btn primary"><i class="fa-solid fa-id-badge"></i> Request Sender ID</a>
      <a href="/client/purchases.php" class="banner-btn outline"><i class="fa-solid fa-cart-shopping"></i> Buy Units</a>
      <a href="/client/send-sms.php" class="banner-btn outline"><i class="fa-solid fa-paper-plane"></i> Send SMS</a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($pendingSenderIds > 0): ?>
<div style="background:rgba(249,115,22,.08);border:1px solid rgba(249,115,22,.3);border-radius:var(--radius-md);padding:10px 16px;margin-bottom:18px;font-size:13px;display:flex;align-items:center;gap:10px">
  <i class="fa-solid fa-clock" style="color:var(--warning)"></i>
  <span>You have <strong><?= $pendingSenderIds ?></strong> pending Sender ID request<?= $pendingSenderIds>1?'s':'' ?>. Approval usually takes 1–2 business days.</span>
  <a href="/client/sender-ids.php" style="margin-left:auto;color:var(--warning);font-weight:600;font-size:12px">View →</a>
</div>
<?php endif; ?>

<!-- KPI Strip -->
<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-coins"></i></div>
    <div class="stat-info">
      <div class="stat-label">SMS Balance</div>
      <div class="stat-value"><?= number_format($unitsBalance, 2) ?></div>
      <div class="stat-trend"><a href="/client/purchases.php" style="color:var(--primary)">+ Buy More</a></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-paper-plane"></i></div>
    <div class="stat-info">
      <div class="stat-label">Sent This Month</div>
      <div class="stat-value"><?= number_format($statsCur['sent_ok'] ?? 0) ?></div>
      <div class="stat-trend <?= ($statsCur['sent_ok']??0) >= ($statsPrev['sent_ok']??0) ? 'up' : 'down' ?>">
        <?php $prevSent = $statsPrev['sent_ok'] ?? 0; ?>
        <?php if ($prevSent > 0): ?>
          <i class="fa-solid fa-arrow-<?= ($statsCur['sent_ok']??0) >= $prevSent ? 'up' : 'down' ?>"></i>
          <?= round((($statsCur['sent_ok']??0) - $prevSent) / $prevSent * 100) ?>% vs last month
        <?php else: ?>
          <i class="fa-solid fa-calendar"></i> This month
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon <?= $deliveryRate >= 90 ? 'green' : ($deliveryRate >= 70 ? 'orange' : 'orange') ?>">
      <i class="fa-solid fa-check-double"></i>
    </div>
    <div class="stat-info">
      <div class="stat-label">Delivery Rate</div>
      <div class="stat-value"><?= $deliveryRate ?>%</div>
      <div class="stat-trend <?= $deliveryRate >= $prevRate ? 'up' : 'down' ?>">
        <?= $prevRate > 0 ? ($deliveryRate >= $prevRate ? '+' : '') . round($deliveryRate - $prevRate, 1) . '% vs last month' : 'This month' ?>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fa-solid fa-coins"></i></div>
    <div class="stat-info">
      <div class="stat-label">Units Used (Month)</div>
      <div class="stat-value"><?= number_format($statsCur['units_used'] ?? 0, 1) ?></div>
      <div class="stat-trend"><i class="fa-solid fa-money-bill-wave"></i> KES <?= number_format($spentThisMonth, 0) ?> spent</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon <?= $activeCampaigns > 0 ? 'blue' : 'orange' ?>"><i class="fa-solid fa-bullhorn"></i></div>
    <div class="stat-info">
      <div class="stat-label">Campaigns</div>
      <div class="stat-value"><?= number_format($totalCampaigns) ?></div>
      <?php if ($activeCampaigns > 0): ?>
        <div class="stat-trend up"><i class="fa-solid fa-circle-notch fa-spin"></i> <?= $activeCampaigns ?> active</div>
      <?php else: ?>
        <div class="stat-trend"><i class="fa-solid fa-history"></i> All time</div>
      <?php endif; ?>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fa-solid fa-xmark-circle"></i></div>
    <div class="stat-info">
      <div class="stat-label">Failed (Month)</div>
      <div class="stat-value"><?= number_format($statsCur['failed'] ?? 0) ?></div>
      <div class="stat-trend <?= ($statsCur['failed']??0) > 0 ? 'down' : 'up' ?>">
        <?= ($statsCur['total']??0) > 0 ? round(($statsCur['failed']??0) / $statsCur['total'] * 100, 1) . '% fail rate' : '—' ?>
      </div>
    </div>
  </div>
</div>

<!-- Chart + Quick Send -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-chart-area" style="color:var(--primary)"></i> Message Activity (14 Days)</h3>
    </div>
    <div class="card-body"><div class="chart-container" style="height:230px"><canvas id="actChart"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-bolt" style="color:var(--primary)"></i> Quick Send</h3>
    </div>
    <div class="card-body">
      <form method="POST" action="/client/actions/quick-send.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
          <label class="form-label">Phone Number <span class="required">*</span></label>
          <input type="text" name="recipient" class="form-control" placeholder="+254712345678" required>
        </div>
        <div class="form-group">
          <label class="form-label">Sender ID <span class="required">*</span></label>
          <select name="sender_id" class="form-control" required>
            <option value="">— Select —</option>
            <?php foreach (DB::query("SELECT sender_id FROM sender_ids WHERE user_id=? AND status='approved'", [$uid]) as $s): ?>
              <option><?= htmlspecialchars($s['sender_id']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group sms-composer">
          <label class="form-label">Message <span class="required">*</span></label>
          <textarea name="message" class="form-control" id="qs" placeholder="Type message…" maxlength="918" required></textarea>
          <div class="sms-counter"><span id="cnt">0</span>/160 · <span id="seg">1</span> SMS · ~<span id="cost">0.00</span> units</div>
        </div>
        <button type="submit" class="btn btn-primary btn-full"><i class="fa-solid fa-paper-plane"></i> Send Now</button>
      </form>
    </div>
  </div>
</div>

<!-- Recent Campaigns -->
<div class="card" style="margin-bottom:24px">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-bullhorn" style="color:var(--primary)"></i> Recent Campaigns</h3>
    <a href="/client/campaigns.php?new=1" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> New Campaign</a>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Name</th><th>Sender ID</th><th>Progress</th><th>Units</th><th>Status</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($recentCampaigns)): ?>
          <tr><td colspan="7"><div class="empty-state"><div class="empty-icon">📢</div><h3>No campaigns yet</h3><p>Send your first bulk campaign.</p><a href="/client/campaigns.php?new=1" class="btn btn-primary">Create Campaign</a></div></td></tr>
        <?php else: foreach ($recentCampaigns as $camp):
          $cs = ['draft'=>'muted','scheduled'=>'warning','queued'=>'warning','running'=>'info','sending'=>'info','completed'=>'success','failed'=>'danger'][$camp['status']] ?? 'muted';
          $pct = $camp['total_count'] > 0 ? round(($camp['sent_count'] + $camp['failed_count']) / $camp['total_count'] * 100) : 0;
        ?>
          <tr>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <strong><?= htmlspecialchars($camp['name']) ?></strong>
            </td>
            <td><code style="font-size:11px"><?= htmlspecialchars($camp['sender_id']) ?></code></td>
            <td style="min-width:120px">
              <div style="display:flex;align-items:center;gap:8px">
                <div style="flex:1;background:var(--bg-muted);border-radius:3px;height:6px">
                  <div style="height:6px;border-radius:3px;background:var(--<?= $cs === 'danger' ? 'danger' : 'primary' ?>);width:<?= $pct ?>%"></div>
                </div>
                <span style="font-size:11px;color:var(--text-secondary);white-space:nowrap"><?= $camp['sent_count'] ?>/<?= $camp['total_count'] ?></span>
              </div>
            </td>
            <td style="font-size:13px"><?= number_format($camp['units_used'], 2) ?></td>
            <td><span class="badge badge-<?= $cs ?>"><?= ucfirst($camp['status']) ?></span></td>
            <td style="font-size:11px;color:var(--text-secondary)"><?= date('d M Y', strtotime($camp['created_at'])) ?></td>
            <td>
              <a href="/client/campaigns.php?view=<?= $camp['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="View"><i class="fa-solid fa-eye"></i></a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalCampaigns > 6): ?>
    <div class="card-footer"><a href="/client/campaigns.php" class="btn btn-outline btn-sm">View all <?= number_format($totalCampaigns) ?> campaigns →</a></div>
  <?php endif; ?>
</div>

<!-- Delivery stats + Purchase summary -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-chart-pie" style="color:var(--primary)"></i> Delivery Breakdown (This Month)</h3></div>
    <div class="card-body" style="display:flex;align-items:center;gap:20px">
      <div style="width:140px;flex-shrink:0"><canvas id="donutChart" height="140"></canvas></div>
      <div style="flex:1">
        <?php
        $dTotal = (int)($statsCur['total'] ?? 0);
        foreach ([
          ['Sent/Delivered', (int)($statsCur['sent_ok'] ?? 0),  '#00c896'],
          ['Failed',         (int)($statsCur['failed'] ?? 0),   '#ef4444'],
          ['Other',          max(0, $dTotal - (int)($statsCur['sent_ok']??0) - (int)($statsCur['failed']??0)), '#94a3b8'],
        ] as [$label, $count, $color]):
          $p = $dTotal > 0 ? round($count / $dTotal * 100) : 0;
        ?>
          <div style="margin-bottom:10px">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px">
              <span style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:50%;background:<?=$color?>;display:inline-block"></span><?=$label?></span>
              <span style="font-weight:700"><?= number_format($count) ?> <span style="color:var(--text-secondary);font-weight:400">(<?= $p ?>%)</span></span>
            </div>
            <div style="height:4px;background:var(--bg-muted);border-radius:2px"><div style="height:4px;border-radius:2px;background:<?=$color?>;width:<?=$p?>%"></div></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-receipt" style="color:var(--primary)"></i> Recent Purchases</h3>
      <a href="/client/purchase-history.php" class="btn btn-outline btn-sm">Full History →</a>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Units</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php
          $recentPurchases = DB::query("SELECT * FROM purchases WHERE user_id=? ORDER BY created_at DESC LIMIT 5", [$uid]);
          if (empty($recentPurchases)): ?>
            <tr><td colspan="4" class="text-center text-muted" style="padding:20px">No purchases yet</td></tr>
          <?php else: foreach ($recentPurchases as $p):
            $ps = ['completed'=>'success','pending'=>'warning','failed'=>'danger','refunded'=>'muted'][$p['status']] ?? 'muted';
          ?>
            <tr>
              <td><?= number_format($p['units']) ?></td>
              <td style="font-weight:600;color:var(--primary)"><?= $p['currency'] ?? 'KES' ?> <?= number_format($p['amount'], 2) ?></td>
              <td><span class="badge badge-<?= $ps ?>"><?= ucfirst($p['status']) ?></span></td>
              <td style="font-size:11px"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$dSent  = (int)($statsCur['sent_ok'] ?? 0);
$dFail  = (int)($statsCur['failed'] ?? 0);
$dOther = max(0, (int)($statsCur['total']??0) - $dSent - $dFail);
$extraScript = <<<JS
<script>
(function(){
  // 14-day activity chart
  new Chart(document.getElementById('actChart'), {
    type:'line',
    data:{
      labels:{$chartDays}||[],
      datasets:[
        {label:'Total',    data:{$chartTotal}||[],  borderColor:'#94a3b8', backgroundColor:'rgba(148,163,184,.06)', fill:true,  tension:.4, pointRadius:2, borderWidth:1.5},
        {label:'Sent/Del', data:{$chartSent}||[],   borderColor:'#00c896', backgroundColor:'rgba(0,200,150,.08)',   fill:true,  tension:.4, pointRadius:2, borderWidth:2},
        {label:'Failed',   data:{$chartFailed}||[], borderColor:'#ef4444', backgroundColor:'transparent',           fill:false, tension:.4, pointRadius:2, borderWidth:1.5},
      ]
    },
    options:{responsive:true,maintainAspectRatio:false,
      plugins:{legend:{display:true,position:'top',labels:{boxWidth:10,font:{size:11}}}},
      scales:{x:{grid:{display:false},ticks:{maxTicksLimit:8,font:{size:10}}},y:{beginAtZero:true,grid:{color:'rgba(0,0,0,.05)'}}}}
  });

  // Delivery donut
  new Chart(document.getElementById('donutChart'), {
    type:'doughnut',
    data:{labels:['Sent/Del','Failed','Other'],
      datasets:[{data:[{$dSent},{$dFail},{$dOther}],
        backgroundColor:['#00c896','#ef4444','#94a3b8'],borderWidth:0,hoverOffset:3}]},
    options:{responsive:false,cutout:'70%',plugins:{legend:{display:false}}}
  });

  // Quick send counter
  const ta = document.getElementById('qs');
  if (ta) {
    ta.addEventListener('input', () => {
      const l = ta.value.length, segs = Math.ceil(l/160)||1;
      document.getElementById('cnt').textContent = l;
      document.getElementById('seg').textContent = segs;
      document.getElementById('cost').textContent = (segs * {$rate}).toFixed(2);
    });
  }

  // Banner carousel
  const carousel = document.getElementById('notifCarousel');
  if (carousel) {
    const container = document.getElementById('carouselContainer');
    const slides = container.querySelectorAll('.banner-slide');
    const nav = document.getElementById('carouselNav');
    let idx = 0; const count = slides.length;
    if (count > 1) {
      slides.forEach((_, i) => { const d = document.createElement('div'); d.className='banner-dot'+(i===0?' active':''); d.onclick=()=>go(i); nav.appendChild(d); });
      const go = (i) => { idx=i; container.style.transform='translateX(-'+(idx*100)+'%)'; nav.querySelectorAll('.banner-dot').forEach((d,j)=>d.classList.toggle('active',j===idx)); };
      let iv = setInterval(()=>go((idx+1)%count),5000);
      carousel.onmouseenter=()=>clearInterval(iv); carousel.onmouseleave=()=>iv=setInterval(()=>go((idx+1)%count),5000);
    }
  }
})();
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
