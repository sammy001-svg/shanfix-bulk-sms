<?php
$pageTitle = 'Sender IDs';
$breadcrumb = [['label'=>'Reseller'],['label'=>'Sender IDs']];
require_once __DIR__ . '/layout.php';

$uid      = $user['id'];
$senderIds = DB::query("SELECT * FROM sender_ids WHERE user_id = ? ORDER BY created_at DESC", [$uid]);
?>

<div class="page-header">
  <div>
    <h1>Sender IDs</h1>
    <div class="subtitle">
      Request and manage your SMS sender identifiers. 
      <a href="/actions/download-sender-id-template.php" style="color:var(--primary); font-weight:600; text-decoration:underline">
        <i class="fa-solid fa-file-arrow-down"></i> Download Application Template
      </a>
    </div>
  </div>
  <button class="btn btn-primary" onclick="openModal('senderModal')">
    <i class="fa-solid fa-plus"></i> Request Sender ID
  </button>
</div>

<div class="alert alert-info" style="margin-bottom:20px">
  <i class="fa-solid fa-circle-info"></i>
  Sender IDs must be approved by an administrator before use. Approvals typically take 1–3 business days.
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-id-badge" style="color:var(--primary)"></i> My Sender IDs</h3>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Sender ID</th><th>Purpose</th><th>Status</th><th>Requested</th><th>Approved/Rejected</th></tr>
      </thead>
      <tbody>
        <?php if (empty($senderIds)): ?>
          <tr><td colspan="5">
            <div class="empty-state">
              <div class="empty-icon">🪪</div>
              <h3>No Sender IDs yet</h3>
              <p>Request a sender ID to brand your SMS messages.</p>
              <button class="btn btn-primary" onclick="openModal('senderModal')">Request Sender ID</button>
            </div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($senderIds as $s): ?>
            <?php $sc = ['approved'=>'success','rejected'=>'danger','pending'=>'warning'][$s['status']] ?? 'muted'; ?>
            <tr>
              <td><strong style="font-size:15px;letter-spacing:0.02em"><?= htmlspecialchars($s['sender_id']) ?></strong></td>
              <td style="max-width:250px;color:var(--text-secondary)"><?= htmlspecialchars($s['purpose'] ?? '—') ?></td>
              <td>
                <span class="badge badge-<?= $sc ?>">
                  <?= $s['status'] === 'pending' ? '⏳ Pending Review' : ($s['status'] === 'approved' ? '✅ Approved' : '❌ Rejected') ?>
                </span>
                <?php if ($s['status'] === 'rejected' && $s['reject_reason']): ?>
                  <div style="font-size:11px;color:var(--danger);margin-top:4px"><?= htmlspecialchars($s['reject_reason']) ?></div>
                <?php endif; ?>
              </td>
              <td style="font-size:12px"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
              <td style="font-size:12px"><?= $s['approved_at'] ? date('d M Y', strtotime($s['approved_at'])) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Request Modal -->
<div class="modal-overlay" id="senderModal">
  <div class="modal" style="max-width:550px">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-id-badge" style="color:var(--primary)"></i> Request Sender ID</h3>
      <button class="modal-close" onclick="closeModal('senderModal')">×</button>
    </div>
    <form id="senderRequestForm" method="POST" action="/reseller/actions/request-sender-id.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      
      <!-- Step 1: Info -->
      <div id="step1" class="modal-body">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px">
          <span style="background:var(--primary); color:#000; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:12px">1</span>
          <h4 style="margin:0">Basic Information</h4>
        </div>
        <div class="form-group">
          <label class="form-label">Sender ID <span class="required">*</span></label>
          <input type="text" name="sender_id" class="form-control" placeholder="e.g. BRANDCO" maxlength="11" required style="text-transform:uppercase;font-weight:700;letter-spacing:0.05em">
          <div class="form-hint">Max 11 characters. Alphanumeric only.</div>
        </div>
        <div class="form-group">
          <label class="form-label">Purpose / Description <span class="required">*</span></label>
          <textarea name="purpose" class="form-control" placeholder="Briefly describe the purpose of this sender ID..." required rows="4"></textarea>
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

<?php
$extraScript = isset($_GET['new']) ? "<script>openModal('senderModal')</script>" : '';
include __DIR__ . '/../includes/layout-footer.php';
?>
