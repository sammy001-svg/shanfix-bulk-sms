<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('client');

$user      = current_user();
$sessionId = (int)($_GET['session_id'] ?? 0);

if (!$sessionId) {
    echo '<div class="alert alert-danger">Invalid session.</div>';
    exit;
}

// Verify the session belongs to one of this client's USSD codes
$session = DB::queryOne("
    SELECT s.*, c.requested_code
    FROM ussd_sessions s
    JOIN ussd_codes c ON s.ussd_code_id = c.id
    WHERE s.id = ? AND c.user_id = ?
", [$sessionId, $user['id']]);

if (!$session) {
    echo '<div class="alert alert-danger">Session not found or access denied.</div>';
    exit;
}

$requests = DB::query("
    SELECT * FROM ussd_requests
    WHERE session_id = ?
    ORDER BY created_at ASC
", [$sessionId]);
?>

<div style="padding:20px">
    <div style="display:flex; justify-content:space-between; margin-bottom:20px; background:var(--bg-muted); padding:15px; border-radius:10px">
        <div><span class="text-muted">Code:</span> <strong><?= htmlspecialchars($session['requested_code']) ?></strong></div>
        <div><span class="text-muted">Phone:</span> <strong><?= htmlspecialchars($session['phone']) ?></strong></div>
        <div><span class="text-muted">Status:</span> <strong><?= strtoupper($session['status']) ?></strong></div>
    </div>

    <div class="timeline" style="position:relative; padding-left:30px">
        <style>
            .timeline::before { content:''; position:absolute; left:10px; top:0; bottom:0; width:2px; background:var(--border); }
            .timeline-item { position:relative; margin-bottom:25px; }
            .timeline-dot { position:absolute; left:-25px; top:5px; width:12px; height:12px; background:var(--primary); border-radius:50%; border:3px solid #fff; }
            .msg-bubble { background:var(--bg-muted); padding:12px 15px; border-radius:12px; font-size:13px; margin-top:5px; border:1px solid var(--border); }
            .msg-label { font-size:10px; font-weight:800; text-transform:uppercase; color:var(--text-muted); margin-bottom:2px; }
        </style>

        <?php if (empty($requests)): ?>
            <div class="text-muted">No interactions recorded for this session.</div>
        <?php endif; ?>

        <?php foreach ($requests as $r): ?>
            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div style="display:flex; justify-content:space-between; align-items:center">
                    <span style="font-size:11px; color:var(--text-muted)"><?= date('H:i:s', strtotime($r['created_at'])) ?></span>
                    <span style="font-size:10px; color:var(--text-muted)"><?= (int)$r['response_time'] ?>ms</span>
                </div>

                <div style="margin-top:8px">
                    <div class="msg-label">User Input</div>
                    <div class="msg-bubble" style="background:#fff">
                        <?= isset($r['input_text']) && $r['input_text'] !== '' ? htmlspecialchars($r['input_text']) : '<i>(Empty/Start)</i>' ?>
                    </div>
                </div>

                <div style="margin-top:12px">
                    <div class="msg-label" style="color:var(--primary)">System Response</div>
                    <div class="msg-bubble" style="border-left:4px solid var(--primary)">
                        <?= nl2br(htmlspecialchars($r['response_text'] ?? '')) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
