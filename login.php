<?php
require_once __DIR__ . '/includes/auth.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
redirect_if_logged_in();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $email    = sanitize($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter your email and password.';
        } else {
            $user = auth_login($email, $password);
            if ($user) {
                switch ($user['role']) {
                    case 'admin':    $dest = '/admin/'; break;
                    case 'reseller': $dest = '/reseller/'; break;
                    case 'client':   $dest = '/client/'; break;
                    default:         $dest = '/login.php'; break;
                }
                redirect($dest);
            } else {
                $error = 'Invalid email or password. Please try again.';
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
  <title>Sign In — <?= htmlspecialchars(Branding::get('system_name')) ?></title>
  <?php Branding::renderStyles(); ?>
  <meta name="description" content="Sign in to Shanfix Technology — The most reliable Bulk SMS platform in the region. Send campaigns, manage contacts, and grow your business instantly.">
  
  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://sms.shanfixtechnology.com/">
  <meta property="og:title" content="Shanfix Technology — Connect Instantly with Bulk SMS">
  <meta property="og:description" content="Send bulk SMS, manage campaigns and contacts from one powerful platform. High delivery rates and reliable infrastructure.">
  <meta property="og:image" content="https://sms.shanfixtechnology.com/assets/images/shanfix_og_image.png">

  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:url" content="https://sms.shanfixtechnology.com/">
  <meta property="twitter:title" content="Shanfix Technology — Connect Instantly with Bulk SMS">
  <meta property="twitter:description" content="Send bulk SMS, manage campaigns and contacts from one powerful platform. High delivery rates and reliable infrastructure.">
  <meta property="twitter:image" content="https://sms.shanfixtechnology.com/assets/images/shanfix_og_image.png">

  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .login-steps { display: flex; flex-direction: column; gap: 20px; margin-top: 32px; }
    .step-item { display: flex; gap: 16px; align-items: flex-start; }
    .step-num {
      width: 32px; height: 32px; border-radius: 50%;
      background: rgba(0,200,150,0.2); border: 1.5px solid var(--primary);
      color: var(--primary); font-weight: 700; font-size: 14px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .step-text h4 { font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 2px; }
    .step-text p  { font-size: 12.5px; color: rgba(255,255,255,0.55); }
    .login-branding h1 { font-size: 34px; font-weight: 800; color: #fff; line-height: 1.2; }
    .login-branding p  { font-size: 15px; color: rgba(255,255,255,0.6); margin-top: 10px; max-width: 380px; }
    .login-branding .brand-badge {
      display: inline-flex; align-items: center; gap: 8px;
      background: rgba(0,200,150,0.15); border: 1px solid rgba(0,200,150,0.3);
      border-radius: 20px; padding: 6px 16px; margin-bottom: 24px;
      font-size: 12px; color: var(--primary); font-weight: 600; letter-spacing: 0.08em;
    }
    .carousel-container {
      position: absolute; inset: 0; width: 100%; height: 100%;
      overflow: hidden; background: var(--bg-dark);
    }
    .carousel-slide {
      position: absolute; inset: 0; opacity: 0; transition: opacity 1.2s ease-in-out;
      display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 60px;
      z-index: 1; text-align: center;
    }
    .carousel-slide.active { opacity: 1; z-index: 2; }
    .carousel-slide img {
      position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover;
      opacity: 1; z-index: -1; filter: brightness(0.5) contrast(1.2);
    }
    .carousel-content { position: relative; z-index: 10; max-width: 600px; }
    .carousel-content h1 { font-size: 54px; font-weight: 800; color: #fff; line-height: 1.1; margin-bottom: 20px; text-shadow: 0 4px 15px rgba(0,0,0,0.4); }
    .carousel-content p { font-size: 20px; color: rgba(255,255,255,0.9); line-height: 1.6; }
    .carousel-dots {
      position: absolute; bottom: 50px; left: 50%; transform: translateX(-50%); display: flex; gap: 12px; z-index: 20;
    }
    .dot {
      width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,0.3);
      cursor: pointer; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .dot.active { width: 32px; border-radius: 5px; background: var(--primary); box-shadow: 0 0 15px rgba(0,200,150,0.5); }

    .login-left { flex: 1; min-width: 0; padding: 0 !important; display: flex !important; align-items: stretch !important; justify-content: stretch !important; }
    .login-branding { flex: 1; height: 100vh !important; min-height: 600px; position: relative !important; }

    .login-right { flex: 1; width: auto !important; min-width: 0; }
    .login-box { max-width: 480px !important; margin: 0 auto; }

    @media (max-width: 1024px) {
      .login-left { display: flex !important; } /* Keep visible on tablets */
      .login-right { width: 400px; }
    }
    @media (max-width: 768px) {
      .login-left { display: none !important; } /* Hide only on mobile */
    }

    .pass-wrapper { position: relative; }
    .pass-wrapper .form-control { padding-right: 42px; }
    .pass-toggle {
      position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
      color: var(--text-muted); cursor: pointer; font-size: 15px;
      transition: color 0.15s;
    }
    .pass-toggle:hover { color: var(--primary); }
    .login-extras { display: flex; justify-content: space-between; align-items: center; margin-top: -8px; margin-bottom: 20px; }
    .login-extras label { display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer; }
    .login-extras input[type=checkbox] { accent-color: var(--primary); }
    .divider { display: flex; align-items: center; gap: 12px; color: var(--text-muted); font-size: 12px; margin: 20px 0; }
    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }
    .floating-orbs { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
    .orb {
      position: absolute; border-radius: 50%;
      background: radial-gradient(circle, rgba(0,200,150,0.15), transparent 70%);
    }
    .orb-1 { width: 300px; height: 300px; top: -80px; left: -80px; }
    .orb-2 { width: 200px; height: 200px; bottom: 20%; right: -60px; }
    .orb-3 { width: 150px; height: 150px; bottom: 10%; left: 30%; background: radial-gradient(circle, rgba(29,233,182,0.1), transparent 70%); }
  </style>
</head>
<body>
<div class="login-page">
  <!-- Left: Branding -->
  <div class="login-left">
    <div class="floating-orbs">
      <div class="orb orb-1"></div>
      <div class="orb orb-2"></div>
      <div class="orb orb-3"></div>
    </div>

    <div class="login-branding" style="position:relative;z-index:1; padding: 0; overflow: hidden; height: 100%; display: flex; flex-direction: column;">
      <div class="carousel-container">
        <div class="carousel-slide active">
          <img src="/360_F_772537249_dLcs2lyUWlAQX4KDi4xAQL9gVjrWqYSi.jpg" alt="Market">
          <div class="carousel-content">
            <div class="brand-badge"><i class="fa-solid fa-bolt"></i> LOCAL BUSINESS</div>
            <h1>Empower Your Business.</h1>
            <p>Reach your customers instantly across the region with our reliable Bulk SMS infrastructure.</p>
          </div>
        </div>
        <div class="carousel-slide">
          <img src="/ai-generated-portrait-of-a-smiling-african-american-woman-using-mobile-phone-in-the-city-at-night-a-happy-beautiful-black-woman-texting-on-her-mobile-phone-ai-generated-free-photo.jpg" alt="City">
          <div class="carousel-content">
            <div class="brand-badge"><i class="fa-solid fa-globe"></i> GLOBAL REACH</div>
            <h1>Connect Anywhere.</h1>
            <p>Send messages across borders with ease. Our platform ensures high delivery rates everywhere.</p>
          </div>
        </div>
        <div class="carousel-slide">
          <img src="/istockphoto-1133045010-612x612.jpg" alt="Home">
          <div class="carousel-content">
            <div class="brand-badge"><i class="fa-solid fa-check-double"></i> RELIABILITY</div>
            <h1>Stay in Touch.</h1>
            <p>Whether for marketing or alerts, trust us to deliver your messages safely and on time.</p>
          </div>
        </div>
        <div class="carousel-slide">
          <img src="/istockphoto-1168817175-612x612.jpg" alt="Office">
          <div class="carousel-content">
            <div class="brand-badge"><i class="fa-solid fa-chart-line"></i> GROWTH</div>
            <h1>Scale with Ease.</h1>
            <p>Powerful analytics and management tools for Admin, Resellers, and Clients in one place.</p>
          </div>
        </div>
        
        <div class="carousel-dots">
          <div class="dot active"></div>
          <div class="dot"></div>
          <div class="dot"></div>
          <div class="dot"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right: Login Form -->
  <div class="login-right">
    <div class="login-box animate-in">
      <div class="login-logo">
        <?php if ($logo = Branding::get('system_logo')): ?>
            <img src="<?= $logo ?>" alt="Logo" style="max-height:45px; margin-bottom:10px">
        <?php else: ?>
            <div class="logo-icon"><?= substr(Branding::get('system_name', 'S'), 0, 1) ?></div>
        <?php endif; ?>
        <div>
          <div class="logo-name"><?= htmlspecialchars(Branding::get('system_name')) ?></div>
          <div class="logo-tag">Enterprise Platform</div>
        </div>
      </div>

      <h1 class="login-title">Welcome back</h1>
      <p class="login-subtitle">Sign in to your account to continue</p>

      <?php if (!empty($error)): ?>
        <div class="alert alert-error">
          <i class="fa-solid fa-circle-exclamation"></i>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['expired'])): ?>
        <div class="alert alert-warning">
          <i class="fa-solid fa-clock"></i>
          Your session expired. Please sign in again.
        </div>
      <?php endif; ?>

      <form method="POST" action="/login.php" id="loginForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <div class="input-group">
            <div class="input-group-text input-addon-left"><i class="fa-regular fa-envelope"></i></div>
            <input
              type="email" id="email" name="email"
              class="form-control with-left"
              placeholder="you@example.com"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              required autocomplete="email">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="pass-wrapper">
            <input
              type="password" id="password" name="password"
              class="form-control"
              placeholder="Enter your password"
              required autocomplete="current-password">
            <span class="pass-toggle" onclick="togglePass()">
              <i class="fa-regular fa-eye" id="passIcon"></i>
            </span>
          </div>
        </div>

        <div class="login-extras">
          <label>
            <input type="checkbox" name="remember"> Remember me
          </label>
          <a href="/forgot-password.php">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg" id="loginBtn">
          <i class="fa-solid fa-right-to-bracket"></i> Sign In
        </button>
      </form>


      <div class="text-center mt-20 links" style="font-size: 14px; color: var(--text-muted);">
        Don't have an account? <a href="/register.php" style="color: var(--primary); text-decoration: none; font-weight: 600;">Create Account</a>
      </div>

      <p class="text-center text-muted mt-20" style="font-size:12px;">
        &copy; <?= date('Y') ?> <?= htmlspecialchars(Branding::get('system_name')) ?>. All rights reserved.
      </p>
    </div>
  </div>
</div>

<script>
function togglePass() {
  const inp = document.getElementById('password');
  const ico = document.getElementById('passIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'fa-regular fa-eye-slash';
  } else {
    inp.type = 'password';
    ico.className = 'fa-regular fa-eye';
  }
}


document.getElementById('loginForm').addEventListener('submit', function() {
  const btn = document.getElementById('loginBtn');
  btn.innerHTML = '<span class="spinner"></span> Signing in...';
  btn.disabled = true;
});

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

function nextSlide() {
  let next = (currentSlide + 1) % slides.length;
  showSlide(next);
}

// Auto play
let carouselInterval = setInterval(nextSlide, 5000);

// Click on dots
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
