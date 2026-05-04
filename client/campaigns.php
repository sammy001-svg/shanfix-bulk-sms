<?php
$pageTitle = 'Campaigns';
$breadcrumb = [['label'=>'Client'],['label'=>'Campaigns']];
require_once __DIR__ . '/layout.php';

$uid      = $user['id'];
$page     = max(1,(int)($_GET['page']??1));
$perPage  = 15; $offset = ($page-1)*$perPage;
$status   = sanitize($_GET['status']??'');
$view     = sanitize($_GET['view']??'campaigns'); // 'campaigns' or 'history'
$where    = ($view === 'history') ? 'WHERE user_id=?' : 'WHERE c.user_id=?'; 
$params   = [$uid];
if ($status){ $where.=' AND status=?'; $params[]=$status; }

if ($view === 'history') {
    $total     = DB::queryOne("SELECT COUNT(*) as c FROM messages $where", $params)['c']??0;
    $items     = DB::query("SELECT * FROM messages $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);
} else {
    $total     = DB::queryOne("SELECT COUNT(*) as c FROM campaigns c $where", $params)['c']??0;
    // Enhanced query to fetch sent/failed counts per campaign
    $items     = DB::query("SELECT c.*, 
        (SELECT COUNT(*) FROM messages m WHERE m.campaign_id = c.id AND m.status IN ('sent','delivered')) as sent_count,
        (SELECT COUNT(*) FROM messages m WHERE m.campaign_id = c.id AND m.status = 'failed') as failed_count
        FROM campaigns c $where ORDER BY c.created_at DESC LIMIT $perPage OFFSET $offset", $params);
}
$totalPages= ceil($total/$perPage);
$senderIds = DB::query("SELECT sender_id FROM sender_ids WHERE user_id=? AND status='approved'",[$uid]);
$groups    = DB::query("SELECT id,name FROM contact_groups WHERE user_id=?",[$uid]);
?>
<div class="page-header">
  <div><h1>Campaigns</h1><div class="subtitle">Manage your bulk SMS campaigns</div></div>
  <button class="btn btn-primary" onclick="openModal('newCampaignModal')"><i class="fa-solid fa-plus"></i> New Campaign</button>
</div>

<!-- Main tabs -->
<div class="tabs" style="margin-bottom:12px">
  <a href="?view=campaigns" class="tab-btn <?= $view==='campaigns'?'active':'' ?>"><i class="fa-solid fa-bullhorn"></i> Bulk Campaigns</a>
  <a href="?view=history" class="tab-btn <?= $view==='history'?'active':'' ?>"><i class="fa-solid fa-history"></i> Sent History (Single SMS)</a>
</div>

<!-- Status tabs -->
<?php if ($view === 'campaigns'): ?>
<div class="tabs" style="margin-bottom:18px">
  <?php foreach ([''=>'All','draft'=>'Draft','scheduled'=>'Scheduled','running'=>'Running','completed'=>'Completed','failed'=>'Failed'] as $k=>$v): ?>
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
        <thead><tr><th>Name</th><th>Sender ID</th><th>Recipients</th><th>Status</th><th>Scheduled</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="7"><div class="empty-state"><div class="empty-icon">📢</div><h3>No Campaigns Found</h3><p>Start your first bulk campaign.</p></div></td></tr>
          <?php else: ?>
            <?php foreach ($items as $c): ?>
              <?php $sc=['draft'=>'muted','scheduled'=>'warning','running'=>'info','completed'=>'success','failed'=>'danger'][$c['status']]??'muted'; ?>
              <tr>
                <td><strong><?=htmlspecialchars($c['name'])?></strong><div style="font-size:11px;color:var(--text-secondary);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($c['message'])?></div></td>
                <td><code style="font-size:12px"><?=htmlspecialchars($c['sender_id'])?></code></td>
                <td>
                  <?php if ($c['status'] === 'completed' || $c['status'] === 'running'): ?>
                    <div style="font-weight:700; color:var(--text-primary); display:flex; gap:8px">
                      <span style="color:var(--success)"><i class="fa-solid fa-check"></i> <?=$c['sent_count']?> Sent</span>
                      <?php if (($c['failed_count']??0) > 0): ?>
                        <span style="color:var(--danger)"><i class="fa-solid fa-xmark"></i> <?=$c['failed_count']?> Failed</span>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <div style="font-weight:700; color:var(--text-primary)"><?=number_format($c['recipients_count']??0)?> Contacts</div>
                  <?php endif; ?>
                </td>
                <td><span class="badge badge-<?=$sc?>"><?=ucfirst($c['status'])?></span></td>
                <td style="font-size:12px"><?=$c['scheduled_at']?date('d M Y H:i',strtotime($c['scheduled_at'])):'<span class="text-muted">—</span>'?></td>
                <td style="font-size:12px"><?=date('d M Y',strtotime($c['created_at']))?></td>
                <td>
                  <?php if ($c['status']==='completed'): ?>
                    <div style="font-size:11px; color:var(--text-muted); margin-bottom:5px">
                       <i class="fa-solid fa-calendar-check"></i> Finished: <?= date('d M, H:i', strtotime($c['updated_at'] ?? $c['created_at'])) ?>
                    </div>
                  <?php endif; ?>
                  
                  <div style="display:flex; gap:5px">
                    <?php if ($c['status']==='draft' || $c['status']==='scheduled'): ?>
                      <button class="btn btn-primary btn-sm btn-icon" onclick='editCampaign(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)' title="Edit/Send"><i class="fa-solid fa-pen"></i></button>
                    <?php endif; ?>
                    
                    <a href="/client/reports.php?campaign_id=<?=$c['id']?>" class="btn btn-muted btn-sm btn-icon" title="View Report"><i class="fa-solid fa-chart-line"></i></a>
                    
                    <?php if ($c['status']==='draft' || $c['status']==='scheduled'): ?>
                      <form method="POST" action="/client/actions/delete-campaign.php" style="display:inline"><input type="hidden" name="id" value="<?=$c['id']?>"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><button class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Delete?')"><i class="fa-solid fa-trash"></i></button></form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      <?php else: ?>
        <thead><tr><th>Recipient</th><th>Sender ID</th><th>Message</th><th>Units</th><th>Status</th><th>Sent At</th></tr></thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">✉️</div><h3>No Messages Found</h3><p>You haven't sent any single SMS yet.</p></div></td></tr>
          <?php else: ?>
            <?php foreach ($items as $m): ?>
              <?php $mc=['sent'=>'success','delivered'=>'success','failed'=>'danger','queued'=>'warning'][$m['status']]??'muted'; ?>
              <tr>
                <td><strong><?=htmlspecialchars($m['recipient'])?></strong></td>
                <td><code><?=htmlspecialchars($m['sender_id'])?></code></td>
                <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=htmlspecialchars($m['message'])?>"><?=htmlspecialchars($m['message'])?></td>
                <td><?=$m['units_charged']?></td>
                <td><span class="badge badge-<?=$mc?>"><?=ucfirst($m['status'])?></span></td>
                <td style="font-size:12px"><?=date('d M Y H:i',strtotime($m['created_at']))?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      <?php endif; ?>
    </table>
  </div>
  <?php if ($totalPages>1): ?>
    <div class="card-footer"><div class="pagination">
      <?php for($p=1;$p<=$totalPages;$p++): ?><a href="?page=<?=$p?>&status=<?=$status?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a><?php endfor; ?>
    </div></div>
  <?php endif; ?>
</div>

<!-- New Campaign Modal -->
<div class="modal-overlay" id="newCampaignModal">
  <div class="modal" style="max-width:620px">
    <div class="modal-header"><h3 class="modal-title" id="campaignModalTitle"><i class="fa-solid fa-bullhorn" style="color:var(--primary)"></i> New Campaign</h3><button class="modal-close" onclick="closeModal('newCampaignModal')">×</button></div>
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
          <div class="tabs"><button type="button" class="tab-btn active" id="tabBtnGrp" onclick="switchTab(this,'cm-grp')">Group</button><button type="button" class="tab-btn" id="tabBtnNums" onclick="switchTab(this,'cm-nums')">Numbers</button><button type="button" class="tab-btn" onclick="switchTab(this,'cm-csv')">CSV Upload</button></div>
          <div class="tab-panel active" id="cm-grp">
            <select name="group_id" id="campaignGroupId" class="form-control"><option value="">-- Select Group --</option><?php foreach ($groups as $g): ?><option value="<?=$g['id']?>"><?=htmlspecialchars($g['name'])?></option><?php endforeach; ?></select>
          </div>
          <div class="tab-panel" id="cm-nums"><textarea name="numbers" id="campaignNumbers" class="form-control" rows="3" placeholder="+254712345678, +254798765432"></textarea></div>
          <div class="tab-panel" id="cm-csv"><div class="upload-zone" onclick="document.getElementById('cm_csv').click()"><i class="fa-solid fa-cloud-arrow-up upload-icon"></i><div class="upload-title">Click to upload CSV</div><div class="upload-sub">Headers: phone, name</div></div><input type="file" id="cm_csv" name="csv_file" accept=".csv" style="display:none"></div>
        </div>
        <div class="form-group sms-composer"><label class="form-label">Message <span class="required">*</span></label>
          <textarea name="message" id="cmMsg" class="form-control" placeholder="Type message..." maxlength="918" required></textarea>
          <div class="sms-counter"><span id="cmChars">0</span>/160 · <span id="cmSegs">1</span> SMS parts</div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="resetCampaign(); closeModal('newCampaignModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Launch Campaign</button></div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
const ta=document.getElementById('cmMsg');
if(ta){ta.addEventListener('input', updateSmsCounter);}

function updateSmsCounter() {
    const l=ta.value.length, s=Math.ceil(l/160)||1;
    document.getElementById('cmChars').textContent=l;
    document.getElementById('cmSegs').textContent=s;
}

function editCampaign(c) {
    document.getElementById('campaignModalTitle').innerHTML = '<i class="fa-solid fa-pen" style="color:var(--primary)"></i> Edit & Send Campaign';
    document.getElementById('campaignId').value = c.id;
    document.getElementById('campaignName').value = c.name;
    document.getElementById('campaignSenderId').value = c.sender_id;
    document.getElementById('campaignScheduledAt').value = c.scheduled_at ? c.scheduled_at.replace(' ', 'T').substring(0, 16) : '';
    document.getElementById('cmMsg').value = c.message;
    
    if (c.group_id) {
        document.getElementById('campaignGroupId').value = c.group_id;
        document.getElementById('tabBtnGrp').click();
    } else if (c.numbers) {
        document.getElementById('campaignNumbers').value = c.numbers;
        document.getElementById('tabBtnNums').click();
    }
    
    updateSmsCounter();
    openModal('newCampaignModal');
}

function resetCampaign() {
    document.getElementById('campaignModalTitle').innerHTML = '<i class="fa-solid fa-bullhorn" style="color:var(--primary)"></i> New Campaign';
    document.getElementById('campaignId').value = '';
    document.getElementById('campaignName').value = '';
    document.getElementById('campaignScheduledAt').value = '';
    document.getElementById('cmMsg').value = '';
    document.getElementById('campaignNumbers').value = '';
    updateSmsCounter();
}

<?php
$editJs = "";
if (isset($_GET['edit'])) {
    $ec = DB::queryOne("SELECT * FROM campaigns WHERE id=? AND user_id=?", [(int)$_GET['edit'], $uid]);
    if ($ec) {
        $editJs = "editCampaign(".json_encode($ec).");";
    }
}
$extraScript = <<<JS
<script>
$editJs
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
