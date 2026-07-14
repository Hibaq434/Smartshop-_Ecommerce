<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/includes/product_images.php';

requireAdmin();
ensureProductsImageColumn($conn);

$sql = 'SELECT id, product_name, price, quantity, image FROM products ORDER BY id DESC';
$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html>

<head>

<title>Product List</title>

<style>

body{
    font-family: Arial, sans-serif;
    background: #f2f2f2;
}

.container{
    width: 90%;
    margin: 40px auto;
    background: white;
    padding: 20px;
    border-radius: 6px;
}

h2{
    color: #24345c;
}

.add-btn{
    background: #28a745;
    color: white;
    padding: 10px 15px;
    text-decoration: none;
    border-radius: 4px;
    font-size: 14px;
}

table{
    width: 100%;
    margin-top: 20px;
    border-collapse: collapse;
}

th{
    background: #24345c;
    color: white;
    padding: 12px;
    text-align: left;
}

td{
    padding: 12px;
    border-bottom: 1px solid #ddd;
}

.edit{
    background: orange;
    color: white;
    padding: 6px 10px;
    text-decoration: none;
    border-radius: 4px;
    font-size: 13px;
}

.delete{
    background: red;
    color: white;
    padding: 6px 10px;
    text-decoration: none;
    border-radius: 4px;
    font-size: 13px;
}

</style>

</head>

<body>

<div class="container">

<h2>Product List</h2>

<a class="add-btn" href="create.php">
+ Add New Product
</a>

<table>

<tr>

<th>ID</th>
<th>Image</th>
<th>Product Name</th>
<th>Price</th>
<th>Quantity</th>
<th>Actions</th>

</tr>

<?php

while($row = mysqli_fetch_assoc($result)){

?>

<tr>

<td><?php echo htmlspecialchars((string)$row['id']); ?></td>

<td>
<img src="<?php echo htmlspecialchars(productImageUrl($row['image'] ?? '')); ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';"
     alt="<?php echo htmlspecialchars((string)$row['product_name']); ?>"
     style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid #ddd;">
</td>

<td><?php echo htmlspecialchars((string)$row['product_name']); ?></td>

<td>$<?php echo htmlspecialchars((string)$row['price']); ?></td>

<td><?php echo htmlspecialchars((string)$row['quantity']); ?></td>

<td>

<a class="edit"
href="edit.php?id=<?php echo $row['id']; ?>">
Edit
</a>

<a class="delete"
href="delete.php?id=<?php echo $row['id']; ?>">
Delete
</a>

</td>

</tr>

<?php } ?>

</table>

</div>

</body>

</html>