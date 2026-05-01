<?php
$pageTitle = 'USSD Command Center';
$breadcrumb = [['label'=>'USSD'],['label'=>'Analytics']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];
$codeId = (int)($_GET['code'] ?? 0);
$myCodes = DB::query("SELECT id, requested_code FROM ussd_codes WHERE user_id = ? AND status = 'approved'", [$uid]);

if ($codeId === 0 && !empty($myCodes)) {
    $codeId = $myCodes[0]['id'];
}

// Stats for the "Command Center"
$totalRequests = 12450; $successRate = 98.4; $totalSessions = 3200; $avgResponseTime = 142;
?>

<div class="analytics-reset-wrapper animate-in" style="background:#0a0c12; margin:-24px; padding:40px; min-height:calc(100vh - 100px); color:#fff; border-radius:0 0 24px 24px">
    
    <!-- Futuristic Header -->
    <div class="d-flex justify-between align-end mb-40">
        <div>
            <div class="d-flex align-center gap-12 mb-8">
                <span style="background:#00f2fe; width:12px; height:12px; border-radius:50%; box-shadow:0 0 15px #00f2fe"></span>
                <span style="font-weight:700; letter-spacing:2px; font-size:12px; color:#00f2fe">SYSTEM OPERATIONAL</span>
            </div>
            <h1 style="font-size:42px; font-weight:900; letter-spacing:-1.5px; margin:0; background:linear-gradient(to right, #fff, #888); -webkit-background-clip:text; -webkit-text-fill-color:transparent;">USSD Analytics</h1>
        </div>
        <div class="d-flex gap-16 align-center">
            <select class="custom-dark-select" onchange="location.href='?code='+this.value">
                <option value="0">ALL SERVICES</option>
                <?php foreach($myCodes as $mc): ?><option value="<?= $mc['id'] ?>" <?= $codeId == $mc['id'] ? 'selected' : '' ?>><?= $mc['requested_code'] ?></option><?php endforeach; ?>
            </select>
            <button class="export-glow-btn"><i class="fa-solid fa-bolt"></i> SYNC DATA</button>
        </div>
    </div>

    <!-- Main Grid: Bento Style with Neon Accents -->
    <div class="bento-grid">
        <!-- Hero Card: Real-time Pulse -->
        <div class="bento-item hero" style="grid-area: hero">
            <div class="bento-content">
                <div class="pulse-container">
                    <svg viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="45" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="2"/>
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#00f2fe" stroke-width="3" stroke-dasharray="282.7" stroke-dashoffset="<?= 282.7 * (1 - ($successRate/100)) ?>" stroke-linecap="round" style="filter:drop-shadow(0 0 8px #00f2fe)"/>
                    </svg>
                    <div class="pulse-value">
                        <div class="val"><?= $successRate ?>%</div>
                        <div class="lbl">SUCCESS RATE</div>
                    </div>
                </div>
                <div class="d-flex justify-around w-100 mt-24">
                    <div class="mini-stat"><span>UPTIME</span><strong>99.9%</strong></div>
                    <div class="mini-stat"><span>ERRORS</span><strong>1.6%</strong></div>
                    <div class="mini-stat"><span>LATENCY</span><strong><?= $avgResponseTime ?>ms</strong></div>
                </div>
            </div>
        </div>

        <!-- Traffic Graph Card -->
        <div class="bento-item graph" style="grid-area: graph">
            <div class="bento-header"><span><i class="fa-solid fa-chart-line"></i> TRAFFIC FLOW</span></div>
            <div class="bento-content">
                <canvas id="trafficNeonChart"></canvas>
            </div>
        </div>

        <!-- Metrics Side Column -->
        <div class="bento-item stat-1" style="grid-area: s1">
            <div class="bento-content d-flex flex-column justify-center align-center">
                <div class="icon-glow blue"><i class="fa-solid fa-server"></i></div>
                <div class="val-big"><?= number_format($totalRequests) ?></div>
                <div class="lbl-small">TOTAL REQUESTS</div>
            </div>
        </div>

        <div class="bento-item stat-2" style="grid-area: s2">
            <div class="bento-content d-flex flex-column justify-center align-center">
                <div class="icon-glow purple"><i class="fa-solid fa-users"></i></div>
                <div class="val-big"><?= number_format($totalSessions) ?></div>
                <div class="lbl-small">UNIQUE SESSIONS</div>
            </div>
        </div>

        <!-- Live Activity Stream -->
        <div class="bento-item activity" style="grid-area: activity">
            <div class="bento-header d-flex justify-between">
                <span><i class="fa-solid fa-terminal"></i> LIVE STREAM</span>
                <span class="scanning-text">SCANNING...</span>
            </div>
            <div class="bento-content p-0" id="neonLog"></div>
        </div>

        <!-- Top Codes Table -->
        <div class="bento-item table" style="grid-area: table">
            <div class="bento-header"><span><i class="fa-solid fa-ranking-star"></i> TOP PERFORMING SERVICES</span></div>
            <div class="bento-content p-0">
                <table class="neon-table">
                    <thead><tr><th>SERVICE</th><th>VOLUME</th><th>STATUS</th></tr></thead>
                    <tbody>
                        <tr><td><strong>*384*10#</strong></td><td>8,450</td><td><span class="neon-badge green">ACTIVE</span></td></tr>
                        <tr><td><strong>*384*12#</strong></td><td>3,200</td><td><span class="neon-badge green">ACTIVE</span></td></tr>
                        <tr><td><strong>*888#</strong></td><td>800</td><td><span class="neon-badge orange">LOAD</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.analytics-reset-wrapper { font-family: 'Inter', sans-serif; }
