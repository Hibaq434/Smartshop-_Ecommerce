<?php

$message = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $username = $_POST['username'];
    $password = $_POST['password'];

    // Demo credentials

    if(($username == "admin" && $password == "admin123") ||

       ($username == "wendy" && $password == "wendy123")){

        $message = "
        <div class='success'>
            ✅ Login Successful<br><br>

            Welcome $username!
        </div>
        ";

    }else{

        $message = "
        <div class='error'>
            Invalid username or password!
        </div>
        ";

    }

}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Smart E-Commerce Login</title>

    <style>

        body{
            font-family: Arial, sans-serif;
            background: #f2f2f2;
        }

        .container{
            width: 320px;
            margin: 60px auto;
            background: white;
            padding: 20px;
            border-radius: 6px;
        }

        h2{
            margin-bottom: 20px;
        }

        input{
            width: 100%;
            padding: 10px;
            margin-bottom: 12px;
            border: 1px solid #ccc;
            background: #eef3ff;
        }

        .login-btn{
            width: 100%;
            padding: 10px;
            background: #24345c;
            color: white;
            border: none;
            cursor: pointer;
        }

        .error{
            color: red;
            text-align: center;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .success{
            background: #e8f5e9;
            border: 1px solid #4caf50;
            color: #2e7d32;
            padding: 10px;
            margin-top: 15px;
            border-radius: 4px;
            text-align: center;
        }

        .demo{
            background: #eef3ff;
            margin-top: 15px;
            padding: 10px;
            text-align: center;
            font-size: 12px;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>Smart E-Commerce Login</h2>

    <?php echo $message; ?>

    <form method="POST">

        <input type="text"
               name="username"
               placeholder="Username"
               required>

        <input type="password"
               name="password"
               placeholder="Password"
               required>

        <button class="login-btn">
            Login
        </button>

    </form>

    <div class="demo">

        <strong>Demo Credentials:</strong><br><br>

        Username: admin / Password: admin123<br>

        Username: Hibaq/ Password: Hibaq123

    </div>

</div>

</body>

</html>