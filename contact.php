<?php

$message = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $name = $_POST['name'];
    $email = $_POST['email'];
    $subject = $_POST['subject'];

    $message = "
    <div class='success'>
        ✅ Message Sent Successfully!<br><br>
        Thank you, <strong>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</strong>!<br>
        We have received your query about <em>'" . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . "'</em>.<br>
        We will get back to you at " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . " within 24 hours.
    </div>
    ";

}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us — SmartShop</title>
    <style>
        :root {
            --blue: #2563EB;
            --blue-dark: #1d4ed8;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-500: #64748b;
            --gray-700: #334155;
            --gray-900: #0f172a;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #f0f7ff 0%, #e8f4fd 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--gray-900);
        }

        .nav {
            display: flex;
            align-items: center;
            padding: 0 24px;
            height: 52px;
            border-bottom: 1px solid var(--gray-200);
            background: #fff;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-logo {
            font-size: 18px;
            font-weight: 700;
            color: var(--blue);
            letter-spacing: -0.5px;
            text-decoration: none;
        }

        .container {
            max-width: 400px;
            width: 90%;
            margin: 60px auto;
            background: white;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-200);
        }

        h3 {
            margin-top: 0;
            margin-bottom: 24px;
            font-size: 20px;
            font-weight: 700;
            color: var(--gray-900);
            text-align: center;
        }

        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 24px;
            background: var(--gray-100);
            padding: 4px;
            border-radius: 8px;
        }

        .tabs a {
            flex: 1;
            text-decoration: none;
        }

        .tabs button {
            width: 100%;
            padding: 8px 12px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            color: var(--gray-500);
            border-radius: 6px;
            transition: all 0.15s;
        }

        .tabs button:hover {
            color: var(--gray-700);
        }

        .tabs .active {
            background: white;
            color: var(--blue);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: var(--gray-500);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input, textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.15s;
            background: var(--gray-50);
            font-family: inherit;
        }

        textarea {
            height: 100px;
            resize: vertical;
        }

        input:focus, textarea:focus {
            border-color: var(--blue);
            background: white;
        }

        .contact-btn {
            width: 100%;
            padding: 12px;
            background: var(--blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.15s;
            margin-top: 8px;
        }

        .contact-btn:hover {
            background: var(--blue-dark);
        }

        .success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #15803d;
            padding: 14px;
            margin-top: 20px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
        }
    </style>
</head>
<body>

<nav class="nav">
    <a href="index.php" class="nav-logo">SmartShop</a>
</nav>

<div class="container">
    <h3>Smart E-Commerce</h3>

    <div class="tabs">
        <a href="register.php">
            <button>Register</button>
        </a>
        <a href="login.php">
            <button>Login</button>
        </a>
        <a href="contact.php">
            <button class="active">Contact</button>
        </a>
    </div>

    <form method="POST">
        <div class="form-group">
            <label for="name">Your Name</label>
            <input type="text" id="name" name="name" placeholder="Enter your name" required autocomplete="name">
        </div>

        <div class="form-group">
            <label for="email">Your Email</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" required autocomplete="email">
        </div>

        <div class="form-group">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" placeholder="Enter subject" required>
        </div>

        <div class="form-group">
            <label for="message">Your Message</label>
            <textarea id="message" name="message" placeholder="Type your message here..." required></textarea>
        </div>

        <button class="contact-btn" type="submit">Send Message</button>
    </form>

    <?php echo $message; ?>
</div>

</body>
</html>