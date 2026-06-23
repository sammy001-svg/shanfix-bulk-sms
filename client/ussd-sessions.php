<?php
$pageTitle = 'USSD Sessions';
$breadcrumb = [['label'=>'USSD'],['label'=>'Sessions']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// Fetch user's USSD codes
$codes = DB::query("SELECT id, requested_code FROM ussd_codes WHERE user_id = ?", [$uid]);
$codeIds = array_column($codes, 'id');

$sessions = [];
if (!empty($codeIds)) {
    $placeholders = implode(',', array_fill(0, count($codeIds), '?'));
    $sessions = DB::query("
        SELECT s.*, c.requested_code 
        FROM ussd_sessions s
        JOIN ussd_codes c ON s.ussd_code_id = c.id
        WHERE s.ussd_code_id IN ($placeholders)
        ORDER BY s.updated_at DESC 
        LIMIT 100
    ", $codeIds);
}
?>

<div class="page-header">
  <div><h1>USSD Sessions</h1><div class="subtitle">Monitor active and past user interactions</div></div>
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
                        <th>Last Activity</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sessions)): ?>
                        <tr><td colspan="7" class="text-center" style="padding:40px; color:var(--text-muted)">No USSD sessions found for your codes.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td style="font-family:monospace; font-size:11px"><?= htmlspecialchars($s['session_id']) ?></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($s['requested_code']) ?></span></td>
                            <td style="font-weight:600"><?= htmlspecialchars($s['phone']) ?></td>
                            <td>
                                <span class="badge badge-<?= $s['status'] === 'active' ? 'success' : 'muted' ?>">
                                    <?= strtoupper($s['status']) ?>
                                </span>
                            </td>
                            <td style="font-size:12px"><?= date('d M, H:i', strtotime($s['created_at'])) ?></td>
                            <td style="font-size:12px"><?= date('d M, H:i', strtotime($s['updated_at'])) ?></td>
                            <td style="text-align:right">
                                <button class="btn btn-icon btn-secondary" onclick="viewSession('<?= $s['session_id'] ?>')" title="View Timeline">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Interaction Modal -->
<div id="sessionModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(4px)">
  <div class="card" style="width:100%; max-width:600px; margin:20px; border:none; box-shadow: var(--shadow-lg)">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
      <h3 class="card-title">Interaction Timeline</h3>
      <button type="button" class="btn btn-icon" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="card-body" id="sessionTimeline" style="max-height:450px; overflow-y:auto; padding:24px; background:var(--bg-muted)">
        <!-- Timeline content loaded via AJAX -->
    </div>
  </div>
</div>

<script>
async function viewSession(sessionId) {
    const timeline = document.getElementById('sessionTimeline');
    timeline.innerHTML = '<div class="text-center" style="padding:40px"><i class="fa-solid fa-spinner fa-spin"></i> Loading interaction history...</div>';
    openModal('sessionModal');

    try {
        const response = await fetch(`/client/actions/get-session-requests.php?session_id=${sessionId}`);
        const html = await response.text();
        timeline.innerHTML = html;
    } catch (e) {
        timeline.innerHTML = '<div class="alert alert-danger">Failed to load session data.</div>';
    }
}
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
