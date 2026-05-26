<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers/Mailer.php';

redirect_if_logged_in();

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $email = strtolower(trim(sanitize($_POST['email'] ?? '')));

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Always show the success message — prevents user enumeration.
            $sent = true;

            $user = DB::queryOne(
                "SELECT id, name, email FROM users WHERE email = ? AND status = 'active'",
                [$email]
            );

            if ($user) {
                // Remove any existing token for this email first.
                DB::execute("DELETE FROM password_resets WHERE email = ?", [$email]);

                $token     = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                DB::execute(
                    "INSERT INTO password_resets (token, email, expires_at) VALUES (?, ?, ?)",
                    [$token, $email, $expiresAt]
                );

                $siteUrl  = rtrim(get_setting('site_url', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/');
                $resetUrl = $siteUrl . '/reset-password.php?token=' . urlencode($token);
                $name     = htmlspecialchars($user['name']);

                $html = Mailer::html('Reset Your Password', <<<BODY
<p>Hi $name,</p>
<p>We received a request to reset the password for your account. Click the button below to set a new password:</p>
<p style="text-align:center;margin:28px 0">
  <a class="btn-link" href="$resetUrl">Reset My Password</a>
</p>
<p>This link expires in <strong>1 hour</strong>. If you didn't request a password reset you can safely ignore this email — your password will not change.</p>
<p style="font-size:13px;color:#888">Can't click the button? Copy and paste this URL into your browser:<br>
<a href="$resetUrl" style="word-break:break-all">$resetUrl</a></p>
BODY);

                Mailer::send($email, $user['name'], 'Reset Your Password', $html);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password — <?= htmlspecialchars(get_setting('site_name', 'Shanfix Technology')) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .auth-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; background:var(--bg-dark); padding:24px; }
    .auth-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:40px 36px; width:100%; max-width:440px; box-shadow:0 8px 40px rgba(0,0,0,.3); }
    .auth-logo { display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:28px; }
    .auth-logo .logo-icon { width:42px; height:42px; border-radius:var(--radius-md); background:var(--primary); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:18px; color:#fff; }
    .auth-title { font-size:22px; font-weight:700; color:var(--text-primary); margin:0 0 6px; }
    .auth-sub   { font-size:14px; color:var(--text-muted); margin:0 0 26px; }
    .back-link  { display:inline-flex; align-items:center; gap:6px; font-size:13px; color:var(--text-muted); text-decoration:none; margin-top:20px; }
    .back-link:hover { color:var(--primary); }
  </style>
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-logo">
      <?php $logo = get_setting('site_logo'); ?>
      <?php if ($logo): ?>
        <img src="<?= $logo ?>" alt="Logo" style="max-height:44px;width:auto">
      <?php else: ?>
        <div class="logo-icon"><?= substr(get_setting('site_name','S'), 0, 1) ?></div>
        <span style="font-size:18px;font-weight:700;color:var(--text-primary)"><?= htmlspecialchars(get_setting('site_name','Shanfix Technology')) ?></span>
      <?php endif; ?>
    </div>

    <?php if ($sent): ?>
      <div class="alert alert-success" style="margin-bottom:0">
        <i class="fa-solid fa-circle-check"></i>
        If that email address is registered, you'll receive a password reset link shortly. Check your spam folder if it doesn't arrive within a few minutes.
      </div>
      <div class="text-center">
        <a href="/login.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Sign In</a>
      </div>
    <?php else: ?>
      <h1 class="auth-title">Forgot your password?</h1>
      <p class="auth-sub">Enter your account email and we'll send you a reset link.</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <div class="input-group">
            <div class="input-group-text input-addon-left"><i class="fa-regular fa-envelope"></i></div>
            <input type="email" id="email" name="email" class="form-control with-left"
              placeholder="you@example.com"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              required autocomplete="email">
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-full btn-lg" id="submitBtn">
          <i class="fa-solid fa-paper-plane"></i> Send Reset Link
        </button>
      </form>

      <div class="text-center">
        <a href="/login.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Sign In</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>
document.querySelector('form')?.addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.innerHTML = '<span class="spinner"></span> Sending...';
  btn.disabled = true;
});
</script>
</body>
</html>
