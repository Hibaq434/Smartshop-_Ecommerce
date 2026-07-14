<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/includes/product_images.php';

requireAdmin();
ensureProductsImageColumn($conn);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid product id.');
}

$row = null;
$stmt = mysqli_prepare($conn, 'SELECT id, product_name, price, quantity, image FROM products WHERE id = ?');
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

$message = '';

if (isset($_POST['update'])) {
    $product = trim((string)($_POST['product'] ?? ''));
    $price = (float)($_POST['price'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $imageFilename = resolveProductImageFilename($_POST['product_image'] ?? null);

    if ($product === '') {
        $message = 'Product name is required.';
    } else {
        $updateStmt = mysqli_prepare($conn, 'UPDATE products SET product_name = ?, price = ?, quantity = ?, image = ? WHERE id = ?');
        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, 'sdisi', $product, $price, $quantity, $imageFilename, $id);
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

input, select{
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
    margin-bottom: 8px;
}

.preview{
    width: 100%;
    height: 180px;
    object-fit: cover;
    border-radius: 6px;
    margin-bottom: 12px;
    border: 1px solid #ddd;
}

.error{
    color: #b91c1c;
    margin-top: 10px;
}

</style>

</head>

<body>

<div class="container">

<h2>Edit Product</h2>

<img class="preview" src="<?= htmlspecialchars(productImageUrl($row['image'] ?? '')) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt="<?= htmlspecialchars((string)$row['product_name']) ?>">

<form method="POST">

<input type="text"
       name="product"
    value="<?php echo htmlspecialchars((string)$row['product_name']); ?>">

<input type="number"
       name="price"
       step="0.01"
       min="0"
    value="<?php echo htmlspecialchars((string)$row['price']); ?>">

<input type="number"
       name="quantity"
       min="0"
    value="<?php echo htmlspecialchars((string)$row['quantity']); ?>">

<label for="product_image">Product Image</label>
<?= productImageDropdown($row['image'] ?? '', 'product_image') ?>

<button name="update">
Update Product
</button>

</form>

<?php if ($message !== ''): ?>
<p class="error"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

</div>

</body>

</html>