<?php
/**
 * Admin: Notification Management - Shanfix Technology
 */
$pageTitle = 'Broadcast Notifications';
$breadcrumb = [['label'=>'Admin'], ['label'=>'Notifications']];
require_once __DIR__ . '/layout.php';

// Fetch all active users for the dropdown
$users = DB::query("SELECT id, name, email, role FROM users WHERE status = 'active' ORDER BY name ASC");
?>

<div class="page-header">
    <div>
        <h1>System Notifications</h1>
        <div class="subtitle">Create alerts, popups, and broadcasts for your users.</div>
    </div>
    <button class="btn btn-primary" onclick="openModal('newNotifModal')">
        <i class="fa-solid fa-plus"></i> Create Notification
    </button>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-bell" style="color:var(--primary)"></i> Recent Notifications</h3>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Title & Message</th>
                    <th>Target Audience</th>
                    <th>Alert Type</th>
                    <th>Popup</th>
                    <th>Date Sent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $recent = DB::query("SELECT n.*, u.name as user_name FROM notifications n LEFT JOIN users u ON n.user_id = u.id ORDER BY n.created_at DESC LIMIT 20");
                foreach ($recent as $n): 
                ?>
                <tr>
                    <td>
                        <div style="font-weight:700; color:var(--text-primary)"><?= htmlspecialchars($n['title']) ?></div>
                        <div style="font-size:12px; color:var(--text-secondary); max-width:350px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">
                            <?= htmlspecialchars($n['message']) ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($n['user_id']): ?>
                            <span class="badge badge-muted">
                                <i class="fa-solid fa-user"></i> <?= htmlspecialchars($n['user_name']) ?>
                            </span>
                        <?php else: ?>
                            <span class="badge badge-success">
                                <i class="fa-solid fa-globe"></i> Broadcast (All)
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= $n['type'] ?>">
                            <?= strtoupper($n['type']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if($n['is_popup']): ?>
                            <span class="badge badge-purple"><i class="fa-solid fa-window-maximize"></i> Popup</span>
                        <?php else: ?>
                            <span class="text-muted" style="font-size:12px">Header Only</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px; color:var(--text-secondary)">
                        <?= date('d M Y, H:i', strtotime($n['created_at'])) ?>
                    </td>
                    <td>
                        <button class="btn btn-danger btn-sm btn-icon" onclick="deleteNotif(<?= $n['id'] ?>)" title="Delete">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted" style="padding:40px">No notifications sent yet.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New Notification Modal -->
<div class="modal-overlay" id="newNotifModal">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-plus-circle" style="color:var(--primary)"></i> Create New Notification</h3>
            <button class="modal-close" onclick="closeModal('newNotifModal')">&times;</button>
        </div>
        <form action="actions/create-notification.php" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group" style="flex:2">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. System Maintenance" required>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label class="form-label">Alert Type</label>
                        <select name="type" class="form-control">
                            <option value="info">Info (Blue)</option>
                            <option value="success">Success (Green)</option>
                            <option value="warning">Warning (Yellow)</option>
                            <option value="danger">Danger (Red)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea name="message" class="form-control" rows="4" placeholder="Enter your message here..." required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:1">
                        <label class="form-label">Target Audience</label>
                        <select name="user_id" class="form-control">
                            <option value="">All Users (Broadcast)</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= $u['role'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label class="form-label">Display Mode</label>
                        <div style="display:flex; gap:15px; align-items:center; height:42px">
                            <label style="display:flex; align-items:center; gap:5px; cursor:pointer">
                                <input type="checkbox" name="is_popup" value="1"> Popup Modal
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Image (Optional)</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <div class="subtitle" style="font-size:11px; margin-top:5px">Recommended for popups. Supports JPG, PNG.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('newNotifModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Send Notification</button>
            </div>
        </form>
    </div>
</div>

<script>
function deleteNotif(id) {
    if (confirm('Are you sure you want to delete this notification?')) {
        window.location.href = 'actions/create-notification.php?action=delete&id=' + id;
    }
}
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
