<?php
// Client Groups page — mirrors reseller/groups.php scoped to current user
$pageTitle = 'Contact Groups';
$breadcrumb = [['label'=>'Client'],['label'=>'Groups']];
require_once __DIR__ . '/layout.php';

$uid    = $user['id'];
$groups = DB::query("SELECT g.*, COUNT(c.id) as contact_count FROM contact_groups g LEFT JOIN contacts c ON c.group_id=g.id WHERE g.user_id=? GROUP BY g.id ORDER BY g.name",[$uid]);
?>
<div class="page-header">
  <div><h1>Contact Groups</h1><div class="subtitle">Organise contacts for targeted campaigns</div></div>
  <button class="btn btn-primary" onclick="openModal('createGroupModal')"><i class="fa-solid fa-plus"></i> New Group</button>
</div>

<?php if (empty($groups)): ?>
  <div class="card"><div class="card-body" style="padding:60px;text-align:center">
    <div class="empty-icon" style="font-size:48px;margin-bottom:12px">👥</div>
    <h3>No Groups Yet</h3>
    <p class="text-muted" style="margin-bottom:20px">Create groups to send targeted campaigns.</p>
    <button class="btn btn-primary btn-lg" onclick="openModal('createGroupModal')"><i class="fa-solid fa-plus"></i> Create First Group</button>
  </div></div>
<?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:16px">
    <?php foreach ($groups as $g): ?>
      <div class="card">
        <div class="card-body" style="padding:20px">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
            <div style="width:44px;height:44px;border-radius:50%;background:var(--primary-light);display:flex;align-items:center;justify-content:center"><i class="fa-solid fa-layer-group" style="color:var(--primary);font-size:20px"></i></div>
            <div class="btn-group">
              <a href="/client/contacts.php?group=<?=$g['id']?>" class="btn btn-outline btn-sm btn-icon" title="View"><i class="fa-solid fa-eye"></i></a>
              <button class="btn btn-outline btn-sm btn-icon" onclick="editGroup(<?=$g['id']?>,'<?=htmlspecialchars(addslashes($g['name']))?>')" title="Edit"><i class="fa-solid fa-pen"></i></button>
              <form method="POST" action="/client/actions/delete-group.php" style="display:inline"><input type="hidden" name="id" value="<?=$g['id']?>"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><button class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Delete group?')"><i class="fa-solid fa-trash"></i></button></form>
            </div>
          </div>
          <h4 style="font-weight:700;margin-bottom:4px"><?=htmlspecialchars($g['name'])?></h4>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px">
            <span class="badge badge-success"><i class="fa-solid fa-users"></i> <?=number_format($g['contact_count'])?> contacts</span>
            <a href="/client/campaigns.php?group=<?=$g['id']?>&new=1" class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane"></i> Send</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="modal-overlay" id="createGroupModal">
  <div class="modal"><div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-layer-group" style="color:var(--primary)"></i> New Group</h3><button class="modal-close" onclick="closeModal('createGroupModal')">×</button></div>
    <form method="POST" action="/client/actions/create-group.php"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <div class="modal-body"><div class="form-group"><label class="form-label">Group Name <span class="required">*</span></label><input type="text" name="name" class="form-control" required></div></div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('createGroupModal')">Cancel</button><button type="submit" class="btn btn-primary">Create</button></div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="editGroupModal">
  <div class="modal"><div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-pen" style="color:var(--primary)"></i> Edit Group</h3><button class="modal-close" onclick="closeModal('editGroupModal')">×</button></div>
    <form method="POST" action="/client/actions/edit-group.php"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="id" id="editGroupId">
      <div class="modal-body"><div class="form-group"><label class="form-label">Group Name <span class="required">*</span></label><input type="text" name="name" id="editGroupName" class="form-control" required></div></div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('editGroupModal')">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
    </form>
  </div>
</div>

<script>
function editGroup(id, name) {
  document.getElementById('editGroupId').value = id;
  document.getElementById('editGroupName').value = name;
  openModal('editGroupModal');
}
</script>
<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
