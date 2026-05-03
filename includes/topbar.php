<?php
/**
 * Top Bar Partial
 * Expects: $pageTitle (string), $breadcrumb (array of ['label'=>'', 'url'=>''])
 */
$user = current_user();
$initials = implode('', array_map(fn($w) => strtoupper($w[0]),
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
    <div class="dropdown" id="notifDropdown">
      <div class="icon-btn" onclick="toggleDropdown('notifDropdown')" title="Notifications">
        <i class="fa-regular fa-bell"></i>
        <?php if ($notifCount > 0): ?>
          <span class="badge"><?= $notifCount ?></span>
        <?php endif; ?>
      </div>
      <div class="dropdown-menu">
        <div style="padding:12px 14px;border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center">
          <strong style="font-size:13px">Notifications</strong>
          <?php if ($notifCount > 0): ?>
            <span style="font-size:10px; color:var(--text-secondary)"><?= $notifCount ?> Unread</span>
          <?php endif; ?>
        </div>
        <div style="max-height: 300px; overflow-y: auto;">
          <?php if (empty($notifs)): ?>
            <div class="dropdown-item" style="justify-content:center; color:var(--text-secondary); font-size:12px; padding:20px 0">
              No new notifications
            </div>
          <?php else: ?>
            <?php foreach ($notifs as $n): ?>
              <?php 
                $icon = match($n['type']) {
                  'success' => 'fa-check-circle',
                  'warning' => 'fa-exclamation-triangle',
                  'danger'  => 'fa-times-circle',
                  default   => 'fa-info-circle'
                };
                $color = "var(--{$n['type']})";
              ?>
              <div class="dropdown-item" style="flex-direction:column; align-items:start; padding:10px 14px">
                <div style="display:flex; align-items:start; gap:10px; width:100%">
                  <i class="fa-solid <?= $icon ?>" style="color:<?= $color ?>; margin-top:3px"></i>
                  <div style="flex:1">
                    <div style="font-weight:600; font-size:12.5px"><?= htmlspecialchars($n['title']) ?></div>
                    <div style="font-size:11.5px; color:var(--text-secondary); line-height:1.4; margin-top:2px">
                      <?= htmlspecialchars($n['message']) ?>
                    </div>
                    <div style="font-size:10px; color:var(--text-muted); margin-top:5px">
                      <?= date('M j, H:i', strtotime($n['created_at'])) ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div class="dropdown-divider"></div>
        <a href="#" class="dropdown-item text-primary-color" style="justify-content:center;font-size:12px" onclick="markAllAsRead()">Mark all as read</a>
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
        <button class="modal-close" onclick="closeModal('notif-modal-<?= $n['id'] ?>')" style="position:absolute; top:15px; right:15px; background:none; border:none; cursor:pointer; font-size:18px; color:var(--text-muted)">
          <i class="fa-solid fa-xmark"></i>
        </button>
        
        <?php if ($n['image_url']): ?>
          <img src="<?= htmlspecialchars($n['image_url']) ?>" style="width:100%; border-radius:12px; margin-bottom:15px; max-height:250px; object-fit:cover">
        <?php endif; ?>

        <div style="margin-bottom:15px">
          <?php 
            $pIcon = match($n['type']) {
              'success' => 'fa-circle-check',
              'warning' => 'fa-triangle-exclamation',
              'danger'  => 'fa-circle-xmark',
              default   => 'fa-circle-info'
            };
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

<script>
function dismissNotif(id) {
    fetch('/includes/actions/notifications.php?action=dismiss&id=' + id)
        .then(() => {
            closeModal('notif-modal-' + id);
        });
}

function markAllAsRead() {
    fetch('/includes/actions/notifications.php?action=read_all')
        .then(() => location.reload());
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
