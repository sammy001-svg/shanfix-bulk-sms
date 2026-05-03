<?php
$pageTitle = 'Contacts';
$breadcrumb = [['label'=>'Client'],['label'=>'Contacts']];
require_once __DIR__ . '/layout.php';

$uid     = $user['id'];
$groupId = (int)($_GET['group']??0);
$search  = sanitize($_GET['q']??'');
$page    = max(1,(int)($_GET['page']??1));
$perPage = 20; $offset=($page-1)*$perPage;
$groups  = DB::query("SELECT g.*,COUNT(c.id) as cnt FROM contact_groups g LEFT JOIN contacts c ON c.group_id=g.id WHERE g.user_id=? GROUP BY g.id ORDER BY g.name",[$uid]);

$where='WHERE c.user_id=?';$params=[$uid];
if ($groupId){$where.=' AND c.group_id=?';$params[]=$groupId;}
if ($search){$where.=' AND (c.phone LIKE ? OR c.name LIKE ?)';$params[]="%$search%";$params[]="%$search%";}
$total    = DB::queryOne("SELECT COUNT(*) as c FROM contacts c $where",$params)['c']??0;
$contacts = DB::query("SELECT c.*,g.name as group_name FROM contacts c LEFT JOIN contact_groups g ON g.id=c.group_id $where ORDER BY c.created_at DESC LIMIT $perPage OFFSET $offset",$params);
$totalPages=ceil($total/$perPage);
?>
<div class="page-header">
  <div><h1>Contacts</h1><div class="subtitle">Manage your contact list</div></div>
  <div class="btn-group"><a href="/client/contacts-import.php" class="btn btn-secondary"><i class="fa-solid fa-upload"></i> Import</a><button class="btn btn-primary" onclick="openModal('addContactModal')"><i class="fa-solid fa-plus"></i> Add Contact</button></div>
</div>

<div style="display:grid;grid-template-columns:220px 1fr;gap:20px;align-items:start">
  <!-- Groups sidebar -->
  <div class="card">
    <div class="card-body" style="padding:8px 0">
      <a href="/client/contacts.php" class="nav-link <?=$groupId===0?'active':''?>" style="color:var(--text-primary)"><span class="nav-icon"><i class="fa-solid fa-users"></i></span><span class="nav-text">All Contacts <span class="badge badge-muted ml-auto"><?=$total?></span></span></a>
      <?php foreach ($groups as $g): ?>
        <a href="?group=<?=$g['id']?>" class="nav-link <?=$groupId===$g['id']?'active':''?>" style="color:var(--text-primary)"><span class="nav-icon"><i class="fa-solid fa-layer-group"></i></span><span class="nav-text"><?=htmlspecialchars($g['name'])?> <span class="badge badge-muted ml-auto"><?=$g['cnt']?></span></span></a>
      <?php endforeach; ?>
      <div style="padding:8px 16px;margin-top:4px"><button class="btn btn-outline btn-sm btn-full" onclick="openModal('addGroupModal')"><i class="fa-solid fa-plus"></i> New Group</button></div>
    </div>
  </div>

  <!-- Contacts table -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-users" style="color:var(--primary)"></i> <?=$groupId?htmlspecialchars(array_values(array_filter($groups,function($g) use ($groupId) { return $g['id']===$groupId;}))[0]['name']??'Contacts'):'All Contacts'?></h3>
      <form method="GET" style="display:flex;gap:8px"><input type="hidden" name="group" value="<?=$groupId?>"><div class="input-group"><div class="input-group-text input-addon-left"><i class="fa-solid fa-magnifying-glass"></i></div><input type="text" name="q" class="form-control with-left" placeholder="Search..." value="<?=htmlspecialchars($search)?>" style="width:180px"></div><button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i></button></form>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Phone</th><th>Name</th><th>Group</th><th>Email</th><th>Added</th><th></th></tr></thead>
        <tbody>
          <?php if (empty($contacts)): ?>
            <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">📋</div><h3>No Contacts</h3><p>Add contacts to start sending campaigns.</p><button class="btn btn-primary" onclick="openModal('addContactModal')"><i class="fa-solid fa-plus"></i> Add Contact</button></div></td></tr>
          <?php else: foreach ($contacts as $c): ?>
            <tr>
              <td><strong><?=htmlspecialchars($c['phone'])?></strong></td>
              <td><?=htmlspecialchars($c['name']??'—')?></td>
              <td><?=$c['group_name']?'<span class="badge badge-success">'.htmlspecialchars($c['group_name']).'</span>':'—'?></td>
              <td style="font-size:12px;color:var(--text-secondary)"><?=htmlspecialchars($c['email']??'—')?></td>
              <td style="font-size:12px"><?=date('d M Y',strtotime($c['created_at']))?></td>
              <td>
                <div class="btn-group">
                  <button class="btn btn-outline btn-sm btn-icon" onclick='editContact(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)' title="Edit"><i class="fa-solid fa-pen"></i></button>
                  <form method="POST" action="/client/actions/delete-contact.php" style="display:inline"><input type="hidden" name="id" value="<?=$c['id']?>"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><button class="btn btn-danger btn-sm btn-icon" onclick="return confirm('Delete contact?')"><i class="fa-solid fa-trash"></i></button></form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($totalPages>1): ?>
      <div class="card-footer"><div class="pagination"><?php for($p=1;$p<=$totalPages;$p++): ?><a href="?page=<?=$p?>&group=<?=$groupId?>&q=<?=urlencode($search)?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a><?php endfor; ?></div></div>
    <?php endif; ?>
  </div>
