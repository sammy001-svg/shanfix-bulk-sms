<?php
// CSV export must happen before layout.php emits headers
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/actions/sms.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    flash_set('danger', 'Campaign ID missing.');
    redirect('/admin/campaigns.php');
}

$c = DB::queryOne(
    "SELECT c.*,
            u.name AS user_name, u.email AS user_email, u.role AS user_role,
            u.status AS user_status, u.sms_units AS user_units,
            p.id AS parent_id, p.name AS parent_name,
            g.name AS group_name
     FROM campaigns c
     JOIN users u        ON c.user_id = u.id
     LEFT JOIN users p   ON u.parent_id = p.id
     LEFT JOIN contact_groups g ON c.group_id = g.id
     WHERE c.id = ?",
    [$id]
);
if (!$c) {
    flash_set('danger', 'Campaign not found.');
    redirect('/admin/campaigns.php');
}

// ── Message-level breakdown ───────────────────────────────────────────────────
// The campaign row only counts sent vs failed at dispatch time. Delivery
// receipts land on the messages table, so the true funnel lives here.
$breakdown    = ['queued'=>0,'sent'=>0,'delivered'=>0,'failed'=>0,'undelivered'=>0];
$loggedTotal  = 0;
$unitsCharged = 0.0;
foreach (DB::query(
    "SELECT status, COUNT(*) AS c, COALESCE(SUM(units_charged),0) AS units
     FROM messages WHERE campaign_id = ? GROUP BY status", [$id]
) as $row) {
    $breakdown[$row['status']] = (int)$row['c'];
    $loggedTotal  += (int)$row['c'];
    $unitsCharged += (float)$row['units'];
}

$targetCount = (int)$c['total_count'];
$accepted    = $breakdown['sent'] + $breakdown['delivered'];
$failedTotal = $breakdown['failed'] + $breakdown['undelivered'];
$processed   = $accepted + $failedTotal;
$deliveryPct = $processed > 0 ? round($breakdown['delivered'] / $processed * 100, 1) : 0.0;
$successPct  = $processed > 0 ? round($accepted / $processed * 100, 1) : 0.0;
$progressPct = $targetCount > 0
    ? min(100, (int)round(($c['sent_count'] + $c['failed_count']) / $targetCount * 100))
    : (in_array($c['status'], ['completed','failed'], true) ? 100 : 0);

$isActive  = in_array($c['status'], ['queued','running','sending'], true);
$canCancel = in_array($c['status'], ['queued','scheduled'], true);
$canRetry  = !$isActive && $c['status'] !== 'scheduled' && $failedTotal > 0;

// Worker health — the rescue cron re-queues anything silent for 5 minutes.
$heartbeatAge  = $c['last_heartbeat_at'] ? time() - strtotime($c['last_heartbeat_at']) : null;
$workerStalled = $c['status'] === 'sending' && $heartbeatAge !== null && $heartbeatAge > 300;

// ── Message log filters ───────────────────────────────────────────────────────
$msgStatus = sanitize($_GET['status'] ?? '');
$msgSearch = sanitize($_GET['q'] ?? '');
$msgPage   = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 25;

if (!array_key_exists($msgStatus, $breakdown)) $msgStatus = '';

$where  = "WHERE m.campaign_id = ?";
$params = [$id];
if ($msgStatus === 'failed') {
    // The "Failed" tab should surface undelivered receipts too — same problem.
    $where .= " AND m.status IN ('failed','undelivered')";
} elseif ($msgStatus) {
    $where .= " AND m.status = ?";
    $params[] = $msgStatus;
}
if ($msgSearch) {
    $where .= " AND (m.recipient LIKE ? OR m.failed_reason LIKE ?)";
    $params[] = "%$msgSearch%";
    $params[] = "%$msgSearch%";
}

