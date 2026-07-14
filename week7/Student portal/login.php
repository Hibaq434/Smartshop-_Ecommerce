<?php
session_start();

// Already logged in → go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

require 'db.php';

$error       = "";
$saved_email = "";

// ── Check for Remember Me cookie ──────────────────────
// If cookie exists, pre-fill the email field
if (isset($_COOKIE['remember_email'])) {
    $saved_email = htmlspecialchars($_COOKIE['remember_email']);
}

// ── Handle login form submission ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email       = trim($_POST['email']    ?? '');
    $password    = $_POST['password']      ?? '';
    $remember_me = isset($_POST['remember_me']); // checkbox

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            // ── Create session ─────────────────────────
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['login_time'] = time();

            // ── Remember Me cookie logic ───────────────
            if ($remember_me) {
                // Cookie lasts 30 days
                $cookie_expiry = time() + (30 * 24 * 60 * 60);
                setcookie('remember_email', $email, $cookie_expiry, '/');
                setcookie('remember_name',  $user['name'], $cookie_expiry, '/');
            } else {
                // User unchecked — delete any existing cookies
                setcookie('remember_email', '', time() - 3600, '/');
                setcookie('remember_name',  '', time() - 3600, '/');
            }

            header("Location: dashboard.php");
            exit;

        } else {
            $error = "Invalid email or password.";
        }
    }
}

