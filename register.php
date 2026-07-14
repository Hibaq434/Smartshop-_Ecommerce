<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/includes/app.php';

ensureCoreSchema($conn);

// Already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname  = trim((string)($_POST['fullname'] ?? ''));
    $email     = trim((string)($_POST['email'] ?? ''));
    $username  = trim((string)($_POST['username'] ?? ''));
    $password  = (string)($_POST['password'] ?? '');
    $confirm   = (string)($_POST['confirm_password'] ?? '');

    // Validate
    if ($fullname === '' || $email === '' || $username === '' || $password === '') {
        $errorMsg = 'All fields are required.';
    } elseif (strlen($password) < 6) {
        $errorMsg = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $errorMsg = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Please enter a valid email address.';
    } else {
        // Check username / email uniqueness
        $check = mysqli_prepare($conn,
            'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        if ($check) {
            mysqli_stmt_bind_param($check, 'ss', $username, $email);
            mysqli_stmt_execute($check);
            mysqli_stmt_store_result($check);
            $exists = mysqli_stmt_num_rows($check) > 0;
            mysqli_stmt_close($check);

            if ($exists) {
                $errorMsg = 'Username or email already taken. Please choose another.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn,
                    'INSERT INTO users (username, email, password_hash, role, full_name)
                     VALUES (?, ?, ?, \'user\', ?)');
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ssss',
                        $username, $email, $hash, $fullname);
                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);
                        header('Location: login.php?msg=' . urlencode('Registration successful! Please login.'));
                        exit;
                    } else {
                        $errorMsg = 'Registration failed: ' . mysqli_stmt_error($stmt);
                        mysqli_stmt_close($stmt);
                    }
                } else {
                    $errorMsg = 'Database error. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — SmartShop</title>
    <style>
        :root {
            --blue: #2563EB; --blue-dark: #1d4ed8;
            --gray-50: #f8fafc; --gray-100: #f1f5f9; --gray-200: #e2e8f0;
            --gray-500: #64748b; --gray-700: #334155; --gray-900: #0f172a;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #f0f7ff 0%, #e8f4fd 100%);
            min-height: 100vh; display: flex; flex-direction: column;
        }
        .nav { display:flex; align-items:center; padding:0 24px; height:52px; border-bottom:1px solid var(--gray-200); background:#fff; }
        .nav-logo { font-size:18px; font-weight:700; color:var(--blue); text-decoration:none; }

        .container {
            max-width: 420px; width: 92%; margin: 40px auto;
            background: #fff; padding: 36px 32px; border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0,0,0,.07); border: 1px solid var(--gray-200);
        }
        .logo-wrap { text-align:center; margin-bottom:24px; }
        .logo-icon { width:52px; height:52px; background:linear-gradient(135deg,#1e3a5f,#2563EB); border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:24px; margin-bottom:10px; }
        h3 { font-size:20px; font-weight:700; color:var(--gray-900); text-align:center; margin-bottom:4px; }
        .sub { font-size:13px; color:var(--gray-500); text-align:center; margin-bottom:28px; }

        .tabs { display:flex; gap:4px; background:var(--gray-100); padding:4px; border-radius:8px; margin-bottom:24px; }
        .tabs a { flex:1; text-decoration:none; }
        .tabs button { width:100%; padding:8px; border:none; background:transparent; cursor:pointer; font-weight:600; font-size:13px; color:var(--gray-500); border-radius:6px; transition:all .15s; }
        .tabs button.active { background:#fff; color:var(--blue); box-shadow:0 1px 4px rgba(0,0,0,.1); }

        .form-group { margin-bottom:16px; }
        label { display:block; font-size:11px; font-weight:600; color:var(--gray-500); text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px; }
        input { width:100%; padding:10px 14px; border:1.5px solid var(--gray-200); border-radius:8px; font-size:14px; outline:none; transition:border-color .15s; background:var(--gray-50); font-family:inherit; }
        input:focus { border-color:var(--blue); background:#fff; }

        .register-btn { width:100%; padding:12px; background:var(--blue); color:#fff; border:none; border-radius:8px; font-weight:700; font-size:14px; cursor:pointer; transition:background .15s; }
        .register-btn:hover { background:var(--blue-dark); }

        .error-box   { background:#fee2e2; border:1px solid #fecaca; color:#b91c1c; padding:12px 14px; border-radius:8px; font-size:13px; margin-bottom:18px; }
        .success-box { background:#dcfce7; border:1px solid #bbf7d0; color:#15803d; padding:12px 14px; border-radius:8px; font-size:13px; margin-bottom:18px; }

        .login-link { text-align:center; margin-top:22px; font-size:13px; color:var(--gray-500); }
        .login-link a { color:var(--blue); text-decoration:none; font-weight:600; }
        .login-link a:hover { text-decoration:underline; }
    </style>
</head>
<body>
<nav class="nav"><a href="index.php" class="nav-logo">SmartShop</a></nav>

<div class="container">
    <div class="logo-wrap">
        <div class="logo-icon">✨</div>
        <h3>Create Account</h3>
        <p class="sub">Join SmartShop as a customer today</p>
    </div>

    <div class="tabs">
        <a href="register.php"><button class="active">Register</button></a>
        <a href="login.php"><button>Login</button></a>
        <a href="contact.php"><button>Contact</button></a>
    </div>

    <?php if ($errorMsg !== ''): ?>
        <div class="error-box">⚠️ <?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>
    <?php if ($successMsg !== ''): ?>
        <div class="success-box">✅ <?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="fullname">Full Name</label>
            <input type="text" id="fullname" name="fullname" placeholder="Your full name" required
                   value="<?= htmlspecialchars((string)($_POST['fullname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="Choose a username" required
                   value="<?= htmlspecialchars((string)($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="your@email.com" required
                   value="<?= htmlspecialchars((string)($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label for="password">Password <span style="font-weight:400;text-transform:none">(min 6 chars)</span></label>
            <input type="password" id="password" name="password" placeholder="Create a password" required>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat your password" required>
        </div>
        <button class="register-btn" type="submit">Create Account</button>
    </form>

    <div class="login-link">Already have an account? <a href="login.php">Login</a></div>
</div>
</body>
</html>