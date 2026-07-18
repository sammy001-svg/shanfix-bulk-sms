<?php
$pageTitle  = 'Dashboard';
$breadcrumb = [['label'=>'Reseller'],['label'=>'Dashboard']];
require_once __DIR__ . '/layout.php';

// ── Stats ──────────────────────────────────────────────────────
$uid           = $user['id'];
$totalClients  = DB::queryOne("SELECT COUNT(*) as c FROM users WHERE role='client' AND parent_id=?",[$uid])['c'] ?? 0;
$totalCampaigns= DB::queryOne("SELECT COUNT(*) as c FROM campaigns WHERE user_id=?",[$uid])['c'] ?? 0;
$msgSent       = DB::queryOne("SELECT COUNT(*) as c FROM messages WHERE user_id=? AND status='sent'",[$uid])['c'] ?? 0;
$msgToday      = DB::queryOne("SELECT COUNT(*) as c FROM messages WHERE user_id=? AND created_at>=CURDATE()",[$uid])['c'] ?? 0;
$units         = $user['sms_units'];
$isNewUser     = $totalCampaigns == 0;

// ── Client summary ─────────────────────────────────────────────
$clientIds     = DB::query("SELECT id FROM users WHERE role='client' AND parent_id=?", [$uid]);
$cidList       = array_column($clientIds, 'id');
$clientMsgSent = 0; $clientUnits = 0; $clientCampaigns = 0; $recentClientCampaigns = [];
$pendingClientPurchases = 0;
if (!empty($cidList)) {
    $in = implode(',', array_fill(0, count($cidList), '?'));
    $clientMsgSent   = (int)(DB::queryOne("SELECT COUNT(*) as c FROM messages WHERE user_id IN ($in) AND status='sent'", $cidList)['c'] ?? 0);
    $clientUnits     = (float)(DB::queryOne("SELECT COALESCE(SUM(sms_units),0) as u FROM users WHERE id IN ($in)", $cidList)['u'] ?? 0);
    $clientCampaigns = (int)(DB::queryOne("SELECT COUNT(*) as c FROM campaigns WHERE user_id IN ($in)", $cidList)['c'] ?? 0);
    $pendingClientPurchases = (int)(DB::queryOne("SELECT COUNT(*) as c FROM purchases WHERE user_id IN ($in) AND status='pending'", $cidList)['c'] ?? 0);
    $recentClientCampaigns  = DB::query(
        "SELECT c.*, u.name as client_name FROM campaigns c JOIN users u ON c.user_id=u.id
         WHERE c.user_id IN ($in) ORDER BY c.created_at DESC LIMIT 6",
        $cidList
    );
}

// ── Chart ──────────────────────────────────────────────────────
$chartData = DB::query("
  SELECT DATE(created_at) as day, COUNT(*) as total
  FROM messages WHERE user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)
  GROUP BY day ORDER BY day ASC
",[$uid]);
$chartLabels = json_encode(array_column($chartData,'day'));
$chartValues = json_encode(array_column($chartData,'total'));

