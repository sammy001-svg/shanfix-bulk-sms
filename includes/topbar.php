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
    <div class="dropdown" id="notifDropdown">
      <div class="icon-btn" onclick="toggleDropdown('notifDropdown')" title="Notifications">
        <i class="fa-regular fa-bell"></i>
        <span class="badge">3</span>
      </div>
      <div class="dropdown-menu">
        <div style="padding:12px 14px;border-bottom:1px solid var(--border)">
          <strong style="font-size:13px">Notifications</strong>
        </div>
        <div class="dropdown-item"><i class="fa-solid fa-check-circle" style="color:var(--success)"></i> Campaign "Promo April" completed</div>
        <div class="dropdown-item"><i class="fa-solid fa-id-badge" style="color:var(--info)"></i> Sender ID "BRANDCO" approved</div>
        <div class="dropdown-item"><i class="fa-solid fa-coins" style="color:var(--warning)"></i> Units purchased: 1,000</div>
        <div class="dropdown-divider"></div>
        <div class="dropdown-item text-primary-color" style="justify-content:center;font-size:12px">View all</div>
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
