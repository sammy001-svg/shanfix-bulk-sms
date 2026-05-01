<?php
/**
 * Sidebar Layout Generator
 * Usage: include 'includes/sidebar.php' inside any panel layout
 * Expects $navItems array and $user from auth
 */
$user  = current_user();
$role  = $user['role'] ?? 'client';
$units = number_format($user['sms_units'] ?? 0, 2);

// Build initials for avatar
$initials = implode('', array_map(fn($w) => strtoupper($w[0]),
    array_slice(explode(' ', $user['name'] ?? 'U'), 0, 2)
));
?>
<aside class="sidebar" id="sidebar">
  <!-- Toggle button -->
  <div class="sidebar-toggle" id="sidebarToggle" title="Toggle sidebar">
    <i class="fa-solid fa-chevron-left" id="toggleIcon"></i>
  </div>

  <!-- Brand -->
  <div class="sidebar-brand">
    <?php if ($logo = Branding::get('system_logo')): ?>
        <img src="<?= $logo ?>" alt="Logo" style="max-height:32px; width:auto; border-radius:4px">
    <?php else: ?>
        <div class="logo-icon"><?= substr(Branding::get('system_name', 'S'), 0, 1) ?></div>
    <?php endif; ?>
    <div class="logo-text-wrap">
      <div class="logo-text"><?= htmlspecialchars(Branding::get('system_name', 'Shanfix Technology')) ?></div>
      <div class="logo-sub"><?= strtoupper($role) ?></div>
    </div>
  </div>

  <!-- User info -->
  <div class="sidebar-user">
    <div class="avatar"><?= $initials ?></div>
    <div class="user-info">
      <div class="user-name"><?= htmlspecialchars($user['name'] ?? '') ?></div>
      <div class="user-role"><?= ucfirst($role) ?></div>
    </div>
  </div>

  <!-- Units badge -->
  <div class="sidebar-units" title="Your SMS units balance">
    <div class="units-icon">💰</div>
    <div>
      <div class="units-label">SMS Units</div>
      <div class="units-value"><?= $units ?></div>
    </div>
    <div style="margin-left:auto;color:rgba(255,255,255,0.4)"><i class="fa-solid fa-rotate-right" style="font-size:12px" id="refreshUnits"></i></div>
  </div>

  <!-- Navigation -->
  <nav class="sidebar-nav">
    <?php foreach ($navItems as $section): ?>
      <?php if (isset($section['type']) && $section['type'] === 'section'): ?>
        <div class="nav-section-title"><?= $section['label'] ?></div>
      <?php else: ?>
        <div class="nav-item">
          <?php if (!empty($section['children'])): ?>
            <a class="nav-link <?= $section['active'] ? 'active' : '' ?>"
               href="#" data-toggle="<?= $section['id'] ?>"
               aria-expanded="<?= $section['active'] ? 'true' : 'false' ?>">
              <span class="nav-icon"><?= $section['icon'] ?></span>
              <span class="nav-text"><?= $section['label'] ?></span>
              <?php if (!empty($section['badge'])): ?>
                <span class="nav-badge"><?= $section['badge'] ?></span>
              <?php endif; ?>
              <span class="nav-arrow"><i class="fa-solid fa-chevron-right"></i></span>
            </a>
            <div class="nav-sub <?= $section['active'] ? 'open' : '' ?>" id="sub-<?= $section['id'] ?>">
              <?php foreach ($section['children'] as $child): ?>
                <a href="<?= $child['url'] ?>"
                   class="nav-link <?= rtrim($_SERVER['PHP_SELF'], '/') === rtrim($child['url'], '/') ? 'active' : '' ?>">
                  <span class="nav-icon"><?= $child['icon'] ?></span>
                  <span class="nav-text"><?= $child['label'] ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <a href="<?= $section['url'] ?>"
               class="nav-link <?= rtrim($_SERVER['PHP_SELF'], '/') === rtrim($section['url'] ?? '', '/') ? 'active' : '' ?>">
              <span class="nav-icon"><?= $section['icon'] ?></span>
              <span class="nav-text"><?= $section['label'] ?></span>
              <?php if (!empty($section['badge'])): ?>
                <span class="nav-badge"><?= $section['badge'] ?></span>
              <?php endif; ?>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <!-- Footer -->
  <div class="sidebar-footer">
    <a href="/logout.php" class="nav-link" style="border-radius:var(--radius-md);color:rgba(255,255,255,0.5)">
      <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
      <span class="nav-text">Sign Out</span>
    </a>
  </div>
</aside>
