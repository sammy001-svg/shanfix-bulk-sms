<?php
/**
 * User Registration - Shanfix Technology
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    redirect_if_logged_in();
}

$error = '';
$success = '';
$role = 'client'; // Default role

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = sanitize($_POST['role'] ?? 'client');

    if (!in_array($role, ['client', 'reseller'])) {
        $role = 'client';
    }

    if (!$name || !$email || !$password) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if email exists
        $existing = DB::queryOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $error = 'Email already registered. Please sign in.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $new_user_id = DB::insert(
                "INSERT INTO users (name, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, ?, 'active')",
                [$name, $email, $phone, $hash, $role]
            );

            if ($new_user_id) {
                generate_user_api_keys((int)$new_user_id);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Account created successfully! Please sign in.'];
                header('Location: /login.php');
                exit;
            } else {
                $error = 'Registration failed. Please try again later.';
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
  <title>Create Account — <?= htmlspecialchars(Branding::get('system_name')) ?></title>
  <?php Branding::renderStyles(); ?>
  <meta name="description" content="Join Shanfix Technology — The most reliable Bulk SMS platform in the region. Create an account today and start reaching your customers instantly.">
  
  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://sms.shanfixtechnology.com/register.php">
  <meta property="og:title" content="Join Shanfix Technology — Connect Instantly with Bulk SMS">
  <meta property="og:description" content="Send bulk SMS, manage campaigns and contacts from one powerful platform. Create your account today.">
  <meta property="og:image" content="https://sms.shanfixtechnology.com/assets/images/shanfix_og_image.png">

  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:url" content="https://sms.shanfixtechnology.com/register.php">
  <meta property="twitter:title" content="Join Shanfix Technology — Connect Instantly with Bulk SMS">
  <meta property="twitter:description" content="Send bulk SMS, manage campaigns and contacts from one powerful platform. Create your account today.">
  <meta property="twitter:image" content="https://sms.shanfixtechnology.com/assets/images/shanfix_og_image.png">
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #00c896;
      --primary-dark: #00a57c;
      --primary-light: rgba(0, 200, 150, 0.1);
      --bg-dark: #060b13;
      --bg-muted: #111927;
      --text-primary: #ffffff;
      --text-muted: #94a3b8;
      --border: rgba(255, 255, 255, 0.08);
      --radius-lg: 16px;
      --radius-sm: 8px;
      --font: 'Inter', system-ui, sans-serif;
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body { font-family: var(--font); background: var(--bg-dark); color: var(--text-primary); margin: 0; overflow-x: hidden; }

    .login-page { min-height: 100vh; display: flex; }
    
    /* Reuse Carousel Styles from Login */
    .carousel-container { position: absolute; inset: 0; width: 100%; height: 100%; overflow: hidden; background: var(--bg-dark); }
    .carousel-slide { 
        position: absolute; inset: 0; opacity: 0; transition: opacity 1.2s ease-in-out; 
        display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 60px;
        z-index: 1; text-align: center;
    }
    .carousel-slide.active { opacity: 1; z-index: 2; }
    .carousel-slide img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: -1; filter: brightness(0.5) contrast(1.2); }
    .carousel-content { position: relative; z-index: 10; max-width: 600px; }
    .carousel-content h1 { font-size: 54px; font-weight: 800; line-height: 1.1; margin-bottom: 20px; text-shadow: 0 4px 15px rgba(0,0,0,0.4); }
    .carousel-content p { font-size: 20px; color: rgba(255,255,255,0.9); line-height: 1.6; }
    .carousel-dots { position: absolute; bottom: 50px; left: 50%; transform: translateX(-50%); display: flex; gap: 12px; z-index: 20; }
    .dot { width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,0.3); cursor: pointer; transition: var(--transition); }
    .dot.active { width: 32px; border-radius: 5px; background: var(--primary); box-shadow: 0 0 15px rgba(0,200,150,0.5); }

    .login-left { flex: 1; min-width: 0; position: relative; display: flex; align-items: stretch; justify-content: stretch; overflow: hidden; }
    @media (max-width: 1024px) { .login-left { display: none; } }

    .login-right { flex: 1; min-width: 0; display: flex; align-items: center; justify-content: center; padding: 40px; background: var(--bg-dark); position: relative; }
    @media (max-width: 1024px) { .login-right { width: 100%; padding: 20px; } }

    .login-box { width: 100%; max-width: 480px; z-index: 1; }
    .login-logo { display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
    .logo-icon { width: 45px; height: 45px; background: var(--primary); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 800; color: #000; }
    .logo-name { font-size: 22px; font-weight: 800; letter-spacing: -0.02em; }
    .logo-tag { font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--primary); font-weight: 700; margin-top: -2px; }

    .login-title { font-size: 32px; font-weight: 800; margin-bottom: 8px; letter-spacing: -0.01em; }
    .login-subtitle { color: var(--text-muted); font-size: 15px; margin-bottom: 32px; }

    .form-group { margin-bottom: 20px; }
    .form-label { display: block; font-size: 14px; font-weight: 500; color: var(--text-muted); margin-bottom: 8px; }
    .form-control {
      width: 100%; padding: 13px 16px; background: var(--bg-muted); border: 1px solid var(--border); border-radius: var(--radius-sm);
      color: #fff; font-size: 15px; transition: var(--transition); font-family: var(--font); box-sizing: border-box;
    }
    .form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-light); background: var(--bg-dark); }

    .btn {
      padding: 14px 24px; border-radius: var(--radius-sm); font-size: 15px; font-weight: 600; cursor: pointer;
      transition: var(--transition); border: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-primary { background: var(--primary); color: #000; }
    .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 20px rgba(0, 200, 150, 0.3); }
    .btn-full { width: 100%; }

    .alert { padding: 14px; border-radius: var(--radius-sm); font-size: 14px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
    .alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
    .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }

    .text-center { text-align: center; }
    .mt-20 { margin-top: 20px; }
    .links { font-size: 14px; color: var(--text-muted); }
    .links a { color: var(--primary); text-decoration: none; font-weight: 600; }
    .links a:hover { text-decoration: underline; }

    /* Password Toggle Styling */
    .pass-wrapper { position: relative; width: 100%; }
    .pass-toggle {
      position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
      color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center;
      transition: var(--transition); z-index: 10;
    }
    .pass-toggle:hover { color: var(--primary); }
    .with-pass { padding-right: 44px !important; }

    .floating-orbs { position: absolute; inset: 0; pointer-events: none; overflow: hidden; opacity: 0.5; }
    .orb { position: absolute; border-radius: 50%; background: radial-gradient(circle, rgba(0,200,150,0.1), transparent 70%); }
    .orb-1 { width: 400px; height: 400px; top: -100px; right: -100px; }
    .orb-2 { width: 300px; height: 300px; bottom: -50px; left: -50px; }

    .spinner { width: 18px; height: 18px; border: 2px solid rgba(0,0,0,0.2); border-top-color: #000; border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Role Selector Styling */
    .role-selector { display: flex; gap: 12px; margin-bottom: 5px; }
    .role-option {
      flex: 1; padding: 12px; border-radius: var(--radius-sm); background: var(--bg-muted); border: 1px solid var(--border);
      display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; cursor: pointer; transition: var(--transition);
    }
    .role-option i { font-size: 20px; color: var(--text-muted); }
    .role-option span { font-size: 13px; font-weight: 600; color: var(--text-muted); }
    input[type="radio"]:checked + .role-option {
      background: var(--primary-light); border-color: var(--primary);
    }
    input[type="radio"]:checked + .role-option i, 
    input[type="radio"]:checked + .role-option span { color: var(--primary); }
    .role-option:hover { border-color: var(--primary); background: rgba(255,255,255,0.02); }
  </style>
</head>
<body>
<div class="login-page">
  <div class="login-left">
    <div class="carousel-container">
      <div class="carousel-slide active">
        <img src="/360_F_772537249_dLcs2lyUWlAQX4KDi4xAQL9gVjrWqYSi.jpg" alt="Market">
        <div class="carousel-content"><h1>Empower Your Business.</h1><p>Reach your customers instantly with our reliable Bulk SMS infrastructure.</p></div>
      </div>
      <div class="carousel-slide">
        <img src="/ai-generated-portrait-of-a-smiling-african-american-woman-using-mobile-phone-in-the-city-at-night-a-happy-beautiful-black-woman-texting-on-her-mobile-phone-ai-generated-free-photo.jpg" alt="City">
        <div class="carousel-content"><h1>Connect Anywhere.</h1><p>Send messages across borders with ease and high delivery rates.</p></div>
      </div>
      <div class="carousel-slide">
        <img src="/istockphoto-1133045010-612x612.jpg" alt="Home">
        <div class="carousel-content"><h1>Stay in Touch.</h1><p>Trust us to deliver your messages safely and on time, every time.</p></div>
      </div>
      <div class="carousel-slide">
        <img src="/istockphoto-1168817175-612x612.jpg" alt="Office">
        <div class="carousel-content"><h1>Scale with Ease.</h1><p>Powerful analytics and management tools to help your business grow.</p></div>
      </div>
      <div class="carousel-dots">
        <div class="dot active"></div><div class="dot"></div><div class="dot"></div><div class="dot"></div>
      </div>
    </div>
  </div>

  <div class="login-right">
    <div class="floating-orbs">
      <div class="orb orb-1"></div>
      <div class="orb orb-2"></div>
    </div>

    <div class="login-box animate-in">
      <div class="login-logo" style="justify-content: center;">
        <?php if ($logo = Branding::get('system_logo')): ?>
            <div style="background: rgba(255,255,255,0.9); padding: 12px 20px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
                <img src="<?= $logo ?>" alt="Logo" style="max-height:50px; width:auto;">
            </div>
        <?php else: ?>
            <div class="logo-icon"><?= substr(Branding::get('system_name', 'S'), 0, 1) ?></div>
            <div class="logo-name"><?= htmlspecialchars(Branding::get('system_name')) ?></div>
        <?php endif; ?>
      </div>

      <h1 class="login-title">Join us today</h1>
      <p class="login-subtitle">Create an account to start sending messages</p>

      <?php if ($error): ?>
        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $error ?></div>
      <?php endif; ?>

      <form method="POST" action="/register.php" id="regForm">
        <div class="form-group">
          <label class="form-label" for="name">Full Name</label>
          <div class="input-group">
            <div class="input-group-text input-addon-left"><i class="fa-regular fa-user"></i></div>
            <input type="text" id="name" name="name" class="form-control with-left" placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <div class="input-group">
            <div class="input-group-text input-addon-left"><i class="fa-regular fa-envelope"></i></div>
            <input type="email" id="email" name="email" class="form-control with-left" placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="phone">Phone Number (Optional)</label>
            <div class="input-group">
                <div class="input-group-text input-addon-left"><i class="fa-solid fa-phone"></i></div>
            <input type="text" id="phone" name="phone" class="form-control with-left" placeholder="+254..." value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Account Type</label>
          <div class="role-selector">
            <input type="radio" name="role" value="client" id="roleClient" <?= ($role === 'client') ? 'checked' : '' ?> hidden>
            <label for="roleClient" class="role-option">
              <i class="fa-solid fa-user"></i>
              <span>Client</span>
            </label>
            
            <input type="radio" name="role" value="reseller" id="roleReseller" <?= ($role === 'reseller') ? 'checked' : '' ?> hidden>
            <label for="roleReseller" class="role-option">
              <i class="fa-solid fa-store"></i>
              <span>Reseller</span>
            </label>
          </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="pass-wrapper">
                  <input type="password" id="password" name="password" class="form-control with-pass" placeholder="••••••••" required>
                  <span class="pass-toggle" onclick="togglePass('password', 'passIcon1')">
                    <i class="fa-regular fa-eye" id="passIcon1"></i>
                  </span>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm</label>
                <div class="pass-wrapper">
                  <input type="password" id="confirm_password" name="confirm_password" class="form-control with-pass" placeholder="••••••••" required>
                  <span class="pass-toggle" onclick="togglePass('confirm_password', 'passIcon2')">
                    <i class="fa-regular fa-eye" id="passIcon2"></i>
                  </span>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full" id="regBtn">
          Create Account
        </button>
      </form>

      <div class="text-center mt-20 links">
        Already have an account? <a href="/login.php">Sign In</a>
      </div>

      <p class="text-center text-muted mt-20" style="font-size:12px;">
        &copy; <?= date('Y') ?> <?= htmlspecialchars(Branding::get('system_name')) ?>. All rights reserved.
      </p>
    </div>
  </div>
</div>

<script>
document.getElementById('regForm').addEventListener('submit', function() {
  const btn = document.getElementById('regBtn');
  btn.innerHTML = '<span class="spinner"></span> Creating account...';
  btn.disabled = true;
});

function togglePass(id, iconId) {
  const inp = document.getElementById(id);
  const ico = document.getElementById(iconId);
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'fa-regular fa-eye-slash';
  } else {
    inp.type = 'password';
    ico.className = 'fa-regular fa-eye';
  }
}

// Carousel Logic
let currentSlide = 0;
const slides = document.querySelectorAll('.carousel-slide');
const dots = document.querySelectorAll('.dot');
function showSlide(index) {
  slides.forEach(s => s.classList.remove('active'));
  dots.forEach(d => d.classList.remove('active'));
  slides[index].classList.add('active');
  dots[index].classList.add('active');
  currentSlide = index;
}
function nextSlide() { showSlide((currentSlide + 1) % slides.length); }
let carouselInterval = setInterval(nextSlide, 5000);
dots.forEach((dot, index) => {
  dot.addEventListener('click', () => {
    clearInterval(carouselInterval);
    showSlide(index);
    carouselInterval = setInterval(nextSlide, 5000);
  });
});
</script>
</body>
</html>
