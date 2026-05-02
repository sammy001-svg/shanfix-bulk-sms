<?php
$pageTitle = 'WhatsApp Smart Automations';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Smart Automations']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// Handle New Rule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_rule'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } else {
        $keyword = sanitize($_POST['keyword']);
        $matchType = $_POST['match_type'];
        $response = sanitize($_POST['response']);
        $mediaUrl = sanitize($_POST['media_url'] ?? '');
        
        DB::execute("
            INSERT INTO whatsapp_chatbots (user_id, keyword, match_type, response, media_url) 
            VALUES (?, ?, ?, ?, ?)
        ", [$uid, $keyword, $matchType, $response, $mediaUrl]);
        flash_set('success', 'Automation rule deployed successfully.');
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    DB::execute("DELETE FROM whatsapp_chatbots WHERE id = ? AND user_id = ?", [$id, $uid]);
    flash_set('success', 'Automation rule removed.');
    header("Location: whatsapp-chatbot.php");
    exit;
}

$rules = DB::query("SELECT * FROM whatsapp_chatbots WHERE user_id = ? ORDER BY trigger_count DESC, created_at DESC", [$uid]);
?>

<style>
.automation-card { border-radius: 16px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid var(--border); overflow: hidden; position: relative; }
.automation-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--primary); }

.rule-header { padding: 16px 20px; background: var(--bg-muted); display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); }
.rule-body { padding: 20px; }
.rule-footer { padding: 12px 20px; background: rgba(0,0,0,0.02); display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: var(--text-muted); }

.keyword-pill { background: var(--primary); color: #fff; padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 12px; font-family: 'JetBrains Mono', monospace; }
.match-type-label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; }

.response-preview { color: var(--text-secondary); font-size: 13px; line-height: 1.6; margin-bottom: 15px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

.trigger-badge { display: flex; align-items: center; gap: 6px; font-weight: 700; color: var(--primary); }
.trigger-badge i { font-size: 14px; }
</style>

<div class="page-header">
  <div><h1>Smart Automations</h1><div class="subtitle">Deploy intelligent auto-replies to provide instant 24/7 customer support</div></div>
  <button class="btn btn-primary btn-lg" onclick="document.getElementById('ruleModal').style.display='flex'">
    <i class="fa-solid fa-plus-circle"></i> Create New Automation
  </button>
</div>

<div class="stats-grid mb-24">
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(59, 130, 246, 0.1); color:var(--primary)"><i class="fa-solid fa-robot"></i></div>
        <div class="stat-details">
            <div class="stat-label">Active Rules</div>
            <div class="stat-value"><?= count($rules) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(16, 185, 129, 0.1); color:var(--success)"><i class="fa-solid fa-bolt"></i></div>
        <div class="stat-details">
            <div class="stat-label">Total Triggers</div>
            <div class="stat-value"><?= number_format(array_sum(array_column($rules, 'trigger_count'))) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(245, 158, 11, 0.1); color:var(--warning)"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-details">
            <div class="stat-label">Last Triggered</div>
            <div class="stat-value" style="font-size:14px"><?= !empty($rules) ? 'Just now' : 'N/A' ?></div>
        </div>
    </div>
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap:24px">
    <?php if (empty($rules)): ?>
        <div class="span-12 text-center" style="padding:100px 0">
            <div style="font-size:48px; color:var(--bg-muted); margin-bottom:20px"><i class="fa-solid fa-robot"></i></div>
            <h3 class="text-muted">No automation rules yet</h3>
            <p class="text-muted">Start by creating your first intelligent auto-reply trigger.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($rules as $r): ?>
        <div class="card automation-card">
            <div class="rule-header">
                <div style="display:flex; flex-direction:column">
                    <span class="match-type-label"><?= str_replace('_', ' ', $r['match_type']) ?> Match</span>
                    <div style="margin-top:5px"><span class="keyword-pill"><?= htmlspecialchars($r['keyword']) ?></span></div>
                </div>
                <div style="display:flex; gap:8px">
                    <button class="btn btn-sm btn-icon"><i class="fa-solid fa-edit"></i></button>
                    <a href="?delete=<?= $r['id'] ?>" class="btn btn-sm btn-icon text-danger" onclick="return confirm('Remove this automation?')"><i class="fa-solid fa-trash"></i></a>
                </div>
            </div>
            <div class="rule-body">
                <div class="response-preview"><?= htmlspecialchars($r['response']) ?></div>
                
                <?php if ($r['media_url']): ?>
                    <div style="display:flex; align-items:center; gap:8px; font-size:11px; color:var(--primary); background:rgba(59, 130, 246, 0.05); padding:8px 12px; border-radius:8px">
                        <i class="fa-solid fa-paperclip"></i>
                        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap"><?= basename($r['media_url']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="rule-footer">
                <div class="trigger-badge">
                    <i class="fa-solid fa-chart-line"></i>
                    <span><?= number_format($r['trigger_count']) ?> Triggers</span>
                </div>
                <div>Created <?= date('d M Y', strtotime($r['created_at'])) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modern Rule Modal -->
<div id="ruleModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(8px)">
  <div class="card" style="width:100%; max-width:600px; margin:20px; border:none; box-shadow: var(--shadow-lg); animation: modalSlide 0.3s ease-out">
    <div class="card-header" style="padding:24px; border-bottom:1px solid var(--border)">
      <div>
          <h3 class="card-title">New Smart Automation</h3>
          <p class="text-muted" style="font-size:12px; margin:0">Configure a keyword-based trigger for rich responses</p>
      </div>
      <button type="button" class="btn btn-icon" onclick="document.getElementById('ruleModal').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
        <div class="card-body" style="padding:24px">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px" class="mb-20">
                <div class="form-group">
                    <label class="form-label">Keyword Trigger</label>
                    <input type="text" name="keyword" class="form-control" placeholder="e.g. HELP" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Match Logic</label>
                    <select name="match_type" class="form-control">
                        <option value="exact">Exact Match (Strict)</option>
                        <option value="contains">Contains Keyword</option>
                        <option value="starts_with">Starts With</option>
                    </select>
                </div>
            </div>

            <div class="form-group mb-20">
                <label class="form-label">Automated Response</label>
                <textarea name="response" class="form-control" rows="5" placeholder="Your automated message..." required></textarea>
                <div class="form-hint">Tip: Use emojis to make your bot feel more human! 🚀</div>
            </div>

            <div class="form-group">
                <label class="form-label">Media Attachment URL (Optional)</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-link"></i></span>
                    <input type="url" name="media_url" class="form-control" placeholder="https://example.com/image.jpg">
                </div>
                <div class="form-hint">Direct link to an image or document to send with the reply.</div>
            </div>
        </div>
        <div class="card-footer" style="padding:20px 24px; display:flex; gap:12px">
            <button type="button" class="btn btn-muted flex-1" onclick="document.getElementById('ruleModal').style.display='none'">Cancel</button>
            <button type="submit" name="save_rule" class="btn btn-primary flex-1">Deploy Automation</button>
        </div>
    </form>
  </div>
</div>

<script>
window.onclick = function(event) {
    if (event.target == document.getElementById('ruleModal')) {
        document.getElementById('ruleModal').style.display = "none";
    }
}
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
