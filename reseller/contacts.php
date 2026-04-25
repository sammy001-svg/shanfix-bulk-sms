<?php
$pageTitle = 'Contacts';
$breadcrumb = [['label'=>'Reseller'],['label'=>'Contacts']];
require_once __DIR__ . '/layout.php';

$uid     = $user['id'];
$groups  = DB::query("SELECT g.*, COUNT(c.id) as count FROM contact_groups g LEFT JOIN contacts c ON c.group_id = g.id WHERE g.user_id=? GROUP BY g.id ORDER BY g.name", [$uid]);
$page    = max(1,(int)($_GET['page']??1));
$perPage = 20;
$offset  = ($page-1)*$perPage;
$grpId   = (int)($_GET['group']??0);
$search  = sanitize($_GET['q']??'');
$where   = ['c.user_id=?'];
$params  = [$uid];
if ($grpId){$where[]='c.group_id=?';$params[]=$grpId;}
if ($search){$where[]='(c.name LIKE ? OR c.phone LIKE ?)';$params[]="%$search%";$params[]="%$search%";}
$w = 'WHERE '.implode(' AND ',$where);
$total   = DB::queryOne("SELECT COUNT(*) as c FROM contacts c $w",$params)['c']??0;
$contacts = DB::query("SELECT c.*,g.name as group_name FROM contacts c LEFT JOIN contact_groups g ON c.group_id=g.id $w ORDER BY c.name ASC LIMIT $perPage OFFSET $offset",$params);
$totalPages = ceil($total/$perPage);
?>

<div class="page-header">
  <div>
    <h1>Contacts & Groups</h1>
    <div class="subtitle">Manage your contact list and groups</div>
  </div>
  <div class="btn-group">
    <button class="btn btn-secondary" onclick="openModal('groupModal')"><i class="fa-solid fa-layer-group"></i> New Group</button>
    <button class="btn btn-secondary" onclick="openModal('importModal')"><i class="fa-solid fa-upload"></i> Import CSV</button>
    <button class="btn btn-primary" onclick="openModal('contactModal')"><i class="fa-solid fa-plus"></i> Add Contact</button>
  </div>
</div>

<div style="display:grid;grid-template-columns:220px 1fr;gap:20px">
  <!-- Groups sidebar -->
  <div>
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-layer-group"></i> Groups</h3></div>
      <div style="padding:8px 0">
        <a href="/reseller/contacts.php" class="nav-link <?= !$grpId?'active':'' ?>" style="color:var(--text-primary)">
          <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
          <span class="nav-text">All Contacts</span>
          <span class="nav-badge"><?= $total ?></span>
        </a>
        <?php foreach ($groups as $g): ?>
          <a href="?group=<?=$g['id']?>" class="nav-link <?= $grpId==(int)$g['id']?'active':'' ?>" style="color:var(--text-primary)">
            <span class="nav-icon"><i class="fa-solid fa-circle-dot" style="font-size:10px;color:var(--primary)"></i></span>
            <span class="nav-text"><?= htmlspecialchars($g['name']) ?></span>
            <span class="nav-badge"><?= $g['count'] ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Contacts table -->
  <div>
    <div class="card">
      <div class="card-header" style="gap:12px">
        <form method="GET" style="display:flex;gap:10px;flex:1">
          <?php if ($grpId): ?><input type="hidden" name="group" value="<?=$grpId?>"><?php endif; ?>
          <div class="input-group" style="flex:1">
            <div class="input-group-text input-addon-left"><i class="fa-solid fa-magnifying-glass"></i></div>
            <input type="text" name="q" class="form-control with-left" placeholder="Search name or phone..." value="<?= htmlspecialchars($search) ?>">
          </div>
          <button class="btn btn-primary"><i class="fa-solid fa-search"></i></button>
        </form>
        <a href="?export=1<?=$grpId?"&group=$grpId":''?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-download"></i> Export</a>
      </div>
      <div class="table-wrapper">
        <table class="data-table">
          <thead><tr><th><input type="checkbox" id="checkAll"></th><th>Name</th><th>Phone</th><th>Email</th><th>Group</th><th>Added</th><th></th></tr></thead>
          <tbody>
            <?php if (empty($contacts)): ?>
              <tr><td colspan="7">
                <div class="empty-state">
                  <div class="empty-icon">👥</div>
                  <h3>No contacts found</h3>
                  <p>Add contacts manually or import a CSV file.</p>
                  <div class="btn-group" style="justify-content:center">
                    <button class="btn btn-secondary" onclick="openModal('importModal')">Import CSV</button>
                    <button class="btn btn-primary" onclick="openModal('contactModal')">Add Contact</button>
                  </div>
                </div>
              </td></tr>
            <?php else: ?>
              <?php foreach ($contacts as $c): ?>
                <tr>
                  <td><input type="checkbox" name="ids[]" value="<?=$c['id']?>"></td>
                  <td><strong><?= htmlspecialchars($c['name'] ?? '—') ?></strong></td>
                  <td><?= htmlspecialchars($c['phone']) ?></td>
                  <td style="color:var(--text-secondary)"><?= htmlspecialchars($c['email'] ?? '—') ?></td>
                  <td><?= $c['group_name'] ? '<span class="badge badge-info">'.htmlspecialchars($c['group_name']).'</span>' : '<span class="text-muted">—</span>' ?></td>
                  <td style="font-size:12px"><?= date('d M Y',strtotime($c['created_at'])) ?></td>
                  <td>
                    <form method="POST" action="/reseller/actions/delete-contact.php" style="display:inline">
                      <input type="hidden" name="id" value="<?=$c['id']?>">
                      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
                      <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Delete" onclick="return confirm('Delete this contact?')"><i class="fa-solid fa-trash"></i></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($totalPages > 1): ?>
        <div class="card-footer">
          <div class="pagination">
            <?php for ($p=1;$p<=$totalPages;$p++): ?>
              <a href="?page=<?=$p?>&group=<?=$grpId?>&q=<?=urlencode($search)?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a>
            <?php endfor; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Add Contact Modal -->
