<?php
require 'db.php';

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    // Validation
    if (empty($name))    $errors[] = "Full name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
                         $errors[] = "A valid email is required.";
    if (strlen($password) < 6)
                         $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm)
                         $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        // Check duplicate email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "An account with that email already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $hash]);
            $success = "Account created! <a href='login.php'>Login here</a>.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register | StudentPortal</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .card {
      background: rgba(255,255,255,0.05);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 16px;
      padding: 40px;
      width: 100%;
      max-width: 430px;
      color: #fff;
    }

    .card h2 {
      font-size: 1.8rem;
      margin-bottom: 6px;
      text-align: center;
    }

    .card h2 span { color: #e94560; }
    .subtitle { text-align: center; color: #a8b2d8; font-size: 0.9rem; margin-bottom: 28px; }

    .alert {
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 0.9rem;
    }

    .alert-error { background: rgba(233,69,96,0.15); border: 1px solid #e94560; color: #e94560; }
    .alert-success { background: rgba(46,213,115,0.15); border: 1px solid #2ed573; color: #2ed573; }
    .alert-success a { color: #2ed573; }

    .form-group { margin-bottom: 18px; }

    label {
      display: block;
      font-size: 0.85rem;
      color: #a8b2d8;
      margin-bottom: 6px;
    }

    input {
      width: 100%;
      padding: 12px 14px;
      background: rgba(255,255,255,0.07);
      border: 1px solid rgba(255,255,255,0.15);
      border-radius: 8px;
      color: #fff;
      font-size: 0.95rem;
      outline: none;
      transition: border 0.3s;
    }

    input:focus { border-color: #e94560; }
    input::placeholder { color: #555e7b; }

    .btn {
      width: 100%;
      padding: 13px;
      background: #e94560;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.3s, transform 0.2s;
      margin-top: 6px;
    }

    .btn:hover { background: #c73652; transform: translateY(-1px); }

    .footer-link {
      text-align: center;
      margin-top: 22px;
      font-size: 0.88rem;
      color: #a8b2d8;
    }

    .footer-link a { color: #e94560; text-decoration: none; font-weight: 600; }
    .footer-link a:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="card">
  <h2>Create <span>Account</span></h2>
  <p class="subtitle">Join the student portal today</p>

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
      <input type="text" name="name" placeholder="John Doe"
             value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required/>
    </div>
    <div class="form-group">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="john@university.ac.ke"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="Min. 6 characters" required/>
    </div>
    <div class="form-group">
      <label>Confirm Password</label>
      <input type="password" name="confirm" placeholder="Repeat password" required/>
    </div>
    <button type="submit" class="btn">Register</button>
  </form>

  <div class="footer-link">
    Already have an account? <a href="login.php">Login</a>
  </div>
</div>
</body>
</html>