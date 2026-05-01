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

// Stats
$totalRequests = 12450; $successRate = 98.4; $totalSessions = 3200; $avgResponseTime = 142;
?>

<div class="analytics-reset-wrapper animate-in" style="background:#0a0c12; margin:-24px; padding:40px; min-height:calc(100vh - 100px); color:#fff; border-radius:0 0 24px 24px">
    
    <div class="d-flex justify-between align-end mb-40">
        <div>
            <div class="d-flex align-center gap-12 mb-8"><span style="background:#00f2fe; width:12px; height:12px; border-radius:50%; box-shadow:0 0 15px #00f2fe"></span><span style="font-weight:700; letter-spacing:2px; font-size:12px; color:#00f2fe">SYSTEM OPERATIONAL</span></div>
            <h1 style="font-size:42px; font-weight:900; letter-spacing:-1.5px; margin:0; background:linear-gradient(to right, #fff, #888); -webkit-background-clip:text; -webkit-text-fill-color:transparent;">USSD Analytics</h1>
        </div>
        <select class="custom-dark-select" onchange="location.href='?code='+this.value">
            <option value="0">ALL SERVICES</option>
            <?php foreach($myCodes as $mc): ?><option value="<?= $mc['id'] ?>" <?= $codeId == $mc['id'] ? 'selected' : '' ?>><?= $mc['requested_code'] ?></option><?php endforeach; ?>
        </select>
    </div>

    <div class="bento-grid">
        <div class="bento-item hero" style="grid-area: hero"><div class="bento-content"><div class="pulse-container"><svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="45" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="2"/><circle cx="50" cy="50" r="45" fill="none" stroke="#00f2fe" stroke-width="3" stroke-dasharray="282.7" stroke-dashoffset="<?= 282.7 * (1 - ($successRate/100)) ?>" stroke-linecap="round" style="filter:drop-shadow(0 0 8px #00f2fe)"/></svg><div class="pulse-value"><div class="val"><?= $successRate ?>%</div><div class="lbl">SUCCESS RATE</div></div></div><div class="d-flex justify-around w-100 mt-24"><div class="mini-stat"><span>UPTIME</span><strong>99.9%</strong></div><div class="mini-stat"><span>LATENCY</span><strong><?= $avgResponseTime ?>ms</strong></div></div></div></div>
        <div class="bento-item graph" style="grid-area: graph"><div class="bento-header"><span>TRAFFIC FLOW</span></div><div class="bento-content"><canvas id="trafficNeonChart"></canvas></div></div>
        <div class="bento-item stat-1" style="grid-area: s1"><div class="bento-content d-flex flex-column justify-center align-center"><div class="icon-glow blue"><i class="fa-solid fa-server"></i></div><div class="val-big"><?= number_format($totalRequests) ?></div><div class="lbl-small">TOTAL REQUESTS</div></div></div>
        <div class="bento-item activity" style="grid-area: activity"><div class="bento-header"><span>LIVE STREAM</span></div><div class="bento-content p-0" id="neonLog" style="height:200px; overflow:auto"></div></div>
        <div class="bento-item table" style="grid-area: table"><div class="bento-header"><span>SERVICE RANKING</span></div><div class="bento-content p-0"><table class="neon-table"><thead><tr><th>SERVICE</th><th>VOLUME</th><th>HEALTH</th></tr></thead><tbody><tr><td>*384*10#</td><td>8,450</td><td><span class="neon-badge green">EXCELLENT</span></td></tr><tr><td>*384*12#</td><td>3,200</td><td><span class="neon-badge green">GOOD</span></td></tr></tbody></table></div></div>
    </div>
</div>

<style>
.analytics-reset-wrapper { font-family: 'Inter', sans-serif; }
.bento-grid { display: grid; grid-template-areas: "hero graph graph" "hero graph graph" "s1 activity activity" "table table table"; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
.bento-item { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 24px; padding: 24px; backdrop-filter: blur(10px); transition: all 0.3s ease; overflow: hidden; }
.bento-header { font-size: 11px; font-weight: 800; letter-spacing: 1.5px; color: rgba(255,255,255,0.4); margin-bottom: 20px; text-transform: uppercase; }
.pulse-container { width: 140px; height: 140px; margin: 0 auto; position: relative; }
.pulse-value { position: absolute; inset: 0; display: flex; flex-direction: column; align-center justify-center; text-align: center; }
.pulse-value .val { font-size: 28px; font-weight: 900; }
.pulse-value .lbl { font-size: 8px; font-weight: 700; color: #00f2fe; }
.icon-glow { width: 40px; height: 40px; border-radius: 50%; display: flex; align-center justify-center; font-size: 16px; margin-bottom: 12px; }
.icon-glow.blue { background: rgba(0, 242, 254, 0.1); color: #00f2fe; box-shadow: 0 0 20px rgba(0, 242, 254, 0.2); }
.val-big { font-size: 32px; font-weight: 900; }
.lbl-small { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.4); }
.mini-stat span { display: block; font-size: 8px; color: rgba(255,255,255,0.4); font-weight: 700; }
.mini-stat strong { font-size: 14px; font-weight: 800; }
.neon-table { width: 100%; border-collapse: collapse; }
.neon-table th { text-align: left; padding: 12px 24px; font-size: 9px; color: rgba(255,255,255,0.3); }
.neon-table td { padding: 12px 24px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.05); }
.neon-badge { font-size: 8px; font-weight: 800; padding: 3px 8px; border-radius: 20px; border: 1px solid rgba(0, 242, 254, 0.3); color: #00f2fe; }
.custom-dark-select { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 8px 16px; border-radius: 10px; font-size: 11px; font-weight: 700; }
.log-line { padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.03); display: flex; justify-between align-center; font-family: monospace; font-size: 10px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('trafficNeonChart'), { type: 'line', data: { labels: ['00:00', '08:00', '12:00', '16:00', '20:00', 'NOW'], datasets: [{ data: [450, 890, 1400, 1100, 1500, 1850], borderColor: '#00f2fe', borderWidth: 3, pointRadius: 0, fill: true, backgroundColor: 'rgba(0, 242, 254, 0.05)', tension: 0.4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { display: false }, x: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,0.3)', font: { size: 9 } } } } } });
    const log = document.getElementById('neonLog');
    setInterval(() => {
        const line = document.createElement('div'); line.className = 'log-line';
        line.innerHTML = `<div><span style="color:rgba(255,255,255,0.2)">[${new Date().toLocaleTimeString()}]</span><span style="color:#00f2fe; font-weight:700; margin-left:10px">INBOUND</span></div><div style="color:#00f2fe; font-weight:900">*384*10#</div>`;
        log.prepend(line); if(log.children.length > 6) log.lastChild.remove();
    }, 2500);
});
</script>
<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
