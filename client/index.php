<?php
$pageTitle  = 'Dashboard';
$breadcrumb = [['label'=>'Client'],['label'=>'Dashboard']];
require_once __DIR__ . '/layout.php';

$uid           = $user['id'];
$units         = $user['sms_units'];
$totalCampaigns= DB::queryOne("SELECT COUNT(*) as c FROM campaigns WHERE user_id=?",[$uid])['c'] ?? 0;
$msgSent       = DB::queryOne("SELECT COUNT(*) as c FROM messages WHERE user_id=? AND status='sent'",[$uid])['c'] ?? 0;
$msgToday      = DB::queryOne("SELECT COUNT(*) as c FROM messages WHERE user_id=? AND DATE(created_at)=CURDATE()",[$uid])['c'] ?? 0;
$isNewUser     = $totalCampaigns == 0 && $units == 0;

$chartData = DB::query("SELECT DATE(created_at) as day,COUNT(*) as total FROM messages WHERE user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY day ORDER BY day",[$uid]);
$chartLabels = json_encode(array_column($chartData,'day'));
$chartValues = json_encode(array_column($chartData,'total'));

$recentMessages = DB::query("SELECT * FROM messages WHERE user_id=? ORDER BY created_at DESC LIMIT 8",[$uid]);

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
        <div class="banner-icon"><i class="fa-solid <?= $icon ?>"></i></div>
        <div class="banner-text">
          <div class="banner-title"><?= htmlspecialchars($b['title']) ?></div>
          <div class="banner-msg"><?= htmlspecialchars($b['message']) ?></div>
          <div class="banner-actions">
            <a href="/client/purchases.php" class="banner-action-link"><i class="fa-solid fa-cart-shopping"></i> Buy Units</a>
            <a href="/client/send-sms.php" class="banner-action-link"><i class="fa-solid fa-paper-plane"></i> Send SMS</a>
            <a href="/client/sender-ids.php" class="banner-action-link"><i class="fa-solid fa-id-badge"></i> Request Sender ID</a>
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
<div class="welcome-banner">
  <div class="banner-content">
    <p style="color:var(--primary);font-size:12px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;margin-bottom:8px">GETTING STARTED</p>
    <h2>Welcome, <?= htmlspecialchars(explode(' ',$user['name'])[0]) ?>! 🎉</h2>
    <p>You're almost ready. Request a Sender ID and top up your units to start sending SMS campaigns.</p>
    <div class="banner-actions">
      <a href="/client/sender-ids.php?new=1" class="banner-btn primary">
        <i class="fa-solid fa-id-badge"></i> Request Sender ID
      </a>
      <a href="/client/purchases.php" class="banner-btn outline">
        <i class="fa-solid fa-cart-shopping"></i> Buy SMS Units
      </a>
      <a href="/client/send-sms.php" class="banner-btn outline">
        <i class="fa-solid fa-paper-plane"></i> Send SMS
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-coins"></i></div>
    <div class="stat-info">
      <div class="stat-label">SMS Units</div>
      <div class="stat-value"><?= number_format($units,2) ?></div>
      <div class="stat-trend"><a href="/client/purchases.php" style="color:var(--primary)">+ Buy More</a></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fa-solid fa-bullhorn"></i></div>
    <div class="stat-info">
      <div class="stat-label">Campaigns</div>
      <div class="stat-value"><?= number_format($totalCampaigns) ?></div>
      <div class="stat-trend up"><i class="fa-solid fa-chart-simple"></i> All time</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-check-double"></i></div>
    <div class="stat-info">
      <div class="stat-label">Messages Sent</div>
      <div class="stat-value"><?= number_format($msgSent) ?></div>
      <div class="stat-trend up"><i class="fa-solid fa-arrow-trend-up"></i> All time</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-paper-plane"></i></div>
    <div class="stat-info">
      <div class="stat-label">Sent Today</div>
      <div class="stat-value"><?= number_format($msgToday) ?></div>
      <div class="stat-trend up"><i class="fa-solid fa-calendar-day"></i> Today</div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-chart-bar" style="color:var(--primary)"></i> Activity (7 Days)</h3>
    </div>
    <div class="card-body"><div class="chart-container" style="height:220px"><canvas id="actChart"></canvas></div></div>
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
            <option value="">-- Select Sender ID --</option>
            <?php foreach (DB::query("SELECT sender_id FROM sender_ids WHERE user_id=? AND status='approved'",[$uid]) as $s): ?>
              <option><?= htmlspecialchars($s['sender_id']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group sms-composer">
          <label class="form-label">Message <span class="required">*</span></label>
          <textarea name="message" class="form-control" id="qs" placeholder="Type message..." maxlength="918" required></textarea>
          <div class="sms-counter"><span id="cnt">0</span>/160 · <span id="seg">1</span> SMS · Cost: <span id="cost">0</span> units</div>
        </div>
        <button type="submit" class="btn btn-primary btn-full"><i class="fa-solid fa-paper-plane"></i> Send Now</button>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-history" style="color:var(--primary)"></i> Recent Messages</h3>
    <a href="/client/reports.php" class="btn btn-outline btn-sm">Full Report</a>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Recipient</th><th>Sender ID</th><th>Message</th><th>Units</th><th>Status</th><th>Sent At</th></tr></thead>
      <tbody>
        <?php if (empty($recentMessages)): ?>
          <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">📭</div><h3>No messages yet</h3><p>Send your first SMS to get started.</p><a href="/client/send-sms.php" class="btn btn-primary">Send SMS</a></div></td></tr>
        <?php else: ?>
          <?php foreach ($recentMessages as $m): ?>
            <?php $sc=['sent'=>'success','delivered'=>'success','failed'=>'danger','queued'=>'warning','undelivered'=>'warning'][$m['status']]??'muted'; ?>
            <tr>
              <td><strong><?= htmlspecialchars($m['recipient']) ?></strong></td>
              <td><code><?= htmlspecialchars($m['sender_id']) ?></code></td>
              <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($m['message']) ?>"><?= htmlspecialchars($m['message']) ?></td>
              <td><?= $m['units_charged'] ?></td>
              <td><span class="badge badge-<?= $sc ?>"><?= ucfirst($m['status']) ?></span></td>
              <td style="font-size:12px"><?= $m['sent_at'] ? date('d M Y H:i',strtotime($m['sent_at'])) : '—' ?></td>
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
  const ctx=document.getElementById('actChart');
  new Chart(ctx,{type:'line',data:{labels:{$chartLabels}||[],datasets:[{label:'Msgs',data:{$chartValues}||[],borderColor:'#00c896',backgroundColor:'rgba(0,200,150,0.08)',fill:true,tension:0.4,pointRadius:4,borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true}}}});
  const ta=document.getElementById('qs');
  if(ta){ta.addEventListener('input',()=>{const l=ta.value.length;document.getElementById('cnt').textContent=l;const s=Math.ceil(l/160)||1;document.getElementById('seg').textContent=s;document.getElementById('cost').textContent=(s * (window.ShanfixConfig.smsRate || 1)).toFixed(2);});}

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
