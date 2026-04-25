<?php
$pageTitle = 'Analytics';
$breadcrumb = [['label'=>'Admin'],['label'=>'Analytics']];
require_once __DIR__ . '/layout.php';

// KPIs
$kpis = [
  'total_users'     => DB::queryOne("SELECT COUNT(*) as c FROM users WHERE role!='admin'",[])['c']??0,
  'total_campaigns' => DB::queryOne("SELECT COUNT(*) as c FROM campaigns",[])['c']??0,
  'total_messages'  => DB::queryOne("SELECT COUNT(*) as c FROM messages",[])['c']??0,
  'total_revenue'   => DB::queryOne("SELECT COALESCE(SUM(amount),0) as t FROM purchases WHERE status='completed'",[])['t']??0,
  'msg_today'       => DB::queryOne("SELECT COUNT(*) as c FROM messages WHERE DATE(created_at)=CURDATE()",[])['c']??0,
  'msg_this_month'  => DB::queryOne("SELECT COUNT(*) as c FROM messages WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())",[])['c']??0,
];

// 30-day trend
$trend = DB::query("SELECT DATE(created_at) as day, COUNT(*) as total FROM messages WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY day ORDER BY day");
$trendLabels = json_encode(array_column($trend,'day'));
$trendValues = json_encode(array_column($trend,'total'));

// Top senders
$topSenders = DB::query("SELECT u.name, u.role, COUNT(m.id) as msgs, SUM(m.units_charged) as units FROM messages m JOIN users u ON m.user_id=u.id GROUP BY m.user_id ORDER BY msgs DESC LIMIT 10");

// Revenue by month
$revData = DB::query("SELECT DATE_FORMAT(created_at,'%Y-%m') as month, SUM(amount) as total FROM purchases WHERE status='completed' AND created_at>=DATE_SUB(NOW(),INTERVAL 6 MONTH) GROUP BY month ORDER BY month");
$revLabels = json_encode(array_column($revData,'month'));
$revValues = json_encode(array_column($revData,'total'));

// Message delivery rates
$delivery = DB::queryOne("SELECT COUNT(*) as total, SUM(status='sent') as sent, SUM(status='delivered') as delivered, SUM(status='failed') as failed FROM messages",[]);
?>
<div class="page-header">
  <div><h1>Analytics</h1><div class="subtitle">Platform-wide performance overview</div></div>
  <div style="font-size:12px;color:var(--text-secondary)">Updated: <?=date('d M Y H:i')?></div>
</div>

<!-- KPI Grid -->
<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-users"></i></div><div class="stat-info"><div class="stat-label">Total Users</div><div class="stat-value"><?=number_format($kpis['total_users'])?></div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-bullhorn"></i></div><div class="stat-info"><div class="stat-label">Total Campaigns</div><div class="stat-value"><?=number_format($kpis['total_campaigns'])?></div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-paper-plane"></i></div><div class="stat-info"><div class="stat-label">Total Messages</div><div class="stat-value"><?=number_format($kpis['total_messages'])?></div><div class="stat-trend up"><i class="fa-solid fa-arrow-up"></i> <?=number_format($kpis['msg_today'])?> today</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-money-bill-wave"></i></div><div class="stat-info"><div class="stat-label">Revenue (KES)</div><div class="stat-value"><?=number_format($kpis['total_revenue'],0)?></div></div></div>
  <div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-check-double"></i></div><div class="stat-info"><div class="stat-label">Delivered</div><div class="stat-value"><?=number_format($delivery['delivered']??0)?></div><div class="stat-trend"><?=$delivery['total']>0?round(($delivery['delivered']??0)/$delivery['total']*100).'%':'—'?> delivery rate</div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-xmark"></i></div><div class="stat-info"><div class="stat-label">Failed</div><div class="stat-value"><?=number_format($delivery['failed']??0)?></div></div></div>
</div>

<!-- Charts -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-chart-area" style="color:var(--primary)"></i> Messages (Last 30 Days)</h3></div>
    <div class="card-body"><div class="chart-container" style="height:240px"><canvas id="trendChart"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-chart-pie" style="color:var(--primary)"></i> Delivery Rate</h3></div>
    <div class="card-body"><div class="chart-container" style="height:240px"><canvas id="donutChart"></canvas></div></div>
  </div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-chart-bar" style="color:var(--primary)"></i> Revenue (6 Months)</h3></div>
    <div class="card-body"><div class="chart-container" style="height:220px"><canvas id="revChart"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-trophy" style="color:var(--primary)"></i> Top Senders</h3></div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>#</th><th>User</th><th>Role</th><th>Messages</th><th>Units</th></tr></thead>
        <tbody>
          <?php foreach ($topSenders as $i=>$s): ?>
            <tr>
              <td><strong style="color:var(--primary)"><?=$i+1?></strong></td>
              <td><?=htmlspecialchars($s['name'])?></td>
              <td><span class="badge <?=$s['role']==='reseller'?'badge-success':'badge-info'?>"><?=ucfirst($s['role'])?></span></td>
              <td><?=number_format($s['msgs'])?></td>
              <td><?=number_format($s['units']??0,2)?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($topSenders)): ?><tr><td colspan="5" class="text-center text-muted" style="padding:20px">No data yet</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$dSent      = (int)($delivery['sent']      ?? 0);
$dDelivered = (int)($delivery['delivered'] ?? 0);
$dFailed    = (int)($delivery['failed']    ?? 0);

$extraScript = <<<JS
<script>
(function(){
  // Trend chart
  new Chart(document.getElementById('trendChart'),{type:'line',data:{labels:{$trendLabels}||[],datasets:[{label:'Messages',data:{$trendValues}||[],borderColor:'#00c896',backgroundColor:'rgba(0,200,150,0.1)',fill:true,tension:0.4,pointRadius:3,borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true}}}});
  // Donut
  new Chart(document.getElementById('donutChart'),{type:'doughnut',data:{labels:['Sent','Delivered','Failed'],datasets:[{data:[{$dSent},{$dDelivered},{$dFailed}],backgroundColor:['#3b82f6','#00c896','#ef4444'],borderWidth:0,hoverOffset:4}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}},cutout:'70%'}});
  // Revenue
  new Chart(document.getElementById('revChart'),{type:'bar',data:{labels:{$revLabels}||[],datasets:[{label:'KES',data:{$revValues}||[],backgroundColor:'rgba(0,200,150,0.7)',borderRadius:6,borderSkipped:false}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true}}}});
})();
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
