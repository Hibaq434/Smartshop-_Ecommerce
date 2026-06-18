<?php
require 'db.php';

$errors  = [];
$success = "";

$departments = ["IT","Human Resources","Finance","Marketing","Operations","Sales","Legal","Customer Support"];
$job_titles  = ["Software Engineer","HR Officer","Accountant","Marketing Analyst","Operations Manager",
                 "Sales Executive","Legal Advisor","Support Agent","Team Lead","Director"];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = trim($_POST['full_name']  ?? '');
    $email      = trim($_POST['email']      ?? '');
    $department = trim($_POST['department'] ?? '');
    $job_title  = trim($_POST['job_title']  ?? '');
    $password   = $_POST['password']  ?? '';
    $confirm    = $_POST['confirm']   ?? '';

    if (empty($full_name))                               $errors[] = "Full name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
                                                         $errors[] = "A valid email is required.";
    if (!in_array($department, $departments))            $errors[] = "Please select a valid department.";
    if (empty($job_title))                               $errors[] = "Job title is required.";
    if (strlen($password) < 6)                           $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm)                          $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM employees WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "An account with that email already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO employees (full_name, email, department, job_title, password)
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$full_name, $email, $department, $job_title, $hash]);
            $success = "Account created successfully! <a href='login.php'>Login here</a>.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register | EmployeePortal</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }

    body {
      font-family:'Segoe UI', sans-serif;
      background:linear-gradient(135deg,#0f2027,#203a43,#2c5364);
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:30px 16px;
    }

    .card {
      background:rgba(255,255,255,0.05);
      backdrop-filter:blur(12px);
      border:1px solid rgba(255,255,255,0.1);
      border-radius:16px;
      padding:40px;
      width:100%;
      max-width:500px;
      color:#fff;
    }

    h2 { font-size:1.8rem; text-align:center; margin-bottom:4px; }
    h2 span { color:#00bcd4; }
    .subtitle { text-align:center; color:#90a4ae; font-size:0.9rem; margin-bottom:28px; }

    .alert { padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:0.88rem; }
    .alert-error   { background:rgba(244,67,54,0.12);  border:1px solid #f44336; color:#f44336; }
    .alert-success { background:rgba(0,188,212,0.12);  border:1px solid #00bcd4; color:#00bcd4; }
    .alert-success a { color:#00bcd4; }

    .row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

    .form-group { margin-bottom:16px; }
    label { display:block; font-size:0.82rem; color:#90a4ae; margin-bottom:5px; }

    input, select {
      width:100%;
      padding:11px 14px;
      background:rgba(255,255,255,0.07);
      border:1px solid rgba(255,255,255,0.15);
      border-radius:8px;
      color:#fff;
      font-size:0.92rem;
      outline:none;
      transition:border 0.3s;
      appearance:none;
    }

    input:focus, select:focus { border-color:#00bcd4; }
    input::placeholder { color:#546e7a; }
    select option { background:#203a43; color:#fff; }

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

    .footer-link { text-align:center; margin-top:20px; font-size:0.87rem; color:#90a4ae; }
    .footer-link a { color:#00bcd4; text-decoration:none; font-weight:600; }
    .footer-link a:hover { text-decoration:underline; }
  </style>
</head>
<body>
<div class="card">
  <h2>Create <span>Account</span></h2>
  <p class="subtitle">Register as a new employee</p>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $e) echo "<div>⚠ $e</div>"; ?>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success">✔ <?= $success ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Full Name</label>
      <input type="text" name="full_name" placeholder="Jane Doe"
             value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required/>
    </div>

    <div class="form-group">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="jane@company.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
    </div>

    <div class="row">
      <div class="form-group">
        <label>Department</label>
        <select name="department" required>
          <option value="">-- Select --</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d ?>" <?= (($_POST['department'] ?? '') === $d) ? 'selected' : '' ?>>
              <?= $d ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Job Title</label>
        <select name="job_title" required>
          <option value="">-- Select --</option>
          <?php foreach ($job_titles as $j): ?>
            <option value="<?= $j ?>" <?= (($_POST['job_title'] ?? '') === $j) ? 'selected' : '' ?>>
              <?= $j ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="row">
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Min. 6 characters" required/>
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm" placeholder="Repeat password" required/>
      </div>
    </div>

    <button type="submit" class="btn">Register</button>
  </form>

  <div class="footer-link">
    Already registered? <a href="login.php">Login</a>
  </div>
</div>
</body>
</html>