// ── CSV export of this campaign's message log ─────────────────────────────────
if (($_GET['export'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="campaign_' . $id . '_messages.csv"');
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['Message ID','Recipient','Status','Units','Gateway Msg ID','Failed Reason','Sent At','Delivered At','Created At']);
    foreach (DB::query("SELECT m.* FROM messages m $where ORDER BY m.id ASC", $params) as $m) {
        fputcsv($fh, [
            $m['id'], $m['recipient'], $m['status'], $m['units_charged'],
            $m['gateway_msg_id'] ?? '', $m['failed_reason'] ?? '',
            $m['sent_at'] ?? '', $m['delivered_at'] ?? '', $m['created_at'],
        ]);
    }
    fclose($fh);
    exit;
}

$msgTotal  = (int)DB::queryValue("SELECT COUNT(*) FROM messages m $where", $params);
$msgPages  = max(1, (int)ceil($msgTotal / $perPage));
$msgPage   = min($msgPage, $msgPages);
$msgOffset = ($msgPage - 1) * $perPage;
$messages  = DB::query(
    "SELECT m.* FROM messages m $where ORDER BY m.id DESC LIMIT $perPage OFFSET $msgOffset",
    $params
);

// ── Top failure reasons ───────────────────────────────────────────────────────
$failReasons = DB::query(
    "SELECT COALESCE(NULLIF(TRIM(failed_reason), ''), 'Unspecified') AS reason, COUNT(*) AS c
     FROM messages
     WHERE campaign_id = ? AND status IN ('failed','undelivered')
     GROUP BY reason ORDER BY c DESC LIMIT 10",
    [$id]
);

// Sender ID still valid? The worker hard-fails a campaign when it is not.
$senderApproved = (bool)DB::queryOne(
    "SELECT id FROM sender_ids WHERE user_id = ? AND BINARY sender_id = ? AND status = 'approved'",
    [$c['user_id'], $c['sender_id']]
);

function campDetailQs(array $ov = []): string {
    global $id, $msgStatus, $msgSearch;
    $base = ['id' => $id];
    if ($msgStatus) $base['status'] = $msgStatus;
    if ($msgSearch) $base['q']      = $msgSearch;
    return http_build_query(array_filter(array_merge($base, $ov), fn($v) => $v !== null && $v !== ''));
}

$statusClass = ['draft'=>'muted','scheduled'=>'warning','queued'=>'warning','running'=>'info',
                'sending'=>'info','completed'=>'success','failed'=>'danger'][$c['status']] ?? 'muted';

$pageTitle  = 'Campaign #' . $id;
$breadcrumb = [['label'=>'Admin'],['label'=>'Campaigns','url'=>'/admin/campaigns.php'],['label'=>$c['name']]];
require_once __DIR__ . '/layout.php';
?>

<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <?= htmlspecialchars($c['name']) ?>
      <span class="badge badge-<?= $statusClass ?>" id="campStatusBadge"><?= ucfirst($c['status']) ?></span>
    </h1>
    <div class="subtitle">
      Campaign #<?= $id ?> &middot;
      <a href="/admin/user-detail.php?id=<?= (int)$c['user_id'] ?>"><?= htmlspecialchars($c['user_name']) ?></a>
      (<?= htmlspecialchars($c['user_email']) ?>) &middot;
      created <?= date('d M Y, H:i', strtotime($c['created_at'])) ?>
    </div>
  </div>
  <div class="btn-group">
    <a href="/admin/campaigns.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back</a>
    <a href="?<?= campDetailQs(['export'=>'1']) ?>" class="btn btn-secondary"><i class="fa-solid fa-download"></i> Export Log</a>
    <?php if ($canRetry): ?>
      <form method="POST" action="/admin/actions/retry-campaign.php" style="display:inline"
            onsubmit="return confirm('Queue a new campaign for the <?= number_format($failedTotal) ?> failed recipient(s)?')">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-rotate-right"></i> Retry <?= number_format($failedTotal) ?> Failed</button>
      </form>
    <?php endif; ?>
    <?php if ($canCancel): ?>
      <form method="POST" action="/admin/actions/cancel-campaign.php" style="display:inline"
            onsubmit="return confirm('Cancel this campaign?')">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-ban"></i> Cancel</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($workerStalled): ?>
  <div class="alert alert-warning">
    <i class="fa-solid fa-triangle-exclamation"></i>
    Worker silent for <?= floor($heartbeatAge / 60) ?> minute(s). The rescue cron re-queues campaigns after 5 minutes without a heartbeat — if this persists, the cron is not running.
  </div>
<?php endif; ?>
<?php if (!$senderApproved): ?>
  <div class="alert alert-danger">
    <i class="fa-solid fa-triangle-exclamation"></i>
    Sender ID <code><?= htmlspecialchars($c['sender_id']) ?></code> is no longer approved for this user. Any send or retry will fail immediately.
    <a href="/admin/sender-ids.php">Review sender IDs</a>
  </div>
<?php endif; ?>

<!-- Delivery funnel -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
    <div class="stat-info">
      <div class="stat-label">Recipients</div>
      <div class="stat-value" id="kpiTotal"><?= number_format($targetCount) ?></div>
      <div class="stat-trend" style="color:var(--text-secondary)"><?= number_format($loggedTotal) ?> logged</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-paper-plane"></i></div>
    <div class="stat-info">
      <div class="stat-label">Accepted by Gateway</div>
      <div class="stat-value" id="kpiSent"><?= number_format($accepted) ?></div>
      <div class="stat-trend" style="color:var(--success)"><?= $successPct ?>% of processed</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
    <div class="stat-info">
      <div class="stat-label">Delivered</div>
      <div class="stat-value" id="kpiDelivered"><?= number_format($breakdown['delivered']) ?></div>
      <div class="stat-trend" style="color:var(--text-secondary)"><?= $deliveryPct ?>% confirmed</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fa-solid fa-circle-xmark"></i></div>
    <div class="stat-info">
      <div class="stat-label">Failed</div>
      <div class="stat-value" id="kpiFailed"><?= number_format($failedTotal) ?></div>
      <div class="stat-trend" style="color:var(--text-secondary)"><?= number_format($breakdown['undelivered']) ?> undelivered</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fa-solid fa-coins"></i></div>
    <div class="stat-info">
      <div class="stat-label">Units Charged</div>
      <div class="stat-value"><?= number_format($unitsCharged, 2) ?></div>
      <div class="stat-trend" style="color:var(--text-secondary)">campaign records <?= number_format($c['units_used'], 2) ?></div>
    </div>
  </div>
</div>

<!-- Dispatch progress -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px 18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <strong style="font-size:13px">Dispatch progress</strong>
      <span style="font-size:12px;color:var(--text-secondary)">
        <span id="progDone"><?= number_format($c['sent_count'] + $c['failed_count']) ?></span>
        of <?= number_format($targetCount) ?> processed &middot; <span id="progPct"><?= $progressPct ?></span>%
      </span>
    </div>
    <?php
      $sentPct = $targetCount > 0 ? min(100, (int)round($c['sent_count'] / $targetCount * 100)) : 0;
      $failPct = $targetCount > 0 ? min(100 - $sentPct, (int)round($c['failed_count'] / $targetCount * 100)) : 0;
    ?>
    <div style="background:var(--border-color);border-radius:4px;height:10px;overflow:hidden;display:flex">
      <div id="progSent" style="height:100%;width:<?= $sentPct ?>%;background:var(--success);transition:width .4s ease"></div>
      <div id="progFail" style="height:100%;width:<?= $failPct ?>%;background:var(--danger);transition:width .4s ease"></div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

  <!-- Campaign detail -->
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-circle-info" style="color:var(--primary)"></i> Campaign Detail</h3></div>
    <div class="card-body" style="padding:0">
      <table class="data-table">
        <tbody>
          <tr>
            <td style="color:var(--text-secondary);width:150px">Sender ID</td>
            <td><code><?= htmlspecialchars($c['sender_id']) ?></code>
              <?php if (!$senderApproved): ?><span class="badge badge-danger" style="font-size:10px">Not approved</span><?php endif; ?>
            </td>
          </tr>
          <tr>
            <td style="color:var(--text-secondary)">Owner</td>
            <td>
              <a href="/admin/user-detail.php?id=<?= (int)$c['user_id'] ?>"><?= htmlspecialchars($c['user_name']) ?></a>
              <span class="badge <?= $c['user_role'] === 'reseller' ? 'badge-success' : 'badge-info' ?>" style="font-size:10px"><?= ucfirst($c['user_role']) ?></span>
              <?php if ($c['parent_name']): ?>
                <div style="font-size:11px;color:var(--text-secondary)">under <a href="/admin/user-detail.php?id=<?= (int)$c['parent_id'] ?>"><?= htmlspecialchars($c['parent_name']) ?></a></div>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <td style="color:var(--text-secondary)">Owner balance</td>
            <td><strong><?= number_format((float)$c['user_units'], 2) ?></strong> units</td>
          </tr>
          <tr>
            <td style="color:var(--text-secondary)">Recipient source</td>
            <td>
              <?php if ($c['group_name']): ?>
                <i class="fa-solid fa-layer-group"></i> Group: <?= htmlspecialchars($c['group_name']) ?>
              <?php elseif ($c['file_path']): ?>
                <i class="fa-solid fa-file-csv"></i> Uploaded file
                <div style="font-size:11px;color:var(--text-secondary);word-break:break-all"><?= htmlspecialchars(basename($c['file_path'])) ?></div>
              <?php elseif ($c['recipients']): ?>
                <i class="fa-solid fa-list"></i> Pasted list
              <?php else: ?>
                <span class="text-muted">&mdash;</span>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <td style="color:var(--text-secondary)">Scheduled for</td>
            <td><?= $c['scheduled_at'] ? date('d M Y, H:i', strtotime($c['scheduled_at'])) : '<span class="text-muted">Immediate</span>' ?></td>
          </tr>
          <tr>
            <td style="color:var(--text-secondary)">Finished</td>
            <td><?= $c['sent_at'] ? date('d M Y, H:i', strtotime($c['sent_at'])) : '<span class="text-muted">&mdash;</span>' ?></td>
          </tr>
          <tr>
            <td style="color:var(--text-secondary)">Worker</td>
            <td>
              <?php if ($c['locked_at']): ?>
                Claimed <?= date('d M H:i', strtotime($c['locked_at'])) ?>
                <div style="font-size:11px;color:<?= $workerStalled ? 'var(--danger)' : 'var(--text-secondary)' ?>">
                  heartbeat <?= $c['last_heartbeat_at'] ? date('d M H:i:s', strtotime($c['last_heartbeat_at'])) : 'never' ?>
                </div>
              <?php else: ?>
                <span class="text-muted">Never claimed</span>
              <?php endif; ?>
            </td>
          </tr>
          <tr>
            <td style="color:var(--text-secondary)">Rows processed</td>
            <td><?= number_format((int)$c['processed_rows']) ?><?= $c['resume_contact_id'] ? ' &middot; cursor at contact #' . (int)$c['resume_contact_id'] : '' ?></td>
          </tr>
        </tbody>
      </table>
      <div style="padding:14px 16px;border-top:1px solid var(--border-color)">
        <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">Message body</div>
        <div style="background:rgba(127,127,127,.08);border-radius:6px;padding:12px;font-size:13px;white-space:pre-wrap;word-break:break-word"><?= htmlspecialchars($c['message']) ?></div>
        <div style="font-size:11px;color:var(--text-secondary);margin-top:6px">
          <?php $segment = SMS::isUnicode($c['message']) ? 70 : 160; ?>
          <?= mb_strlen($c['message']) ?> characters &middot;
          <?= max(1, (int)ceil(mb_strlen($c['message']) / $segment)) ?> part(s) per message
          &middot; <?= $segment === 70 ? 'Unicode (UCS-2)' : 'GSM-7' ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Failure breakdown -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-triangle-exclamation" style="color:var(--danger)"></i> Failure Reasons</h3>
      <?php if ($failedTotal > 0): ?>
        <a href="?<?= campDetailQs(['status'=>'failed','page'=>1]) ?>" class="btn btn-outline btn-sm">View all</a>
      <?php endif; ?>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (empty($failReasons)): ?>
        <div class="text-center text-muted" style="padding:40px">
          <i class="fa-solid fa-circle-check" style="font-size:28px;color:var(--success);display:block;margin-bottom:10px"></i>
          No failures recorded on this campaign
        </div>
      <?php else: ?>
        <table class="data-table">
          <thead><tr><th>Reason</th><th style="width:80px;text-align:right">Count</th><th style="width:90px;text-align:right">Share</th></tr></thead>
          <tbody>
            <?php foreach ($failReasons as $r): ?>
              <tr>
                <td style="font-size:12px;word-break:break-word"><?= htmlspecialchars($r['reason']) ?></td>
                <td style="text-align:right;font-weight:700;color:var(--danger)"><?= number_format($r['c']) ?></td>
                <td style="text-align:right;font-size:12px;color:var(--text-secondary)"><?= $failedTotal > 0 ? round($r['c'] / $failedTotal * 100, 1) : 0 ?>%</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Message log -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-envelope-open-text" style="color:var(--primary)"></i> Message Log <span class="badge badge-muted"><?= number_format($msgTotal) ?></span></h3>
  </div>

  <div class="card-body" style="padding:14px 18px;border-bottom:1px solid var(--border-color)">
    <div class="tabs" style="margin-bottom:12px">
      <?php foreach (['' => 'All', 'sent' => 'Sent', 'delivered' => 'Delivered', 'failed' => 'Failed', 'queued' => 'Queued'] as $k => $label):
        $tabCount = $k === '' ? $loggedTotal : ($k === 'failed' ? $failedTotal : $breakdown[$k]); ?>
        <a href="?<?= campDetailQs(['status'=>$k,'page'=>1]) ?>" class="tab-btn <?= $msgStatus === $k ? 'active' : '' ?>">
          <?= $label ?> <span class="badge badge-muted" style="font-size:10px"><?= number_format($tabCount) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <input type="hidden" name="id" value="<?= $id ?>">
      <?php if ($msgStatus): ?><input type="hidden" name="status" value="<?= htmlspecialchars($msgStatus) ?>"><?php endif; ?>
      <div class="input-group" style="flex:1;min-width:220px">
        <div class="input-group-text input-addon-left"><i class="fa-solid fa-magnifying-glass"></i></div>
        <input type="text" name="q" class="form-control with-left" placeholder="Recipient number or failure reason…" value="<?= htmlspecialchars($msgSearch) ?>">
      </div>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Search</button>
      <?php if ($msgSearch || $msgStatus): ?>
        <a href="/admin/campaign-detail.php?id=<?= $id ?>" class="btn btn-secondary">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Recipient</th><th>Status</th><th>Units</th><th>Gateway Ref</th><th>Failure Reason</th><th>Sent</th><th>Delivered</th></tr>
      </thead>
      <tbody>
        <?php if (empty($messages)): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:40px">
            <?= $loggedTotal === 0 ? 'No messages logged yet for this campaign' : 'No messages match these filters' ?>
          </td></tr>
        <?php else: foreach ($messages as $m):
          $ms = ['sent'=>'success','delivered'=>'success','failed'=>'danger','queued'=>'warning','undelivered'=>'warning'][$m['status']] ?? 'muted'; ?>
          <tr>
            <td style="font-weight:600;font-size:13px"><?= htmlspecialchars($m['recipient']) ?></td>
            <td><span class="badge badge-<?= $ms ?>"><?= ucfirst($m['status']) ?></span></td>
            <td><?= number_format((float)$m['units_charged'], 2) ?></td>
            <td><code style="font-size:11px"><?= $m['gateway_msg_id'] ? htmlspecialchars($m['gateway_msg_id']) : '&mdash;' ?></code></td>
            <td style="font-size:12px;color:var(--danger);max-width:260px;word-break:break-word"><?= $m['failed_reason'] ? htmlspecialchars($m['failed_reason']) : '<span class="text-muted">&mdash;</span>' ?></td>
            <td style="font-size:11px"><?= $m['sent_at'] ? date('d M H:i:s', strtotime($m['sent_at'])) : '&mdash;' ?></td>
            <td style="font-size:11px"><?= $m['delivered_at'] ? date('d M H:i:s', strtotime($m['delivered_at'])) : '&mdash;' ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
    <div style="font-size:12px;color:var(--text-secondary)">
      <?php if ($msgTotal > 0): ?>
        Showing <?= number_format($msgOffset + 1) ?> &ndash; <?= number_format(min($msgPage * $perPage, $msgTotal)) ?> of <?= number_format($msgTotal) ?>
      <?php else: ?>
        No messages
      <?php endif; ?>
    </div>
    <?php if ($msgPages > 1): ?>
      <div style="display:flex;gap:6px">
        <?php if ($msgPage > 1): ?>
          <a href="?<?= campDetailQs(['page'=>$msgPage-1]) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i> Prev</a>
        <?php endif; ?>
        <?php if ($msgPage < $msgPages): ?>
          <a href="?<?= campDetailQs(['page'=>$msgPage+1]) ?>" class="btn btn-primary btn-sm">Next <i class="fa-solid fa-chevron-right"></i></a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
$jsCampaignId = $id;
$jsStatus     = $c['status'];
$extraScript  = <<<JS
<script>
// Live refresh while the campaign is still moving. Polling stops once the
// worker reports a terminal status; the page reloads once at that point so the
// message log, failure breakdown and action buttons all catch up.
const CAMPAIGN_ID = $jsCampaignId;
const ACTIVE_STATES = ['queued','running','sending'];
let currentStatus = '$jsStatus';
let pollTimer = null;

function setText(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
}

function pollDetail() {
    clearTimeout(pollTimer);
    if (document.hidden || !ACTIVE_STATES.includes(currentStatus)) return;

    fetch('/admin/api/campaign-status.php?campaign_id=' + CAMPAIGN_ID)
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (!data || !data.campaign) return;
            const c = data.campaign;
            const b = data.breakdown || {};

            const total   = parseInt(c.total_count)  || 0;
            const sent    = parseInt(c.sent_count)   || 0;
            const failed  = parseInt(c.failed_count) || 0;
            const done    = sent + failed;
            const pct     = total > 0 ? Math.min(100, Math.round(done / total * 100)) : 0;
            const sentPct = total > 0 ? Math.min(100, Math.round(sent / total * 100)) : 0;
            const failPct = total > 0 ? Math.min(100 - sentPct, Math.round(failed / total * 100)) : 0;

            setText('progDone', done.toLocaleString());
            setText('progPct', pct);
            const ps = document.getElementById('progSent');
            const pf = document.getElementById('progFail');
            if (ps) ps.style.width = sentPct + '%';
            if (pf) pf.style.width = failPct + '%';

            setText('kpiTotal', total.toLocaleString());
            setText('kpiSent', ((b.sent || 0) + (b.delivered || 0)).toLocaleString());
            setText('kpiDelivered', (b.delivered || 0).toLocaleString());
            setText('kpiFailed', ((b.failed || 0) + (b.undelivered || 0)).toLocaleString());

            if (c.status !== currentStatus) {
                currentStatus = c.status;
                const badge = document.getElementById('campStatusBadge');
                if (badge) {
                    const cls = {draft:'muted',scheduled:'warning',queued:'warning',running:'info',
                                 sending:'info',completed:'success',failed:'danger'};
                    badge.className = 'badge badge-' + (cls[c.status] || 'muted');
                    badge.textContent = c.status.charAt(0).toUpperCase() + c.status.slice(1);
                }
                if (!ACTIVE_STATES.includes(c.status)) { location.reload(); return; }
            }
        })
        .catch(() => {})
        .finally(() => { pollTimer = setTimeout(pollDetail, 3000); });
}

document.addEventListener('visibilitychange', () => { if (!document.hidden) pollDetail(); });
pollDetail();
</script>
JS;

include __DIR__ . '/../includes/layout-footer.php';
