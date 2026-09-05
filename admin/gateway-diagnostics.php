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

// -- Kopo Kopo checks ----------------------------------------------------------
require_once __DIR__ . '/../includes/gateways/kopokopo.php';

/** Does a column exist? A missing one means a migration has not been run. */
function diagHasColumn(string $table, string $column): bool {
    try {
        return (bool)DB::queryOne(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );
    } catch (Throwable $e) {
        return false;
    }
}

$kkSet = function (string $key): string {
    $r = DB::queryOne("SELECT value FROM system_settings WHERE `key` = ?", ["kk_$key"]);
    return trim((string)($r['value'] ?? ''));
};

$kkChecks = [
    'Client ID'     => ['set' => $kkSet('client_id')     !== '', 'required' => true],
    'Client Secret' => ['set' => $kkSet('client_secret') !== '', 'required' => true],
    'Till Number'   => ['set' => $kkSet('till_number')   !== '', 'required' => true],
    'API Key'       => ['set' => KopoKopo::apiKey()      !== '', 'required' => false],
    'Base URL'      => ['set' => true, 'required' => true, 'note' => KopoKopo::baseUrl()],
    'Callback URL'  => ['set' => true, 'required' => true, 'note' => KopoKopo::callbackUrl()],
];

// Migrations the payment flow depends on.
$kkMigrations = [
    'purchases.gateway_ref'         => diagHasColumn('purchases', 'gateway_ref'),
    'ussd_transactions.gateway_ref' => diagHasColumn('ussd_transactions', 'gateway_ref'),
    'messages.dlr_status'           => diagHasColumn('messages', 'dlr_status'),
];

// -- Kopo Kopo token / STK tests -----------------------------------------------
$kkTokenResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kk_test_token'])) {
    if (!csrf_verify()) {
        $kkTokenResult = ['ok' => false, 'msg' => 'CSRF token mismatch.'];
    } else {
        $tok = KopoKopo::getToken();
        $kkTokenResult = $tok
            ? ['ok' => true,  'msg' => 'Authenticated. Token received (' . strlen($tok) . ' chars).']
            : ['ok' => false, 'msg' => 'Could not obtain a token. Check the Client ID, Client Secret and Base URL, then see the PHP error log.'];
    }
}

$kkStkResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kk_test_phone'])) {
    if (!csrf_verify()) {
        $kkStkResult = ['ok' => false, 'msg' => 'CSRF token mismatch.'];
    } else {
        $kkPhone  = trim($_POST['kk_test_phone'] ?? '');
        $kkAmount = max(1, (int)($_POST['kk_test_amount'] ?? 1));
        if ($kkPhone === '') {
            $kkStkResult = ['ok' => false, 'msg' => 'Phone number is required.'];
        } else {
            // DIAG prefix - the webhook ignores it, so no wallet is touched.
            $r = KopoKopo::initiateSTKPush($kkPhone, $kkAmount, 'DIAG' . time());
            $kkStkResult = $r['success']
                ? ['ok' => true,  'msg' => 'STK push accepted by Kopo Kopo. Check the phone for the PIN prompt. Resource: ' . ($r['location'] ?: 'no Location header returned')]
                : ['ok' => false, 'msg' => $r['error'] ?? 'Unknown error'];
        }
    }
}

// -- Delivery receipt health ---------------------------------------------------
$dlrHealth = null;
if (diagHasColumn('messages', 'dlr_status')) {
    try {
        $dlrHealth = DB::queryOne(
            "SELECT COUNT(*) AS total,
                    SUM(dlr_status IS NOT NULL AND dlr_status <> '') AS with_dlr,
                    MAX(CASE WHEN dlr_status IS NOT NULL AND dlr_status <> '' THEN created_at END) AS last_dlr
             FROM messages
             WHERE created_at >= NOW() - INTERVAL 7 DAY"
        );
    } catch (Throwable $e) {
        $dlrHealth = null;
    }
}

