<?php
require 'auth.php';
require_role('manager');   // only managers and admins can access
require 'db.php';

$stmt = $pdo->query("SELECT id, full_name, email, department, job_title, role, created_at FROM employees ORDER BY created_at DESC");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
$role = htmlspecialchars($_SESSION['emp_role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>All Employees | EmployeePortal</title>
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
      display:flex; align-items:center; gap:10px; padding:10px 14px;
      background:rgba(244,67,54,0.1); border:1px solid rgba(244,67,54,0.3);
      color:#f44336; border-radius:8px; font-size:0.88rem; text-decoration:none; transition:all 0.3s;
    }
    .logout-btn:hover { background:rgba(244,67,54,0.2); }

    .main { margin-left:240px; flex:1; padding:30px; }

    h1 { font-size:1.5rem; margin-bottom:4px; }
    .sub { color:#546e7a; font-size:0.85rem; margin-bottom:28px; }

    .access-badge {
      display:inline-block; background:rgba(244,67,54,0.12); border:1px solid #f44336;
      color:#f44336; padding:4px 14px; border-radius:50px; font-size:0.75rem; margin-bottom:20px;
    }

    table { width:100%; border-collapse:collapse; background:#0d1526; border-radius:12px; overflow:hidden; }

    thead th {
      padding:14px 16px; text-align:left; font-size:0.78rem;
      text-transform:uppercase; letter-spacing:1px; color:#546e7a;
      border-bottom:1px solid #1e2d45;
    }

    tbody tr { border-bottom:1px solid #1e2d45; transition:background 0.2s; }
    tbody tr:last-child { border-bottom:none; }
    tbody tr:hover { background:rgba(0,188,212,0.04); }

    td { padding:13px 16px; font-size:0.87rem; }

    .role-badge {
      display:inline-block; padding:3px 10px; border-radius:50px;
      font-size:0.72rem; font-weight:600; text-transform:capitalize;
    }
    .role-admin    { background:rgba(244,67,54,0.15); color:#f44336; border:1px solid #f44336; }
    .role-manager  { background:rgba(255,152,0,0.15); color:#ff9800; border:1px solid #ff9800; }
    .role-employee { background:rgba(0,188,212,0.15); color:#00bcd4; border:1px solid #00bcd4; }

    .count { color:#00bcd4; font-weight:700; }
  </style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">Employee<span>Portal</span></div>
  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="dashboard.php" class="nav-item"><span>🏠</span> Dashboard</a>
    <a href="profile.php"   class="nav-item"><span>👤</span> My Profile</a>
    <div class="nav-label">Management</div>
    <a href="admin.php" class="nav-item active"><span>👥</span> All Employees</a>
  </nav>
  <div class="sidebar-footer">
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
  </div>
</aside>

<main class="main">
  <div class="access-badge">🔒 Manager / Admin Only</div>
  <h1>All Employees</h1>
  <p class="sub">Total: <span class="count"><?= count($employees) ?></span> registered employees</p>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Name</th>
        <th>Email</th>
        <th>Department</th>
        <th>Job Title</th>
        <th>Role</th>
        <th>Joined</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($employees as $i => $emp): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><?= htmlspecialchars($emp['full_name']) ?></td>
        <td><?= htmlspecialchars($emp['email']) ?></td>
        <td><?= htmlspecialchars($emp['department']) ?></td>
        <td><?= htmlspecialchars($emp['job_title']) ?></td>
        <td><span class="role-badge role-<?= $emp['role'] ?>"><?= $emp['role'] ?></span></td>
        <td><?= date("d M Y", strtotime($emp['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</main>
</body>
</html>