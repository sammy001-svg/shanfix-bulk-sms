<?php
/**
 * Top Bar Partial
 * Expects: $pageTitle (string), $breadcrumb (array of ['label'=>'', 'url'=>''])
 */
$user = current_user();
$initials = implode('', array_map(function($w) { return strtoupper($w[0]); },
    array_slice(explode(' ', $user['name'] ?? 'U'), 0, 2)
));
?>
<header class="topbar">
  <div class="topbar-left">
    <!-- Mobile hamburger -->
    <button class="icon-btn" id="mobileMenuBtn" style="display:none" title="Menu">
      <i class="fa-solid fa-bars"></i>
    </button>

    <div>
      <div class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
      <?php if (!empty($breadcrumb)): ?>
        <div class="breadcrumb">
          <?php foreach ($breadcrumb as $i => $crumb): ?>
            <?php if ($i > 0): ?><span>›</span><?php endif; ?>
            <?php if (!empty($crumb['url'])): ?>
              <a href="<?= $crumb['url'] ?>"><?= htmlspecialchars($crumb['label']) ?></a>
            <?php else: ?>
              <?= htmlspecialchars($crumb['label']) ?>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="topbar-right">
    <div class="topbar-search">
      <span class="search-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
      <input type="text" placeholder="Search..." id="globalSearch">
    </div>

    <!-- Theme Toggle -->
    <div class="icon-btn" id="themeToggle" title="Toggle Theme">
      <i class="fa-solid fa-moon"></i>
    </div>

    <!-- Notifications -->
    <?php 
    $notifs = get_unread_notifications($user['id']); 
    $notifCount = count($notifs);

    // Dashboard-specific persistent popups
    $isDashboard = (basename($_SERVER['PHP_SELF']) === 'index.php');
    $popupNotifs = $isDashboard ? get_dashboard_popups($user['id']) : [];
    ?>
    <div class="notifications-wrapper">
      <div class="icon-btn" onclick="openModal('notificationModal')" title="Notifications">
        <i class="fa-regular fa-bell"></i>
        <?php if ($notifCount > 0): ?>
          <span class="badge" id="notifBadge"><?= $notifCount ?></span>
        <?php endif; ?>
      </div>
    </div>

    <!-- User Menu -->
    <div class="dropdown" id="userDropdown">
      <div class="topbar-avatar" onclick="toggleDropdown('userDropdown')" title="Account">
        <?= $initials ?>
      </div>
      <div class="dropdown-menu">
        <div style="padding:12px 14px;border-bottom:1px solid var(--border)">
          <div style="font-weight:700;font-size:13.5px"><?= htmlspecialchars($user['name'] ?? '') ?></div>
          <div style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($user['email'] ?? '') ?></div>
        </div>
        <a class="dropdown-item" href="/<?= $user['role'] ?>/profile.php">
          <i class="fa-regular fa-user"></i> My Profile
        </a>
        <a class="dropdown-item" href="/<?= $user['role'] ?>/settings.php">
          <i class="fa-solid fa-gear"></i> Settings
        </a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item danger" href="/logout.php">
          <i class="fa-solid fa-right-from-bracket"></i> Sign Out
        </a>
      </div>
    </div>
  </div>
</header>

<!-- Popup Notification Modals (Dashboard Only) -->
<?php if ($isDashboard): ?>
  <?php foreach ($popupNotifs as $n): ?>
    <div class="modal-overlay" id="notif-modal-<?= $n['id'] ?>">
      <div class="modal" style="max-width:500px; text-align:center; position:relative">
        <button type="button" class="modal-close" onclick="closeModal('notif-modal-<?= $n['id'] ?>')" style="position:absolute; top:15px; right:15px; background:none; border:none; cursor:pointer; font-size:20px; color:var(--text-muted); z-index:10">
          <i class="fa-solid fa-xmark" style="pointer-events:none"></i>
        </button>
        
        <?php if ($n['image_url']): ?>
          <img src="<?= htmlspecialchars($n['image_url']) ?>" style="width:100%; border-radius:12px; margin-bottom:15px; max-height:250px; object-fit:cover">
        <?php endif; ?>

        <div style="margin-bottom:15px">
          <?php 
            switch ($n['type']) {
              case 'success': $pIcon = 'fa-circle-check'; break;
              case 'warning': $pIcon = 'fa-triangle-exclamation'; break;
              case 'danger':  $pIcon = 'fa-circle-xmark'; break;
              default:        $pIcon = 'fa-circle-info'; break;
            }
          ?>
          <i class="fa-solid <?= $pIcon ?>" style="font-size:48px; color:var(--<?= $n['type'] ?>); margin-bottom:15px"></i>
          <h2 style="margin-bottom:10px"><?= htmlspecialchars($n['title']) ?></h2>
          <p style="color:var(--text-secondary); line-height:1.6"><?= nl2br(htmlspecialchars($n['message'])) ?></p>
        </div>
        
        <button class="btn btn-<?= $n['type'] ?>" style="width:100%" onclick="dismissNotif(<?= $n['id'] ?>)">
          Got it
        </button>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<!-- Notifications Modal -->