$siteUrlSetting = rtrim((string)(DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'site_url'")['value'] ?? ''), '/');

// webhooks/sms-dlr.php enforces dlr_webhook_token when one is set. A URL
// registered without the matching ?token= is rejected with 403, which looks
// exactly like Onfon never calling at all — worth flagging explicitly.
$dlrToken = (string)(DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'dlr_webhook_token'")['value'] ?? '');

// -- Message ID capture --------------------------------------------------------
// A delivery receipt is matched to a message by gateway_msg_id. If Onfon's send
// response does not return MessageId we store NULL, and then no receipt can
// ever match anything - no amount of portal configuration would help. This is
// the first thing to rule out when receipts never appear.
$msgIdHealth = null;
try {
    $msgIdHealth = DB::queryOne(
        "SELECT COUNT(*) AS total,
                SUM(gateway_msg_id IS NOT NULL AND gateway_msg_id <> '') AS with_id
         FROM messages
         WHERE status IN ('sent','delivered','undelivered')
           AND created_at >= NOW() - INTERVAL 30 DAY"
    );
} catch (Throwable $e) {
    $msgIdHealth = null;
}

// -- DLR self-test -------------------------------------------------------------
// Proves whether our own receipt endpoint works, so "no carrier receipts" can
// be pinned on either our side or the Onfon portal configuration rather than
// guessed at. Posts a synthetic receipt for a real recent message over HTTP,
// exactly as Onfon would, then checks whether the row actually changed.
$dlrTestResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dlr_selftest'])) {
    if (!csrf_verify()) {
        $dlrTestResult = ['ok' => false, 'steps' => [['Security', false, 'CSRF token mismatch.']]];
    } else {
        $steps = [];

        // 1. Find a real message to test against; its status is restored after.
        $probe = DB::queryOne(
            "SELECT id, gateway_msg_id, status, dlr_status
             FROM messages
             WHERE gateway_msg_id IS NOT NULL AND gateway_msg_id <> ''
             ORDER BY id DESC LIMIT 1"
        );

        if (!$probe) {
            $steps[] = ['Find a sent message', false,
                'No message has a gateway_msg_id yet. Send one SMS first, then run this test.'];
            $dlrTestResult = ['ok' => false, 'steps' => $steps];
        } else {
            $steps[] = ['Find a sent message', true,
                'Message #' . $probe['id'] . ' (gateway id ' . $probe['gateway_msg_id'] . ')'];

            $before = $probe['dlr_status'];

            // 2. POST a synthetic receipt to our own DLR URL, as Onfon would.
            $target = rtrim($siteUrlSetting, '/') . '/api/v1/dlr.php';
            $ch = curl_init($target);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'MessageId'         => $probe['gateway_msg_id'],
                    'Status'            => 'DeliveredToTerminal',
                    'StatusDescription' => 'DeliveredToTerminal',
                    'MobileNumber'      => '254700000000',
                    'Timestamp'         => date('Y-m-d H:i:s'),
                ]),
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => false, // self-call; cert chain is not what we are testing
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cerr = curl_error($ch);
            curl_close($ch);

            if ($body === false) {
                $steps[] = ['Reach ' . $target, false, 'Could not connect: ' . $cerr
                    . ' — the server may block requests to itself, which does not by itself mean Onfon cannot reach it.'];
            } elseif ($code !== 200) {
                $steps[] = ['Reach ' . $target, false, "HTTP $code returned. "
                    . ($code === 403 ? 'Blocked — check .htaccess and any firewall or ModSecurity rule.' : 'Response: ' . substr((string)$body, 0, 200))];
            } else {
                $steps[] = ['Reach ' . $target, true, "HTTP 200 — " . substr((string)$body, 0, 160)];
            }

            // 3. Did the receipt actually land on the row?
            $after = DB::queryOne("SELECT dlr_status FROM messages WHERE id = ?", [$probe['id']]);
            $wrote = ($after['dlr_status'] ?? null) === 'DelivredToTerminal';
            $steps[] = ['Record the status', $wrote, $wrote
                ? 'messages.dlr_status was set to DelivredToTerminal — the receiving side works end to end.'
                : 'dlr_status is ' . var_export($after['dlr_status'] ?? null, true) . ' — the endpoint did not record the receipt.'];

            // 4. Put the row back exactly as it was; this is a test, not a change.
            DB::execute("UPDATE messages SET dlr_status = ? WHERE id = ?", [$before, $probe['id']]);
            $steps[] = ['Restore the message', true, 'Message #' . $probe['id'] . ' returned to its previous state.'];

            $dlrTestResult = ['ok' => $wrote, 'steps' => $steps];
        }
    }
}

