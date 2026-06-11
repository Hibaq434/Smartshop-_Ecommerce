<?php

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid product id.');
}

$row = null;
$stmt = mysqli_prepare($conn, 'SELECT id, product_name, price, quantity FROM products WHERE id = ?');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}

if (!$row) {
    http_response_code(404);
    exit('Product not found.');
}

if (isset($_POST['update'])) {
    $product = trim((string)($_POST['product'] ?? ''));
    $price = (float)($_POST['price'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);

    if ($product === '') {
        // fall through and re-render the form
    } else {
        $updateStmt = mysqli_prepare($conn, 'UPDATE products SET product_name = ?, price = ?, quantity = ? WHERE id = ?');
        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, 'sdii', $product, $price, $quantity, $id);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
        }
        header('Location: read.php');
        exit;
    }
}

?>

<!DOCTYPE html>
<html>

<head>

<title>Edit Product</title>

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

</style>

</head>

<body>

<div class="container">

<h2>Edit Product</h2>

<form method="POST">

<input type="text"
       name="product"
    value="<?php echo htmlspecialchars((string)$row['product_name']); ?>">

<input type="number"
       name="price"
    value="<?php echo htmlspecialchars((string)$row['price']); ?>">

<input type="number"
       name="quantity"
    value="<?php echo htmlspecialchars((string)$row['quantity']); ?>">

<button name="update">
Update Product
</button>

</form>

</div>

</body>

</html>