<?php
$pageTitle = 'Campaigns';
$breadcrumb = [['label'=>'Client'],['label'=>'Campaigns']];
require_once __DIR__ . '/layout.php';

$uid      = $user['id'];
$page     = max(1,(int)($_GET['page']??1));
$perPage  = 15; $offset = ($page-1)*$perPage;
$status   = sanitize($_GET['status']??'');
$view     = sanitize($_GET['view']??'campaigns');
$params   = [$uid];

if ($view === 'history') {
    $where = 'WHERE user_id=?';
    if ($status){ $where.=' AND status=?'; $params[]=$status; }
    $total = DB::queryOne("SELECT COUNT(*) as c FROM messages $where", $params)['c']??0;
    $items = DB::query("SELECT * FROM messages $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);
} else {
    $where = 'WHERE c.user_id=?';
    if ($status){ $where.=' AND c.status=?'; $params[]=$status; }
    $total = DB::queryOne("SELECT COUNT(*) as c FROM campaigns c $where", $params)['c']??0;
    $items = DB::query(
        "SELECT c.* FROM campaigns c $where ORDER BY c.created_at DESC LIMIT $perPage OFFSET $offset",
        $params
    );
}
$totalPages= ceil($total/$perPage);
$senderIds = DB::query("SELECT sender_id FROM sender_ids WHERE user_id=? AND status='approved'",[$uid]);
$groups    = DB::query("SELECT id,name FROM contact_groups WHERE user_id=?",[$uid]);
?>
<div class="page-header">
  <div><h1>Campaigns</h1><div class="subtitle">Manage your bulk SMS campaigns</div></div>
  <button class="btn btn-primary" onclick="openModal('newCampaignModal')"><i class="fa-solid fa-plus"></i> New Campaign</button>
</div>

<div class="tabs" style="margin-bottom:12px">
  <a href="?view=campaigns" class="tab-btn <?= $view==='campaigns'?'active':'' ?>"><i class="fa-solid fa-bullhorn"></i> Bulk Campaigns</a>
  <a href="?view=history"   class="tab-btn <?= $view==='history'?'active':'' ?>"><i class="fa-solid fa-history"></i> Sent History (Single SMS)</a>
</div>

<?php if ($view === 'campaigns'): ?>
<div class="tabs" style="margin-bottom:18px">
  <?php foreach ([''=>'All','draft'=>'Draft','scheduled'=>'Scheduled','queued'=>'Queued','sending'=>'Sending','completed'=>'Completed','failed'=>'Failed'] as $k=>$v): ?>
    <a href="?view=campaigns&status=<?=$k?>" class="tab-btn <?=$status===$k?'active':''?>"><?=$v?></a>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="tabs" style="margin-bottom:18px">
  <?php foreach ([''=>'All Sent','sent'=>'Sent','delivered'=>'Delivered','failed'=>'Failed'] as $k=>$v): ?>
    <a href="?view=history&status=<?=$k?>" class="tab-btn <?=$status===$k?'active':''?>"><?=$v?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrapper">
    <table class="data-table">

      <?php if ($view === 'campaigns'): ?>
      <thead>
        <tr>
          <th>Campaign</th>
          <th>Sender ID</th>
          <th>Progress</th>
          <th>Status</th>
          <th>Scheduled</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
          <tr><td colspan="7"><div class="empty-state"><div class="empty-icon">📢</div><h3>No Campaigns Found</h3><p>Start your first bulk campaign.</p></div></td></tr>
        <?php else: ?>
          <?php foreach ($items as $c):
            $sc = ['draft'=>'muted','scheduled'=>'warning','queued'=>'warning','running'=>'info','sending'=>'info','completed'=>'success','failed'=>'danger'][$c['status']] ?? 'muted';
            $isActive = in_array($c['status'], ['queued','running','sending']);
            $isDone   = in_array($c['status'], ['completed','failed']);
            $total    = (int)($c['total_count'] ?? 0);
            $sent     = (int)($c['sent_count']   ?? 0);
            $failCnt  = (int)($c['failed_count'] ?? 0);
            $done     = $sent + $failCnt;
            $pct      = $total > 0 ? min(100, round($done / $total * 100)) : ($isDone ? 100 : 0);
          ?>
          <tr data-campaign-id="<?=$c['id']?>" data-status="<?=htmlspecialchars($c['status'])?>">
            <td>
              <strong><?=htmlspecialchars($c['name'])?></strong>
              <div style="font-size:11px;color:var(--text-secondary);max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($c['message'])?></div>
              <?php if ($isDone && $c['sent_at']): ?>
                <div style="font-size:11px;color:var(--text-secondary);margin-top:2px"><i class="fa-solid fa-calendar-check"></i> Finished <?=date('d M, H:i',strtotime($c['sent_at']))?></div>
              <?php endif; ?>
            </td>
            <td><code style="font-size:12px"><?=htmlspecialchars($c['sender_id'])?></code></td>
            <td class="progress-cell" style="min-width:180px">
              <?php if ($total > 0 || $isActive || $isDone): ?>
                <div class="campaign-counts" style="display:flex;gap:10px;font-size:13px;font-weight:600;margin-bottom:5px">
                  <span class="cnt-sent" style="color:var(--success)"><i class="fa-solid fa-check"></i> <span class="cnt-val"><?=number_format($sent)?></span> Sent</span>
                  <span class="cnt-failed" style="color:var(--danger)<?= $failCnt === 0 ? ';display:none' : '' ?>"><i class="fa-solid fa-xmark"></i> <span class="cnt-val"><?=number_format($failCnt)?></span> Failed</span>
                </div>
                <?php
                  $sentPct = $total > 0 ? min(100, (int)round($sent   / $total * 100)) : ($isDone && $failCnt === 0 ? 100 : 0);
                  $failPct = $total > 0 ? min(100 - $sentPct, (int)round($failCnt / $total * 100)) : 0;
                ?>
                <div class="prog-wrap" style="background:var(--border-color);border-radius:4px;height:7px;overflow:hidden;display:flex" title="<?=$pct?>% complete">
                  <div class="prog-sent" style="height:100%;width:<?=$sentPct?>%;background:var(--success);transition:width .4s ease"></div>
                  <div class="prog-fail" style="height:100%;width:<?=$failPct?>%;background:var(--danger);transition:width .4s ease"></div>
                </div>
                <div style="font-size:10px;color:var(--text-secondary);margin-top:2px"><span class="cnt-pct"><?=$pct?></span>% · <span class="cnt-total"><?=number_format($total)?></span> total</div>
              <?php else: ?>
                <div class="no-progress" style="font-weight:700;color:var(--text-primary)"><?=number_format($total)?> Contacts</div>
              <?php endif; ?>
            </td>
            <td class="status-cell">
              <span class="badge badge-<?=$sc?> campaign-status-badge"><?=ucfirst($c['status'])?></span>
              <?php if ($isActive): ?>
                <div class="sending-spinner" style="font-size:10px;color:var(--text-secondary);margin-top:3px"><i class="fa-solid fa-circle-notch fa-spin" style="font-size:9px"></i> Sending…</div>
              <?php endif; ?>
            </td>
            <td style="font-size:12px"><?=$c['scheduled_at']?date('d M Y H:i',strtotime($c['scheduled_at'])):'<span class="text-muted">—</span>'?></td>
            <td style="font-size:12px"><?=date('d M Y',strtotime($c['created_at']))?></td>
            <td>
              <div style="display:flex;gap:5px;flex-wrap:wrap">
                <?php if (in_array($c['status'],['draft','scheduled'])): ?>
                  <button class="btn btn-primary btn-sm btn-icon" onclick='editCampaign(<?=htmlspecialchars(json_encode($c),ENT_QUOTES,"UTF-8")?>)' title="Edit/Send"><i class="fa-solid fa-pen"></i></button>
                <?php endif; ?>
                <a href="/client/reports.php?campaign_id=<?=$c['id']?>" class="btn btn-muted btn-sm btn-icon" title="View Report"><i class="fa-solid fa-chart-line"></i></a>
                <?php if ($failCnt > 0): ?>
                  <button class="btn btn-danger btn-sm fail-btn" onclick="showFailures(<?=$c['id']?>,<?=htmlspecialchars(json_encode($c['name']),ENT_QUOTES,'UTF-8')?>)" title="View Failed Messages" style="font-size:11px;padding:4px 8px">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?=number_format($failCnt)?> Failed
                  </button>
                <?php endif; ?>
                <?php if (in_array($c['status'],['draft','scheduled'])): ?>
                  <form method="POST" action="/client/actions/delete-campaign.php" style="display:inline">
                    <input type="hidden" name="id" value="<?=$c['id']?>">
                    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
                    <button class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Delete?')"><i class="fa-solid fa-trash"></i></button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>

      <?php else: ?>
      <thead><tr><th>Recipient</th><th>Sender ID</th><th>Message</th><th>Units</th><th>Status</th><th>Reason</th><th>Sent At</th></tr></thead>
      <tbody>
        <?php if (empty($items)): ?>
          <tr><td colspan="7"><div class="empty-state"><div class="empty-icon">✉️</div><h3>No Messages Found</h3></div></td></tr>
        <?php else: ?>
          <?php foreach ($items as $m):
            $mc=['sent'=>'success','delivered'=>'success','failed'=>'danger','queued'=>'warning'][$m['status']]??'muted'; ?>
            <tr>
              <td><strong><?=htmlspecialchars($m['recipient'])?></strong></td>
              <td><code><?=htmlspecialchars($m['sender_id'])?></code></td>
              <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--text-secondary)" title="<?=htmlspecialchars($m['message'])?>"><?=htmlspecialchars($m['message'])?></td>
              <td><?=$m['units_charged']?></td>
              <td><span class="badge badge-<?=$mc?>"><?=ucfirst($m['status'])?></span></td>
              <td style="font-size:11px;color:var(--danger);max-width:200px"><?=htmlspecialchars($m['failed_reason']??'')?></td>
              <td style="font-size:11px"><?=$m['sent_at']?date('d M Y H:i',strtotime($m['sent_at'])):'—'?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php endif; ?>
    </table>
  </div>
  <?php if ($totalPages>1): ?>
    <div class="card-footer"><div class="pagination">
      <?php for($p=1;$p<=$totalPages;$p++): ?><a href="?page=<?=$p?>&view=<?=$view?>&status=<?=$status?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a><?php endfor; ?>
    </div></div>
  <?php endif; ?>