$recentCampaigns = DB::query("
  SELECT * FROM campaigns WHERE user_id=? ORDER BY created_at DESC LIMIT 6
",[$uid]);

// Fetch Dashboard Banners
$banners = get_dashboard_banners($uid);
?>

<?php if (!empty($banners)): ?>
<div class="banner-carousel" id="notifCarousel">
  <div class="banner-container" id="carouselContainer">
    <?php foreach ($banners as $b): 
      $icon = ['info'=>'fa-circle-info','success'=>'fa-circle-check','warning'=>'fa-triangle-exclamation','danger'=>'fa-circle-exclamation'][$b['type']] ?? 'fa-bell';
    ?>
      <div class="banner-slide <?= $b['type'] ?>">
        <div class="banner-text">
          <div class="banner-title">
            <?= htmlspecialchars($b['title']) ?>
            <?php 
              echo [
                'info' => ' <i class="fa-solid fa-circle-info" style="font-size:0.9em; opacity:0.8"></i>',
                'success' => ' 💐',
                'warning' => ' 😟',
                'danger' => ' 😭'
              ][$b['type']] ?? '';
            ?>
          </div>
          <div class="banner-msg"><?= htmlspecialchars($b['message']) ?></div>
          <div class="banner-actions">
            <a href="/reseller/purchases.php" class="banner-action-link"><i class="fa-solid fa-cart-shopping"></i> Buy Units</a>
            <a href="/reseller/send-sms.php" class="banner-action-link"><i class="fa-solid fa-paper-plane"></i> Send SMS</a>
            <a href="/reseller/sender-ids.php" class="banner-action-link"><i class="fa-solid fa-id-badge"></i> Request Sender ID</a>
          </div>
        </div>
        <?php if ($b['image_url']): ?>
          <div class="banner-img" style="height:80px; width:120px; border-radius:8px; background:url('<?= $b['image_url'] ?>') center/cover"></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (count($banners) > 1): ?>
    <div class="banner-nav" id="carouselNav"></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($isNewUser && empty($banners)): ?>
<!-- Welcome Banner -->
<div class="welcome-banner" style="margin-bottom:28px">
  <img src="/assets/images/welcome-hero.jpg" alt="" class="banner-img" onerror="this.style.display='none'">
  <div class="banner-content">
    <p style="color:var(--primary);font-size:12px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;margin-bottom:8px">
      👋 Welcome to Shanfix Technology
    </p>
    <h2>Hello <?= htmlspecialchars(explode(' ',$user['name'])[0]) ?>, you're all set!</h2>
    <p>To send SMS you need a sender ID and some units.<br>Acquiring units is fast — top up via M-Pesa instantly.</p>
    <div class="banner-actions">
      <a href="/reseller/sender-ids.php?new=1" class="banner-btn primary">
        <i class="fa-solid fa-id-badge"></i> Request Sender ID
      </a>
      <a href="/reseller/purchases.php" class="banner-btn outline">
        <i class="fa-solid fa-cart-shopping"></i> Buy SMS Units
      </a>
      <a href="/reseller/send-sms.php" class="banner-btn outline">
        <i class="fa-solid fa-paper-plane"></i> Send SMS
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-coins"></i></div>
    <div class="stat-info">
      <div class="stat-label">SMS Units Balance</div>
      <div class="stat-value"><?= number_format($units, 2) ?></div>
      <div class="stat-trend up"><a href="/reseller/purchases.php" style="color:inherit">+ Top Up</a></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
    <div class="stat-info">
      <div class="stat-label">My Clients</div>
      <div class="stat-value"><?= number_format($totalClients) ?></div>
      <div class="stat-trend up"><a href="/reseller/clients.php" style="color:inherit">Manage →</a></div>
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
    <div class="stat-icon blue"><i class="fa-solid fa-paper-plane"></i></div>
    <div class="stat-info">
      <div class="stat-label">Sent Today</div>
      <div class="stat-value"><?= number_format($msgToday) ?></div>
      <div class="stat-trend up"><i class="fa-solid fa-message"></i> <?= number_format($msgSent) ?> total</div>
    </div>
  </div>
</div>

<!-- Client Summary Section -->
<?php if ($totalClients > 0): ?>
<div style="margin-bottom:24px">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
    <h3 style="font-size:15px;font-weight:700;margin:0"><i class="fa-solid fa-users" style="color:var(--primary);margin-right:8px"></i>Client Activity Summary</h3>
    <a href="/reseller/clients.php" class="btn btn-outline btn-sm">View All Clients →</a>
  </div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px">
    <div class="stat-card" style="margin:0">
      <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
      <div class="stat-info"><div class="stat-label">Active Clients</div><div class="stat-value"><?= number_format($totalClients) ?></div></div>
    </div>
    <div class="stat-card" style="margin:0">
      <div class="stat-icon green"><i class="fa-solid fa-coins"></i></div>
      <div class="stat-info"><div class="stat-label">Client Units</div><div class="stat-value"><?= number_format($clientUnits, 0) ?></div></div>
    </div>
    <div class="stat-card" style="margin:0">
      <div class="stat-icon orange"><i class="fa-solid fa-paper-plane"></i></div>
      <div class="stat-info"><div class="stat-label">Client Msgs Sent</div><div class="stat-value"><?= number_format($clientMsgSent) ?></div></div>
    </div>
    <div class="stat-card" style="margin:0">
      <div class="stat-icon <?= $pendingClientPurchases > 0 ? 'orange' : 'blue' ?>"><i class="fa-solid fa-receipt"></i></div>
      <div class="stat-info">
        <div class="stat-label">Pending Purchases</div>
        <div class="stat-value"><?= number_format($pendingClientPurchases) ?></div>
        <?php if ($pendingClientPurchases > 0): ?>
          <div class="stat-trend" style="color:var(--warning)"><a href="/reseller/purchase-requests.php" style="color:inherit">Review →</a></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if (!empty($recentClientCampaigns)): ?>
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-bullhorn" style="color:var(--primary)"></i> Recent Client Campaigns</h3>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Client</th><th>Campaign</th><th>Sender ID</th><th>Sent</th><th>Failed</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($recentClientCampaigns as $cc):
            $ccs = ['draft'=>'muted','scheduled'=>'warning','queued'=>'warning','sending'=>'info','running'=>'info','completed'=>'success','failed'=>'danger'][$cc['status']]??'muted';
          ?>
            <tr>
              <td style="font-size:13px;font-weight:600"><a href="/reseller/client-detail.php?id=<?= $cc['user_id'] ?>" style="color:inherit"><?= htmlspecialchars($cc['client_name']) ?></a></td>
              <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($cc['name']) ?></td>
              <td><code style="font-size:11px"><?= htmlspecialchars($cc['sender_id']) ?></code></td>
              <td style="color:var(--success)"><?= number_format($cc['sent_count']) ?></td>
              <td style="color:var(--danger)"><?= number_format($cc['failed_count']) ?></td>
              <td><span class="badge badge-<?= $ccs ?>"><?= ucfirst($cc['status']) ?></span></td>
              <td style="font-size:11px"><?= date('d M Y', strtotime($cc['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Chart + Quick Send -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px;">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-chart-area" style="color:var(--primary)"></i> Messages Sent (7 Days)</h3>
    </div>
    <div class="card-body">
      <div class="chart-container" style="height:240px"><canvas id="msgChart"></canvas></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-bolt" style="color:var(--primary)"></i> Quick Send</h3>
    </div>
    <div class="card-body">
      <form method="POST" action="/reseller/actions/quick-send.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
          <label class="form-label">Recipient Number<span class="required">*</span></label>
          <input type="text" name="recipient" class="form-control" placeholder="+254712345678" required>
        </div>
        <div class="form-group">
          <label class="form-label">Sender ID<span class="required">*</span></label>
          <select name="sender_id" class="form-control" required>
            <option value="">-- Select --</option>
            <?php foreach (DB::query("SELECT sender_id FROM sender_ids WHERE user_id=? AND status='approved'",[$uid]) as $s): ?>
              <option value="<?= htmlspecialchars($s['sender_id']) ?>"><?= htmlspecialchars($s['sender_id']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group sms-composer">
          <label class="form-label">Message<span class="required">*</span></label>
          <textarea name="message" class="form-control" id="quickMsg" placeholder="Type your message..." required maxlength="918"></textarea>
          <div class="sms-counter"><span id="qCharCount">0</span>/160 · <span id="qSmsCount">1</span> SMS</div>
        </div>
        <button type="submit" class="btn btn-primary btn-full">
          <i class="fa-solid fa-paper-plane"></i> Send Now
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Recent Campaigns -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-list" style="color:var(--primary)"></i> Recent Campaigns</h3>
    <a href="/reseller/campaigns.php" class="btn btn-primary btn-sm">+ New Campaign</a>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Name</th><th>Sender ID</th><th>Recipients</th><th>Sent</th><th>Failed</th><th>Units</th><th>Status</th><th>Date</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (empty($recentCampaigns)): ?>
          <tr><td colspan="9"><div class="empty-state"><div class="empty-icon">📢</div><h3>No campaigns yet</h3><p>Create your first campaign and start reaching your audience.</p><a href="/reseller/campaigns.php?new=1" class="btn btn-primary">Create Campaign</a></div></td></tr>
        <?php else: ?>
          <?php foreach ($recentCampaigns as $c): ?>
            <?php $sc = ['completed'=>'success','sending'=>'info','queued'=>'warning','failed'=>'danger','draft'=>'muted'][$c['status']] ?? 'muted'; ?>
            <tr>
              <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
              <td><code><?= htmlspecialchars($c['sender_id']) ?></code></td>
              <td><?= number_format($c['total_count']) ?></td>
              <td style="color:var(--success)"><?= number_format($c['sent_count']) ?></td>
              <td style="color:var(--danger)"><?= number_format($c['failed_count']) ?></td>
              <td><?= number_format($c['units_used'],2) ?></td>
              <td><span class="badge badge-<?= $sc ?>"><?= ucfirst($c['status']) ?></span></td>
              <td style="font-size:12px"><?= date('d M Y',strtotime($c['created_at'])) ?></td>
              <td style="white-space:nowrap">
                <a href="/reseller/campaigns.php?view=<?= $c['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="View"><i class="fa-solid fa-eye"></i></a>
                <?php if ($c['status']==='draft'): ?>
                  <a href="/reseller/campaigns.php?edit=<?= $c['id'] ?>" class="btn btn-outline btn-sm btn-icon" title="Edit"><i class="fa-solid fa-pen"></i></a>
                <?php endif; ?>
              </td>
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
(function(){
  const ctx = document.getElementById('msgChart');
  const labels = {$chartLabels} || [];
  const values = {$chartValues} || [];
  new Chart(ctx, {
    type:'bar',
    data:{
      labels,
      datasets:[{
        label:'Messages',data:values,
        backgroundColor:'rgba(0,200,150,0.7)',
        borderRadius:6,borderSkipped:false
      }]
    },
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},
      scales:{x:{grid:{display:false}},y:{beginAtZero:true,grid:{color:'rgba(0,0,0,0.05)'}}}}
  });
  const ta=document.getElementById('quickMsg');
  if(ta){ta.addEventListener('input',function(){
    const l=ta.value.length;
    document.getElementById('qCharCount').textContent=l;
    document.getElementById('qSmsCount').textContent=Math.ceil(l/160)||1;
  });}

  // Banner Carousel Logic
  const carousel = document.getElementById('notifCarousel');
  if (carousel) {
      const container = document.getElementById('carouselContainer');
      const slides = container.querySelectorAll('.banner-slide');
      const nav = document.getElementById('carouselNav');
      let currentIdx = 0;
      const count = slides.length;

      if (count > 1) {
          // Create dots
          slides.forEach((_, i) => {
              const dot = document.createElement('div');
              dot.className = 'banner-dot' + (i === 0 ? ' active' : '');
              dot.onclick = () => goToSlide(i);
              nav.appendChild(dot);
          });

          function updateDots() {
              nav.querySelectorAll('.banner-dot').forEach((dot, i) => {
                  dot.classList.toggle('active', i === currentIdx);
              });
          }

          function goToSlide(idx) {
              currentIdx = idx;
              container.style.transform = 'translateX(-' + (currentIdx * 100) + '%)';
              updateDots();
          }

          function nextSlide() {
              currentIdx = (currentIdx + 1) % count;
              goToSlide(currentIdx);
          }

          let interval = setInterval(nextSlide, 5000);
          carousel.onmouseenter = () => clearInterval(interval);
          carousel.onmouseleave = () => interval = setInterval(nextSlide, 5000);
      }
  }
})();
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
