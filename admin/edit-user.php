<?php
$pageTitle = 'Edit User';
$breadcrumb = [['label'=>'Admin'],['label'=>'Users', 'url'=>'/admin/resellers.php'],['label'=>'Edit User']];
require_once __DIR__ . '/layout.php';

$id = (int)($_GET['id'] ?? 0);
$u  = DB::queryOne("SELECT * FROM users WHERE id=?", [$id]);

if (!$u) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'User not found.'];
    redirect('/admin/resellers.php');
}

// Get resellers for parent assignment
$resellers = DB::query("SELECT id,name FROM users WHERE role='reseller' AND status='active' AND id != ? ORDER BY name", [$id]);
?>

<div class="page-header">
  <div><h1>Edit User: <?= htmlspecialchars($u['name']) ?></h1><div class="subtitle">Update account details and preferences</div></div>
  <a href="/admin/resellers.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Users</a>
</div>

<div class="card" style="max-width:800px;margin:0 auto">
  <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-user-edit" style="color:var(--primary)"></i> User Profile</h3></div>
  <form method="POST" action="/admin/actions/edit-user.php">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="id" value="<?= $u['id'] ?>">
    
    <div class="card-body">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($u['name']) ?>" required></div>
        <div class="form-group"><label class="form-label">Email Address <span class="required">*</span></label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required></div>
      </div>

      <div class="form-row">
        <div class="form-group"><label class="form-label">Phone Number</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($u['phone'] ?? '') ?>" placeholder="+254..."></div>
        <div class="form-group"><label class="form-label">Company Name</label><input type="text" name="company" class="form-control" value="<?= htmlspecialchars($u['company'] ?? '') ?>"></div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Account Role <span class="required">*</span></label>
          <select name="role" class="form-control" required>
            <option value="client" <?= $u['role']==='client'?'selected':'' ?>>Client</option>
            <option value="reseller" <?= $u['role']==='reseller'?'selected':'' ?>>Reseller</option>
            <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Administrator</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Account Status <span class="required">*</span></label>
          <select name="status" class="form-control" required>
            <option value="active" <?= $u['status']==='active'?'selected':'' ?>>Active</option>
            <option value="suspended" <?= $u['status']==='suspended'?'selected':'' ?>>Suspended</option>
            <option value="pending" <?= $u['status']==='pending'?'selected':'' ?>>Pending Approval</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Parent Account (Reseller)</label>
        <select name="parent_id" class="form-control">
          <option value="">None / Direct</option>
          <?php foreach ($resellers as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $u['parent_id']==$r['id']?'selected':'' ?>><?= htmlspecialchars($r['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">Clients assigned to a reseller will consume that reseller's units.</div>
      </div>

      <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">
        <h4 style="margin-bottom:12px"><i class="fa-solid fa-lock" style="font-size:14px;margin-right:6px"></i> Security & Billing Control</h4>
        <div class="form-row">
          <div class="form-group"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" placeholder="Leave blank to keep current"></div>
        </div>
        
        <div class="form-row mt-12">
          <div class="form-group">
            <label class="form-label">SMS Units Balance</label>
            <input type="number" name="sms_units" class="form-control" step="0.01" value="<?= (float)$u['sms_units'] ?>">
          </div>
          <div class="form-group">
            <label class="form-label">SMS Rate (Per Unit)</label>
            <input type="number" name="custom_unit_price" class="form-control" step="0.0001" value="<?= $u['custom_unit_price'] ? (float)$u['custom_unit_price'] : '' ?>" placeholder="Default: 0.80">
          </div>
        </div>

        <div class="form-row mt-12">
          <div class="form-group">
            <label class="form-label">WhatsApp Balance (KES)</label>
            <input type="number" name="whatsapp_balance" class="form-control" step="0.01" value="<?= (float)$u['whatsapp_balance'] ?>">
          </div>
          <div class="form-group">
            <label class="form-label">WhatsApp Rate (Buying/Selling)</label>
            <input type="number" name="whatsapp_rate" class="form-control" step="0.01" value="<?= (float)$u['whatsapp_rate'] ?>">
            <div class="form-hint">Admin sets this for Resellers. Resellers set this for Clients.</div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="card-footer" style="display:flex;justify-content:flex-end;gap:12px">
      <a href="/admin/resellers.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