</div>

<!-- ── Failed Messages Drawer ────────────────────────────────────────────────── -->
<div id="failuresDrawer" style="display:none;position:fixed;top:0;right:0;width:520px;max-width:100vw;height:100vh;background:var(--card-bg);box-shadow:-4px 0 24px rgba(0,0,0,.18);z-index:9999;flex-direction:column;transform:translateX(100%);transition:transform .3s">
  <div style="padding:18px 20px;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;gap:12px">
    <div>
      <h3 style="margin:0;font-size:16px" id="drawerTitle">Failed Messages</h3>
      <div id="drawerSubtitle" style="font-size:12px;color:var(--text-secondary);margin-top:2px"></div>
    </div>
    <button onclick="closeDrawer()" style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-secondary);line-height:1">×</button>
  </div>
  <div id="drawerBody" style="flex:1;overflow-y:auto;padding:0"></div>
  <div id="drawerPager" style="padding:12px 20px;border-top:1px solid var(--border-color);display:flex;gap:8px;align-items:center;justify-content:center"></div>
</div>
<div id="drawerOverlay" onclick="closeDrawer()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9998"></div>

<!-- ── New / Edit Campaign Modal ─────────────────────────────────────────────── -->
<div class="modal-overlay" id="newCampaignModal">
  <div class="modal" style="max-width:620px">
    <div class="modal-header">
      <h3 class="modal-title" id="campaignModalTitle"><i class="fa-solid fa-bullhorn" style="color:var(--primary)"></i> New Campaign</h3>
      <button class="modal-close" onclick="closeModal('newCampaignModal')">×</button>
    </div>
    <form method="POST" action="/client/actions/create-campaign.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="id" id="campaignId">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Campaign Name <span class="required">*</span></label><input type="text" name="name" id="campaignName" class="form-control" required></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Sender ID <span class="required">*</span></label>
            <select name="sender_id" id="campaignSenderId" class="form-control" required>
              <option value="">-- Select --</option>
              <?php foreach ($senderIds as $s): ?><option value="<?=htmlspecialchars($s['sender_id'])?>"><?=htmlspecialchars($s['sender_id'])?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Schedule</label><input type="datetime-local" name="scheduled_at" id="campaignScheduledAt" class="form-control"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Recipients <span class="required">*</span></label>
          <div class="tabs">
            <button type="button" class="tab-btn active" id="tabBtnGrp"  onclick="switchTab(this,'cm-grp')">Group</button>
            <button type="button" class="tab-btn"        id="tabBtnNums" onclick="switchTab(this,'cm-nums')">Numbers</button>
            <button type="button" class="tab-btn"                         onclick="switchTab(this,'cm-csv')">CSV Upload</button>
          </div>
          <div class="tab-panel active" id="cm-grp">
            <select name="group_id" id="campaignGroupId" class="form-control">
              <option value="">-- Select Group --</option>
              <?php foreach ($groups as $g): ?><option value="<?=$g['id']?>"><?=htmlspecialchars($g['name'])?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="tab-panel" id="cm-nums"><textarea name="numbers" id="campaignNumbers" class="form-control" rows="3" placeholder="+254712345678, +254798765432"></textarea></div>
          <div class="tab-panel" id="cm-csv">
            <div class="upload-zone" onclick="document.getElementById('cm_csv').click()">
              <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
              <div class="upload-title">Click to upload CSV</div>
              <div class="upload-sub">Headers: phone, name</div>
            </div>
            <input type="file" id="cm_csv" name="csv_file" accept=".csv" style="display:none">
          </div>
        </div>
        <div class="form-group sms-composer">
          <label class="form-label">Message <span class="required">*</span></label>
          <textarea name="message" id="cmMsg" class="form-control" placeholder="Type message…" maxlength="918" required></textarea>
          <div class="sms-counter"><span id="cmChars">0</span>/<span id="cmLimit">160</span> · <span id="cmSegs">1</span> SMS parts<span id="cmEncoding" style="color:var(--warning);margin-left:4px"></span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="resetCampaign();closeModal('newCampaignModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Launch Campaign</button>
      </div>
    </form>
  </div>
