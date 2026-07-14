<?php
session_start();

$name = $_SESSION['user_name'] ?? 'Student';

// ── Destroy session ────────────────────────────────
session_unset();
session_destroy();

// ── Cookie: only clear if user clicked "Logout & Forget Me" ──
$forget = isset($_GET['forget']) && $_GET['forget'] === '1';
if ($forget) {
    setcookie('remember_email', '', time() - 3600, '/');
    setcookie('remember_name',  '', time() - 3600, '/');
}

$has_cookie = isset($_COOKIE['remember_email']) && !$forget;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Logged Out | Student Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet"/>
  <!-- Auto redirect after 5 seconds -->
  <meta http-equiv="refresh" content="5;url=login.php"/>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#0f172a,#1e293b);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{background:rgba(255,255,255,0.04);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.08);border-radius:20px;padding:48px 36px;max-width:420px;width:100%;text-align:center;color:#fff}
    .icon{font-size:3.5rem;margin-bottom:16px}
    h1{font-family:'Space Grotesk',sans-serif;font-size:1.6rem;font-weight:700;margin-bottom:8px}
    .sub{color:#64748b;font-size:0.88rem;margin-bottom:28px;line-height:1.6}

    .status-box{background:#0d1526;border:1px solid #1e293b;border-radius:12px;padding:18px;margin-bottom:24px;text-align:left}
    .status-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #131f35;font-size:0.83rem}
    .status-row:last-child{border-bottom:none}
    .status-row .k{color:#475569}
    .status-done{color:#4ade80;font-weight:600}
    .status-kept{color:#818cf8;font-weight:600}
    .status-cleared{color:#f87171;font-weight:600}

    .cookie-note{
      background:rgba(129,140,248,0.08);
      border:1px solid rgba(129,140,248,0.2);
      border-radius:10px;
      padding:14px;
      font-size:0.8rem;
      color:#94a3b8;
      margin-bottom:24px;
      line-height:1.6;
      text-align:left;
    }
    .cookie-note strong{color:#a5b4fc}

    .btn-group{display:flex;flex-direction:column;gap:10px}
    .btn{display:block;padding:12px;border-radius:10px;font-size:0.88rem;font-weight:600;text-decoration:none;transition:all 0.25s;border:none;cursor:pointer;font-family:'Inter',sans-serif}
    .btn-primary{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
    .btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(99,102,241,0.35)}
    .btn-danger{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#f87171}
    .btn-danger:hover{background:rgba(239,68,68,0.2)}

    .redirect-note{margin-top:20px;font-size:0.75rem;color:#334155}
    .redirect-note span{color:#818cf8}
  </style>
</head>
<body>
<div class="card">
  <div class="icon">👋</div>
  <h1>You've been logged out</h1>
  <p class="sub">Your session has been ended securely. See you next time, <?= htmlspecialchars($name) ?>!</p>

  <!-- What happened summary -->
  <div class="status-box">
    <div class="status-row">
      <span class="k">Session destroyed</span>
      <span class="status-done">✔ Done</span>
    </div>
    <div class="status-row">
      <span class="k">Session data cleared</span>
      <span class="status-done">✔ Done</span>
    </div>
    <div class="status-row">
      <span class="k">Remember Me cookie</span>
      <?php if ($forget): ?>
        <span class="status-cleared">✖ Cleared</span>
      <?php elseif ($has_cookie): ?>
        <span class="status-kept">🍪 Kept (30 days)</span>
      <?php else: ?>
        <span class="status-cleared">✖ Not set</span>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($has_cookie): ?>
  <div class="cookie-note">
    🍪 <strong>Remember Me is still active.</strong> Your email will be pre-filled next time you visit the login page. To remove this, click "Logout & Forget Me" below.
  </div>
  <?php endif; ?>

  <div class="btn-group">
    <a href="login.php" class="btn btn-primary">🔐 Login Again</a>
    <?php if ($has_cookie): ?>
    <a href="logout.php?forget=1" class="btn btn-danger">🗑 Logout &amp; Forget Me</a>
    <?php endif; ?>
  </div>

  <p class="redirect-note">Redirecting to login in <span>5 seconds</span>...</p>
</div>
</body>
</html>