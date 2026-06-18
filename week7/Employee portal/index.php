<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Employee Portal</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }

    body {
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .hero { text-align:center; color:#fff; padding:40px; }

    .badge {
      display:inline-block;
      background:rgba(0,188,212,0.15);
      border:1px solid #00bcd4;
      color:#00bcd4;
      padding:6px 20px;
      border-radius:50px;
      font-size:0.85rem;
      letter-spacing:1px;
      margin-bottom:22px;
    }

    h1 { font-size:3rem; font-weight:800; margin-bottom:10px; letter-spacing:2px; }
    h1 span { color:#00bcd4; }

    p { color:#90a4ae; font-size:1.05rem; margin-bottom:40px; }

    .btn-group { display:flex; gap:20px; justify-content:center; flex-wrap:wrap; }

    .btn {
      padding:14px 38px;
      border-radius:8px;
      font-size:1rem;
      font-weight:600;
      text-decoration:none;
      transition:all 0.3s;
      cursor:pointer;
      border:none;
    }

    .btn-primary { background:#00bcd4; color:#0f2027; }
    .btn-primary:hover { background:#00acc1; transform:translateY(-2px); }

    .btn-outline { background:transparent; color:#fff; border:2px solid #fff; }
    .btn-outline:hover { background:#fff; color:#0f2027; transform:translateY(-2px); }

    .features {
      display:flex;
      gap:30px;
      justify-content:center;
      margin-top:50px;
      flex-wrap:wrap;
    }

    .feature { text-align:center; color:#90a4ae; font-size:0.85rem; }
    .feature .icon { font-size:1.8rem; margin-bottom:6px; }
  </style>
</head>
<body>
<div class="hero">
  <div class="badge">🏢 Company Internal System</div>
  <h1>Employee<span>Portal</span></h1>
  <p>Manage your workforce. Secure. Fast. Simple.</p>
  <div class="btn-group">
    <a href="login.php" class="btn btn-primary">Login</a>
    <a href="register.php" class="btn btn-outline">Register</a>
  </div>

  <div class="features">
    <div class="feature"><div class="icon">🔐</div>Secure Login</div>
    <div class="feature"><div class="icon">👤</div>Employee Profiles</div>
    <div class="feature"><div class="icon">📋</div>Department Management</div>
    <div class="feature"><div class="icon">🛡️</div>Role-Based Access</div>
  </div>
</div>
</body>
</html>