</div>

<?php
$editJs = '';
if (isset($_GET['edit'])) {
    $ec = DB::queryOne("SELECT * FROM campaigns WHERE id=? AND user_id=?", [(int)$_GET['edit'], $uid]);
    if ($ec) $editJs = 'editCampaign(' . json_encode($ec) . ');';
}

$extraScript = '<script>';
if ($editJs) $extraScript .= $editJs;
$extraScript .= '
// ── GSM-7 charset (matches PHP SMS::isUnicode) ────────────────────────────────
const GSM7_SET = new Set([
    0x40,0xA3,0x24,0xA5,0xE8,0xE9,0xF9,0xEC,0xF2,0xC7,
    0x0A,0xD8,0xF8,0x0D,0xC5,0xE5,0x394,0x5F,0x3A6,0x393,
    0x39B,0x3A9,0x3A0,0x3A8,0x3A3,0x398,0x39E,0x1B,0xC6,0xE6,
    0xDF,0xC9,
    0x20,0x21,0x22,0x23,0xA4,0x25,0x26,0x27,0x28,0x29,
    0x2A,0x2B,0x2C,0x2D,0x2E,0x2F,
    0x30,0x31,0x32,0x33,0x34,0x35,0x36,0x37,0x38,0x39,
    0x3A,0x3B,0x3C,0x3D,0x3E,0x3F,0xA1,
    ...Array.from({length:26},(_,i)=>0x41+i),
    0xC4,0xD6,0xD1,0xDC,0xA7,0xBF,
    ...Array.from({length:26},(_,i)=>0x61+i),
    0xE4,0xF6,0xF1,0xFC,0xE0,
    0x7C,0x5E,0x20AC,0x7B,0x7D,0x5B,0x7E,0x5D,0x5C
]);
function msgIsUnicode(msg) {
    return [...msg].some(c => !GSM7_SET.has(c.codePointAt(0)));
}

