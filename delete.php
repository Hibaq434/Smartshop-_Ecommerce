<?php

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid product id.');
}

if (isset($_POST['confirm'])) {
    $stmt = mysqli_prepare($conn, 'DELETE FROM products WHERE id = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header('Location: read.php');
    exit;
}

if (isset($_POST['cancel'])) {
    header('Location: read.php');
    exit;
}

?>

<!DOCTYPE html>
<html>

<head>

<title>Delete Product</title>

<style>

body{
    font-family: Arial;
    background: #f2f2f2;
}

.container{
    width: 350px;
    margin: 100px auto;
    background: white;
    padding: 25px;
    border-radius: 6px;
    text-align: center;
}

h2{
    color: #d32f2f;
}

button{
    padding: 10px 20px;
    border: none;
    color: white;
    cursor: pointer;
    margin: 10px;
}

.delete-btn{
    background: red;
}

.cancel-btn{
    background: gray;
}

</style>

</head>

<body>

<div class="container">

<h2>Delete Confirmation</h2>

<p>
Are you sure you want to delete this product?
</p>

<form method="POST">

<button class="delete-btn"
        name="confirm">
        Yes, Delete
</button>

<button class="cancel-btn"
        name="cancel">
        Cancel
</button>

</form>

</div>

</body>

</html>