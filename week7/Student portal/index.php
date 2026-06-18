<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Student Portal</title>
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

    .hero {
      text-align: center;
      color: #fff;
      padding: 40px;
    }

    .hero h1 {
      font-size: 3rem;
      margin-bottom: 10px;
      letter-spacing: 2px;
    }

    .hero h1 span { color: #e94560; }

    .hero p {
      font-size: 1.1rem;
      color: #a8b2d8;
      margin-bottom: 40px;
    }

    .btn-group { display: flex; gap: 20px; justify-content: center; }

    .btn {
      padding: 14px 36px;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s;
      cursor: pointer;
      border: none;
    }

    .btn-primary {
      background: #e94560;
      color: #fff;
    }

    .btn-primary:hover { background: #c73652; transform: translateY(-2px); }

    .btn-outline {
      background: transparent;
      color: #fff;
      border: 2px solid #fff;
    }

    .btn-outline:hover { background: #fff; color: #1a1a2e; transform: translateY(-2px); }

    .badge {
      display: inline-block;
      background: rgba(233,69,96,0.15);
      border: 1px solid #e94560;
      color: #e94560;
      padding: 6px 18px;
      border-radius: 50px;
      font-size: 0.85rem;
      margin-bottom: 20px;
      letter-spacing: 1px;
    }
  </style>
</head>
<body>
  <div class="hero">
    <div class="badge">🎓 Welcome</div>
    <h1>Smart<span>Portal</span></h1>
    <p>Your gateway to academic resources and student services.</p>
    <div class="btn-group">
      <a href="login.php" class="btn btn-primary">Login</a>
      <a href="register.php" class="btn btn-outline">Register</a>
    </div>
  </div>
</body>
</html>