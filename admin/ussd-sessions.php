<?php
$pageTitle = 'Global USSD Sessions';
$breadcrumb = [['label'=>'Admin'],['label'=>'USSD'],['label'=>'Sessions']];
require_once __DIR__ . '/layout.php';

$sessions = DB::query("
    SELECT s.*, u.name as user_name, uc.requested_code 
    FROM ussd_sessions s 
    JOIN users u ON s.user_id = u.id 
    JOIN ussd_codes uc ON s.ussd_code_id = uc.id 
    ORDER BY s.created_at DESC 
    LIMIT 100
");
?>

<div class="page-header">
  <div><h1>USSD Sessions</h1><div class="subtitle">Real-time log of USSD interactions across the platform</div></div>
</div>

<div class="card">
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
          <tr>
              <th>Session ID</th>
              <th>User</th>
              <th>USSD Code</th>
              <th>Phone Number</th>
              <th>Status</th>
              <th>Started</th>
          </tr>
      </thead>
      <tbody>
        <?php if (empty($sessions)): ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:30px">No active or past sessions found</td></tr>
        <?php else: ?>
          <?php foreach ($sessions as $s): ?>
            <tr>
              <td><code style="font-size:11px"><?= htmlspecialchars($s['session_id']) ?></code></td>
              <td><?= htmlspecialchars($s['user_name']) ?></td>
              <td><strong><?= htmlspecialchars($s['requested_code']) ?></strong></td>
              <td><?= htmlspecialchars($s['phone']) ?></td>
              <td>
                  <span class="badge badge-<?= $s['status']==='active'?'success':'muted' ?>">
                      <?= ucfirst($s['status']) ?>
                  </span>
              </td>
              <td style="font-size:12px"><?= date('d M Y, H:i:s', strtotime($s['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
