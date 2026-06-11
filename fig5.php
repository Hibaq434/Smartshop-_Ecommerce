<!DOCTYPE html>
<html>

<head>

    <title>Dynamic User Input Handler</title>

    <style>

        body{
            font-family: Arial, sans-serif;
            background-color: #f2f2f2;
        }

        .container{
            width: 400px;
            margin: 40px auto;
            background: white;
            padding: 25px;
            border-radius: 8px;
        }

        h2{
            color: #1f2d4d;
        }

        input{
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            margin-bottom: 15px;
        }

        .preview{
            background: #eef3ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

    </style>

</head>

<body>

<div class="container">

    <h2>Dynamic User Input Handler</h2>

    <p>Smart E-Commerce Order Form</p>

    <hr>

    <div class="preview">

        <strong>📄 Live Preview:</strong><br><br>

        Name: <?php echo $_GET['name'] ?? 'Your Name'; ?><br>

        Email: <?php echo $_GET['email'] ?? 'example@gmail.com'; ?><br>

        Product: <?php echo $_GET['product'] ?? 'MacBook Air'; ?><br>

        Quantity: <?php echo $_GET['quantity'] ?? '1'; ?>

    </div>

    <form method="GET">

        <label>Full Name:</label>

        <input type="text" name="name">

        <label>Email Address:</label>

        <input type="email" name="email">

        <label>Product:</label>

        <input type="text" name="product">

        <label>Quantity:</label>

        <input type="number" name="quantity">

        <input type="submit" value="Submit">

    </form>

</div>

</body>

</html>