// Show remembered name in greeting if cookie exists
$remembered_name = $_COOKIE['remember_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login | Student Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#0f172a,#1e293b,#0f172a);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}

    .wrapper{width:100%;max-width:420px}

    /* Welcome back greeting shown when cookie exists */
    .cookie-greeting{
      background:rgba(99,102,241,0.1);
      border:1px solid rgba(99,102,241,0.3);
      border-radius:12px;
      padding:14px 18px;
      margin-bottom:18px;
      display:flex;
      align-items:center;
      gap:12px;
      color:#a5b4fc;
      font-size:0.88rem;
    }
    .cookie-greeting .cg-icon{font-size:1.4rem}
    .cookie-greeting strong{color:#c7d2fe}

    .card{
      background:rgba(255,255,255,0.04);
      backdrop-filter:blur(16px);
      border:1px solid rgba(255,255,255,0.08);
      border-radius:20px;
      padding:44px 36px;
      color:#fff;
    }

    .logo{text-align:center;margin-bottom:8px}
    .logo-icon{font-size:2.4rem;margin-bottom:8px}
    .logo h1{font-size:1.6rem;font-weight:700}
    .logo h1 span{color:#818cf8}
    .logo p{color:#64748b;font-size:0.85rem;margin-top:4px}

    .divider{border:none;border-top:1px solid rgba(255,255,255,0.07);margin:24px 0}

    .alert-error{
      background:rgba(239,68,68,0.1);
      border:1px solid rgba(239,68,68,0.3);
      color:#fca5a5;
      padding:12px 14px;
      border-radius:10px;
      margin-bottom:18px;
      font-size:0.85rem;
      display:flex;
      align-items:center;
      gap:8px;
    }

    .form-group{margin-bottom:16px}
    label{display:block;font-size:0.78rem;font-weight:600;color:#94a3b8;margin-bottom:6px;letter-spacing:0.5px;text-transform:uppercase}

    input[type="email"],
    input[type="password"]{
      width:100%;
      padding:12px 14px;
      background:rgba(255,255,255,0.06);
      border:1px solid rgba(255,255,255,0.1);
      border-radius:10px;
      color:#fff;
      font-size:0.92rem;
      font-family:'Inter',sans-serif;
      outline:none;
      transition:border-color 0.25s,background 0.25s;
    }

    input[type="email"]:focus,
    input[type="password"]:focus{
      border-color:#818cf8;
      background:rgba(129,140,248,0.06);
    }

    input::placeholder{color:#334155}

    /* ── Remember Me checkbox ── */
    .remember-row{
      display:flex;
      align-items:center;
      justify-content:space-between;
      margin-bottom:22px;
      margin-top:4px;
    }

    .checkbox-label{
      display:flex;
      align-items:center;
      gap:10px;
      cursor:pointer;
      user-select:none;
      font-size:0.85rem;
      color:#94a3b8;
    }

    /* Custom checkbox */
    .checkbox-label input[type="checkbox"]{display:none}

    .custom-check{
      width:18px;height:18px;
      border:1px solid rgba(255,255,255,0.2);
      border-radius:5px;
      background:rgba(255,255,255,0.05);
      display:flex;align-items:center;justify-content:center;
      transition:all 0.2s;
      flex-shrink:0;
    }

    .checkbox-label input[type="checkbox"]:checked + .custom-check{
      background:#818cf8;
      border-color:#818cf8;
    }

    .checkbox-label input[type="checkbox"]:checked + .custom-check::after{
      content:'✓';
      color:#fff;
      font-size:0.75rem;
      font-weight:700;
    }

    .cookie-info{
      font-size:0.72rem;
      color:#475569;
      margin-top:4px;
    }

    .btn-login{
      width:100%;
      padding:13px;
      background:linear-gradient(135deg,#6366f1,#8b5cf6);
      color:#fff;
      border:none;
      border-radius:10px;
      font-size:0.95rem;
      font-weight:600;
      font-family:'Inter',sans-serif;
      cursor:pointer;
      transition:all 0.25s;
      letter-spacing:0.3px;
    }

    .btn-login:hover{
      transform:translateY(-2px);
      box-shadow:0 8px 24px rgba(99,102,241,0.4);
    }

    .footer-link{
      text-align:center;
      margin-top:20px;
      font-size:0.83rem;
      color:#475569;
    }

    .footer-link a{color:#818cf8;text-decoration:none;font-weight:500}
    .footer-link a:hover{text-decoration:underline}

    /* Cookie explanation box */
    .how-it-works{
      margin-top:20px;
      background:rgba(255,255,255,0.02);
      border:1px dashed rgba(255,255,255,0.08);
      border-radius:10px;
      padding:14px;
      font-size:0.75rem;
      color:#475569;
      line-height:1.6;
    }
    .how-it-works strong{color:#64748b}
  </style>
</head>
<body>
<div class="wrapper">

  <!-- Cookie greeting (only shows if Remember Me was used before) -->
  <?php if ($remembered_name): ?>
  <div class="cookie-greeting">
    <span class="cg-icon">🍪</span>
    <div>Welcome back, <strong><?= $remembered_name ?>!</strong><br/>
    Your email has been remembered for you.</div>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="logo">
      <div class="logo-icon">🎓</div>
      <h1>Student<span>Portal</span></h1>
      <p>Sign in to your account</p>
    </div>

    <hr class="divider"/>

    <?php if ($error): ?>
      <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">

      <div class="form-group">
        <label>Email Address</label>
        <!-- Pre-filled from cookie if Remember Me was checked -->
        <input type="email" name="email"
               placeholder="you@university.ac.ke"
               value="<?= $saved_email ?>"
               required/>
      </div>

      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password"
               placeholder="Your password"
               required/>
      </div>

      <!-- ── REMEMBER ME ── -->
      <div class="remember-row">
        <label class="checkbox-label">
          <input type="checkbox" name="remember_me"
                 <?= $saved_email ? 'checked' : '' ?>/>
          <span class="custom-check"></span>
          Remember me for 30 days
        </label>
      </div>

      <button type="submit" class="btn-login">Login →</button>
    </form>

    <div class="footer-link">
      Don't have an account? <a href="register.php">Register</a>
    </div>

    <div class="how-it-works">
      <strong>🍪 How Remember Me works:</strong> When checked, your email is saved
      in a browser cookie for 30 days. Next time you visit, the email field
      is pre-filled automatically. Your password is never stored in a cookie.
    </div>
  </div>
</div>
</body>
</html>