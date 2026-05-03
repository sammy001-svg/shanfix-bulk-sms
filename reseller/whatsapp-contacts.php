<?php
$pageTitle = 'WhatsApp Contacts';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Contacts & Groups']];
require_once __DIR__ . '/layout.php';

$uid     = $user['id'];
$groupId = (int)($_GET['group']??0);
$search  = sanitize($_GET['q']??'');
$page    = max(1,(int)($_GET['page']??1));
$perPage = 20; $offset=($page-1)*$perPage;

$groups  = DB::query("SELECT g.*, COUNT(c.id) as cnt FROM whatsapp_contact_groups g LEFT JOIN whatsapp_contacts c ON c.group_id=g.id WHERE g.user_id=? GROUP BY g.id ORDER BY g.name", [$uid]);

$where='WHERE c.user_id=?'; $params=[$uid];
if ($groupId) { $where.=' AND c.group_id=?'; $params[]=$groupId; }
if ($search)  { $where.=' AND (c.phone LIKE ? OR c.name LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; }

$total    = DB::queryOne("SELECT COUNT(*) as c FROM whatsapp_contacts c $where", $params)['c']??0;
$contacts = DB::query("SELECT c.*, g.name as group_name FROM whatsapp_contacts c LEFT JOIN whatsapp_contact_groups g ON g.id=c.group_id $where ORDER BY c.created_at DESC LIMIT $perPage OFFSET $offset", $params);
$totalPages = ceil($total/$perPage);
?>

<div class="page-header">
  <div><h1>WhatsApp Contacts</h1><div class="subtitle">Manage your WhatsApp-specific lists</div></div>
  <div class="btn-group">
    <button class="btn btn-secondary" onclick="openModal('importModal')"><i class="fa-solid fa-upload"></i> Import</button>
    <button class="btn btn-primary" onclick="openModal('addContactModal')"><i class="fa-solid fa-plus"></i> Add Contact</button>
  </div>
</div>

<div style="display:grid; grid-template-columns: 240px 1fr; gap:24px;">
  <div class="card">
    <div class="card-header">Groups</div>
    <div class="card-body p-0">
      <a href="whatsapp-contacts.php" class="nav-link <?= $groupId === 0 ? 'active' : '' ?>">All Contacts (<?= $total ?>)</a>
      <?php foreach ($groups as $g): ?>
        <a href="?group=<?= $g['id'] ?>" class="nav-link <?= $groupId === $g['id'] ? 'active' : '' ?>"><?= htmlspecialchars($g['name']) ?> (<?= $g['cnt'] ?>)</a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Contacts List</div>
    <div class="table-responsive">
      <table class="table">
        <thead><tr><th>Phone</th><th>Name</th><th>Group</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($contacts as $c): ?>
            <tr>
              <td><strong><?= htmlspecialchars($c['phone']) ?></strong></td>
              <td><?= htmlspecialchars($c['name']??'—') ?></td>
              <td><?= $c['group_name'] ? '<span class="badge">'.htmlspecialchars($c['group_name']).'</span>' : '—' ?></td>
              <td><button class="btn btn-icon btn-sm"><i class="fa-solid fa-edit"></i></button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
