<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/session_helper.php';

// Already logged in → redirect based on role
if (isLoggedIn()) {
    header(isAdmin() ? 'Location: index.php?p=admin' : 'Location: index.php');
    exit;
}

$error = (string)($_GET['error'] ?? '');
$successMsg = (string)($_GET['msg'] ?? '');
$loginError = '';

// ── Handle login submission ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $loginError = 'Please enter both username and password.';
    } else {
        $stmt = mysqli_prepare($conn,
            'SELECT id, username, email, password_hash, role, full_name
               FROM users WHERE username = ? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user   = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if ($user && password_verify($password, (string)$user['password_hash'])) {
                // Success — set session
                session_regenerate_id(true);
                $_SESSION['user_id']   = (int)$user['id'];
                $_SESSION['username']  = (string)$user['username'];
                $_SESSION['full_name'] = (string)($user['full_name'] ?? $user['username']);
                $_SESSION['role']      = (string)$user['role'];

                // Redirect by role
                if ($user['role'] === 'admin') {
                    header('Location: index.php?p=admin&msg=' . urlencode('Welcome back, ' . $user['username'] . '! You are logged in as Admin.'));
                } else {
                    header('Location: index.php?msg=' . urlencode('Welcome back, ' . $user['username'] . '!'));
                }
                exit;
            } else {
                $loginError = 'Invalid username or password.';
            }
        } else {
            $loginError = 'Database error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — SmartShop</title>
    <style>
        :root {
            --blue: #2563EB;
            --blue-dark: #1d4ed8;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-500: #64748b;
            --gray-700: #334155;
            --gray-900: #0f172a;
            --red: #dc2626;
            --green: #16a34a;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #f0f7ff 0%, #e8f4fd 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .nav {
            display: flex;
            align-items: center;
            padding: 0 24px;
            height: 52px;
            border-bottom: 1px solid var(--gray-200);
            background: #fff;
        }
        .nav-logo {
            font-size: 18px;
            font-weight: 700;
            color: var(--blue);
            text-decoration: none;
        }

        .container {
            max-width: 420px;
            width: 92%;
            margin: 52px auto;
            background: #fff;
            padding: 36px 32px;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0,0,0,.07);
            border: 1px solid var(--gray-200);
        }

        .logo-wrap {
            text-align: center;
            margin-bottom: 24px;
        }
        .logo-icon {
            width: 52px; height: 52px;
            background: linear-gradient(135deg, #1e3a5f, #2563EB);
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 10px;
        }
        h3 { font-size: 20px; font-weight: 700; color: var(--gray-900); text-align: center; margin-bottom: 4px; }
        .sub { font-size: 13px; color: var(--gray-500); text-align: center; margin-bottom: 28px; }

        /* Role tabs */
        .role-tabs { display: flex; gap: 4px; background: var(--gray-100); padding: 4px; border-radius: 8px; margin-bottom: 24px; }
        .role-tab  { flex: 1; padding: 9px 0; text-align: center; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; color: var(--gray-500); transition: all .2s; border: none; background: transparent; }
        .role-tab.active { background: #fff; color: var(--blue); box-shadow: 0 1px 4px rgba(0,0,0,.1); }
        .role-tab .icon { display: block; font-size: 18px; margin-bottom: 2px; }

        .form-group { margin-bottom: 18px; }
        label {
            display: block;
            font-size: 11px; font-weight: 600; color: var(--gray-500);
            text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px;
        }
        input {
            width: 100%; padding: 10px 14px;
            border: 1.5px solid var(--gray-200); border-radius: 8px;
            font-size: 14px; outline: none;
            transition: border-color .15s;
            background: var(--gray-50);
            font-family: inherit;
        }
        input:focus { border-color: var(--blue); background: #fff; }

        .login-btn {
            width: 100%; padding: 12px;
            background: var(--blue); color: #fff;
            border: none; border-radius: 8px;
            font-weight: 700; font-size: 14px;
            cursor: pointer; transition: background .15s;
            letter-spacing: .3px;
        }
        .login-btn:hover { background: var(--blue-dark); }

        .error-box {
            background: #fee2e2; border: 1px solid #fecaca; color: #b91c1c;
            padding: 12px 14px; border-radius: 8px; font-size: 13px;
            margin-bottom: 18px;
        }

        /* Demo credentials card */
        .demo-card {
            margin-top: 24px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            padding: 14px 16px;
        }
        .demo-title { font-size: 11px; font-weight: 700; color: var(--gray-500); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; }
        .demo-row   { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid var(--gray-200); font-size: 12px; }
        .demo-row:last-child { border: none; }
        .demo-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 700; }
        .badge-admin { background: #dbeafe; color: #1e40af; }
        .badge-user  { background: #dcfce7; color: #15803d; }
        .demo-cred   { color: var(--gray-700); font-family: monospace; font-size: 12px; }

        .register-link { text-align: center; margin-top: 22px; font-size: 13px; color: var(--gray-500); }
        .register-link a { color: var(--blue); text-decoration: none; font-weight: 600; }
        .register-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<nav class="nav">
    <a href="index.php" class="nav-logo">SmartShop</a>
</nav>

<div class="container">
    <div class="logo-wrap">
        <div class="logo-icon">🛍️</div>
        <h3>Welcome back</h3>
        <p class="sub">Login to your SmartShop account</p>
    </div>

    <!-- Visual role switcher (cosmetic — role comes from DB) -->
    <div class="role-tabs" id="roleTabs">
        <button class="role-tab active" id="tabUser"  onclick="setTab('user')">
            <span class="icon">👤</span>Customer
        </button>
        <button class="role-tab"        id="tabAdmin" onclick="setTab('admin')">
            <span class="icon">🛡️</span>Admin
        </button>
    </div>

    <?php if ($successMsg !== ''): ?>
        <div style="background:#dcfce7;border:1px solid #bbf7d0;color:#15803d;padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:18px;">✅ <?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if ($loginError !== ''): ?>
        <div class="error-box">⚠️ <?= htmlspecialchars($loginError) ?></div>
    <?php elseif ($error !== ''): ?>
        <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="loginForm">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   placeholder="Enter your username"
                   value="<?= htmlspecialchars((string)($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                   required autocomplete="username">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   placeholder="Enter your password"
                   required autocomplete="current-password">
        </div>
        <button class="login-btn" type="submit" id="loginBtn">Login as Customer</button>
    </form>

    <!-- Demo credentials -->
    <div class="demo-card">
        <div class="demo-title">Demo Accounts</div>
        <div class="demo-row">
            <span><span class="demo-badge badge-admin">Admin</span></span>
            <span class="demo-cred">admin / Admin@123</span>
        </div>
        <div class="demo-row">
            <span><span class="demo-badge badge-user">User</span></span>
            <span class="demo-cred">john / User@123</span>
        </div>
    </div>

    <div class="register-link">
        Do not have account? <a href="register.php">Register</a>
    </div>
</div>

<script>
function setTab(role) {
    const isAdmin = role === 'admin';
    document.getElementById('tabUser').classList.toggle('active', !isAdmin);
    document.getElementById('tabAdmin').classList.toggle('active', isAdmin);
    document.getElementById('loginBtn').textContent = isAdmin ? 'Login as Admin' : 'Login as Customer';
}
</script>
</body>
</html>