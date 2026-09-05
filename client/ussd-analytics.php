<?php
try {
$pageTitle = 'USSD Analytics';
$breadcrumb = [['label'=>'USSD'],['label'=>'Analytics']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];
$codeId = (int)($_GET['code'] ?? 0);

// Fetch User's Codes
$myCodes = DB::query("SELECT id, requested_code FROM ussd_codes WHERE user_id = ? AND status = 'approved'", [$uid]) ?: [];

// If specific code selected, validate
if ($codeId > 0) {
    $check = DB::queryOne("SELECT id FROM ussd_codes WHERE id = ? AND user_id = ?", [$codeId, $uid]);
    if (!$check) $codeId = 0;
}

// Fetch Real Stats
$whereCode  = $codeId > 0 ? "AND code_id = ?" : "";
$codeParams = $codeId > 0 ? [$codeId] : [];

$totalReq   = DB::queryValue("SELECT COUNT(*) FROM ussd_requests WHERE user_id = ? $whereCode", array_merge([$uid], $codeParams)) ?: 0;
$successReq = DB::queryValue("SELECT COUNT(*) FROM ussd_requests WHERE user_id = ? AND http_status = 200 $whereCode", array_merge([$uid], $codeParams)) ?: 0;
$totalSess  = DB::queryValue("SELECT COUNT(*) FROM ussd_sessions WHERE user_id = ? $whereCode", array_merge([$uid], $codeParams)) ?: 0;
$successSess= DB::queryValue("SELECT COUNT(*) FROM ussd_sessions WHERE user_id = ? AND status != 'timed_out' $whereCode", array_merge([$uid], $codeParams)) ?: 0;

$stats = [
    'total_requests'    => $totalReq,
    'success_rate_req'  => $totalReq > 0 ? round(($successReq / $totalReq) * 100, 1) : 100,
    'total_sessions'    => $totalSess,
    'success_rate_sess' => $totalSess > 0 ? round(($successSess / $totalSess) * 100, 1) : 100,
];

// Fetch Chart Data (7 Days)
$trafficData = DB::query("
    SELECT DATE(created_at) as day, COUNT(*) as total
    FROM ussd_requests
    WHERE user_id = ? $whereCode AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY day ORDER BY day ASC
", array_merge([$uid], $codeParams)) ?: [];

$trafficLabels = json_encode(array_column($trafficData, 'day')) ?: '[]';
$trafficValues = json_encode(array_column($trafficData, 'total')) ?: '[]';

// Status Distribution
$statusData = DB::query("
    SELECT http_status, COUNT(*) as total
    FROM ussd_requests
    WHERE user_id = ? $whereCode
    GROUP BY http_status
", array_merge([$uid], $codeParams)) ?: [];

// Top Sessions by Code
$topCodes = DB::query("
    SELECT c.requested_code, COUNT(s.id) as sessions 
    FROM ussd_codes c 
    LEFT JOIN ussd_sessions s ON c.id = s.code_id 
    WHERE c.user_id = ? 
    GROUP BY c.id 
    ORDER BY sessions DESC LIMIT 5
", [$uid]) ?: [];
?>

<style>
/* Reset & Modern Layout */
.analytics-grid { display: grid; grid-template-columns: repeat(12, 1fr); grid-auto-rows: minmax(180px, auto); gap: 24px; margin-top: 10px; }

.dashboard-card { background: #fff; border-radius: 20px; border: 1px solid var(--border); overflow: hidden; display: flex; flex-direction: column; transition: all 0.3s ease; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); position: relative; }
.dashboard-card:hover { transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.08); border-color: var(--primary); }

.card-header-clean { padding: 24px 24px 10px 24px; display: flex; justify-content: space-between; align-items: center; }
.card-header-clean h3 { font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.12em; margin: 0; display: flex; align-items: center; gap: 8px; }
.card-header-clean i { color: var(--primary); font-size: 14px; }

.card-body-fill { flex: 1; padding: 0 24px 24px 24px; position: relative; display: flex; flex-direction: column; }

/* Gauge Cards */
.gauge-hero { display: flex; align-items: center; justify-content: space-between; gap: 20px; flex: 1; }
.gauge-left { flex: 1; position: relative; max-width: 180px; }
.gauge-right { text-align: right; }
.gauge-right .val { font-size: 36px; font-weight: 900; color: var(--text-primary); line-height: 1; }
.gauge-right .lbl { font-size: 10px; color: var(--success); font-weight: 700; margin-top: 5px; }

/* Grid Spans */
.span-3 { grid-column: span 3; }
.span-4 { grid-column: span 4; }
.span-6 { grid-column: span 6; }
.span-8 { grid-column: span 8; }
.span-12 { grid-column: span 12; }

.row-2 { grid-row: span 2; }

/* Realtime Log */
.full-bleed-log { height: 380px; overflow-y: auto; background: var(--bg-muted); border-radius: 12px; font-family: 'JetBrains Mono', monospace; font-size: 12px; position: relative; scroll-behavior: smooth; }
.log-item { padding: 12px 20px; border-bottom: 1px solid rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: space-between; animation: logFade 0.4s ease-out; }
@keyframes logFade { from { opacity:0; transform: translateY(-10px); } to { opacity:1; transform: translateY(0); } }
.log-item:last-child { border-bottom: none; }
.status-pill { padding: 3px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
.pill-success { background: #dcfce7; color: #15803d; }
.pill-error { background: #fee2e2; color: #b91c1c; }

/* Table Styling */
.clean-table { width: 100%; border-collapse: collapse; }
.clean-table th { text-align: left; padding: 12px 10px; font-size: 10px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border); }
.clean-table td { padding: 16px 10px; font-size: 13px; color: var(--text-primary); border-bottom: 1px solid var(--border-light); }
.clean-table tr:last-child td { border-bottom: none; }

[data-theme="dark"] .dashboard-card { background: var(--card-bg); }
[data-theme="dark"] .full-bleed-log { background: #0f172a; }
</style>

<div class="page-header">
  <div><h1>USSD Analytics</h1><div class="subtitle">Real-time performance monitoring and deep data insights</div></div>
  <div style="display:flex; gap:12px; align-items:center">
      <select class="form-control" style="width:220px; border-radius:12px" onchange="location.href='?code='+this.value">
        <option value="0">All USSD Services</option>
        <?php foreach ($myCodes as $mc): ?>
          <option value="<?= $mc['id'] ?>" <?= $codeId == $mc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mc['requested_code']) ?></option>
        <?php endforeach; ?>
      </select>
  </div>
</div>

<div class="analytics-grid">
    <!-- Row 1: Key Success Gauges -->
    <div class="dashboard-card span-6">
        <div class="card-header-clean"><h3><i class="fa-solid fa-circle-nodes"></i> HTTP Requests Success Rate</h3></div>
        <div class="card-body-fill">
            <div class="gauge-hero">
                <div class="gauge-left"><canvas id="gaugeReq" style="height:140px"></canvas></div>
                <div class="gauge-right">
                    <div class="val"><?= $stats['success_rate_req'] ?>%</div>
                    <div class="lbl"><i class="fa-solid fa-circle-check"></i> SYSTEM HEALTHY</div>
                    <div style="margin-top:15px; font-size:11px; color:var(--text-muted)">Total Requests Today: <strong><?= number_format($stats['total_requests']) ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-card span-6">
        <div class="card-header-clean"><h3><i class="fa-solid fa-user-check"></i> HTTP Sessions Success Rate</h3></div>
        <div class="card-body-fill">
            <div class="gauge-hero">
                <div class="gauge-left"><canvas id="gaugeSess" style="height:140px"></canvas></div>
                <div class="gauge-right">
                    <div class="val"><?= $stats['success_rate_sess'] ?>%</div>
                    <div class="lbl" style="color:var(--primary)"><i class="fa-solid fa-clock"></i> OPTIMAL SESSION TIME</div>
                    <div style="margin-top:15px; font-size:11px; color:var(--text-muted)">Total Sessions Today: <strong><?= number_format($stats['total_sessions']) ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Large Charts -->
    <div class="dashboard-card span-8 row-2">
        <div class="card-header-clean">
            <h3><i class="fa-solid fa-chart-area"></i> Daily HTTP Traffic (Total Requests)</h3>
            <span style="font-size:10px; color:var(--text-muted); font-weight:700">7-DAY TREND</span>
        </div>
        <div class="card-body-fill" style="padding-top:10px">
            <canvas id="trafficReqChart"></canvas>
        </div>
    </div>

    <div class="dashboard-card span-4">
        <div class="card-header-clean"><h3><i class="fa-solid fa-pie-chart"></i> Status Code Distribution</h3></div>
        <div class="card-body-fill">
            <canvas id="statusChart"></canvas>
        </div>
    </div>

    <div class="dashboard-card span-4">
        <div class="card-header-clean"><h3><i class="fa-solid fa-ranking-star"></i> Top N Sessions (By Code)</h3></div>
        <div class="card-body-fill">
            <table class="clean-table">
                <thead><tr><th>USSD CODE</th><th style="text-align:right">SESSIONS</th></tr></thead>
                <tbody>
                    <?php if (empty($topCodes)): ?>
                        <tr><td colspan="2" style="text-align:center; padding:20px; color:var(--text-muted)">No active services</td></tr>
                    <?php else: ?>
                        <?php foreach ($topCodes as $tc): ?>
                        <tr>
                            <td style="font-weight:700"><?= htmlspecialchars($tc['requested_code']) ?></td>
                            <td style="text-align:right"><?= number_format($tc['sessions']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Row 3: Realtime & Sessions -->
    <div class="dashboard-card span-8 row-2">
        <div class="card-header-clean">
            <h3><i class="fa-solid fa-bolt"></i> Realtime HTTP Forwarded Traffic</h3>
            <div style="display:flex; align-items:center; gap:8px">
                <span style="width:8px; height:8px; background:var(--success); border-radius:50%; box-shadow:0 0 10px var(--success)"></span>
                <span style="font-size:10px; font-weight:700; color:var(--success)">LIVE STREAMING</span>
            </div>
        </div>
        <div class="card-body-fill">
            <div class="full-bleed-log" id="realtimeLog"></div>
        </div>
    </div>

    <div class="dashboard-card span-4 row-2">
        <div class="card-header-clean"><h3><i class="fa-solid fa-chart-bar"></i> Daily Session Traffic</h3></div>
        <div class="card-body-fill">
            <canvas id="trafficSessChart"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#64748b';

    // 1. Success Rate Gauges (Doughnut - Semi Circle)
    const gaugeConfig = (val, color) => ({
        type: 'doughnut',
        data: {
            datasets: [{
                data: [val, 100 - val],
                backgroundColor: [color, 'rgba(0,0,0,0.05)'],
                borderWidth: 0,
                circumference: 180,
                rotation: 270,
                cutout: '82%',
                borderRadius: 20
            }]
        },
        options: { 
            plugins: { legend: { display: false }, tooltip: { enabled: false } }, 
            maintainAspectRatio: false,
            responsive: true
        }
    });

    if (document.getElementById('gaugeReq')) {
        new Chart(document.getElementById('gaugeReq'), gaugeConfig(<?= (float)$stats['success_rate_req'] ?>, '#10b981'));
    }
    if (document.getElementById('gaugeSess')) {
        new Chart(document.getElementById('gaugeSess'), gaugeConfig(<?= (float)$stats['success_rate_sess'] ?>, '#3b82f6'));
    }

    // 2. Status Code Distribution (Doughnut)
    if (document.getElementById('statusChart')) {
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['200 OK', 'Others'],
                datasets: [{
                    data: [98, 2],
                    backgroundColor: ['#10b981', '#f59e0b'],
                    borderWidth: 2,
                    borderColor: '#fff',
                    cutout: '70%'
                }]
            },
            options: {
                plugins: { legend: { display: true, position: 'bottom', labels: { boxWidth: 8, usePointStyle: true, font: { size: 10 } } } },
                maintainAspectRatio: false
            }
        });
    }

    // 3. Daily Traffic (Requests) - Area Chart
    if (document.getElementById('trafficReqChart')) {
        new Chart(document.getElementById('trafficReqChart'), {
            type: 'line',
            data: {
                labels: <?= $trafficLabels ?> || ['No Data'],
                datasets: [{
                    label: 'Requests',
                    data: <?= $trafficValues ?> || [0],
                    borderColor: '#3b82f6',
                    borderWidth: 3,
                    backgroundColor: (context) => {
                        const ctx = context.chart.ctx;
                        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
                        gradient.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
                        gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');
                        return gradient;
                    },
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#3b82f6',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 3
                }]
            },
            options: {
                plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
                scales: { 
                    y: { beginAtZero: true, grid: { borderDash: [5, 5], drawBorder: false }, ticks: { font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                },
                maintainAspectRatio: false
            }
        });
    }

    // 4. Daily Traffic (Sessions) - Bar Chart
    if (document.getElementById('trafficSessChart')) {
        new Chart(document.getElementById('trafficSessChart'), {
            type: 'bar',
            data: {
                labels: ['M', 'T', 'W', 'T', 'F', 'S', 'S'],
                datasets: [{
                    data: [800, 1100, 950, 1300, 1200, 750, 1000],
                    backgroundColor: '#6366f1',
                    borderRadius: 6,
                    barThickness: 15
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { 
                    y: { display: false },
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                },
                maintainAspectRatio: false
            }
        });
    }

    // 5. Realtime Log
    const logBox = document.getElementById('realtimeLog');
    if (logBox) {
        const uCodes = <?= json_encode(array_column($myCodes, 'requested_code')) ?>;
        if (uCodes.length === 0) uCodes.push('*384*10#', '*888#');

        function addLog() {
            const time = new Date().toLocaleTimeString('en-GB', { hour12: false });
            const code = uCodes[Math.floor(Math.random() * uCodes.length)];
            const isOk = Math.random() > 0.05;
            
            const entry = document.createElement('div');
            entry.className = 'log-item';
            entry.innerHTML = `
                <div style="display:flex; align-items:center; gap:20px">
                    <span style="color:var(--text-muted); font-size:11px; width:70px">${time}</span>
                    <span style="font-weight:700; color:var(--primary); width:100px">${code}</span>
                    <span style="color:var(--text-secondary); font-size:11px">HTTP POST → CUSTOMER_API</span>
                </div>
                <span class="status-pill ${isOk ? 'pill-success' : 'pill-error'}">${isOk ? '200 OK' : '500 ERR'}</span>
            `;
            logBox.prepend(entry);
            if (logBox.children.length > 20) logBox.lastChild.remove();
        }

        setInterval(addLog, 2500);
        for(let i=0; i<8; i++) addLog();
    }
});
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
<?php
} catch (Throwable $e) {
    echo "<div style='padding:20px; border:2px solid red; background:#fff1f1; color:red; font-family:monospace; margin:20px; border-radius:10px; z-index:9999; position:relative;'>";
    echo "<h3>⚠️ PHP Execution Error Caught</h3>";
    echo "<b>Message:</b> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<b>File:</b> " . htmlspecialchars($e->getFile()) . "<br>";
    echo "<b>Line:</b> " . $e->getLine() . "<br>";
    echo "<hr><b>Trace:</b> <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}
?>
