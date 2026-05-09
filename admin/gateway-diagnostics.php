<?php
$pageTitle = 'Gateway Diagnostics';
$breadcrumb = [['label'=>'Admin'],['label'=>'Settings','url'=>'/admin/settings.php'],['label'=>'Gateway Diagnostics']];
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/../includes/gateways/onfon.php';

$logPath = __DIR__ . '/../includes/gateways/onfon_debug.log';

// ── Handle clear-log POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_log'])) {
    if (csrf_verify()) {
        @file_put_contents($logPath, '');
        header('Location: /admin/gateway-diagnostics.php?cleared=1');
        exit;
    }
}

// ── Handle test SMS POST ───────────────────────────────────────────────────────
$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_phone'])) {
    if (!csrf_verify()) {
        $testResult = ['ok' => false, 'msg' => 'CSRF token mismatch.'];
    } else {
        $testPhone    = trim($_POST['test_phone'] ?? '');
        $testSenderId = trim($_POST['test_sender_id'] ?? '');
        $testMessage  = trim($_POST['test_message'] ?? 'Shanfix SMS test message.');
        if (!$testPhone || !$testSenderId) {
            $testResult = ['ok' => false, 'msg' => 'Phone number and Sender ID are required.'];
        } else {
            $res = Onfon::sendSMS($testPhone, $testMessage, $testSenderId);
            $testResult = ['ok' => $res['success'] ?? false, 'msg' => $res['success'] ? ('Sent! ID: ' . ($res['id'] ?? '—')) : ($res['error'] ?? 'Unknown error')];
        }
    }
}

// ── Environment info ───────────────────────────────────────────────────────────
$disabledFns = array_filter(array_map('trim', explode(',', ini_get('disable_functions'))));
$checkFns    = ['exec', 'shell_exec', 'popen', 'proc_open', 'system'];
$envInfo = [
    'PHP Version'     => PHP_VERSION,
    'PHP Binary'      => PHP_BINARY,
    'OS'              => PHP_OS_FAMILY,
    'memory_limit'    => ini_get('memory_limit'),
    'max_exec_time'   => ini_get('max_execution_time') . 's',
    'upload_max'      => ini_get('upload_max_filesize'),
    'post_max'        => ini_get('post_max_size'),
    'disable_functions' => implode(', ', $disabledFns) ?: '(none)',
];

// ── Read last N lines of the debug log ────────────────────────────────────────
$logLines = [];
if (file_exists($logPath)) {
    $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logLines = array_slice($lines, -150); // last 150 entries
    $logLines = array_reverse($logLines);  // newest first
}

// ── Sender IDs for test form ───────────────────────────────────────────────────
$approvedSenders = DB::query("SELECT DISTINCT sender_id FROM sender_ids WHERE status = 'approved' ORDER BY sender_id");
?>

<div class="page-header">
  <div>
    <h1>Gateway Diagnostics</h1>
    <div class="subtitle">Test the Onfon API and inspect recent call logs</div>
  </div>
  <form method="POST" style="display:inline" onsubmit="return confirm('Clear the entire debug log?')">
    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
    <input type="hidden" name="clear_log" value="1">
    <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-trash"></i> Clear Log</button>
  </form>
</div>

<?php
if (isset($_GET['cleared'])): ?>
  <div class="alert alert-success"><i class="fa-solid fa-check"></i> Debug log cleared.</div>
<?php endif; ?>

<?php if ($testResult !== null): ?>
  <div class="alert alert-<?=$testResult['ok']?'success':'danger'?>">
    <i class="fa-solid fa-<?=$testResult['ok']?'check':'triangle-exclamation'?>"></i>
    <?=htmlspecialchars($testResult['msg'])?>
  </div>
<?php endif; ?>

