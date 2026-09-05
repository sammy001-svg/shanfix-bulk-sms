<?php
/**
 * Shared Delivery Report view — Shanfix Technology
 * Rendered identically by the admin, reseller and client portals.
 * Include AFTER layout.php; delivery-report-data.php supplies every variable.
 */
$drPresets = [
    'today'     => 'Today',
    'yesterday' => 'Yesterday',
    'last7'     => 'Last 7 days',
    'week'      => 'This week',
    'last30'    => 'Last 30 days',
    'month'     => 'This month',
    'lastmonth' => 'Last month',
];
?>

<div class="page-header">
  <div>
    <h1>User delivery reports</h1>
    <div class="subtitle">Delivery status breakdown by day, straight from the carrier receipts</div>
  </div>
  <div class="btn-group">
    <a href="?<?= dr_qs(['mode' => 'detailed']) ?>"  class="btn <?= $drMode === 'detailed'  ? 'btn-primary' : 'btn-secondary' ?>">Detailed Status</a>
    <a href="?<?= dr_qs(['mode' => 'aggregate']) ?>" class="btn <?= $drMode === 'aggregate' ? 'btn-primary' : 'btn-secondary' ?>">Aggregate Status</a>
  </div>
</div>

<!-- Range picker -->
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:18px">
    <form method="GET" id="drForm">
      <input type="hidden" name="mode" value="<?= htmlspecialchars($drMode) ?>">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:14px">
        <div class="form-group" style="margin:0">
          <label class="form-label">From</label>
          <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($drFrom) ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group" style="margin:0">
          <label class="form-label">To</label>
          <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($drTo) ?>" max="<?= date('Y-m-d') ?>">
        </div>
      </div>

      <div class="tabs" style="margin-bottom:14px">
        <?php foreach ($drPresets as $key => $label): ?>
          <a href="?<?= dr_qs(['preset' => $key, 'from' => null, 'to' => null]) ?>"
             class="tab-btn <?= $drPreset === $key ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($drUserOptions)): ?>
        <div class="form-group" style="margin-bottom:14px">
          <label class="form-label"><?= htmlspecialchars($drUserFilterLabel) ?></label>
          <select name="user_id" class="form-control">
            <option value="">All <?= htmlspecialchars($drUserFilterLabel) ?>s</option>
            <?php foreach ($drUserOptions as $opt): ?>
              <option value="<?= (int)$opt['id'] ?>" <?= $drUserId === (int)$opt['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($opt['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-danger" style="width:100%;padding:12px;font-weight:700;letter-spacing:.5px">
        GENERATE REPORT
      </button>
    </form>
  </div>
</div>

<?php if (!$drHasDlrColumn): ?>
  <div class="alert alert-warning" style="margin-bottom:18px">
    <i class="fa-solid fa-database"></i>
    <strong>Database update required.</strong>
    The <code>messages.dlr_status</code> column does not exist yet, so carrier delivery statuses
    cannot be stored and every column below is derived from our own send result.
    <?php if ((current_user()['role'] ?? '') === 'admin'): ?>
      Apply it in one click at <a href="/admin/database-updates.php">Admin &rarr; Database Updates</a>.
    <?php else: ?>
      Ask your administrator to apply the pending database updates.
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- Report -->
<div class="card">
  <div class="card-header" style="justify-content:space-between">
    <h3 class="card-title">
      <i class="fa-solid fa-table-list" style="color:var(--primary)"></i>
      <?= date('d M Y', strtotime($drFrom)) ?> &ndash; <?= date('d M Y', strtotime($drTo)) ?>
      <span class="badge badge-muted"><?= number_format($drTotals['total_sms']) ?> SMS</span>
    </h3>
    <div style="display:flex;gap:8px;align-items:center">
      <button type="button" id="drDensityBtn" class="btn btn-outline btn-sm" title="Toggle row density">
        <i class="fa-solid fa-bars"></i> DENSITY
      </button>
      <a href="?<?= dr_qs(['export' => '1']) ?>" class="btn btn-outline btn-sm">
        <i class="fa-solid fa-download"></i> EXPORT
      </a>
    </div>
  </div>

  <div class="table-wrapper">
    <table class="data-table" id="drTable">
      <thead>
        <tr>
          <th style="min-width:110px">Date</th>
          <?php foreach ($drColumns as $col): ?>
            <th style="white-space:nowrap"><?= htmlspecialchars($col) ?></th>
          <?php endforeach; ?>
          <th style="white-space:nowrap">total_sms</th>
          <th style="white-space:nowrap">total_units</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($drRows)): ?>
          <tr>
            <td colspan="<?= count($drColumns) + 3 ?>" class="text-center text-muted" style="padding:40px">
              <i class="fa-solid fa-inbox" style="font-size:26px;display:block;margin-bottom:10px;opacity:.5"></i>
              No messages in this date range
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($drRows as $day => $row): ?>
            <tr>
              <td style="font-weight:600"><?= htmlspecialchars($day) ?></td>
              <?php foreach ($drColumns as $col): ?>
                <?php $v = $row['cells'][$col] ?? 0; ?>
                <td><?= $v > 0 ? number_format($v) : '' ?></td>
              <?php endforeach; ?>
              <td style="font-weight:700"><?= number_format($row['total_sms']) ?></td>
              <td style="font-weight:700;color:var(--primary)"><?= number_format($row['total_units'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if (!empty($drRows)): ?>
        <tfoot>
          <tr style="border-top:2px solid var(--border-color);font-weight:800">
            <td>TOTAL</td>
            <?php foreach ($drColumns as $col): ?>
              <td><?= number_format($drTotals['cells'][$col] ?? 0) ?></td>
            <?php endforeach; ?>
            <td><?= number_format($drTotals['total_sms']) ?></td>
            <td style="color:var(--primary)"><?= number_format($drTotals['total_units'], 2) ?></td>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>

  <div class="card-footer" style="font-size:12px;color:var(--text-secondary)">
    <?= count($drRows) ?> day(s) &middot; <?= count($drColumns) ?> status column(s)
    <?php if ($drMode === 'aggregate'): ?>
      &middot; carrier statuses collapsed into Delivered / Pending / Failed
    <?php endif; ?>
    <?php if ($drTotalMsgs > 0): ?>
      <div style="margin-top:6px">
        <?php if ($drWithDlr === 0 && $drWithGatewayId === 0): ?>
          <span class="badge badge-danger">No gateway message IDs</span>
          None of these messages carry a gateway message ID, so no delivery receipt can be matched to them.
          Registering a delivery-report URL will not help until the gateway returns message IDs on send.
          <?php if ((current_user()['role'] ?? '') === 'admin'): ?>
            See <a href="/admin/gateway-diagnostics.php">Gateway Diag &rarr; Message ID capture</a>.
          <?php endif; ?>
        <?php elseif ($drWithDlr === 0): ?>
          <span class="badge badge-warning">No carrier receipts</span>
          Every status here is derived from our own send result. Register the delivery-report URL in the
          Onfon portal (Account &rarr; SMS Settings &rarr; Delivery Report URL) for the true carrier statuses.
        <?php else: ?>
          <span class="badge badge-success"><?= $drCoveragePct ?>% carrier-confirmed</span>
          <?= number_format($drWithDlr) ?> of <?= number_format($drTotalMsgs) ?> messages carry a delivery receipt;
          the rest fall back to our send result.
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($drSources)): ?>
<details class="card" style="margin-top:18px">
  <summary style="padding:14px 18px;cursor:pointer;font-weight:700;font-size:13px">
    <i class="fa-solid fa-circle-question" style="color:var(--primary)"></i>
    Where these statuses came from
    <span style="font-weight:400;color:var(--text-secondary)">&mdash; open this if a column is unexpectedly empty</span>
  </summary>
  <div class="table-wrapper" style="border-top:1px solid var(--border-color)">
    <table class="data-table">
      <thead>
        <tr>
          <th>Carrier receipt</th>
          <th>Our status</th>
          <th>Failure reason recorded at send</th>
          <th style="text-align:right">Messages</th>
          <th>Counted under</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($drSources as $src): ?>
          <?php
            $hasReceipt = $src['source'] !== '(no carrier receipt)';
            $resolved   = $hasReceipt
                ? DlrStatus::normalise($src['source'])
                : ($src['reason'] !== '' && in_array($src['enum_status'], ['failed','undelivered'], true)
                    ? DlrStatus::fromFailureReason($src['reason'])
                    : DlrStatus::fromEnum($src['enum_status']));
          ?>
          <tr>
            <td style="font-size:12px">
              <?php if ($hasReceipt): ?>
                <code><?= htmlspecialchars($src['source']) ?></code>
              <?php else: ?>
                <span class="badge badge-warning" style="font-size:10px">none</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px"><?= htmlspecialchars($src['enum_status']) ?></td>
            <td style="font-size:12px;max-width:320px;word-break:break-word;color:var(--text-secondary)">
              <?= $src['reason'] !== '' ? htmlspecialchars($src['reason']) : '&mdash;' ?>
            </td>
            <td style="text-align:right;font-weight:700"><?= number_format($src['cnt']) ?></td>
            <td style="font-size:12px;font-weight:600"><?= htmlspecialchars($resolved) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer" style="font-size:11px;color:var(--text-secondary)">
    A column stays empty when no message reached that state. If the left column reads
    &ldquo;none&rdquo; for everything, no delivery receipts are arriving &mdash; register the DLR URL in the
    Onfon portal, then check Admin &rarr; Gateway Diag &rarr; Delivery Receipts.
  </div>
</details>
<?php endif; ?>

<?php
$extraScript = <<<'JS'
<script>
// Row density, remembered per browser like the source report's DENSITY control.
(function () {
    const table = document.getElementById('drTable');
    const btn   = document.getElementById('drDensityBtn');
    if (!table || !btn) return;

    const STYLE_ID = 'dr-density-style';
    function apply(compact) {
        let el = document.getElementById(STYLE_ID);
        if (!el) {
            el = document.createElement('style');
            el.id = STYLE_ID;
            document.head.appendChild(el);
        }
        el.textContent = compact
            ? '#drTable td, #drTable th { padding-top: 4px !important; padding-bottom: 4px !important; font-size: 12px; }'
            : '';
    }

    let compact = false;
    try { compact = localStorage.getItem('dr_density') === 'compact'; } catch (e) {}
    apply(compact);

    btn.addEventListener('click', function () {
        compact = !compact;
        apply(compact);
        try { localStorage.setItem('dr_density', compact ? 'compact' : 'comfortable'); } catch (e) {}
    });
})();
</script>
JS;