// ── SMS counter ───────────────────────────────────────────────────────────────
const ta = document.getElementById("cmMsg");
if (ta) ta.addEventListener("input", updateSmsCounter);
function updateSmsCounter() {
    const msg     = ta.value;
    const chars   = [...msg].length;
    const unicode = msgIsUnicode(msg);
    const limit   = unicode ? 70 : 160;
    const segs    = chars === 0 ? 1 : Math.ceil(chars / limit);
    document.getElementById("cmChars").textContent    = chars;
    document.getElementById("cmLimit").textContent    = limit;
    document.getElementById("cmSegs").textContent     = segs;
    document.getElementById("cmEncoding").textContent = unicode ? "Unicode" : "";
}

// ── Campaign modal helpers ────────────────────────────────────────────────────
function editCampaign(c) {
    document.getElementById("campaignModalTitle").innerHTML = \'<i class="fa-solid fa-pen" style="color:var(--primary)"></i> Edit & Send Campaign\';
    document.getElementById("campaignId").value        = c.id;
    document.getElementById("campaignName").value      = c.name;
    document.getElementById("campaignSenderId").value  = c.sender_id;
    document.getElementById("campaignScheduledAt").value = c.scheduled_at ? c.scheduled_at.replace(" ","T").substring(0,16) : "";
    document.getElementById("cmMsg").value = c.message;
    if (c.group_id)  { document.getElementById("campaignGroupId").value = c.group_id; document.getElementById("tabBtnGrp").click(); }
    else if (c.numbers) { document.getElementById("campaignNumbers").value = c.numbers; document.getElementById("tabBtnNums").click(); }
    updateSmsCounter();
    openModal("newCampaignModal");
}
function resetCampaign() {
    document.getElementById("campaignModalTitle").innerHTML = \'<i class="fa-solid fa-bullhorn" style="color:var(--primary)"></i> New Campaign\';
    ["campaignId","campaignName","campaignScheduledAt","cmMsg","campaignNumbers"].forEach(id => document.getElementById(id).value = "");
    updateSmsCounter();
}

