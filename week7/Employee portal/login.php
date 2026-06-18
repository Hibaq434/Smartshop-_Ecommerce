<?php
session_start();
require 'db.php';

if (isset($_SESSION['emp_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ?");
        $stmt->execute([$email]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($emp && password_verify($password, $emp['password'])) {
            // Regenerate session ID to prevent fixation attacks
            session_regenerate_id(true);

            $_SESSION['emp_id']         = $emp['id'];
            $_SESSION['emp_name']       = $emp['full_name'];
            $_SESSION['emp_email']      = $emp['email'];
            $_SESSION['emp_department'] = $emp['department'];
            $_SESSION['emp_job_title']  = $emp['job_title'];
            $_SESSION['emp_role']       = $emp['role'];
            $_SESSION['login_time']     = time();

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login | EmployeePortal</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }

    body {
      font-family:'Segoe UI', sans-serif;
      background:linear-gradient(135deg,#0f2027,#203a43,#2c5364);
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
    }

    .card {
      background:rgba(255,255,255,0.05);
      backdrop-filter:blur(12px);
      border:1px solid rgba(255,255,255,0.1);
      border-radius:16px;
      padding:44px 40px;
      width:100%;
      max-width:400px;
      color:#fff;
    }

    .logo { text-align:center; font-size:2rem; font-weight:800; margin-bottom:6px; }
    .logo span { color:#00bcd4; }
    .subtitle { text-align:center; color:#90a4ae; font-size:0.9rem; margin-bottom:30px; }

    .alert-error {
      background:rgba(244,67,54,0.12);
      border:1px solid #f44336;
      color:#f44336;
      padding:12px 16px;
      border-radius:8px;
      margin-bottom:20px;
      font-size:0.88rem;
    }

    .form-group { margin-bottom:18px; }
    label { display:block; font-size:0.82rem; color:#90a4ae; margin-bottom:5px; }

    input {
      width:100%;
      padding:12px 14px;
      background:rgba(255,255,255,0.07);
      border:1px solid rgba(255,255,255,0.15);
      border-radius:8px;
      color:#fff;
      font-size:0.92rem;
      outline:none;
      transition:border 0.3s;
    }

    input:focus { border-color:#00bcd4; }
    input::placeholder { color:#546e7a; }

    .btn {
      width:100%;
      padding:13px;
      background:#00bcd4;
      color:#0f2027;
      border:none;
      border-radius:8px;
      font-size:1rem;
      font-weight:700;
      cursor:pointer;
      transition:all 0.3s;
      margin-top:6px;
    }

    .btn:hover { background:#00acc1; transform:translateY(-1px); }

    .footer-link { text-align:center; margin-top:22px; font-size:0.87rem; color:#90a4ae; }
    .footer-link a { color:#00bcd4; text-decoration:none; font-weight:600; }
    .footer-link a:hover { text-decoration:underline; }
  </style>
</head>
<body>
<div class="card">
  <div class="logo">Employee<span>Portal</span></div>
  <p class="subtitle">Sign in to your employee account</p>

  <?php if ($error): ?>
    <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="jane@company.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="Your password" required/>
    </div>
    <button type="submit" class="btn">Login</button>
  </form>

  <div class="footer-link">
    New employee? <a href="register.php">Register here</a>
  </div>
</div>
</body>
</html>