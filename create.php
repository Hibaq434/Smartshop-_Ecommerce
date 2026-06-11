<?php

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product = trim((string)($_POST['product'] ?? ''));
    $price = (float)($_POST['price'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);

    if ($product === '') {
        $message = 'Product name is required.';
    } else {
        $stmt = mysqli_prepare($conn, 'INSERT INTO products (product_name, price, quantity) VALUES (?, ?, ?)');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sdi', $product, $price, $quantity);
            if (mysqli_stmt_execute($stmt)) {
                // If the user came from the new UI, send them back there.
                header('Location: index.php?p=admin&section=products&msg=' . urlencode('Product added'));
                exit;
            }
            mysqli_stmt_close($stmt);
        }
        $message = 'Failed to add product.';
    }
}

?>

<!DOCTYPE html>
<html>

<head>

<title>Create Product</title>

<style>

body{
    font-family: Arial;
    background: #f2f2f2;
}

.container{
    width: 350px;
    margin: 40px auto;
    background: white;
    padding: 20px;
}

input{
    width: 100%;
    padding: 10px;
    margin-bottom: 12px;
}

button{
    width: 100%;
    padding: 10px;
    background: #24345c;
    color: white;
    border: none;
}

.success{
    color: green;
    margin-top: 10px;
}

</style>

</head>

<body>

<div class="container">

<h2>Add Product</h2>

<form method="POST">

<input type="text" name="product" placeholder="Product Name" required>

<input type="number" name="price" placeholder="Price" required>

<input type="number" name="quantity" placeholder="Quantity" required>

<button>Add Product</button>

</form>

<p class="success"><?php echo $message; ?></p>

</div>

</body>

</html>