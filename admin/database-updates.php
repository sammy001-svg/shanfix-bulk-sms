<?php
/**
 * Admin: Database Updates
 *
 * Shows which additive schema changes the running code expects and whether each
 * is present, and applies the missing ones in one click. Exists so a feature is
 * never silently broken just because a .sql file in database/ was never run.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers/schema.php';
require_role('admin');

$items   = Schema::status();
$pending = array_values(array_filter($items, fn($i) => !$i['applied']));

$pageTitle  = 'Database Updates';
$breadcrumb = [['label'=>'Admin'],['label'=>'System'],['label'=>'Database Updates']];
require_once __DIR__ . '/layout.php';
?>

<div class="page-header">
  <div>
    <h1>Database Updates</h1>
    <div class="subtitle">Schema changes the current code expects, and whether your database has them</div>
  </div>
  <?php if (!empty($pending)): ?>
    <form method="POST" action="/admin/actions/run-migrations.php"
          onsubmit="return confirm('Apply <?= count($pending) ?> pending database update(s)?\n\nThese only add columns and indexes. Nothing is dropped or overwritten.')">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-wand-magic-sparkles"></i> Apply <?= count($pending) ?> Pending Update<?= count($pending) === 1 ? '' : 's' ?>
      </button>
    </form>
  <?php endif; ?>
</div>

<?php if (empty($pending)): ?>
  <div class="alert alert-success">
    <i class="fa-solid fa-circle-check"></i>
    Your database is up to date — all <?= count($items) ?> expected changes are present.
  </div>
<?php else: ?>
  <div class="alert alert-warning">
    <i class="fa-solid fa-triangle-exclamation"></i>
    <strong><?= count($pending) ?> update(s) pending.</strong>
    Features that depend on them will be missing data or degraded until they are applied.
    These are additive only — columns and indexes are added, nothing is dropped or rewritten.
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-database" style="color:var(--primary)"></i> Expected Changes</h3>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Status</th><th>Change</th><th>Type</th><th>What it is for</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $i): ?>
          <tr>
            <td style="width:110px">
              <?php if (!empty($i['blocked'])): ?>
                <span class="badge badge-danger">Blocked</span>
              <?php elseif ($i['applied']): ?>
                <span class="badge badge-success">Present</span>
              <?php else: ?>
                <span class="badge badge-warning">Pending</span>
              <?php endif; ?>
            </td>
            <td><code style="font-size:12px"><?= htmlspecialchars($i['key']) ?></code>
              <?php if (!empty($i['blocked'])): ?>
                <div style="font-size:11px;color:var(--danger)"><?= htmlspecialchars($i['blocked']) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:12px"><?= htmlspecialchars($i['type']) ?></td>
            <td style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($i['purpose']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer" style="font-size:11px;color:var(--text-secondary)">
    Equivalent .sql files live in <code>database/</code> if you would rather apply them yourself.
    Running them and using this page are interchangeable — both check before acting.
  </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
