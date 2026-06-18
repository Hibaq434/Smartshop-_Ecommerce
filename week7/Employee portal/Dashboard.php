<?php require 'auth.php'; ?>
<?php
$name       = htmlspecialchars($_SESSION['emp_name']);
$email      = htmlspecialchars($_SESSION['emp_email']);
$dept       = htmlspecialchars($_SESSION['emp_department']);
$title      = htmlspecialchars($_SESSION['emp_job_title']);
$role       = htmlspecialchars($_SESSION['emp_role']);
$initials   = strtoupper(substr($name, 0, 1));
$login_time = date("D, d M Y · H:i", $_SESSION['login_time']);

// Show timeout warning if 5 min remain
$time_left = SESSION_TIMEOUT - (time() - $_SESSION['login_time']);
$warn_timeout = $time_left < 300;

// Unauthorized message
$unauth = isset($_GET['error']) && $_GET['error'] === 'unauthorized';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard | EmployeePortal</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }

    body { font-family:'Segoe UI',sans-serif; background:#0a0f1e; color:#e0e6f0; min-height:100vh; display:flex; }

    /* ── Sidebar ── */
    .sidebar {
      width:240px;
      background:#0d1526;
      border-right:1px solid #1e2d45;
      display:flex;
      flex-direction:column;
      padding:0;
      position:fixed;
      height:100vh;
      top:0; left:0;
    }

    .sidebar-brand {
      padding:24px 20px;
      font-size:1.2rem;
      font-weight:800;
      border-bottom:1px solid #1e2d45;
    }

    .sidebar-brand span { color:#00bcd4; }

    .sidebar-nav { flex:1; padding:20px 0; }

    .nav-label {
      font-size:0.7rem;
      text-transform:uppercase;
      letter-spacing:1.5px;
      color:#3d5a80;
      padding:10px 20px 6px;
    }

    .nav-item {
      display:flex;
      align-items:center;
      gap:12px;
      padding:11px 20px;
      color:#8899b0;
      text-decoration:none;
      font-size:0.9rem;
      transition:all 0.2s;
      border-left:3px solid transparent;
    }

    .nav-item:hover, .nav-item.active {
      color:#fff;
      background:rgba(0,188,212,0.08);
      border-left-color:#00bcd4;
    }

    .nav-item .icon { font-size:1rem; }

    .sidebar-footer {
      padding:20px;
      border-top:1px solid #1e2d45;
    }

    .logout-btn {
      display:flex;
      align-items:center;
      gap:10px;
      width:100%;
      padding:10px 14px;
      background:rgba(244,67,54,0.1);
      border:1px solid rgba(244,67,54,0.3);
      color:#f44336;
      border-radius:8px;
      font-size:0.88rem;
      text-decoration:none;
      transition:all 0.3s;
      cursor:pointer;
    }

    .logout-btn:hover { background:rgba(244,67,54,0.2); }

    /* ── Main ── */
    .main { margin-left:240px; flex:1; padding:30px; }

    /* ── Top bar ── */
    .topbar {
      display:flex;
      align-items:center;
      justify-content:space-between;
      margin-bottom:28px;
    }

    .topbar h1 { font-size:1.5rem; font-weight:700; }
    .topbar p  { color:#546e7a; font-size:0.85rem; margin-top:2px; }

    .topbar-right { display:flex; align-items:center; gap:14px; }

    .emp-chip {
      display:flex;
      align-items:center;
      gap:10px;
      background:#0d1526;
      border:1px solid #1e2d45;
      border-radius:50px;
      padding:6px 16px 6px 6px;
    }

    .emp-avatar {
      width:34px; height:34px;
      background:linear-gradient(135deg,#00bcd4,#0097a7);
      border-radius:50%;
      display:flex;
      align-items:center;
      justify-content:center;
      font-weight:700;
      font-size:0.9rem;
      color:#0f2027;
    }

    .emp-chip-name { font-size:0.85rem; font-weight:600; }
    .emp-chip-role { font-size:0.75rem; color:#546e7a; text-transform:capitalize; }

    /* ── Alerts ── */
    .alert {
      padding:13px 18px;
      border-radius:10px;
      margin-bottom:22px;
      font-size:0.88rem;
      display:flex;
      align-items:center;
      gap:10px;
    }

    .alert-warn  { background:rgba(255,152,0,0.1);  border:1px solid #ff9800; color:#ff9800; }
    .alert-error { background:rgba(244,67,54,0.1);  border:1px solid #f44336; color:#f44336; }

    /* ── Stats ── */
    .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:18px; margin-bottom:28px; }

    .stat-card {
      background:#0d1526;
      border:1px solid #1e2d45;
      border-radius:12px;
      padding:22px;
      transition:border-color 0.3s;
    }

    .stat-card:hover { border-color:#00bcd4; }
    .stat-card .s-icon { font-size:1.6rem; margin-bottom:10px; }
    .stat-card .s-val  { font-size:2rem; font-weight:700; color:#00bcd4; }
    .stat-card .s-lbl  { color:#546e7a; font-size:0.82rem; margin-top:4px; }

    /* ── Grid Layout ── */
    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }

    .panel {
      background:#0d1526;
      border:1px solid #1e2d45;
      border-radius:12px;
      padding:22px;
    }

    .panel h3 { font-size:1rem; margin-bottom:16px; color:#90a4ae; }

    /* ── Profile info ── */
    .info-row {
      display:flex;
      justify-content:space-between;
      padding:10px 0;
      border-bottom:1px solid #1e2d45;
      font-size:0.88rem;
    }

    .info-row:last-child { border-bottom:none; }
    .info-row .key { color:#546e7a; }

    .role-badge {
      display:inline-block;
      padding:3px 12px;
      border-radius:50px;
      font-size:0.75rem;
      font-weight:600;
      text-transform:capitalize;
    }

    .role-admin    { background:rgba(244,67,54,0.15);  color:#f44336; border:1px solid #f44336; }
    .role-manager  { background:rgba(255,152,0,0.15);  color:#ff9800; border:1px solid #ff9800; }
    .role-employee { background:rgba(0,188,212,0.15);  color:#00bcd4; border:1px solid #00bcd4; }

    /* ── Quick actions ── */
    .actions { display:grid; grid-template-columns:1fr 1fr; gap:12px; }

    .action-btn {
      display:flex;
      align-items:center;
      gap:10px;
      padding:13px 16px;
      background:rgba(0,188,212,0.06);
      border:1px solid rgba(0,188,212,0.2);
      border-radius:10px;
      color:#e0e6f0;
      text-decoration:none;
      font-size:0.85rem;
      transition:all 0.2s;
    }

    .action-btn:hover { background:rgba(0,188,212,0.14); border-color:#00bcd4; }
    .action-btn .a-icon { font-size:1.2rem; }

    /* ── Announcements ── */
    .announce-item {
      padding:12px 0;
      border-bottom:1px solid #1e2d45;
      font-size:0.87rem;
    }

    .announce-item:last-child { border-bottom:none; }
    .announce-item .a-title { font-weight:600; margin-bottom:3px; }
    .announce-item .a-meta  { color:#546e7a; font-size:0.78rem; }

    /* ── Session bar ── */
    .session-bar {
      background:#0d1526;
      border:1px solid #1e2d45;
      border-radius:10px;
      padding:14px 20px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      font-size:0.83rem;
      color:#546e7a;
      margin-top:24px;
    }

    .session-bar span { color:#00bcd4; }
  </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-brand">Employee<span>Portal</span></div>

  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" class="nav-item active"><span class="icon">🏠</span> Dashboard</a>
    <a href="profile.php"   class="nav-item"><span class="icon">👤</span> My Profile</a>

    <div class="nav-label">Work</div>
    <a href="#" class="nav-item"><span class="icon">📋</span> Tasks</a>
    <a href="#" class="nav-item"><span class="icon">📅</span> Schedule</a>
    <a href="#" class="nav-item"><span class="icon">📁</span> Documents</a>

    <?php if (in_array($role, ['manager','admin'])): ?>
    <div class="nav-label">Management</div>
    <a href="admin.php" class="nav-item"><span class="icon">👥</span> All Employees</a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
  </div>
</aside>

<!-- Main Content -->
<main class="main">

  <!-- Top bar -->
  <div class="topbar">
    <div>
      <h1>Dashboard</h1>
      <p>Welcome back, <?= $name ?>!</p>
    </div>
    <div class="topbar-right">
      <div class="emp-chip">
        <div class="emp-avatar"><?= $initials ?></div>
        <div>
          <div class="emp-chip-name"><?= $name ?></div>
          <div class="emp-chip-role"><?= $role ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Alerts -->
  <?php if ($warn_timeout): ?>
    <div class="alert alert-warn">⏰ Your session will expire in less than 5 minutes. Save your work!</div>
  <?php endif; ?>

  <?php if ($unauth): ?>
    <div class="alert alert-error">🔒 You do not have permission to access that page.</div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card">
      <div class="s-icon">📋</div>
      <div class="s-val">8</div>
      <div class="s-lbl">Active Tasks</div>
    </div>
    <div class="stat-card">
      <div class="s-icon">✅</div>
      <div class="s-val">23</div>
      <div class="s-lbl">Completed Tasks</div>
    </div>
    <div class="stat-card">
      <div class="s-icon">📅</div>
      <div class="s-val">3</div>
      <div class="s-lbl">Upcoming Meetings</div>
    </div>
    <div class="stat-card">
      <div class="s-icon">🏖️</div>
      <div class="s-val">12</div>
      <div class="s-lbl">Leave Days Left</div>
    </div>
  </div>

  <!-- Grid panels -->
  <div class="grid-2">

    <!-- Profile Info -->
    <div class="panel">
      <h3>👤 My Information</h3>
      <div class="info-row"><span class="key">Name</span><span><?= $name ?></span></div>
      <div class="info-row"><span class="key">Email</span><span><?= $email ?></span></div>
      <div class="info-row"><span class="key">Department</span><span><?= $dept ?></span></div>
      <div class="info-row"><span class="key">Job Title</span><span><?= $title ?></span></div>
      <div class="info-row">
        <span class="key">Role</span>
        <span class="role-badge role-<?= $role ?>"><?= $role ?></span>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="panel">
      <h3>⚡ Quick Actions</h3>
      <div class="actions">
        <a href="profile.php" class="action-btn"><span class="a-icon">✏️</span> Edit Profile</a>
        <a href="#" class="action-btn"><span class="a-icon">📤</span> Submit Report</a>
        <a href="#" class="action-btn"><span class="a-icon">🗓️</span> Book Leave</a>
        <a href="#" class="action-btn"><span class="a-icon">📨</span> Send Message</a>
        <?php if (in_array($role, ['manager','admin'])): ?>
        <a href="admin.php" class="action-btn"><span class="a-icon">👥</span> Manage Team</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Announcements -->
  <div class="panel">
    <h3>📢 Company Announcements</h3>
    <div class="announce-item">
      <div class="a-title">🎉 Annual Company Retreat — July 2026</div>
      <div class="a-meta">HR Department · June 10, 2026</div>
    </div>
    <div class="announce-item">
      <div class="a-title">🔐 Mandatory Security Training</div>
      <div class="a-meta">IT Department · June 5, 2026</div>
    </div>
    <div class="announce-item">
      <div class="a-title">📊 Q2 Performance Reviews Open</div>
      <div class="a-meta">Management · June 1, 2026</div>
    </div>
  </div>

  <!-- Session info -->
  <div class="session-bar">
    <div>🔐 Session started: <span><?= $login_time ?></span></div>
    <div>⏱ Auto-logout after 30 minutes of inactivity</div>
  </div>

</main>
</body>
</html>