</div>

<!-- Modals -->
<div class="modal-overlay" id="addContactModal">
  <div class="modal"><div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-user-plus" style="color:var(--primary)"></i> Add Contact</h3><button class="modal-close" onclick="closeModal('addContactModal')">×</button></div>
    <form method="POST" action="/client/actions/add-contact.php"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Phone <span class="required">*</span></label><input type="text" name="phone" class="form-control" placeholder="+254712345678" required></div>
        <div class="form-group"><label class="form-label">Name</label><input type="text" name="name" class="form-control"></div>
        <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
        <div class="form-group"><label class="form-label">Group</label><select name="group_id" class="form-control"><option value="">No Group</option><?php foreach ($groups as $g): ?><option value="<?=$g['id']?>"><?=htmlspecialchars($g['name'])?></option><?php endforeach; ?></select></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addContactModal')">Cancel</button><button type="submit" class="btn btn-primary">Add</button></div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="editContactModal">
  <div class="modal"><div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-user-pen" style="color:var(--primary)"></i> Edit Contact</h3><button class="modal-close" onclick="closeModal('editContactModal')">×</button></div>
    <form method="POST" action="/client/actions/edit-contact.php"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="id" id="editContactId">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Phone <span class="required">*</span></label><input type="text" name="phone" id="editContactPhone" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Name</label><input type="text" name="name" id="editContactName" class="form-control"></div>
        <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" id="editContactEmail" class="form-control"></div>
        <div class="form-group"><label class="form-label">Group</label><select name="group_id" id="editContactGroup" class="form-control"><option value="">No Group</option><?php foreach ($groups as $g): ?><option value="<?=$g['id']?>"><?=htmlspecialchars($g['name'])?></option><?php endforeach; ?></select></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('editContactModal')">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="addGroupModal">
  <div class="modal"><div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-layer-group" style="color:var(--primary)"></i> New Group</h3><button class="modal-close" onclick="closeModal('addGroupModal')">×</button></div>
    <form method="POST" action="/client/actions/create-group.php"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <div class="modal-body"><div class="form-group"><label class="form-label">Group Name <span class="required">*</span></label><input type="text" name="name" class="form-control" required></div></div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addGroupModal')">Cancel</button><button type="submit" class="btn btn-primary">Create</button></div>
    </form>
  </div>
</div>

<script>
function editContact(contact) {
  document.getElementById('editContactId').value = contact.id;
  document.getElementById('editContactPhone').value = contact.phone;
  document.getElementById('editContactName').value = contact.name || '';
  document.getElementById('editContactEmail').value = contact.email || '';
  document.getElementById('editContactGroup').value = contact.group_id || '';
  openModal('editContactModal');
}
</script>
<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
