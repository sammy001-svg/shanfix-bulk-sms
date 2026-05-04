<?php
$pageTitle = 'USSD Sessions';
$breadcrumb = [['label'=>'USSD'],['label'=>'Sessions']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];
$codeId = (int)($_GET['code'] ?? 0);

// Filter logic
$where = "WHERE s.user_id = ?";
$params = [$uid];

if ($codeId > 0) {
    $where .= " AND s.ussd_code_id = ?";
    $params[] = $codeId;
}

// Fetch Sessions with Code Details
$sessions = DB::query("
    SELECT s.*, c.requested_code 
    FROM ussd_sessions s
    JOIN ussd_codes c ON s.ussd_code_id = c.id
    $where
    ORDER BY s.created_at DESC
    LIMIT 100
", $params);

// Fetch User's Codes for filter dropdown
$myCodes = DB::query("SELECT id, requested_code FROM ussd_codes WHERE user_id = ? AND status = 'approved'", [$uid]);
?>

<div class="page-header">
  <div><h1>USSD Sessions</h1><div class="subtitle">Detailed history of all USSD interactions</div></div>
  <div style="display:flex; gap:12px; align-items:center">
      <label class="form-label" style="margin:0; font-size:12px; font-weight:700">Filter By Code:</label>
      <select class="form-control" style="width:200px" onchange="location.href='?code='+this.value">
        <option value="0">All Codes</option>
        <?php foreach ($myCodes as $mc): ?>
          <option value="<?= $mc['id'] ?>" <?= $codeId == $mc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mc['requested_code']) ?></option>
        <?php endforeach; ?>
      </select>
  </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Session ID</th>
                        <th>USSD Code</th>
                        <th>Phone Number</th>
                        <th>Status</th>
                        <th>Started</th>
                        <th>Ended</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sessions)): ?>
                        <tr><td colspan="7" class="text-center" style="padding:40px; color:var(--text-muted)">No USSD sessions found for this filter.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td style="font-family:monospace; font-size:12px"><?= htmlspecialchars($s['session_id']) ?></td>
                            <td><span class="badge badge-primary"><?= htmlspecialchars($s['requested_code']) ?></span></td>
                            <td><strong><?= htmlspecialchars($s['phone']) ?></strong></td>
                            <td>
                                <?php if ($s['status'] === 'active'): ?>
                                    <span class="badge badge-warning"><i class="fa-solid fa-spinner fa-spin"></i> Active</span>
                                <?php elseif ($s['status'] === 'completed'): ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-check-circle"></i> Completed</span>
                                <?php else: ?>
                                    <span class="badge badge-muted"><i class="fa-solid fa-clock"></i> Timed Out</span>
                                <?php endif; ?>
                            </td>
                            <td><span style="font-size:12px"><?= date('d M, H:i:s', strtotime($s['created_at'])) ?></span></td>
                            <td>
                                <span style="font-size:12px">
                                    <?= $s['ended_at'] ? date('H:i:s', strtotime($s['ended_at'])) : '<span class="text-muted">N/A</span>' ?>
                                </span>
                            </td>
                            <td style="text-align:right">
                                <button class="btn btn-sm btn-outline" onclick="viewSessionDetails(<?= $s['id'] ?>)" title="View Requests">
                                    <i class="fa-solid fa-list-check"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Session Details Modal -->
<div id="sessionModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(4px)">
  <div class="card" style="width:100%; max-width:800px; margin:20px; max-height:90vh; display:flex; flex-direction:column">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
      <h3 class="card-title"><i class="fa-solid fa-history" style="color:var(--primary)"></i> Interaction History</h3>
      <button type="button" class="btn btn-icon" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="card-body" style="padding:0; overflow-y:auto" id="sessionRequestsBody">
        <!-- Loaded via AJAX -->
        <div style="padding:40px; text-align:center"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color:var(--primary)"></i></div>
    </div>
    <div class="card-footer" style="text-align:right">
        <button type="button" class="btn btn-muted" onclick="closeModal()">Close</button>
    </div>
  </div>
</div>

<script>
function viewSessionDetails(sessionId) {
    const modal = document.getElementById('sessionModal');
    const body = document.getElementById('sessionRequestsBody');
    openModal('sessionModal');
    body.innerHTML = '<div style="padding:40px; text-align:center"><i class="fa-solid fa-spinner fa-spin fa-2x" style="color:var(--primary)"></i></div>';

    fetch(`/reseller/actions/get-session-requests.php?session_id=${sessionId}`)
        .then(r => r.text())
        .then(html => {
            body.innerHTML = html;
        })
        .catch(err => {
            body.innerHTML = '<div class="alert alert-danger m-20">Error loading details.</div>';
        });
}


</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
