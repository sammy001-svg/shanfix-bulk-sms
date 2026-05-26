<?php
require_once __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

$token = sanitize($_GET['token'] ?? '');
$error = '';
$done  = false;

$reset = $token ? DB::queryOne(
    "SELECT r.*, u.name FROM password_resets r
     JOIN users u ON u.email = r.email
     WHERE r.token = ? AND r.expires_at > NOW()",
    [$token]
) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request. Please refresh and try again.';
    } elseif (!$reset) {
        $error = 'This password reset link is invalid or has expired. <a href="/forgot-password.php">Request a new one.</a>';
    } else {
        $password = $_POST['password']         ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            DB::execute("UPDATE users SET password_hash = ? WHERE email = ?", [$hash, $reset['email']]);
            DB::execute("DELETE FROM password_resets WHERE token = ?", [$token]);
            $done = true;
        }
    }
}

$siteName = get_setting('site_name', 'Shanfix Technology');
$siteLogo = get_setting('site_logo');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password — <?= htmlspecialchars($siteName) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .auth-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; background:var(--bg-dark); padding:24px; }
    .auth-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:40px 36px; width:100%; max-width:440px; box-shadow:0 8px 40px rgba(0,0,0,.3); }
    .auth-logo { display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:28px; }
    .auth-logo .logo-icon { width:42px; height:42px; border-radius:var(--radius-md); background:var(--primary); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:18px; color:#fff; }
    .auth-title { font-size:22px; font-weight:700; color:var(--text-primary); margin:0 0 6px; }
    .auth-sub   { font-size:14px; color:var(--text-muted); margin:0 0 26px; }
    .pass-wrapper { position:relative; }
    .pass-wrapper .form-control { padding-right:42px; }
    .pass-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%); color:var(--text-muted); cursor:pointer; font-size:15px; }
    .pass-toggle:hover { color:var(--primary); }
    .back-link { display:inline-flex; align-items:center; gap:6px; font-size:13px; color:var(--text-muted); text-decoration:none; margin-top:20px; }
    .back-link:hover { color:var(--primary); }
  </style>
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">
      <?php if ($siteLogo): ?>
        <img src="<?= $siteLogo ?>" alt="Logo" style="max-height:44px;width:auto">
      <?php else: ?>
        <div class="logo-icon"><?= substr($siteName, 0, 1) ?></div>
        <span style="font-size:18px;font-weight:700;color:var(--text-primary)"><?= htmlspecialchars($siteName) ?></span>
      <?php endif; ?>
    </div>

    <?php if ($done): ?>
      <div class="alert alert-success" style="margin-bottom:20px">
        <i class="fa-solid fa-circle-check"></i>
        Your password has been updated successfully.
      </div>
      <a href="/login.php" class="btn btn-primary btn-full">
        <i class="fa-solid fa-right-to-bracket"></i> Sign In
      </a>

    <?php elseif (!$reset && !$_POST): ?>
      <div class="alert alert-error" style="margin-bottom:20px">
        <i class="fa-solid fa-triangle-exclamation"></i>
        This password reset link is invalid or has expired.
      </div>
      <a href="/forgot-password.php" class="btn btn-primary btn-full">
        <i class="fa-solid fa-paper-plane"></i> Request New Link
      </a>

    <?php else: ?>
      <h1 class="auth-title">Set a new password</h1>
      <p class="auth-sub">
        <?php if ($reset): ?>
          Resetting password for <strong><?= htmlspecialchars($reset['email']) ?></strong>
        <?php else: ?>
          Create a new password for your account.
        <?php endif; ?>
      </p>

      <?php if ($error): ?>
        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $error ?></div>
      <?php endif; ?>

      <form method="POST" id="resetForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="form-group">
          <label class="form-label" for="password">New Password</label>
          <div class="pass-wrapper">
            <input type="password" id="password" name="password" class="form-control"
              placeholder="At least 6 characters" required minlength="6" autocomplete="new-password">
            <span class="pass-toggle" onclick="togglePass('password','icon1')">
              <i class="fa-regular fa-eye" id="icon1"></i>
            </span>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="confirm_password">Confirm New Password</label>
          <div class="pass-wrapper">
            <input type="password" id="confirm_password" name="confirm_password" class="form-control"
              placeholder="Repeat your new password" required minlength="6" autocomplete="new-password">
            <span class="pass-toggle" onclick="togglePass('confirm_password','icon2')">
              <i class="fa-regular fa-eye" id="icon2"></i>
            </span>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg" id="submitBtn">
          <i class="fa-solid fa-lock"></i> Update Password
        </button>
      </form>

      <div class="text-center">
        <a href="/login.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Sign In</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>
function togglePass(id, iconId) {
  const inp  = document.getElementById(id);
  const icon = document.getElementById(iconId);
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.className = 'fa-regular fa-eye-slash';
  } else {
    inp.type = 'password';
    icon.className = 'fa-regular fa-eye';
  }
}

document.getElementById('resetForm')?.addEventListener('submit', function(e) {
  const p = document.getElementById('password').value;
  const c = document.getElementById('confirm_password').value;
  if (p !== c) {
    e.preventDefault();
    alert('Passwords do not match.');
    return;
  }
  const btn = document.getElementById('submitBtn');
  btn.innerHTML = '<span class="spinner"></span> Updating...';
  btn.disabled = true;
});
</script>
</body>
</html>
