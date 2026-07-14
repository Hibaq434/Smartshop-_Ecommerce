<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/app.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'message' => 'Login required.']);
    exit;
}

$cartId = (int)($_POST['cart_id'] ?? 0);
$delta = (int)($_POST['delta'] ?? 0);
if ($cartId <= 0 || !in_array($delta, [-1, 1], true)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$find = mysqli_prepare($conn, 'SELECT quantity FROM cart WHERE id = ? AND user_id = ? LIMIT 1');
if (!$find) {
    echo json_encode(['ok' => false, 'message' => 'Database error.']);
    exit;
}

mysqli_stmt_bind_param($find, 'ii', $cartId, $userId);
mysqli_stmt_execute($find);
$res = mysqli_stmt_get_result($find);
$row = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($find);

if (!$row) {
    echo json_encode(['ok' => false, 'message' => 'Cart item not found.']);
    exit;
}

$newQty = (int)$row['quantity'] + $delta;
if ($newQty <= 0) {
    $del = mysqli_prepare($conn, 'DELETE FROM cart WHERE id = ? AND user_id = ?');
    if ($del) {
        mysqli_stmt_bind_param($del, 'ii', $cartId, $userId);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
    }
} else {
    $upd = mysqli_prepare($conn, 'UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?');
    if ($upd) {
        mysqli_stmt_bind_param($upd, 'iii', $newQty, $cartId, $userId);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }
}

echo json_encode(['ok' => true, 'count' => getCartCount($conn)]);
