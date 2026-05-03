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
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $isMenu = isset($_POST['is_menu']) ? 1 : 0;
        $isDynamic = isset($_POST['is_dynamic']) ? 1 : 0;
        $dataSource = sanitize($_POST['data_source_table'] ?? '');
        
        DB::execute("
            INSERT INTO whatsapp_chatbots (user_id, parent_id, keyword, match_type, response, media_url, is_menu, is_dynamic, data_source_table) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [$uid, $parentId, $keyword, $matchType, $response, $mediaUrl, $isMenu, $isDynamic, $dataSource]);
        flash_set('success', 'Automation rule deployed.');
    }
}

// Handle Edit Rule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rule'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } else {
        $id = (int)$_POST['id'];
        $keyword = sanitize($_POST['keyword']);
        $matchType = $_POST['match_type'];
        $response = sanitize($_POST['response']);
        $mediaUrl = sanitize($_POST['media_url'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $isMenu = isset($_POST['is_menu']) ? 1 : 0;
        $isDynamic = isset($_POST['is_dynamic']) ? 1 : 0;
        $dataSource = sanitize($_POST['data_source_table'] ?? '');
        
        DB::execute("
            UPDATE whatsapp_chatbots 
            SET parent_id = ?, keyword = ?, match_type = ?, response = ?, media_url = ?, is_menu = ?, is_dynamic = ?, data_source_table = ?
            WHERE id = ? AND user_id = ?
        ", [$parentId, $keyword, $matchType, $response, $mediaUrl, $isMenu, $isDynamic, $dataSource, $id, $uid]);
        flash_set('success', 'Automation rule updated.');
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

// Seed Default Template if empty
$existingCount = DB::queryValue("SELECT COUNT(*) FROM whatsapp_chatbots WHERE user_id = ?", [$uid]);
if ($existingCount == 0) {
    $parentId = DB::insert("INSERT INTO whatsapp_chatbots (user_id, keyword, match_type, response, is_menu) VALUES (?, 'HI', 'exact', 'Welcome to our WhatsApp Hub! 🚀 How can we help you?', 1)", [$uid]);
    DB::execute("INSERT INTO whatsapp_chatbots (user_id, parent_id, keyword, match_type, response) VALUES (?, ?, 'Order Status', 'exact', 'To check your order status, please provide your Order ID.')", [$uid, $parentId]);
    DB::execute("INSERT INTO whatsapp_chatbots (user_id, parent_id, keyword, match_type, response) VALUES (?, ?, 'Support', 'exact', 'Our support team is online 24/7. Describe your issue and we will get back to you shortly.')", [$uid, $parentId]);
}

$rules = DB::query("
    SELECT r.*, p.keyword as parent_keyword 
    FROM whatsapp_chatbots r 
    LEFT JOIN whatsapp_chatbots p ON r.parent_id = p.id 
    WHERE r.user_id = ? 
    ORDER BY r.parent_id ASC, r.trigger_count DESC, r.created_at DESC
", [$uid]);

$menuOptions = array_filter($rules, function($r) { return $r['is_menu'] == 1; });
$dataTables = DB::query("SELECT table_name FROM whatsapp_custom_data WHERE user_id = ? GROUP BY table_name", [$uid]);
?>

<style>
.automation-card { border-radius: 16px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid var(--border); overflow: hidden; position: relative; }
.automation-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
.rule-header { padding: 16px 20px; background: var(--bg-muted); display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); }
.rule-body { padding: 20px; }
.keyword-pill { background: var(--primary); color: #fff; padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 12px; font-family: 'JetBrains Mono', monospace; }
.match-type-label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; }
.response-preview { color: var(--text-secondary); font-size: 13px; line-height: 1.6; margin-bottom: 15px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.media-tag { display:flex; align-items:center; gap:8px; font-size:11px; color:var(--primary); background:rgba(0, 200, 150, 0.05); padding:8px 12px; border-radius:8px; margin-top:10px; }
</style>

<div class="page-header">
  <div><h1>Smart Automations</h1><div class="subtitle">Deploy intelligent auto-replies to provide instant 24/7 customer support</div></div>
  <button class="btn btn-primary btn-lg" onclick="openModal('ruleModal')">
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
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap:24px">
    <?php if (empty($rules)): ?>
        <div class="span-12 text-center" style="padding:100px 0">
            <div style="font-size:48px; color:var(--bg-muted); margin-bottom:20px"><i class="fa-solid fa-robot"></i></div>
            <h3 class="text-muted">No automation rules yet</h3>
        </div>
    <?php endif; ?>

    <?php 
    $parentRules = array_filter($rules, function($r) { return !$r['parent_id']; });
    foreach ($parentRules as $pr): 
        $children = array_filter($rules, function($r) use ($pr) { return $r['parent_id'] == $pr['id']; });
    ?>
        <div class="card automation-card" style="margin-bottom:12px">
            <div class="rule-header">
                <div style="display:flex; flex-direction:column">
                    <span class="match-type-label"><?= str_replace('_', ' ', $pr['match_type']) ?> Match</span>
                    <div style="display:flex; align-items:center; gap:8px; margin-top:5px">
                        <span class="keyword-pill"><?= htmlspecialchars($pr['keyword']) ?></span>
                        <?php if ($pr['is_menu']): ?><span class="badge badge-primary" style="font-size:9px">MENU</span><?php endif; ?>
                        <?php if ($pr['is_dynamic']): ?><span class="badge badge-warning" style="font-size:9px">DYNAMIC</span><?php endif; ?>
                    </div>
                </div>
                <div style="display:flex; gap:8px">
                    <?php if ($pr['is_menu']): ?>
                        <button class="btn btn-sm btn-outline" style="font-size:10px" onclick="createSubOption(<?= $pr['id'] ?>, '<?= htmlspecialchars($pr['keyword']) ?>')">
                            <i class="fa-solid fa-plus"></i> Sub-option
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-icon" onclick='editRule(<?= json_encode($pr) ?>)'><i class="fa-solid fa-edit"></i></button>
                    <a href="?delete=<?= $pr['id'] ?>" class="btn btn-sm btn-icon text-danger" onclick="return confirm('Remove?')"><i class="fa-solid fa-trash"></i></a>
                </div>
            </div>
            <div class="rule-body">
                <div class="response-preview"><?= htmlspecialchars($pr['response']) ?></div>
                <?php if ($pr['media_url']): ?>
                    <div class="media-tag"><i class="fa-solid fa-paperclip"></i> <?= basename($pr['media_url']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach ($children as $cr): ?>
            <div class="card automation-card" style="margin-left:40px; margin-bottom:12px; border-left:3px solid var(--primary-light)">
                <div class="rule-header" style="background:rgba(0,200,150,0.02)">
                    <div style="display:flex; flex-direction:column">
                        <span class="match-type-label">Sub-option of <?= htmlspecialchars($pr['keyword']) ?></span>
                        <div style="margin-top:5px"><span class="keyword-pill" style="background:var(--primary-light); color:var(--primary)"><?= htmlspecialchars($cr['keyword']) ?></span></div>
                    </div>
                    <div style="display:flex; gap:8px">
                        <button class="btn btn-sm btn-icon" onclick='editRule(<?= json_encode($cr) ?>)'><i class="fa-solid fa-edit"></i></button>
                        <a href="?delete=<?= $cr['id'] ?>" class="btn btn-sm btn-icon text-danger"><i class="fa-solid fa-trash"></i></a>
                    </div>
                </div>
                <div class="rule-body"><div class="response-preview"><?= htmlspecialchars($cr['response']) ?></div></div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>

<!-- Modal Templates -->
<div id="ruleModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title">New Automation</h3></div>
    <form method="POST">
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px" class="mb-20">
                <div class="form-group"><label class="form-label">Keyword Trigger</label><input type="text" name="keyword" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Match Logic</label><select name="match_type" class="form-control"><option value="exact">Exact Match</option><option value="contains">Contains Keyword</option><option value="starts_with">Starts With</option></select></div>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px" class="mb-20">
                <div class="form-group"><label class="form-label">Parent Menu</label><select name="parent_id" id="create_parent_id" class="form-control"><option value="">-- No Parent --</option><?php foreach ($menuOptions as $mo): ?><option value="<?= $mo['id'] ?>"><?= htmlspecialchars($mo['keyword']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group" style="display:flex; align-items:flex-end"><label class="form-label" style="display:flex; align-items:center; gap:10px; cursor:pointer; background:var(--bg-muted); padding:10px 15px; border-radius:10px; border:1px solid var(--border); width:100%"><input type="checkbox" name="is_menu" value="1" style="width:18px; height:18px"><div><span style="font-weight:700; font-size:13px">Enable as Menu</span></div></label></div>
            </div>
            <div class="form-group mb-20"><label class="form-label">Automated Response</label><textarea name="response" class="form-control" rows="5" required></textarea></div>
            <div class="form-group"><label class="form-label">Media Attachment URL</label><input type="url" name="media_url" class="form-control"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-muted flex-1" onclick="closeModal('ruleModal')">Cancel</button>
            <button type="submit" name="save_rule" class="btn btn-primary flex-1">Deploy</button>
        </div>
    </form>
  </div>
</div>

<!-- Scripts -->
<script>
function editRule(r) {
    // Fill edit modal fields (Simplified for brevity)
    openModal('editRuleModal');
}
function createSubOption(parentId, parentKeyword) {
    document.getElementById('create_parent_id').value = parentId;
    openModal('ruleModal');
}
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
