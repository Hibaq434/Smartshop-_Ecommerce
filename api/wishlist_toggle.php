<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/app.php';

header('Content-Type: application/json; charset=utf-8');

ensureWishlistTable($conn);

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'message' => 'Please login to use your wishlist.']);
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($productId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid product.']);
    exit;
}

$checkStmt = mysqli_prepare($conn, 'SELECT id FROM wishlist WHERE user_id = ? AND product_id = ? LIMIT 1');
mysqli_stmt_bind_param($checkStmt, 'ii', $userId, $productId);
mysqli_stmt_execute($checkStmt);
mysqli_stmt_store_result($checkStmt);
$exists = mysqli_stmt_num_rows($checkStmt) > 0;
mysqli_stmt_close($checkStmt);

if ($exists) {
    $stmt = mysqli_prepare($conn, 'DELETE FROM wishlist WHERE user_id = ? AND product_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $userId, $productId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['ok' => true, 'inWishlist' => false, 'message' => 'Removed from wishlist.']);
    exit;
}

$stmt = mysqli_prepare($conn, 'INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)');
mysqli_stmt_bind_param($stmt, 'ii', $userId, $productId);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

echo json_encode(['ok' => $ok, 'inWishlist' => $ok, 'message' => $ok ? 'Added to wishlist.' : 'Could not update wishlist.']);
