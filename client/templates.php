<?php
$pageTitle = 'SMS Templates';
$breadcrumb = [['label'=>'Client'],['label'=>'SMS Templates']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];
$templates = DB::query("SELECT * FROM sms_templates WHERE user_id = ? ORDER BY created_at DESC", [$uid]);

// Handle single template fetch for edit via GET
$editTemplate = null;
if (isset($_GET['edit'])) {
    $editTemplate = DB::queryOne("SELECT * FROM sms_templates WHERE id = ? AND user_id = ?", [$_GET['edit'], $uid]);
}
?>

<div class="page-header">
  <div><h1>SMS Templates</h1><div class="subtitle">Manage frequently used messages and canned responses</div></div>
  <button class="btn btn-primary" onclick="initCreateTemplate()"><i class="fa-solid fa-plus"></i> Create Template</button>
</div>

<div class="card">
    <div class="card-body" style="padding:0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Message Preview</th>
                    <th>Created</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($templates)): ?>
                    <tr><td colspan="4" class="text-center" style="padding:40px; color:var(--text-muted)">No templates found. Create your first one to save time!</td></tr>
                <?php endif; ?>
                <?php foreach ($templates as $t): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($t['title']) ?></strong></td>
                        <td style="max-width:400px"><div class="text-truncate" title="<?= htmlspecialchars($t['message']) ?>"><?= htmlspecialchars($t['message']) ?></div></td>
                        <td><span style="font-size:12px"><?= date('d M, Y', strtotime($t['created_at'])) ?></span></td>
                        <td style="text-align:right">
                            <div style="display:flex; gap:8px; justify-content:flex-end">
                                <button class="btn btn-sm btn-outline" onclick='editTemplate(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, "UTF-8") ?>)' title="Edit"><i class="fa-solid fa-pen"></i></button>
                                <a href="/client/actions/delete-template.php?id=<?= $t['id'] ?>&csrf_token=<?= csrf_token() ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this template?')" title="Delete"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Template Modal -->
<div class="modal-overlay" id="templateModal">
    <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <h3 id="modalTitle">Create SMS Template</h3>
      <button class="modal-close" onclick="closeModal('templateModal')">&times;</button>
    </div>
    <form action="/client/actions/save-template.php" method="POST" id="templateForm">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="id" id="tplId" value="">
      
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Template Title <span class="required">*</span></label>
          <input type="text" name="title" id="tplTitle" class="form-control" placeholder="e.g. Welcome Message, Payment Reminder" required>
        </div>
        <div class="form-group">
          <label class="form-label">Message Content <span class="required">*</span></label>
          <textarea name="message" id="tplMessage" class="form-control" rows="5" placeholder="Type your message here..." required></textarea>
          <div style="margin-top:8px; font-size:12px; color:var(--text-muted)">
            <span id="tplChars">0</span> characters · <span id="tplSegs">1</span> SMS part(s)
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('templateModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Template</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
(function() {
    window.initCreateTemplate = function() {
        document.getElementById('tplId').value = '';
        document.getElementById('templateForm').reset();
        document.getElementById('modalTitle').textContent = 'Create SMS Template';
        openModal('templateModal');
    };
    window.editTemplate = function(t) {
        document.getElementById('tplId').value = t.id;
        document.getElementById('tplTitle').value = t.title;
        document.getElementById('tplMessage').value = t.message;
        document.getElementById('modalTitle').textContent = 'Edit SMS Template';
        openModal('templateModal');
        updateCounter();
    };

    const tplMsg = document.getElementById('tplMessage');
    function updateCounter() {
        if (!tplMsg) return;
        const l = tplMsg.value.length;
        const s = Math.ceil(l/160) || 1;
        document.getElementById('tplChars').textContent = l;
        document.getElementById('tplSegs').textContent = s;
    }
    if(tplMsg) tplMsg.addEventListener('input', updateCounter);
})();
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