<!-- ── Environment ─────────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:18px">
  <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-server" style="color:var(--primary)"></i> Server Environment</h3></div>
  <div class="card-body" style="padding:0">
    <table class="data-table" style="margin:0">
      <tbody>
        <?php foreach ($envInfo as $label => $val): ?>
          <tr>
            <td style="width:200px;font-weight:600;padding:8px 16px"><?=htmlspecialchars($label)?></td>
            <td style="padding:8px 16px;font-family:monospace;font-size:13px"><?=htmlspecialchars($val)?></td>
          </tr>
        <?php endforeach; ?>
        <?php foreach ($checkFns as $fn): ?>
          <tr>
            <td style="width:200px;font-weight:600;padding:8px 16px"><?=$fn?>()</td>
            <td style="padding:8px 16px">
              <?php if (!function_exists($fn) || in_array($fn, $disabledFns)): ?>
                <span class="badge badge-danger">DISABLED</span>
              <?php else: ?>
                <span class="badge badge-success">Available</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td style="width:200px;font-weight:600;padding:8px 16px">Debug log file</td>
          <td style="padding:8px 16px;font-family:monospace;font-size:12px">
            <?=file_exists($logPath)
                ? '<span class="badge badge-success">Exists</span> ' . number_format(filesize($logPath)) . ' bytes — ' . htmlspecialchars(realpath($logPath))
                : '<span class="badge badge-warning">Not created yet</span>'?>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Test SMS ────────────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:18px">
  <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-paper-plane" style="color:var(--primary)"></i> Test Single SMS</h3></div>
  <div class="card-body">
    <form method="POST" style="display:grid;grid-template-columns:1fr 1fr 2fr auto;gap:12px;align-items:end">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <div class="form-group" style="margin:0">
        <label class="form-label">Recipient Phone</label>
        <input type="text" name="test_phone" class="form-control" placeholder="e.g. 0712345678" value="<?=htmlspecialchars($_POST['test_phone']??'')?>">
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Sender ID</label>
        <select name="test_sender_id" class="form-control">
          <option value="">— Select —</option>
          <?php foreach ($approvedSenders as $s): ?>
            <option value="<?=htmlspecialchars($s['sender_id'])?>" <?=(($_POST['test_sender_id']??'')===$s['sender_id'])?'selected':''?>>
              <?=htmlspecialchars($s['sender_id'])?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label">Message</label>
        <input type="text" name="test_message" class="form-control" value="<?=htmlspecialchars($_POST['test_message']??'Shanfix SMS test message.')?>">
      </div>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Send Test</button>
    </form>
    <p style="margin:12px 0 0;font-size:12px;color:var(--text-secondary)">
      This sends a real SMS via Onfon and charges 1 unit from the test sender's account. The API response is logged below.
    </p>
  </div>
</div>

<!-- ── Debug Log ──────────────────────────────────────────────────────────── -->
<div class="card">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
    <h3 class="card-title"><i class="fa-solid fa-terminal" style="color:var(--primary)"></i> Onfon API Debug Log <small style="font-weight:400;color:var(--text-secondary)">(last 150 entries, newest first)</small></h3>
    <button onclick="location.reload()" class="btn btn-secondary" style="padding:4px 12px;font-size:12px"><i class="fa-solid fa-rotate-right"></i> Refresh</button>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($logLines)): ?>
      <div style="padding:30px;text-align:center;color:var(--text-secondary)">
        <i class="fa-solid fa-file-circle-question" style="font-size:32px;margin-bottom:8px;display:block"></i>
        No log entries yet. Send an SMS or run a campaign to generate logs.
      </div>
    <?php else: ?>
      <div style="overflow-x:auto;max-height:600px;overflow-y:auto">
        <table style="width:100%;border-collapse:collapse;font-family:monospace;font-size:11px">
          <?php foreach ($logLines as $line): ?>
            <?php
            $isBatch  = strpos($line, '] BATCH') !== false;
            // Highlight as error if: HTTP is not 200, or CURL has an error, or Onfon ErrorCode != 0
            $hasError = preg_match('/\| HTTP: (?!200)/', $line)
                     || preg_match('/CURL_ERR: (?!none)/', $line)
                     || preg_match('/"ErrorCode":\s*[^0\s]/', $line);
            $rowStyle = $hasError ? 'background:rgba(220,53,69,.07)' : ($isBatch ? 'background:rgba(13,110,253,.04)' : '');
            ?>
            <tr style="<?=$rowStyle?>;border-bottom:1px solid var(--border-color)">
              <td style="padding:5px 14px;white-space:pre-wrap;word-break:break-all;line-height:1.5;color:<?=$hasError?'#dc3545':'var(--text-primary)'?>">
                <?=htmlspecialchars($line)?>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
