<?php
session_start();

// Guard: must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$name  = htmlspecialchars($_SESSION['user_name']);
$email = htmlspecialchars($_SESSION['user_email']);
$initials = strtoupper(substr($name, 0, 1));

// Get joined date (just today as demo – real app would pull from DB)
$joined = date("F j, Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard | StudentPortal</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Segoe UI', sans-serif;
      background: #0d1117;
      color: #e6edf3;
      min-height: 100vh;
    }

    /* ── Navbar ── */
    nav {
      background: #161b22;
      border-bottom: 1px solid #30363d;
      padding: 0 30px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 60px;
    }

    .nav-brand { font-size: 1.3rem; font-weight: 700; }
    .nav-brand span { color: #e94560; }

    .nav-right { display: flex; align-items: center; gap: 16px; }

    .avatar {
      width: 38px; height: 38px;
      background: #e94560;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1rem;
    }

    .logout-btn {
      padding: 7px 18px;
      background: transparent;
      border: 1px solid #e94560;
      color: #e94560;
      border-radius: 6px;
      font-size: 0.85rem;
      text-decoration: none;
      transition: all 0.3s;
    }

    .logout-btn:hover { background: #e94560; color: #fff; }

    /* ── Layout ── */
    .container { max-width: 1100px; margin: 0 auto; padding: 36px 20px; }

    .welcome-banner {
      background: linear-gradient(135deg, #161b22, #1f2937);
      border: 1px solid #30363d;
      border-radius: 14px;
      padding: 30px;
      margin-bottom: 30px;
      display: flex;
      align-items: center;
      gap: 22px;
    }

    .welcome-avatar {
      width: 70px; height: 70px;
      background: linear-gradient(135deg, #e94560, #c73652);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      font-weight: 700;
      flex-shrink: 0;
    }

    .welcome-text h2 { font-size: 1.6rem; margin-bottom: 4px; }
    .welcome-text p { color: #8b949e; font-size: 0.9rem; }

    /* ── Stats ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 18px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: #161b22;
      border: 1px solid #30363d;
      border-radius: 12px;
      padding: 22px;
    }

    .stat-card .icon {
      font-size: 1.8rem;
      margin-bottom: 10px;
    }

    .stat-card .value {
      font-size: 2rem;
      font-weight: 700;
      color: #e94560;
    }

    .stat-card .label { color: #8b949e; font-size: 0.85rem; margin-top: 4px; }

    /* ── Modules ── */
    .section-title {
      font-size: 1.1rem;
      font-weight: 600;
      color: #8b949e;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 16px;
    }

    .modules-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 16px;
    }

    .module-card {
      background: #161b22;
      border: 1px solid #30363d;
      border-radius: 12px;
      padding: 20px;
      cursor: default;
      transition: transform 0.2s, border-color 0.2s;
    }

    .module-card:hover { transform: translateY(-3px); border-color: #e94560; }

    .module-card .mod-icon { font-size: 1.5rem; margin-bottom: 10px; }
    .module-card h4 { font-size: 1rem; margin-bottom: 4px; }
    .module-card p { color: #8b949e; font-size: 0.82rem; }

    .badge-new {
      display: inline-block;
      background: rgba(233,69,96,0.15);
      border: 1px solid #e94560;
      color: #e94560;
      font-size: 0.7rem;
      padding: 2px 8px;
      border-radius: 50px;
      margin-left: 8px;
      vertical-align: middle;
    }

    /* ── Profile Info ── */
    .info-card {
      background: #161b22;
      border: 1px solid #30363d;
      border-radius: 12px;
      padding: 24px;
      margin-top: 28px;
    }

    .info-row {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid #21262d;
      font-size: 0.9rem;
    }

    .info-row:last-child { border-bottom: none; }
    .info-row .key { color: #8b949e; }
    .info-row .val { font-weight: 500; }
  </style>
</head>
<body>

<!-- Navbar -->
<nav>
  <div class="nav-brand">Smart<span>Portal</span></div>
  <div class="nav-right">
    <div class="avatar"><?= $initials ?></div>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</nav>

<!-- Main Content -->
<div class="container">

  <!-- Welcome Banner -->
  <div class="welcome-banner">
    <div class="welcome-avatar"><?= $initials ?></div>
    <div class="welcome-text">
      <h2>Welcome back, <?= $name ?>! 👋</h2>
      <p><?= $email ?> &nbsp;·&nbsp; Student Account &nbsp;·&nbsp; Active</p>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="icon">📚</div>
      <div class="value">6</div>
      <div class="label">Enrolled Courses</div>
    </div>
    <div class="stat-card">
      <div class="icon">✅</div>
      <div class="value">14</div>
      <div class="label">Assignments Done</div>
    </div>
    <div class="stat-card">
      <div class="icon">📊</div>
      <div class="value">78%</div>
      <div class="label">Average Grade</div>
    </div>
    <div class="stat-card">
      <div class="icon">🏅</div>
      <div class="value">3</div>
      <div class="label">Achievements</div>
    </div>
  </div>

  <!-- Modules -->
  <div class="section-title">My Modules</div>
  <div class="modules-grid">
    <div class="module-card">
      <div class="mod-icon">💻</div>
      <h4>Web Development <span class="badge-new">NEW</span></h4>
      <p>HTML, CSS, PHP, MySQL fundamentals</p>
    </div>
    <div class="module-card">
      <div class="mod-icon">🗄️</div>
      <h4>Database Systems</h4>
      <p>Relational databases & SQL queries</p>
    </div>
    <div class="module-card">
      <div class="mod-icon">🐍</div>
      <h4>Python Programming</h4>
      <p>Scripting, OOP & data handling</p>
    </div>
    <div class="module-card">
      <div class="mod-icon">🔐</div>
      <h4>Cybersecurity Basics</h4>
      <p>Security principles & best practices</p>
    </div>
    <div class="module-card">
      <div class="mod-icon">🌐</div>
      <h4>Networking</h4>
      <p>Protocols, OSI model & TCP/IP</p>
    </div>
    <div class="module-card">
      <div class="mod-icon">📱</div>
      <h4>Mobile Development</h4>
      <p>Android basics & UI design</p>
    </div>
  </div>

  <!-- Account Info -->
  <div class="info-card">
    <div class="section-title" style="margin-bottom:12px">Account Details</div>
    <div class="info-row"><span class="key">Full Name</span><span class="val"><?= $name ?></span></div>
    <div class="info-row"><span class="key">Email</span><span class="val"><?= $email ?></span></div>
    <div class="info-row"><span class="key">Role</span><span class="val">Student</span></div>
    <div class="info-row"><span class="key">Member Since</span><span class="val"><?= $joined ?></span></div>
    <div class="info-row"><span class="key">Status</span><span class="val" style="color:#2ed573">✔ Active</span></div>
  </div>

</div>
</body>
</html>