// -- Environment info ----------------------------------------------------------
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
// Read only the tail of the file — file() loads the ENTIRE log into memory,
// and this log grows unbounded (every gateway call appends).  A few hundred
// MB of log froze the page and could exhaust memory_limit.
$logLines = [];
if (file_exists($logPath)) {
    $fh = fopen($logPath, 'rb');
    if ($fh) {
        $size  = filesize($logPath);
        $chunk = 256 * 1024; // 256 KB tail is plenty for 150 lines
        fseek($fh, max(0, $size - $chunk));
        $tail = stream_get_contents($fh);
        fclose($fh);
        $lines = array_values(array_filter(explode("\n", $tail), 'strlen'));
        if ($size > $chunk && count($lines) > 1) array_shift($lines); // drop partial first line
        $logLines = array_reverse(array_slice($lines, -150)); // newest first
    }
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

<!-- Delivery receipts -->
<div class="card" style="margin-bottom:18px">
  <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-truck-fast" style="color:var(--primary)"></i> Delivery Receipts (DLR)</h3></div>
  <div class="card-body" style="padding:0">
    <table class="data-table" style="margin:0">
      <tbody>
        <tr>
          <td style="width:220px;font-weight:600;padding:8px 16px">DLR URL to register</td>
          <td style="padding:8px 16px;font-family:monospace;font-size:12px;word-break:break-all">
            <?= htmlspecialchars($siteUrlSetting . '/api/v1/dlr.php') ?>
            <div style="font-size:11px;color:var(--text-secondary);font-family:inherit;margin-top:3px">
              Paste into Onfon &rarr; Account &rarr; SMS Settings &rarr; Delivery Report URL.
              <?= htmlspecialchars($siteUrlSetting . '/webhooks/sms-dlr.php') ?> also works &mdash; both endpoints record identical statuses.
            </div>
          </td>
        </tr>
        <tr>
          <td style="width:220px;font-weight:600;padding:8px 16px;vertical-align:top">Message ID capture</td>
          <td style="padding:8px 16px">
            <?php if ($msgIdHealth === null): ?>
              <span class="badge badge-muted">Unavailable</span>
            <?php else: ?>
              <?php $mTot = (int)$msgIdHealth['total']; $mWith = (int)$msgIdHealth['with_id']; ?>
              <?php if ($mTot === 0): ?>
                <span class="badge badge-muted">No messages in the last 30 days</span>
              <?php elseif ($mWith === 0): ?>
                <span class="badge badge-danger">None captured</span>
                <div style="font-size:12px;color:var(--text-secondary);margin-top:6px">
                  <strong>This is why no delivery receipts can ever be recorded.</strong>
                  All <?= number_format($mTot) ?> sent messages have an empty <code>gateway_msg_id</code>, and a receipt is
                  matched to a message by exactly that value. Onfon's send response is not returning
                  <code>MessageId</code>, so even a correctly registered DLR URL would match nothing.
                  Check <code>includes/gateways/onfon_debug.log</code> for a raw send response, and ask Onfon to
                  enable message IDs / delivery reports on the account.
                </div>
              <?php elseif ($mWith < $mTot): ?>
                <span class="badge badge-warning"><?= round($mWith / $mTot * 100, 1) ?>% captured</span>
                <span style="font-size:12px;color:var(--text-secondary)"> &mdash; <?= number_format($mWith) ?> of <?= number_format($mTot) ?> messages can be matched to a receipt; the rest cannot.</span>
              <?php else: ?>
                <span class="badge badge-success">All captured</span>
                <span style="font-size:12px;color:var(--text-secondary)"> &mdash; <?= number_format($mWith) ?> messages carry a gateway ID and can be matched to a receipt.</span>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
        <?php if ($dlrHealth === null): ?>
          <tr>
            <td style="width:220px;font-weight:600;padding:8px 16px">Receipts (last 7 days)</td>
            <td style="padding:8px 16px">
              <span class="badge badge-danger">Unavailable</span>
              <span style="font-size:12px;color:var(--text-secondary)"> &mdash; messages.dlr_status is missing; run <code>database/dlr_status_migration.sql</code></span>
            </td>
          </tr>
        <?php else: ?>
          <tr>
            <td style="width:220px;font-weight:600;padding:8px 16px">Receipts (last 7 days)</td>
            <td style="padding:8px 16px">
              <?php $tot = (int)$dlrHealth['total']; $wd = (int)$dlrHealth['with_dlr']; ?>
              <?php if ($tot === 0): ?>
                <span class="badge badge-muted">No messages sent</span>
              <?php elseif ($wd === 0): ?>
                <span class="badge badge-danger">None received</span>
                <span style="font-size:12px;color:var(--text-secondary)"> &mdash; <?= number_format($tot) ?> messages sent, 0 receipts. Onfon is not calling the DLR URL above, so the granular delivery statuses cannot populate.</span>
              <?php else: ?>
                <span class="badge badge-success"><?= round($wd / $tot * 100, 1) ?>% covered</span>
                <span style="font-size:12px;color:var(--text-secondary)"> &mdash; <?= number_format($wd) ?> of <?= number_format($tot) ?> messages. Last receipt: <?= htmlspecialchars($dlrHealth['last_dlr'] ?? 'never') ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endif; ?>
        <tr>
          <td style="width:220px;font-weight:600;padding:8px 16px;vertical-align:top">Token protection</td>
          <td style="padding:8px 16px">
            <?php if ($dlrToken === ''): ?>
              <span class="badge badge-success">Not set</span>
              <span style="font-size:12px;color:var(--text-secondary)"> &mdash; both DLR URLs accept receipts as-is. Nothing to match in the Onfon portal.</span>
            <?php else: ?>
              <span class="badge badge-warning">Enabled</span>
              <div style="font-size:12px;color:var(--text-secondary);margin-top:6px">
                <strong>A token is configured</strong>, so <code>/webhooks/sms-dlr.php</code> rejects any receipt whose
                <code>?token=</code> does not match &mdash; which looks identical to Onfon never calling at all.
                Either register the URL below verbatim, token included, or clear the token in
                Settings &rarr; Email &amp; Alerts and register the plain URL.
              </div>
              <div style="font-family:monospace;font-size:12px;margin-top:6px;word-break:break-all;padding:8px;background:rgba(127,127,127,.08);border-radius:4px">
                <?= htmlspecialchars(rtrim($siteUrlSetting, '/') . '/webhooks/sms-dlr.php?token=' . $dlrToken) ?>
              </div>
              <div style="font-size:12px;color:var(--text-secondary);margin-top:6px">
                Simpler alternative: register <code><?= htmlspecialchars(rtrim($siteUrlSetting, '/') . '/api/v1/dlr.php') ?></code>,
                which is not token-protected and records identical statuses.
              </div>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <td style="width:220px;font-weight:600;padding:8px 16px;vertical-align:top">Self-test</td>
          <td style="padding:8px 16px">
            <form method="POST" style="margin:0">
              <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
              <div style="font-size:12px;color:var(--text-secondary);margin-bottom:8px">
                Posts a synthetic receipt to our own DLR endpoint for the most recent sent message,
                checks whether it was recorded, then puts the message back exactly as it was.
                If this passes but real receipts never arrive, the problem is the Onfon portal
                configuration rather than this server.
              </div>
              <button type="submit" name="dlr_selftest" value="1" class="btn btn-secondary">
                <i class="fa-solid fa-vial"></i> Run DLR Self-Test
              </button>
            </form>
            <?php if ($dlrTestResult): ?>
              <div style="margin-top:12px">
                <?php foreach ($dlrTestResult['steps'] as [$label, $ok, $detail]): ?>
                  <div style="display:flex;gap:8px;align-items:flex-start;margin-bottom:6px;font-size:12px">
                    <span class="badge badge-<?=$ok ? 'success' : 'danger'?>" style="font-size:10px;flex-shrink:0">
                      <?=$ok ? 'PASS' : 'FAIL'?>
                    </span>
                    <div>
                      <strong><?=htmlspecialchars($label)?></strong>
                      <div style="color:var(--text-secondary);word-break:break-all"><?=htmlspecialchars($detail)?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
                <div class="alert alert-<?=$dlrTestResult['ok'] ? 'success' : 'warning'?>" style="margin-top:10px;font-size:12px">
                  <?php if ($dlrTestResult['ok']): ?>
                    Our receiving side is working. If the Delivery Reports page still says
                    &ldquo;No carrier receipts&rdquo;, Onfon is not calling the URL above &mdash; register it in
                    the Onfon portal under Account &rarr; SMS Settings &rarr; Delivery Report URL.
                  <?php else: ?>
                    The receipt was not recorded. Fix the failing step above before looking at the Onfon portal.
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <td style="width:220px;font-weight:600;padding:8px 16px">Raw DLR log</td>
          <td style="padding:8px 16px;font-family:monospace;font-size:12px">
            <?php $dlrLog = __DIR__ . '/../includes/gateways/dlr_debug.log'; ?>
            <?= file_exists($dlrLog)
                ? '<span class="badge badge-success">Exists</span> ' . number_format(filesize($dlrLog)) . ' bytes'
                : '<span class="badge badge-warning">Not created yet</span> no DLR has reached /api/v1/dlr.php' ?>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Kopo Kopo -->
<div class="card" style="margin-bottom:18px">
  <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-money-bill-transfer" style="color:var(--primary)"></i> Kopo Kopo Payments</h3></div>
  <div class="card-body" style="padding:0">
    <table class="data-table" style="margin:0">
      <tbody>
        <?php foreach ($kkChecks as $label => $c): ?>
          <tr>
            <td style="width:200px;font-weight:600;padding:8px 16px"><?=htmlspecialchars($label)?></td>
            <td style="padding:8px 16px;font-family:monospace;font-size:12px;word-break:break-all">
              <?php if (!$c['set']): ?>
                <span class="badge badge-<?=$c['required'] ? 'danger' : 'warning'?>"><?=$c['required'] ? 'MISSING' : 'Not set'?></span>
                <?php if (!$c['required']): ?><span style="color:var(--text-secondary)"> &mdash; webhook signatures cannot be verified</span><?php endif; ?>
              <?php else: ?>
                <span class="badge badge-success">OK</span>
                <?php if (!empty($c['note'])): ?> <?=htmlspecialchars($c['note'])?><?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php foreach ($kkMigrations as $what => $present): ?>
          <tr>
            <td style="width:200px;font-weight:600;padding:8px 16px"><?=htmlspecialchars($what)?></td>
            <td style="padding:8px 16px">
              <?php if ($present): ?>
                <span class="badge badge-success">Present</span>
              <?php else: ?>
                <span class="badge badge-danger">MISSING</span>
                <span style="font-size:12px;color:var(--text-secondary)"> &mdash; run the matching file in <code>database/</code>; payments fail until you do</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card-body" style="border-top:1px solid var(--border-color);display:grid;grid-template-columns:1fr 1fr;gap:20px">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <div style="font-weight:700;font-size:13px;margin-bottom:8px">1. Test credentials</div>
      <div style="font-size:12px;color:var(--text-secondary);margin-bottom:10px">Requests an OAuth token. Proves the Client ID and Secret are correct without moving money.</div>
      <button type="submit" name="kk_test_token" value="1" class="btn btn-secondary"><i class="fa-solid fa-key"></i> Test Authentication</button>
      <?php if ($kkTokenResult): ?>
        <div class="alert alert-<?=$kkTokenResult['ok'] ? 'success' : 'danger'?>" style="margin-top:12px;font-size:12px"><?=htmlspecialchars($kkTokenResult['msg'])?></div>
      <?php endif; ?>
    </form>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <div style="font-weight:700;font-size:13px;margin-bottom:8px">2. Test STK push</div>
      <div style="font-size:12px;color:var(--text-secondary);margin-bottom:10px">Sends a real prompt for a real amount. Uses a DIAG reference the webhook ignores, so no wallet is credited.</div>
      <div style="display:flex;gap:8px;margin-bottom:10px">
        <input type="text" name="kk_test_phone" class="form-control" placeholder="0712345678" value="<?=htmlspecialchars($_POST['kk_test_phone'] ?? '')?>">
        <input type="number" name="kk_test_amount" class="form-control" style="width:110px" min="1" value="<?=htmlspecialchars($_POST['kk_test_amount'] ?? '1')?>" title="Amount in KES">
      </div>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-mobile-screen-button"></i> Send Test STK</button>
      <?php if ($kkStkResult): ?>
        <div class="alert alert-<?=$kkStkResult['ok'] ? 'success' : 'danger'?>" style="margin-top:12px;font-size:12px;word-break:break-all"><?=htmlspecialchars($kkStkResult['msg'])?></div>
      <?php endif; ?>
    </form>
  </div>
</div>

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
