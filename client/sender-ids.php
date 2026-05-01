<?php
// Client Sender IDs page
$pageTitle = 'Sender IDs';
$breadcrumb = [['label'=>'Client'],['label'=>'Sender IDs']];
require_once __DIR__ . '/layout.php';

$uid      = $user['id'];
$senderIds= DB::query("SELECT * FROM sender_ids WHERE user_id=? ORDER BY created_at DESC",[$uid]);
?>
<div class="page-header">
  <div>
    <h1>Sender IDs</h1>
    <div class="subtitle">Your approved sender identifiers. <a href="/actions/download-sender-id-template.php" style="color:var(--primary); font-weight:600; text-decoration:underline"><i class="fa-solid fa-file-arrow-down"></i> Download Application Template</a></div>
  </div>
  <button class="btn btn-primary" onclick="openModal('requestModal')"><i class="fa-solid fa-plus"></i> Request Sender ID</button>
</div>

<div class="card">
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Sender ID</th><th>Status</th><th>Requested</th><th>Approved</th><th>Note</th></tr></thead>
      <tbody>
        <?php if (empty($senderIds)): ?>
          <tr><td colspan="5"><div class="empty-state">
            <div class="empty-icon">🪪</div><h3>No Sender IDs</h3>
            <p>Request a Sender ID to personalise your messages.</p>
            <button class="btn btn-primary" onclick="openModal('requestModal')"><i class="fa-solid fa-plus"></i> Request Now</button>
          </div></td></tr>
        <?php else: foreach ($senderIds as $s):
          $sc=['approved'=>'success','pending'=>'warning','rejected'=>'danger'][$s['status']]??'muted'; ?>
          <tr>
            <td><code style="font-size:14px;font-weight:700"><?=htmlspecialchars($s['sender_id'])?></code></td>
            <td><span class="badge badge-<?=$sc?>"><?=ucfirst($s['status'])?></span></td>
            <td style="font-size:12px"><?=date('d M Y',strtotime($s['created_at']))?></td>
            <td style="font-size:12px"><?=$s['approved_at']?date('d M Y',strtotime($s['approved_at'])):'—'?></td>
            <td style="font-size:12px;color:var(--text-secondary)"><?=htmlspecialchars($s['rejection_reason']??'—')?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="requestModal">
  <div class="modal" style="max-width:550px">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-id-badge" style="color:var(--primary)"></i> Request Sender ID</h3>
      <button class="modal-close" onclick="closeModal('requestModal')">×</button>
    </div>
    <form id="senderRequestForm" method="POST" action="/client/actions/request-sender-id.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      
      <!-- Step 1: Info -->
      <div id="step1" class="modal-body">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px">
          <span style="background:var(--primary); color:#000; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:12px">1</span>
          <h4 style="margin:0">Basic Information</h4>
        </div>
        <div class="form-group">
          <label class="form-label">Sender ID <span class="required">*</span></label>
          <input type="text" name="sender_id" class="form-control" maxlength="11" placeholder="e.g. MyBrand" required>
          <div class="form-hint">Max 11 characters. Alphanumeric only.</div>
        </div>
        <div class="form-group">
          <label class="form-label">Purpose / Use Case <span class="required">*</span></label>
          <textarea name="purpose" class="form-control" rows="3" placeholder="Briefly describe what this sender ID will be used for..." required></textarea>
        </div>
      </div>

      <!-- Step 2: Uploads -->
      <div id="step2" class="modal-body" style="display:none">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px">
          <span style="background:var(--primary); color:#000; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:12px">2</span>
          <h4 style="margin:0">Required Documents</h4>
        </div>
        <div class="form-group">
          <label class="form-label">Application Letter <span class="required">*</span></label>
          <input type="file" name="application_letter" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
          <div class="form-hint">Official request on company letterhead (PDF/Image). <a href="/actions/download-sender-id-template.php" style="color:var(--primary); text-decoration:underline">Download Template</a></div>
        </div>
        <div class="form-group">
          <label class="form-label">Business Registration Certificate <span class="required">*</span></label>
          <input type="file" name="registration_cert" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
          <div class="form-hint">Certificate of Incorporation or Business Name (PDF/Image).</div>
        </div>
        <div class="alert alert-info" style="font-size:12px">
          <i class="fa-solid fa-circle-info"></i> All documents must be clear and readable for faster approval.
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="prevBtn" style="display:none" onclick="goToStep(1)">Previous</button>
        <button type="button" class="btn btn-primary" id="nextBtn" onclick="goToStep(2)">Next Step <i class="fa-solid fa-arrow-right"></i></button>
        <button type="submit" class="btn btn-primary" id="submitBtn" style="display:none"><i class="fa-solid fa-paper-plane"></i> Submit Request</button>
      </div>
    </form>
  </div>
</div>

<script>
function goToStep(step) {
    if (step === 2) {
        // Simple validation for step 1
        const sid = document.querySelector('input[name="sender_id"]').value;
        const purpose = document.querySelector('textarea[name="purpose"]').value;
        if (!sid || !purpose) {
            alert('Please fill in all required fields.');
            return;
        }
        document.getElementById('step1').style.display = 'none';
        document.getElementById('step2').style.display = 'block';
        document.getElementById('nextBtn').style.display = 'none';
        document.getElementById('prevBtn').style.display = 'block';
        document.getElementById('submitBtn').style.display = 'block';
    } else {
        document.getElementById('step1').style.display = 'block';
        document.getElementById('step2').style.display = 'none';
        document.getElementById('nextBtn').style.display = 'block';
        document.getElementById('prevBtn').style.display = 'none';
        document.getElementById('submitBtn').style.display = 'none';
    }
}
</script>
<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