<div class="modal-overlay" id="contactModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-user-plus" style="color:var(--primary)"></i> Add Contact</h3>
      <button class="modal-close" onclick="closeModal('contactModal')">×</button>
    </div>
    <form method="POST" action="/reseller/actions/save-contact.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Name</label><input type="text" name="name" class="form-control" placeholder="John Doe"></div>
          <div class="form-group"><label class="form-label">Phone <span class="required">*</span></label><input type="text" name="phone" class="form-control" placeholder="+254712345678" required></div>
        </div>
        <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" placeholder="john@example.com"></div>
        <div class="form-group">
          <label class="form-label">Group</label>
          <select name="group_id" class="form-control">
            <option value="">No Group</option>
            <?php foreach ($groups as $g): ?>
              <option value="<?=$g['id']?>"><?=htmlspecialchars($g['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('contactModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Contact</button>
      </div>
    </form>
  </div>
</div>

<!-- New Group Modal -->
<div class="modal-overlay" id="groupModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-layer-group" style="color:var(--primary)"></i> New Group</h3>
      <button class="modal-close" onclick="closeModal('groupModal')">×</button>
    </div>
    <form method="POST" action="/reseller/actions/save-group.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Group Name <span class="required">*</span></label><input type="text" name="name" class="form-control" placeholder="e.g. VIP Customers" required></div>
        <div class="form-group"><label class="form-label">Description</label><input type="text" name="description" class="form-control" placeholder="Optional description"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('groupModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Group</button>
      </div>
    </form>
  </div>
</div>

<!-- Import CSV Modal -->
<div class="modal-overlay" id="importModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-upload" style="color:var(--primary)"></i> Import Contacts (CSV)</h3>
      <button class="modal-close" onclick="closeModal('importModal')">×</button>
    </div>
    <form method="POST" action="/reseller/actions/import-contacts.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="modal-body">
        <div class="alert alert-info"><i class="fa-solid fa-info-circle"></i> CSV must have columns: <code>name, phone, email</code> (header row required)</div>
        <div class="form-group">
          <label class="form-label">CSV File <span class="required">*</span></label>
          <div class="upload-zone" onclick="document.getElementById('csvImport').click()" id="csvZone">
            <div class="upload-icon">📄</div>
            <p>Click to choose a <strong>CSV file</strong></p>
          </div>
          <input type="file" id="csvImport" name="csv_file" accept=".csv" style="display:none" onchange="document.getElementById('csvZone').querySelector('p').textContent='File: '+this.files[0].name">
        </div>
        <div class="form-group">
          <label class="form-label">Assign to Group (optional)</label>
          <select name="group_id" class="form-control">
            <option value="">No Group</option>
            <?php foreach ($groups as $g): ?><option value="<?=$g['id']?>"><?=htmlspecialchars($g['name'])?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('importModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i> Import</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('checkAll')?.addEventListener('change', function(){
  document.querySelectorAll('input[name="ids[]"]').forEach(cb=>cb.checked=this.checked);
});
</script>
<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