// ── Live campaign polling ─────────────────────────────────────────────────────
// Polls all campaign IDs visible on this page (not just ones active at load).
// Fast (2 s) when any campaign is active, slow (9 s) otherwise.
// Pauses when the tab is hidden; resumes immediately when it becomes visible.
let pollTimer = null;

function allCampaignIds() {
    return [...document.querySelectorAll("tr[data-campaign-id]")]
        .map(r => r.dataset.campaignId).filter(Boolean);
}
function hasActiveCampaigns() {
    return !!document.querySelector(\'tr[data-status="queued"], tr[data-status="running"], tr[data-status="sending"]\');
}
function scheduleNextPoll() {
    pollTimer = setTimeout(doPoll, hasActiveCampaigns() ? 2000 : 9000);
}

function doPoll() {
    clearTimeout(pollTimer);
    if (document.hidden) return;
    const ids = allCampaignIds();
    if (!ids.length) { scheduleNextPoll(); return; }
    fetch("/client/api/campaign-status.php?ids=" + ids.join(","))
        .then(r => r.ok ? r.json() : null)
        .then(data => { if (data) data.campaigns.forEach(applyUpdate); })
        .catch(() => {})
        .finally(scheduleNextPoll);
}

function applyUpdate(c) {
    const row = document.querySelector(`tr[data-campaign-id="${c.id}"]`);
    if (!row) return;

    const sent     = parseInt(c.sent_count)   || 0;
    const failed   = parseInt(c.failed_count) || 0;
    const total    = parseInt(c.total_count)  || 0;
    const done     = sent + failed;
    const pct      = total > 0 ? Math.min(100, Math.round(done   / total * 100)) : 0;
    const sentPct  = total > 0 ? Math.min(100, Math.round(sent   / total * 100)) : 0;
    const failPct  = total > 0 ? Math.min(100 - sentPct, Math.round(failed / total * 100)) : 0;
    const isActive = ["queued","running","sending"].includes(c.status);

    // Inject progress markup for campaigns that had no counts when page loaded
    const cell = row.querySelector(".progress-cell");
    if (cell && !cell.querySelector(".prog-wrap") && (total > 0 || isActive)) {
        cell.innerHTML =
            \'<div class="campaign-counts" style="display:flex;gap:10px;font-size:13px;font-weight:600;margin-bottom:5px">\' +
            \'<span class="cnt-sent" style="color:var(--success)"><i class="fa-solid fa-check"></i> <span class="cnt-val">0</span> Sent</span>\' +
            \'<span class="cnt-failed" style="color:var(--danger);display:none"><i class="fa-solid fa-xmark"></i> <span class="cnt-val">0</span> Failed</span>\' +
            \'</div>\' +
            \'<div class="prog-wrap" style="background:var(--border-color);border-radius:4px;height:7px;overflow:hidden;display:flex">\' +
            \'<div class="prog-sent" style="height:100%;width:0%;background:var(--success);transition:width .4s ease"></div>\' +
            \'<div class="prog-fail" style="height:100%;width:0%;background:var(--danger);transition:width .4s ease"></div>\' +
            \'</div>\' +
            \'<div style="font-size:10px;color:var(--text-secondary);margin-top:2px"><span class="cnt-pct">0</span>% · <span class="cnt-total">0</span> total</div>\';
    }

    // Update counts and dual-segment progress bar
    const sentVal  = row.querySelector(".cnt-sent .cnt-val");
    const failVal  = row.querySelector(".cnt-failed .cnt-val");
    const failSpan = row.querySelector(".cnt-failed");
    const pctEl    = row.querySelector(".cnt-pct");
    const totalEl  = row.querySelector(".cnt-total");
    const progSent = row.querySelector(".prog-sent");
    const progFail = row.querySelector(".prog-fail");

    if (sentVal)  sentVal.textContent  = sent.toLocaleString();
    if (failVal)  failVal.textContent  = failed.toLocaleString();
    if (pctEl)    pctEl.textContent    = pct;
    if (totalEl)  totalEl.textContent  = total.toLocaleString();
    if (failSpan) failSpan.style.display = failed > 0 ? "" : "none";
    if (progSent) progSent.style.width   = sentPct + "%";
    if (progFail) progFail.style.width   = failPct + "%";

    // Status badge — update on every transition
    if (row.dataset.status !== c.status) {
        row.dataset.status = c.status;
        const badge = row.querySelector(".campaign-status-badge");
        if (badge) {
            const cls = {draft:"muted",scheduled:"warning",queued:"warning",running:"info",sending:"info",completed:"success",failed:"danger"};
            badge.className = `badge badge-${cls[c.status]||"muted"} campaign-status-badge`;
            badge.textContent = c.status.charAt(0).toUpperCase() + c.status.slice(1);
        }
    }

    // Sending spinner — add when active, remove when terminal
    const statusCell = row.querySelector(".status-cell");
    if (statusCell) {
        const spinner = statusCell.querySelector(".sending-spinner");
        if (isActive && !spinner) {
            const d = document.createElement("div");
            d.className = "sending-spinner";
            d.style.cssText = "font-size:10px;color:var(--text-secondary);margin-top:3px";
            d.innerHTML = \'<i class="fa-solid fa-circle-notch fa-spin" style="font-size:9px"></i> Sending…\';
            statusCell.appendChild(d);
        } else if (!isActive && spinner) {
            spinner.remove();
        }
    }

    if (failed > 0) refreshFailButton(row, c.id, failed);
}

function refreshFailButton(row, campaignId, failedCount) {
    let btn = row.querySelector(".fail-btn");
    if (!btn) {
        const actionsDiv = row.querySelector("td:last-child > div");
        if (!actionsDiv) return;
        btn = document.createElement("button");
        btn.className = "btn btn-danger btn-sm fail-btn";
        btn.style.cssText = "font-size:11px;padding:4px 8px";
        btn.title = "View Failed Messages";
        const name = row.querySelector("strong")?.textContent || "";
        btn.addEventListener("click", () => showFailures(campaignId, name));
        actionsDiv.appendChild(btn);
    }
    btn.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> ${failedCount.toLocaleString()} Failed`;
}

// Start polling immediately; resume instantly when tab becomes visible again
document.addEventListener("visibilitychange", () => { if (!document.hidden) doPoll(); });
doPoll();

// ── Failures drawer ───────────────────────────────────────────────────────────
let drawerCampaignId  = null;
let drawerCurrentPage = 1;
let drawerHideTimer   = null;

function showFailures(campaignId, campaignName) {
    drawerCampaignId  = campaignId;
    drawerCurrentPage = 1;
    if (drawerHideTimer) { clearTimeout(drawerHideTimer); drawerHideTimer = null; }
    document.getElementById("drawerTitle").textContent    = "Failed Messages";
    document.getElementById("drawerSubtitle").textContent = campaignName;
    document.getElementById("drawerBody").innerHTML       = \'<div style="padding:40px;text-align:center"><i class="fa-solid fa-circle-notch fa-spin" style="font-size:24px;color:var(--primary)"></i></div>\';
    document.getElementById("drawerPager").innerHTML      = "";
    // Show drawer
    const drawer  = document.getElementById("failuresDrawer");
    const overlay = document.getElementById("drawerOverlay");
    drawer.style.display  = "flex";
    overlay.style.display = "block";
    requestAnimationFrame(() => { drawer.style.transform = "translateX(0)"; });
    loadFailures(campaignId, 1);
}

function closeDrawer() {
    const drawer  = document.getElementById("failuresDrawer");
    const overlay = document.getElementById("drawerOverlay");
    drawer.style.transform = "translateX(100%)";
    overlay.style.display  = "none";
    drawerHideTimer = setTimeout(() => { drawer.style.display = "none"; drawerHideTimer = null; }, 310);
    drawerCampaignId = null;
}

function loadFailures(campaignId, page) {
    drawerCurrentPage = page;
    document.getElementById("drawerBody").innerHTML = \'<div style="padding:40px;text-align:center"><i class="fa-solid fa-circle-notch fa-spin" style="font-size:24px;color:var(--primary)"></i></div>\';
    fetch(`/client/api/campaign-status.php?campaign_id=${campaignId}&page=${page}`)
        .then(r => r.ok ? r.json() : null)
        .then(data => {
            if (!data) { document.getElementById("drawerBody").innerHTML = \'<div style="padding:30px;text-align:center;color:var(--danger)">Failed to load.</div>\'; return; }
            renderFailures(data);
        })
        .catch(() => { document.getElementById("drawerBody").innerHTML = \'<div style="padding:30px;text-align:center;color:var(--danger)">Network error.</div>\'; });
}

function renderFailures(data) {
    const c = data.campaign;
    document.getElementById("drawerSubtitle").textContent = `${(c.failed_count||data.failed_total||0).toLocaleString()} failed of ${(c.total_count||0).toLocaleString()} total`;

    if (!data.failed_messages || data.failed_messages.length === 0) {
        document.getElementById("drawerBody").innerHTML = \'<div style="padding:40px;text-align:center;color:var(--text-secondary)"><i class="fa-solid fa-circle-check" style="font-size:28px;color:var(--success);margin-bottom:8px;display:block"></i>No failures recorded yet.</div>\';
        document.getElementById("drawerPager").innerHTML = "";
        return;
    }

    let html = \'<table style="width:100%;border-collapse:collapse;font-size:13px">\';
    html += \'<thead style="position:sticky;top:0;background:var(--card-bg);z-index:1"><tr>\';
    html += \'<th style="text-align:left;padding:10px 16px;border-bottom:1px solid var(--border-color);color:var(--text-secondary);font-size:11px;text-transform:uppercase">Phone</th>\';
    html += \'<th style="text-align:left;padding:10px 16px;border-bottom:1px solid var(--border-color);color:var(--text-secondary);font-size:11px;text-transform:uppercase">Reason</th>\';
    html += \'<th style="text-align:left;padding:10px 16px;border-bottom:1px solid var(--border-color);color:var(--text-secondary);font-size:11px;text-transform:uppercase">Time</th>\';
    html += \'</tr></thead><tbody>\';

    data.failed_messages.forEach(m => {
        const reason  = m.failed_reason || "—";
        const preview = m.message ? m.message.substring(0, 60) + (m.message.length > 60 ? "…" : "") : "";
        const time    = m.created_at ? new Date(m.created_at).toLocaleString(undefined, {month:"short",day:"numeric",hour:"2-digit",minute:"2-digit"}) : "—";
        html += `<tr style="border-bottom:1px solid var(--border-color)">
            <td style="padding:9px 16px">
              <div style="font-family:monospace;font-size:12px;font-weight:600">${escHtml(m.recipient)}</div>
              ${preview ? `<div style="font-size:11px;color:var(--text-secondary);margin-top:2px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(m.message||"")}">${escHtml(preview)}</div>` : ""}
            </td>
            <td style="padding:9px 16px;font-size:12px;color:var(--danger);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escHtml(reason)}">${escHtml(reason)}</td>
            <td style="padding:9px 16px;font-size:11px;color:var(--text-secondary);white-space:nowrap">${time}</td>
        </tr>`;
    });
    html += "</tbody></table>";
    document.getElementById("drawerBody").innerHTML = html;

    // Pager — Prev / Next
    const failLabel = `<span style="font-size:12px;color:var(--text-secondary)">${data.failed_total.toLocaleString()} failed · Page ${data.page} of ${data.pages}</span>`;
    let pager = failLabel;
    if (data.pages > 1) {
        let nav = "";
        if (data.page > 1)
            nav += `<button onclick="loadFailures(${drawerCampaignId},${data.page - 1})" class="btn btn-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i> Prev</button>`;
        nav += failLabel;
        if (data.page < data.pages)
            nav += `<button onclick="loadFailures(${drawerCampaignId},${data.page + 1})" class="btn btn-primary btn-sm">Next <i class="fa-solid fa-chevron-right"></i></button>`;
        pager = nav;
    }
    document.getElementById("drawerPager").innerHTML = pager;

    // Auto-refresh if campaign is still running
    if (["queued","running","sending"].includes(c.status) && drawerCampaignId === c.id) {
        setTimeout(() => { if (drawerCampaignId === c.id) loadFailures(c.id, drawerCurrentPage); }, 5000);
    }
}

function escHtml(s) {
    return String(s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;");
}
</script>';

include __DIR__ . '/../includes/layout-footer.php';
?>
