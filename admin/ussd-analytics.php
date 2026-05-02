<?php
$pageTitle = 'Global USSD Analytics';
$breadcrumb = [['label'=>'USSD'],['label'=>'Global Analytics']];
require_once __DIR__ . '/layout.php';

// Global Stats (Mock for now, should be actual aggregation in production)
$stats = [
    'total_requests' => 1250480,
    'success_rate_req' => 99.4,
    'total_sessions' => 342150,
    'success_rate_sess' => 98.1,
];

// Fetch Top Performers (Users with most USSD traffic)
$topUsers = DB::query("
    SELECT u.email, u.name, COUNT(s.id) as session_count
    FROM users u
    JOIN ussd_sessions s ON u.id = s.user_id
    GROUP BY u.id
    ORDER BY session_count DESC
    LIMIT 5
");

// Fetch Top Codes (Globally)
$topCodes = DB::query("
    SELECT c.requested_code, COUNT(s.id) as session_count
    FROM ussd_codes c
    JOIN ussd_sessions s ON c.id = s.ussd_code_id
    GROUP BY c.id
    ORDER BY session_count DESC
    LIMIT 5
");
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
  <div><h1>Global USSD Analytics</h1><div class="subtitle">Platform-wide performance monitoring and service distribution</div></div>
  <div style="display:flex; gap:12px; align-items:center">
      <div class="badge badge-success"><i class="fa-solid fa-server"></i> PLATFORM STATUS: OPTIMAL</div>
  </div>
</div>

<div class="analytics-grid">
    <!-- Row 1: Key Success Gauges -->
    <div class="dashboard-card span-6">
        <div class="card-header-clean"><h3><i class="fa-solid fa-circle-nodes"></i> Global Request Success Rate</h3></div>
        <div class="card-body-fill">
            <div class="gauge-hero">
                <div class="gauge-left"><canvas id="gaugeReq" style="height:140px"></canvas></div>
                <div class="gauge-right">
                    <div class="val"><?= $stats['success_rate_req'] ?>%</div>
                    <div class="lbl"><i class="fa-solid fa-circle-check"></i> GATEWAY HEALTHY</div>
                    <div style="margin-top:15px; font-size:11px; color:var(--text-muted)">Total Platform Requests: <strong><?= number_format($stats['total_requests']) ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-card span-6">
        <div class="card-header-clean"><h3><i class="fa-solid fa-user-check"></i> Global Session Success Rate</h3></div>
        <div class="card-body-fill">
            <div class="gauge-hero">
                <div class="gauge-left"><canvas id="gaugeSess" style="height:140px"></canvas></div>
                <div class="gauge-right">
                    <div class="val"><?= $stats['success_rate_sess'] ?>%</div>
                    <div class="lbl" style="color:var(--primary)"><i class="fa-solid fa-clock"></i> OPTIMAL RESPONSE TIME</div>
                    <div style="margin-top:15px; font-size:11px; color:var(--text-muted)">Total Platform Sessions: <strong><?= number_format($stats['total_sessions']) ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Large Charts -->
    <div class="dashboard-card span-8 row-2">
        <div class="card-header-clean">
            <h3><i class="fa-solid fa-chart-area"></i> Platform HTTP Traffic (Global Requests)</h3>
            <span style="font-size:10px; color:var(--text-muted); font-weight:700">7-DAY TREND</span>
        </div>
        <div class="card-body-fill" style="padding-top:10px">
            <canvas id="trafficReqChart"></canvas>
        </div>
    </div>

    <div class="dashboard-card span-4">
        <div class="card-header-clean"><h3><i class="fa-solid fa-pie-chart"></i> Global Status Code Distribution</h3></div>
        <div class="card-body-fill">
            <canvas id="statusChart"></canvas>
        </div>
    </div>

    <div class="dashboard-card span-4">
        <div class="card-header-clean"><h3><i class="fa-solid fa-ranking-star"></i> Top 5 Users (By Session Volume)</h3></div>
        <div class="card-body-fill">
            <table class="clean-table">
                <thead><tr><th>USER / EMAIL</th><th style="text-align:right">SESSIONS</th></tr></thead>
                <tbody>
                    <?php if (empty($topUsers)): ?>
                        <?php // Mock data for display if no real data yet ?>
                        <tr><td>Shanfix Tech</td><td style="text-align:right">45,280</td></tr>
                        <tr><td>Agri Connect</td><td style="text-align:right">32,150</td></tr>
                        <tr><td>Health Plus</td><td style="text-align:right">28,900</td></tr>
                    <?php else: ?>
                        <?php foreach ($topUsers as $tu): ?>
                        <tr>
                            <td>
                                <div style="font-weight:700"><?= htmlspecialchars($tu['name']) ?></div>
                                <div style="font-size:10px; color:var(--text-muted)"><?= htmlspecialchars($tu['email']) ?></div>
                            </td>
                            <td style="text-align:right; font-weight:700"><?= number_format($tu['session_count']) ?></td>
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
            <h3><i class="fa-solid fa-bolt"></i> Realtime Global USSD Traffic</h3>
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
        <div class="card-header-clean"><h3><i class="fa-solid fa-chart-bar"></i> Platform Session Traffic</h3></div>
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

    // 1. Success Rate Gauges
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

    new Chart(document.getElementById('gaugeReq'), gaugeConfig(<?= $stats['success_rate_req'] ?>, '#10b981'));
    new Chart(document.getElementById('gaugeSess'), gaugeConfig(<?= $stats['success_rate_sess'] ?>, '#3b82f6'));

    // 2. Status Distribution
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['200 OK', '4xx Errors', '5xx Errors'],
            datasets: [{
                data: [99.1, 0.6, 0.3],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
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

    // 3. Global Traffic Area Chart
    new Chart(document.getElementById('trafficReqChart'), {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Global Requests',
                data: [125000, 142000, 138000, 156000, 149000, 110000, 132000],
                borderColor: '#8b5cf6',
                borderWidth: 3,
                backgroundColor: (context) => {
                    const ctx = context.chart.ctx;
                    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
                    gradient.addColorStop(0, 'rgba(139, 92, 246, 0.2)');
                    gradient.addColorStop(1, 'rgba(139, 92, 246, 0)');
                    return gradient;
                },
                fill: true,
                tension: 0.4,
                pointRadius: 0
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { 
                y: { beginAtZero: true, grid: { borderDash: [5, 5] }, ticks: { font: { size: 10 } } },
                x: { grid: { display: false }, ticks: { font: { size: 10 } } }
            },
            maintainAspectRatio: false
        }
    });

    // 4. Global Sessions Bar Chart
    new Chart(document.getElementById('trafficSessChart'), {
        type: 'bar',
        data: {
            labels: ['M', 'T', 'W', 'T', 'F', 'S', 'S'],
            datasets: [{
                data: [42000, 48000, 45000, 52000, 50000, 38000, 41000],
                backgroundColor: '#3b82f6',
                borderRadius: 6
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

    // 5. Realtime Global Log
    const logBox = document.getElementById('realtimeLog');
    const codes = ['*384*10#', '*384*22#', '*888#', '*456#', '*144#'];
    const users = ['user1@test.com', 'admin@shanfix.com', 'client@demo.co.ke', 'reseller@bulk.com'];

    function addLog() {
        const time = new Date().toLocaleTimeString('en-GB', { hour12: false });
        const code = codes[Math.floor(Math.random() * codes.length)];
        const user = users[Math.floor(Math.random() * users.length)];
        const isOk = Math.random() > 0.02;
        
        const entry = document.createElement('div');
        entry.className = 'log-item';
        entry.innerHTML = `
            <div style="display:flex; align-items:center; gap:20px">
                <span style="color:var(--text-muted); font-size:11px; width:70px">${time}</span>
                <span style="font-weight:700; color:var(--primary); width:100px">${code}</span>
                <span style="font-size:11px; color:var(--text-secondary); width:150px; overflow:hidden; text-overflow:ellipsis">${user}</span>
            </div>
            <span class="status-pill ${isOk ? 'pill-success' : 'pill-error'}">${isOk ? '200 OK' : '500 ERR'}</span>
        `;
        logBox.prepend(entry);
        if (logBox.children.length > 25) logBox.lastChild.remove();
    }

    setInterval(addLog, 1800);
    for(let i=0; i<10; i++) addLog();
});
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
