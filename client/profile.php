<?php
$pageTitle = 'My Profile';
$breadcrumb = [['label'=>'Client'],['label'=>'Profile']];
require_once __DIR__ . '/layout.php';

$u = current_user();
?>

<div class="page-header">
  <div><h1>My Profile</h1><div class="subtitle">Manage your personal information</div></div>
</div>

<div class="card" style="max-width:800px">
  <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-user-circle" style="color:var(--primary)"></i> Personal Details</h3></div>
  <form method="POST" action="/client/actions/update-profile.php">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <div class="card-body">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($u['name']??'') ?>" required></div>
        <div class="form-group"><label class="form-label">Email Address <span class="required">*</span></label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']??'') ?>" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Phone Number</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($u['phone']??'') ?>" placeholder="+254..."></div>
        <div class="form-group"><label class="form-label">Company Name</label><input type="text" name="company" class="form-control" value="<?= htmlspecialchars($u['company']??'') ?>"></div>
      </div>
      <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">
        <h4 style="margin-bottom:12px"><i class="fa-solid fa-lock" style="font-size:14px"></i> Account Security</h4>
        <div class="form-group"><label class="form-label">Current Password <span class="required">*</span></label><input type="password" name="current_password" class="form-control" placeholder="Required to save changes" required></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current"></div>
          <div class="form-group"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" placeholder=""></div>
        </div>
      </div>
    </div>
    <div class="card-footer"><button type="submit" class="btn btn-primary">Update Profile</button></div>
  </form>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
