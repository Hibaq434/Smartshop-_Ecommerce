<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/dbconnect.php';
require_once __DIR__ . '/session_helper.php';
require_once __DIR__ . '/includes/product_images.php';

requireAdmin();
ensureProductsImageColumn($conn);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product = trim((string)($_POST['product'] ?? ''));
    $price = (float)($_POST['price'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $imageFilename = resolveProductImageFilename($_POST['product_image'] ?? null);

    if ($product === '') {
        $message = 'Product name is required.';
    } else {
        $stmt = mysqli_prepare($conn, 'INSERT INTO products (product_name, price, quantity, image) VALUES (?, ?, ?, ?)');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sdis', $product, $price, $quantity, $imageFilename);
            if (mysqli_stmt_execute($stmt)) {
                header('Location: dashboard.php?section=products&msg=' . urlencode('Product added'));
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
}

.success{
    color: green;
    margin-top: 10px;
}

.error{
    color: #b91c1c;
    margin-top: 10px;
}

</style>

</head>

<body>

<div class="container">

<h2>Add Product</h2>

<form method="POST">

<input type="text" name="product" placeholder="Product Name" required>

<input type="number" name="price" placeholder="Price" step="0.01" min="0" required>

<input type="number" name="quantity" placeholder="Quantity" min="0" required>

<label for="product_image">Product Image</label>
<?= productImageDropdown(PRODUCT_IMAGE_DEFAULT_FILE, 'product_image') ?>

<button>Add Product</button>

</form>

<?php if ($message !== ''): ?>
<p class="<?= str_contains($message, 'Failed') ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

</div>

</body>

</html>
