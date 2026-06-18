<?php require 'auth.php'; ?>
<?php
$name     = htmlspecialchars($_SESSION['emp_name']);
$email    = htmlspecialchars($_SESSION['emp_email']);
$dept     = htmlspecialchars($_SESSION['emp_department']);
$title    = htmlspecialchars($_SESSION['emp_job_title']);
$role     = htmlspecialchars($_SESSION['emp_role']);
$initials = strtoupper(substr($name, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Profile | EmployeePortal</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Segoe UI',sans-serif; background:#0a0f1e; color:#e0e6f0; min-height:100vh; display:flex; }

    .sidebar {
      width:240px; background:#0d1526; border-right:1px solid #1e2d45;
      display:flex; flex-direction:column; position:fixed; height:100vh;
    }

    .sidebar-brand { padding:24px 20px; font-size:1.2rem; font-weight:800; border-bottom:1px solid #1e2d45; }
    .sidebar-brand span { color:#00bcd4; }

    .sidebar-nav { flex:1; padding:20px 0; }
    .nav-label { font-size:0.7rem; text-transform:uppercase; letter-spacing:1.5px; color:#3d5a80; padding:10px 20px 6px; }

    .nav-item {
      display:flex; align-items:center; gap:12px; padding:11px 20px;
      color:#8899b0; text-decoration:none; font-size:0.9rem;
      transition:all 0.2s; border-left:3px solid transparent;
    }
    .nav-item:hover, .nav-item.active { color:#fff; background:rgba(0,188,212,0.08); border-left-color:#00bcd4; }

    .sidebar-footer { padding:20px; border-top:1px solid #1e2d45; }
    .logout-btn {
      display:flex; align-items:center; gap:10px; width:100%; padding:10px 14px;
      background:rgba(244,67,54,0.1); border:1px solid rgba(244,67,54,0.3);
      color:#f44336; border-radius:8px; font-size:0.88rem; text-decoration:none; transition:all 0.3s;
    }
    .logout-btn:hover { background:rgba(244,67,54,0.2); }

    .main { margin-left:240px; flex:1; padding:30px; max-width:700px; }

    h1 { font-size:1.5rem; margin-bottom:6px; }
    .sub { color:#546e7a; font-size:0.85rem; margin-bottom:28px; }

    .profile-header {
      background:#0d1526; border:1px solid #1e2d45; border-radius:14px;
      padding:28px; display:flex; align-items:center; gap:22px; margin-bottom:24px;
    }

    .big-avatar {
      width:80px; height:80px;
      background:linear-gradient(135deg,#00bcd4,#0097a7);
      border-radius:50%; display:flex; align-items:center; justify-content:center;
      font-size:2.2rem; font-weight:800; color:#0f2027; flex-shrink:0;
    }

    .profile-meta h2 { font-size:1.4rem; }
    .profile-meta p  { color:#546e7a; font-size:0.88rem; margin-top:4px; }

    .role-badge {
      display:inline-block; padding:3px 12px; border-radius:50px; font-size:0.75rem;
      font-weight:600; text-transform:capitalize; margin-top:8px;
      background:rgba(0,188,212,0.15); color:#00bcd4; border:1px solid #00bcd4;
    }

    .panel { background:#0d1526; border:1px solid #1e2d45; border-radius:12px; padding:24px; margin-bottom:20px; }
    .panel h3 { font-size:1rem; color:#90a4ae; margin-bottom:16px; }

    .info-row { display:flex; justify-content:space-between; padding:11px 0; border-bottom:1px solid #1e2d45; font-size:0.88rem; }
    .info-row:last-child { border-bottom:none; }
    .info-row .key { color:#546e7a; }

    .status-dot { display:inline-block; width:8px; height:8px; background:#4caf50; border-radius:50%; margin-right:6px; }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">Employee<span>Portal</span></div>
  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" class="nav-item"><span>🏠</span> Dashboard</a>
    <a href="profile.php"   class="nav-item active"><span>👤</span> My Profile</a>
    <div class="nav-label">Work</div>
    <a href="#" class="nav-item"><span>📋</span> Tasks</a>
    <a href="#" class="nav-item"><span>📅</span> Schedule</a>
    <?php if (in_array($role, ['manager','admin'])): ?>
    <div class="nav-label">Management</div>
    <a href="admin.php" class="nav-item"><span>👥</span> All Employees</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
  </div>
</aside>

<main class="main">
  <h1>My Profile</h1>
  <p class="sub">View your employee information</p>

  <div class="profile-header">
    <div class="big-avatar"><?= $initials ?></div>
    <div class="profile-meta">
      <h2><?= $name ?></h2>
      <p><?= $title ?> · <?= $dept ?></p>
      <span class="role-badge"><?= $role ?></span>
    </div>
  </div>

  <div class="panel">
    <h3>📋 Account Details</h3>
    <div class="info-row"><span class="key">Full Name</span><span><?= $name ?></span></div>
    <div class="info-row"><span class="key">Email</span><span><?= $email ?></span></div>
    <div class="info-row"><span class="key">Department</span><span><?= $dept ?></span></div>
    <div class="info-row"><span class="key">Job Title</span><span><?= $title ?></span></div>
    <div class="info-row"><span class="key">Role</span><span><?= ucfirst($role) ?></span></div>
    <div class="info-row">
      <span class="key">Status</span>
      <span><span class="status-dot"></span>Active</span>
    </div>
  </div>
</main>
</body>
</html>