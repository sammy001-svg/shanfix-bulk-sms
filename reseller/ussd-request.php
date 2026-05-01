<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('reseller');
$user = current_user();
$uid = $user['id'];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'CSRF Token mismatch.');
        redirect('/reseller/ussd-request.php');
    }

    $type     = $_POST['type'] ?? 'shared';
    $code     = sanitize($_POST['code'] ?? '');
    $purpose  = sanitize($_POST['purpose'] ?? '');

    if (empty($purpose)) {
        flash_set('danger', 'Please provide a purpose for your USSD request.');
    } else {
        DB::insert(
            "INSERT INTO ussd_codes (user_id, type, requested_code, purpose, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())",
            [$uid, $type, $code, $purpose]
        );
        flash_set('success', 'Your USSD request has been submitted and is pending approval.');
        redirect('/reseller/ussd-codes.php');
    }
}

$pageTitle = 'Request USSD Code';
$breadcrumb = [['label'=>'USSD'],['label'=>'Request Code']];
require_once __DIR__ . '/layout.php';
?>

<div class="page-header">
  <div><h1>Request USSD Code</h1><div class="subtitle">Submit a request for a shared or dedicated USSD service</div></div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fa-solid fa-plus-circle"></i> New Request</div></div>
            <div class="card-body">
                <form action="" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    
                    <div class="form-group mb-4">
                        <label class="form-label">USSD Type <span class="required">*</span></label>
                        <div class="d-flex gap-16">
                            <label class="radio-card">
                                <input type="radio" name="type" value="shared" checked onchange="toggleCodeInput(false)">
                                <div class="radio-content">
                                    <div class="radio-icon"><i class="fa-solid fa-share-nodes"></i></div>
                                    <div class="radio-text">
                                        <div class="radio-title">Shared USSD</div>
                                        <div class="radio-desc">Uses a sub-code (e.g. *384*10#)</div>
                                    </div>
                                </div>
                            </label>
                            <label class="radio-card">
                                <input type="radio" name="type" value="dedicated" onchange="toggleCodeInput(true)">
                                <div class="radio-content">
                                    <div class="radio-icon"><i class="fa-solid fa-crown"></i></div>
                                    <div class="radio-text">
                                        <div class="radio-title">Dedicated USSD</div>
                                        <div class="radio-desc">Own a full code (e.g. *888#)</div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="form-group mb-4" id="codeInputGroup">
                        <label class="form-label">Preferred Code (Optional)</label>
                        <input type="text" name="code" class="form-control" placeholder="e.g. *384*123# or *888#">
                        <div class="text-muted mt-8" style="font-size:12px">Leave blank if you want the system to assign one.</div>
                    </div>

                    <div class="form-group mb-4">
                        <label class="form-label">Purpose / Use Case <span class="required">*</span></label>
                        <textarea name="purpose" class="form-control" rows="4" placeholder="Briefly describe what this USSD code will be used for..." required></textarea>
                    </div>

                    <div class="alert alert-info" style="font-size:13px">
                        <i class="fa-solid fa-circle-info"></i> Requests are usually processed within 24-48 hours. Dedicated codes may involve additional setup fees.
                    </div>

                    <div class="mt-24">
                        <button type="submit" class="btn btn-primary btn-block">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.radio-card { flex: 1; cursor: pointer; position: relative; }
.radio-card input { position: absolute; opacity: 0; }
.radio-content { border: 2px solid var(--border); border-radius: var(--radius-md); padding: 16px; display: flex; align-items: center; gap: 12px; transition: var(--transition); }
.radio-card input:checked + .radio-content { border-color: var(--primary); background: var(--primary-light); }
.radio-icon { width: 40px; height: 40px; background: var(--bg-muted); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; color: var(--text-secondary); transition: var(--transition); }
.radio-card input:checked + .radio-content .radio-icon { background: var(--primary); color: #fff; }
.radio-title { font-weight: 700; font-size: 14px; color: var(--text-primary); }
.radio-desc { font-size: 11px; color: var(--text-muted); }
</style>

<script>
function toggleCodeInput(isDedicated) {
    const label = document.querySelector('#codeInputGroup .form-label');
    if (isDedicated) {
        label.textContent = 'Preferred Dedicated Code';
    } else {
        label.textContent = 'Preferred Extension (Optional)';
    }
}
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