.bento-grid {
    display: grid;
    grid-template-areas: 
        "hero graph graph"
        "hero graph graph"
        "s1 activity activity"
        "s2 activity activity"
        "table table table";
    grid-template-columns: 1fr 1fr 1fr;
    grid-template-rows: auto auto auto auto auto;
    gap: 20px;
}

.bento-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 24px;
    padding: 24px;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    overflow: hidden;
    position: relative;
}
.bento-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.15);
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.bento-header { font-size: 11px; font-weight: 800; letter-spacing: 1.5px; color: rgba(255,255,255,0.4); margin-bottom: 20px; text-transform: uppercase; }
.pulse-container { width: 160px; height: 160px; margin: 0 auto; position: relative; }
.pulse-value { position: absolute; inset: 0; display: flex; flex-direction: column; align-center justify-center; text-align: center; }
.pulse-value .val { font-size: 32px; font-weight: 900; color: #fff; }
.pulse-value .lbl { font-size: 9px; font-weight: 700; color: #00f2fe; }

.icon-glow { width: 50px; height: 50px; border-radius: 50%; display: flex; align-center justify-center; font-size: 20px; margin-bottom: 16px; }
.icon-glow.blue { background: rgba(0, 242, 254, 0.1); color: #00f2fe; box-shadow: 0 0 20px rgba(0, 242, 254, 0.2); }
.icon-glow.purple { background: rgba(160, 32, 240, 0.1); color: #a020f0; box-shadow: 0 0 20px rgba(160, 32, 240, 0.2); }

.val-big { font-size: 36px; font-weight: 900; margin-bottom: 4px; }
.lbl-small { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.4); }

.mini-stat { text-align: center; }
.mini-stat span { display: block; font-size: 9px; color: rgba(255,255,255,0.4); font-weight: 700; margin-bottom: 4px; }
.mini-stat strong { font-size: 16px; font-weight: 800; }

.scanning-text { color: #00f2fe; animation: blink 1.5s infinite; }
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }

.neon-table { width: 100%; border-collapse: collapse; }
.neon-table th { text-align: left; padding: 16px 24px; font-size: 10px; color: rgba(255,255,255,0.3); border-bottom: 1px solid rgba(255,255,255,0.05); }
.neon-table td { padding: 16px 24px; font-size: 14px; border-bottom: 1px solid rgba(255,255,255,0.05); }
.neon-badge { font-size: 9px; font-weight: 800; padding: 4px 10px; border-radius: 20px; }
.neon-badge.green { background: rgba(0, 242, 254, 0.1); color: #00f2fe; border: 1px solid rgba(0, 242, 254, 0.3); }
.neon-badge.orange { background: rgba(255, 165, 0, 0.1); color: #ffa500; border: 1px solid rgba(255, 165, 0, 0.3); }

.custom-dark-select { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 10px 20px; border-radius: 12px; font-size: 12px; font-weight: 700; outline: none; }
.export-glow-btn { background: linear-gradient(135deg, #00f2fe, #4facfe); border: none; color: #fff; padding: 10px 24px; border-radius: 12px; font-size: 12px; font-weight: 800; box-shadow: 0 4px 15px rgba(0, 242, 254, 0.3); cursor: pointer; }

#neonLog { height: 260px; overflow-y: auto; padding: 0 24px; }
.log-line { padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.03); display: flex; justify-between align-center; font-family: 'JetBrains Mono', monospace; font-size: 11px; }
.log-line .ts { color: rgba(255,255,255,0.2); }
.log-line .msg { color: #00f2fe; font-weight: 700; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Neon Traffic Chart
    const ctx = document.getElementById('trafficNeonChart');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['12AM', '4AM', '8AM', '12PM', '4PM', '8PM', 'NOW'],
            datasets: [{
                data: [450, 380, 890, 1400, 1100, 1500, 1850],
                borderColor: '#00f2fe',
                borderWidth: 4,
                pointRadius: 0,
                fill: true,
                backgroundColor: (context) => {
                    const chart = context.chart;
                    const {ctx, chartArea} = chart;
                    if (!chartArea) return null;
                    const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                    gradient.addColorStop(0, 'rgba(0, 242, 254, 0)');
                    gradient.addColorStop(1, 'rgba(0, 242, 254, 0.2)');
                    return gradient;
                },
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { display: false },
                x: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,0.3)', font: { size: 10, weight: '700' } } }
            }
        }
    });

    // Neon Log Stream
    const log = document.getElementById('neonLog');
    const uids = ['*384*10#', '*384*12#', '*888#'];
    
    function addLog() {
        const line = document.createElement('div');
        line.className = 'log-line animate-in';
        line.innerHTML = `
            <div>
                <span class="ts">[${new Date().toLocaleTimeString()}]</span>
                <span class="msg ml-12">INBOUND REQUEST</span>
                <span class="ml-12" style="color:rgba(255,255,255,0.5)">PID: ${Math.floor(Math.random()*9999)}</span>
            </div>
            <div style="color:#00f2fe; font-weight:900">${uids[Math.floor(Math.random()*uids.length)]}</div>
        `;
        log.prepend(line);
        if(log.children.length > 8) log.lastChild.remove();
    }

    for(let i=0; i<6; i++) addLog();
    setInterval(addLog, 2500);
});
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
