<?php
$pageTitle = 'My USSD Codes';
$breadcrumb = [['label'=>'USSD'],['label'=>'My Codes']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];
$codes = DB::query("SELECT * FROM ussd_codes WHERE user_id = ? ORDER BY created_at DESC", [$uid]);
?>

<div class="page-header">
  <div><h1>My USSD Codes</h1><div class="subtitle">Overview of your approved and pending USSD services</div></div>
  <a href="/reseller/ussd-request.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> New Request</a>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>USSD Code</th>
                        <th>Type</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Date Requested</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($codes)): ?>
                        <tr><td colspan="6" class="text-center" style="padding:40px; color:var(--text-muted)">No USSD codes found. <a href="/reseller/ussd-request.php">Submit your first request here!</a></td></tr>
                    <?php endif; ?>
                    <?php foreach ($codes as $c): ?>
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px">
                                    <div style="width:32px; height:32px; background:var(--bg-muted); border-radius:6px; display:flex; align-items:center; justify-content:center; color:var(--primary)">
                                        <i class="fa-solid fa-hashtag"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight:700"><?= $c['requested_code'] ?: '<i>Not Assigned</i>' ?></div>
                                        <div style="font-size:11px; color:var(--text-muted)"><?= ucfirst($c['type']) ?> USSD</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge badge-purple"><?= strtoupper($c['type']) ?></span></td>
                            <td><div class="text-truncate" style="max-width:200px" title="<?= htmlspecialchars($c['purpose']) ?>"><?= htmlspecialchars($c['purpose']) ?></div></td>
                            <td>
                                <?php if ($c['status'] === 'approved'): ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-check-circle"></i> Approved</span>
                                <?php elseif ($c['status'] === 'rejected'): ?>
                                    <span class="badge badge-danger" title="<?= htmlspecialchars($c['reject_reason']) ?>"><i class="fa-solid fa-times-circle"></i> Rejected</span>
                                <?php else: ?>
                                    <span class="badge badge-warning"><i class="fa-solid fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><span style="font-size:12px"><?= date('d M, Y', strtotime($c['created_at'])) ?></span></td>
                            <td style="text-align:right">
                                <div style="display:flex; gap:8px; justify-content:flex-end">
                                    <?php if ($c['status'] === 'approved'): ?>
                                        <a href="/reseller/ussd-sessions.php?code=<?= $c['id'] ?>" class="btn btn-sm btn-outline" title="View Sessions"><i class="fa-solid fa-history"></i></a>
                                        <a href="/reseller/ussd-analytics.php?code=<?= $c['id'] ?>" class="btn btn-sm btn-outline" title="Analytics"><i class="fa-solid fa-chart-line"></i></a>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline" onclick="alert('Purpose: <?= addslashes($c['purpose']) ?>')" title="Details"><i class="fa-solid fa-info-circle"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
