<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/app.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'message' => 'Please login to add items to cart.']);
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$qty = (int)($_POST['quantity'] ?? 1);
if ($productId <= 0 || $qty <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid cart request.']);
    exit;
}

$checkStmt = mysqli_prepare($conn, 'SELECT id, quantity FROM products WHERE id = ? LIMIT 1');
if (!$checkStmt) {
    echo json_encode(['ok' => false, 'message' => 'Database error.']);
    exit;
}

mysqli_stmt_bind_param($checkStmt, 'i', $productId);
mysqli_stmt_execute($checkStmt);
$checkRes = mysqli_stmt_get_result($checkStmt);
$product = $checkRes ? mysqli_fetch_assoc($checkRes) : null;
mysqli_stmt_close($checkStmt);

if (!$product) {
    echo json_encode(['ok' => false, 'message' => 'Product not found.']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$upsert = mysqli_prepare(
    $conn,
    'INSERT INTO cart (user_id, product_id, quantity)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
);

if (!$upsert) {
    echo json_encode(['ok' => false, 'message' => 'Could not update cart.']);
    exit;
}

mysqli_stmt_bind_param($upsert, 'iii', $userId, $productId, $qty);
$ok = mysqli_stmt_execute($upsert);
mysqli_stmt_close($upsert);

if (!$ok) {
    echo json_encode(['ok' => false, 'message' => 'Failed to add item.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Product added to cart.',
    'count' => getCartCount($conn),
]);
