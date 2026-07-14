<?php
session_start();

// ── Session guard ──────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Session timeout — 30 minutes
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: login.php?reason=timeout");
    exit;
}
$_SESSION['login_time'] = time(); // refresh activity

$name      = htmlspecialchars($_SESSION['user_name']);
$email     = htmlspecialchars($_SESSION['user_email']);
$initials  = strtoupper(substr($name, 0, 1));
$login_at  = date("D d M Y · H:i:s", $_SESSION['login_time']);
$session_id= session_id();

// Cookie status
$has_cookie    = isset($_COOKIE['remember_email']);
$cookie_email  = $has_cookie ? htmlspecialchars($_COOKIE['remember_email']) : 'Not set';
$cookie_expiry = $has_cookie ? date("d M Y", time() + (30 * 24 * 60 * 60)) : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard | Student Portal</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet"/>
  <style>
    *{margin:0;padding:0;box-sizing:border-box}
    body{font-family:'Inter',sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;display:flex}

    /* Sidebar */
    .sidebar{width:240px;background:#0d1526;border-right:1px solid #1e293b;display:flex;flex-direction:column;position:fixed;height:100vh}
    .sb-brand{padding:24px 20px;border-bottom:1px solid #1e293b}
    .sb-brand h2{font-family:'Space Grotesk',sans-serif;font-size:1.2rem;font-weight:700}
    .sb-brand h2 span{color:#818cf8}
    .sb-role{display:inline-block;background:rgba(129,140,248,0.12);color:#818cf8;border:1px solid rgba(129,140,248,0.3);font-size:0.68rem;padding:2px 10px;border-radius:50px;margin-top:6px;font-weight:600}
    .sb-nav{flex:1;padding:18px 0}
    .nav-sec{font-size:0.67rem;text-transform:uppercase;letter-spacing:1.5px;color:#334155;padding:10px 20px 5px}
    .nav-item{display:flex;align-items:center;gap:11px;padding:10px 20px;color:#64748b;text-decoration:none;font-size:0.87rem;transition:all 0.2s;border-left:3px solid transparent}
    .nav-item:hover,.nav-item.active{color:#fff;background:rgba(129,140,248,0.08);border-left-color:#818cf8}
    .sb-foot{padding:18px;border-top:1px solid #1e293b}
    .logout-btn{display:flex;align-items:center;gap:9px;padding:10px 14px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);color:#f87171;border-radius:8px;font-size:0.85rem;text-decoration:none;transition:all 0.3s}
    .logout-btn:hover{background:rgba(239,68,68,0.16)}

    /* Main */
    .main{margin-left:240px;flex:1;padding:30px}

    /* Top bar */
    .topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px}
    .topbar h1{font-family:'Space Grotesk',sans-serif;font-size:1.4rem;font-weight:700}
    .topbar p{color:#475569;font-size:0.82rem;margin-top:2px}
    .user-chip{display:flex;align-items:center;gap:10px;background:#0d1526;border:1px solid #1e293b;border-radius:50px;padding:5px 16px 5px 5px}
    .avatar{width:36px;height:36px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.95rem;color:#fff}
    .chip-name{font-size:0.83rem;font-weight:600}
    .chip-sub{font-size:0.72rem;color:#475569}

    /* Stats */
    .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:26px}
    .stat{background:#0d1526;border:1px solid #1e293b;border-radius:12px;padding:20px;transition:border-color 0.3s}
    .stat:hover{border-color:#818cf8}
    .stat .s-icon{font-size:1.5rem;margin-bottom:9px}
    .stat .s-val{font-family:'Space Grotesk',sans-serif;font-size:1.8rem;font-weight:700;color:#818cf8}
    .stat .s-lbl{color:#475569;font-size:0.78rem;margin-top:3px}

    /* Panels */
    .g2{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px}
    .panel{background:#0d1526;border:1px solid #1e293b;border-radius:12px;padding:22px}
    .panel h3{font-size:0.82rem;text-transform:uppercase;letter-spacing:1px;color:#475569;margin-bottom:16px;font-weight:600}

    .irow{display:flex;justify-content:space-between;align-items:flex-start;padding:9px 0;border-bottom:1px solid #131f35;font-size:0.85rem;gap:12px}
    .irow:last-child{border-bottom:none}
    .irow .k{color:#475569;flex-shrink:0}
    .irow .v{color:#e2e8f0;text-align:right;word-break:break-all;font-size:0.82rem}

    /* Session box */
    .session-panel{
      background:#0d1526;
      border:1px solid #1e293b;
      border-radius:12px;
      padding:22px;
      margin-bottom:20px;
    }
    .session-panel h3{font-size:0.82rem;text-transform:uppercase;letter-spacing:1px;color:#475569;margin-bottom:16px;font-weight:600}
    .session-id{
      font-family:monospace;
      font-size:0.78rem;
      background:#131f35;
      border:1px solid #1e293b;
      border-radius:6px;
      padding:10px 14px;
      color:#818cf8;
      word-break:break-all;
      margin-top:8px;
    }

    /* Cookie status */
    .cookie-panel{
      background:#0d1526;
      border:1px solid;
      border-radius:12px;
      padding:22px;
      margin-bottom:20px;
    }
    .cookie-panel.active{border-color:rgba(129,140,248,0.3)}
    .cookie-panel.inactive{border-color:#1e293b}
    .cookie-panel h3{font-size:0.82rem;text-transform:uppercase;letter-spacing:1px;color:#475569;margin-bottom:16px;font-weight:600}

    .cookie-status{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:6px 14px;
      border-radius:50px;
      font-size:0.8rem;
      font-weight:600;
      margin-bottom:14px;
    }
    .cookie-status.on{background:rgba(129,140,248,0.12);color:#818cf8;border:1px solid rgba(129,140,248,0.3)}
    .cookie-status.off{background:rgba(100,116,139,0.1);color:#64748b;border:1px solid #1e293b}

    /* How it works explanation */
    .explainer{
      background:#0a0f1e;
      border:1px solid #1e293b;
      border-radius:12px;
      padding:22px;
      margin-bottom:20px;
    }
    .explainer h3{font-size:0.82rem;text-transform:uppercase;letter-spacing:1px;color:#475569;margin-bottom:16px;font-weight:600}
    .flow{display:flex;flex-direction:column;gap:0}
    .flow-step{display:flex;gap:16px;position:relative;padding-bottom:20px}
    .flow-step:last-child{padding-bottom:0}
    .flow-step::before{content:'';position:absolute;left:15px;top:32px;bottom:0;width:1px;background:#1e293b}
    .flow-step:last-child::before{display:none}
    .step-num{width:30px;height:30px;border-radius:50%;background:rgba(129,140,248,0.15);border:1px solid rgba(129,140,248,0.3);color:#818cf8;font-size:0.78rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;z-index:1}
    .step-body .step-title{font-size:0.87rem;font-weight:600;margin-bottom:3px}
    .step-body .step-desc{font-size:0.78rem;color:#475569;line-height:1.6}
    .step-body code{background:#131f35;color:#a5b4fc;padding:1px 6px;border-radius:4px;font-size:0.75rem}
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="sb-brand">
    <h2>Student<span>Portal</span></h2>
    <span class="sb-role">🎓 Student</span>
  </div>
  <nav class="sb-nav">
    <div class="nav-sec">Main</div>
    <a href="dashboard.php" class="nav-item active">🏠 Dashboard</a>
    <div class="nav-sec">Academic</div>
    <a href="#" class="nav-item">📚 My Courses</a>
    <a href="#" class="nav-item">📊 Grades</a>
    <a href="#" class="nav-item">📅 Timetable</a>
  </nav>
  <div class="sb-foot">
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
  </div>
</aside>

<main class="main">

  <!-- Top bar -->
  <div class="topbar">
    <div>
      <h1>Welcome back, <?= $name ?>! 👋</h1>
      <p>Here's your session and cookie status</p>
    </div>
    <div class="user-chip">
      <div class="avatar"><?= $initials ?></div>
      <div>
        <div class="chip-name"><?= $name ?></div>
        <div class="chip-sub">Student</div>
      </div>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats">
    <div class="stat"><div class="s-icon">📚</div><div class="s-val">6</div><div class="s-lbl">Enrolled Units</div></div>
    <div class="stat"><div class="s-icon">✅</div><div class="s-val">14</div><div class="s-lbl">Completed Tasks</div></div>
    <div class="stat"><div class="s-icon">📊</div><div class="s-val">76%</div><div class="s-lbl">Average Grade</div></div>
    <div class="stat"><div class="s-icon">🍪</div><div class="s-val"><?= $has_cookie ? 'ON' : 'OFF' ?></div><div class="s-lbl">Remember Me</div></div>
  </div>

  <!-- Session + Cookie info side by side -->
  <div class="g2">

    <!-- Session info -->
    <div class="panel">
      <h3>🔐 Active Session</h3>
      <div class="irow"><span class="k">Name</span><span class="v"><?= $name ?></span></div>
      <div class="irow"><span class="k">Email</span><span class="v"><?= $email ?></span></div>
      <div class="irow"><span class="k">Logged in at</span><span class="v"><?= $login_at ?></span></div>
      <div class="irow"><span class="k">Timeout</span><span class="v">30 minutes inactivity</span></div>
      <div class="irow"><span class="k">Session ID</span><span class="v" style="font-family:monospace;font-size:0.72rem;color:#818cf8"><?= substr($session_id, 0, 24) ?>...</span></div>
    </div>

    <!-- Cookie info -->
    <div class="panel">
      <h3>🍪 Remember Me Cookie</h3>
      <div class="cookie-status <?= $has_cookie ? 'on' : 'off' ?>">
        <?= $has_cookie ? '✔ Cookie Active' : '✖ No Cookie Set' ?>
      </div>
      <div class="irow"><span class="k">Saved Email</span><span class="v"><?= $cookie_email ?></span></div>
      <div class="irow"><span class="k">Expires</span><span class="v"><?= $cookie_expiry ?></span></div>
      <div class="irow"><span class="k">Duration</span><span class="v">30 days</span></div>
      <div class="irow"><span class="k">Password stored?</span><span class="v" style="color:#4ade80">Never ✔</span></div>
    </div>
  </div>

  <!-- How sessions & cookies work together -->
  <div class="explainer">
    <h3>💡 How Sessions & Cookies Work Together</h3>
    <div class="flow">

      <div class="flow-step">
        <div class="step-num">1</div>
        <div class="step-body">
          <div class="step-title">User logs in</div>
          <div class="step-desc">PHP verifies email & password against the database using <code>password_verify()</code>.</div>
        </div>
      </div>

      <div class="flow-step">
        <div class="step-num">2</div>
        <div class="step-body">
          <div class="step-title">Session created</div>
          <div class="step-desc"><code>session_start()</code> creates a unique session ID stored on the server. <code>$_SESSION</code> holds the user's name, email and login time for this visit only.</div>
        </div>
      </div>

      <div class="flow-step">
        <div class="step-num">3</div>
        <div class="step-body">
          <div class="step-title">Remember Me cookie set (if checked)</div>
          <div class="step-desc"><code>setcookie('remember_email', $email, time()+2592000)</code> saves the email in the browser for 30 days. The password is never stored in a cookie.</div>
        </div>
      </div>

      <div class="flow-step">
        <div class="step-num">4</div>
        <div class="step-body">
          <div class="step-title">Next visit — cookie pre-fills the form</div>
          <div class="step-desc"><code>$_COOKIE['remember_email']</code> is read on login.php and placed in the email field automatically, saving the user from retyping it.</div>
        </div>
      </div>

      <div class="flow-step">
        <div class="step-num">5</div>
        <div class="step-body">
          <div class="step-title">Logout — session destroyed, cookie cleared</div>
          <div class="step-desc"><code>session_destroy()</code> ends the server session. If the user unchecks Remember Me, <code>setcookie(..., time()-3600)</code> deletes the cookie immediately.</div>
        </div>
      </div>

    </div>
  </div>

</main>
</body>
</html>