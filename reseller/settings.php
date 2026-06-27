<?php
/**
 * Reseller Settings Page - Shanfix Technology
 * Allows resellers to manage their profile and account settings.
 */
$pageTitle = 'Account Settings';
$breadcrumb = [['label'=>'Reseller'],['label'=>'Settings']];
require_once __DIR__ . '/layout.php';

// $user is already set by layout.php via current_user()

$activeTab = sanitize($_GET['tab'] ?? 'profile');
?>

<div class="page-header">
    <div>
        <h1>Account Settings</h1>
        <div class="subtitle">Manage your profile and account preferences</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:250px 1fr;gap:24px;align-items:start">
    <!-- Tabs sidebar -->
    <div class="card">
        <div style="padding:8px 0">
            <a href="?tab=profile" class="nav-link <?= $activeTab === 'profile' ? 'active' : '' ?>" style="color:var(--text-primary)">
                <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                <span class="nav-text">My Profile</span>
            </a>
            <a href="?tab=security" class="nav-link <?= $activeTab === 'security' ? 'active' : '' ?>" style="color:var(--text-primary)">
                <span class="nav-icon"><i class="fa-solid fa-shield-halved"></i></span>
                <span class="nav-text">Security</span>
            </a>
        </div>
    </div>

    <!-- Settings form -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid <?= $activeTab === 'profile' ? 'fa-user' : 'fa-shield-halved' ?>" style="color:var(--primary)"></i> 
                <?= ucfirst($activeTab) ?> Settings
            </h3>
        </div>
        
        <form method="POST" action="actions/save-settings.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="tab" value="<?= $activeTab ?>">
            
            <div class="card-body">
                <?php if ($activeTab === 'profile'): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="alert alert-info">
                        <i class="fa-solid fa-circle-info"></i> Your role is <strong><?= ucfirst($user['role']) ?></strong>. 
                        Your current balance is <strong><?= number_format($user['sms_units'], 2) ?> units</strong>.
                    </div>
                <?php elseif ($activeTab === 'security'): ?>
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" placeholder="Required to change password">
                    </div>
                    <hr style="margin:20px 0; border-color:var(--border)">
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password">
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="card-footer" style="padding:16px 20px">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
