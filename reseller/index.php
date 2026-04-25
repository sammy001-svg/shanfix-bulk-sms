<?php
$pageTitle  = 'Dashboard';
$breadcrumb = [['label'=>'Reseller'],['label'=>'Dashboard']];
require_once __DIR__ . '/layout.php';

// ── Stats ──────────────────────────────────────────────────────
$uid           = $user['id'];
$totalClients  = DB::queryOne("SELECT COUNT(*) as c FROM users WHERE role='client' AND parent_id=?",[$uid])['c'] ?? 0;
$totalCampaigns= DB::queryOne("SELECT COUNT(*) as c FROM campaigns WHERE user_id=?",[$uid])['c'] ?? 0;
$msgSent       = DB::queryOne("SELECT COUNT(*) as c FROM messages WHERE user_id=? AND status='sent'",[$uid])['c'] ?? 0;
$msgToday      = DB::queryOne("SELECT COUNT(*) as c FROM messages WHERE user_id=? AND DATE(created_at)=CURDATE()",[$uid])['c'] ?? 0;
$units         = $user['sms_units'];
$isNewUser     = $totalCampaigns == 0;

// ── Chart ──────────────────────────────────────────────────────
$chartData = DB::query("
  SELECT DATE(created_at) as day, COUNT(*) as total
  FROM messages WHERE user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)
  GROUP BY day ORDER BY day ASC
",[$uid]);
$chartLabels = json_encode(array_column($chartData,'day'));
$chartValues = json_encode(array_column($chartData,'total'));

// ── Recent activity ────────────────────────────────────────────
$recentCampaigns = DB::query("
  SELECT * FROM campaigns WHERE user_id=? ORDER BY created_at DESC LIMIT 6
",[$uid]);
?>

<?php if ($isNewUser): ?>
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
})();
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
