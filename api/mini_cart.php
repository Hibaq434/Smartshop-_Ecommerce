<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/app.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'message' => 'Please login to view your cart.', 'items' => [], 'count' => 0, 'total' => '0.00']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

$sql = 'SELECT c.id AS cart_id, c.quantity AS qty,
               p.id AS product_id, p.product_name, p.price, p.image, p.quantity AS stock
          FROM cart c
          JOIN products p ON p.id = c.product_id
         WHERE c.user_id = ?
         ORDER BY c.id DESC
         LIMIT 5';

$items = [];
$total = 0.0;
$hasStockIssue = false;

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $qty = (int)$row['qty'];
        $stock = (int)$row['stock'];
        $subtotal = (float)$row['price'] * $qty;
        $total += $subtotal;
        $outOfStock = $stock <= 0;
        $overStock = !$outOfStock && $qty > $stock;
        if ($outOfStock || $overStock) {
            $hasStockIssue = true;
        }
        $items[] = [
            'name'       => (string)$row['product_name'],
            'qty'        => $qty,
            'image'      => productImageUrl((string)($row['image'] ?? '')),
            'subtotal'   => number_format($subtotal, 2),
            'product_id' => (int)$row['product_id'],
            'stock'      => $stock,
            'outOfStock' => $outOfStock,
            'overStock'  => $overStock,
        ];
    }
    mysqli_stmt_close($stmt);
}

echo json_encode([
    'ok'            => true,
    'items'         => $items,
    'count'         => getCartCount($conn),
    'total'         => number_format($total, 2),
    'hasStockIssue' => $hasStockIssue,
]);