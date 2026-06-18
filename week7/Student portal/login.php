<?php
session_start();
require 'db.php';

// Already logged in → go to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email']= $user['email'];
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
  <title>Login | StudentPortal</title>
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
      max-width: 400px;
      color: #fff;
    }

    .logo { text-align: center; font-size: 2.2rem; font-weight: 700; margin-bottom: 6px; }
    .logo span { color: #e94560; }
    .subtitle { text-align: center; color: #a8b2d8; font-size: 0.9rem; margin-bottom: 28px; }

    .alert-error {
      background: rgba(233,69,96,0.15);
      border: 1px solid #e94560;
      color: #e94560;
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 0.9rem;
    }

    .form-group { margin-bottom: 18px; }

    label { display: block; font-size: 0.85rem; color: #a8b2d8; margin-bottom: 6px; }

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
  <div class="logo">Smart<span>Portal</span></div>
  <p class="subtitle">Sign in to your student account</p>

  <?php if ($error): ?>
    <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="john@university.ac.ke"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" placeholder="Your password" required/>
    </div>
    <button type="submit" class="btn">Login</button>
  </form>

  <div class="footer-link">
    Don't have an account? <a href="register.php">Register</a>
  </div>
</div>
</body>
</html>