<div class="modal-overlay" id="notificationModal">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-bell" style="color:var(--primary)"></i> Notifications</h3>
      <button type="button" class="modal-close" onclick="closeModal('notificationModal')">
        <i class="fa-solid fa-xmark" style="pointer-events:none"></i>
      </button>
    </div>
    <div class="modal-body" style="max-height:60vh; overflow-y:auto; padding:0">
      <?php if (empty($notifs)): ?>
        <div style="text-align:center; padding:40px 20px; color:var(--text-muted)">
          <i class="fa-regular fa-bell-slash" style="font-size:48px; margin-bottom:15px; opacity:0.3"></i>
          <p>You have no unread notifications.</p>
        </div>
      <?php else: ?>
        <div id="notifList">
          <?php foreach ($notifs as $n): ?>
            <?php 
              switch ($n['type']) {
                case 'success': $icon = 'fa-circle-check'; break;
                case 'warning': $icon = 'fa-triangle-exclamation'; break;
                case 'danger':  $icon = 'fa-circle-xmark'; break;
                default:        $icon = 'fa-circle-info'; break;
              }
              $color = "var(--{$n['type']})";
            ?>
            <div style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; gap:15px">
              <i class="fa-solid <?= $icon ?>" style="font-size:20px; color:<?= $color ?>; margin-top:3px"></i>
              <div style="flex:1">
                <div style="font-weight:700; font-size:14px; margin-bottom:4px"><?= htmlspecialchars($n['title']) ?></div>
                <div style="font-size:13px; color:var(--text-secondary); line-height:1.5"><?= nl2br(htmlspecialchars($n['message'])) ?></div>
                <div style="font-size:11px; color:var(--text-muted); margin-top:8px"><?= date('M j, Y — H:i', strtotime($n['created_at'])) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="modal-footer" style="padding:15px 20px; display:flex; justify-content:flex-end">
      <?php if (!empty($notifs)): ?>
        <button class="btn btn-primary" id="markAllBtn" onclick="markAllAsRead()">
          <i class="fa-solid fa-check-double"></i> Mark all as read
        </button>
      <?php else: ?>
        <button class="btn btn-muted" onclick="closeModal('notificationModal')">Close</button>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function dismissNotif(id) {
    fetch('/includes/actions/notifications.php?action=dismiss&id=' + id)
        .then(() => {
            closeModal('notif-modal-' + id);
        });
}

function markAllAsRead() {
    const btn = document.getElementById('markAllBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
    }

    fetch('/includes/actions/notifications.php?action=read_all')
        .then(response => {
            // Hide the badge instantly
            const badge = document.getElementById('notifBadge');
            if (badge) badge.style.display = 'none';

            // Show success in modal
            const notifList = document.getElementById('notifList');
            if (notifList) {
                notifList.innerHTML = `
                    <div style="text-align:center; padding:40px 20px; color:var(--text-muted)">
                        <i class="fa-regular fa-bell-slash" style="font-size:48px; margin-bottom:15px; opacity:0.3"></i>
                        <p>All notifications marked as read.</p>
                    </div>
                `;
            }
            
            if (btn) {
                btn.style.display = 'none';
            }

            // Optional: Close modal after a delay
            setTimeout(() => {
                closeModal('notificationModal');
            }, 1500);
        })
        .catch(err => {
            console.error('Error marking notifications as read:', err);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-check-double"></i> Mark all as read';
            }
        });
}

// Auto-show popup modals on load
document.addEventListener('DOMContentLoaded', () => {
    <?php if ($isDashboard): ?>
        <?php foreach ($popupNotifs as $n): ?>
            setTimeout(() => {
                openModal('notif-modal-<?= $n['id'] ?>');
            }, 300);
        <?php endforeach; ?>
    <?php endif; ?>
});
</script>
