<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/app.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'message' => 'Login required.']);
    exit;
}

$cartId = (int)($_POST['cart_id'] ?? 0);
if ($cartId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid cart item.']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$del = mysqli_prepare($conn, 'DELETE FROM cart WHERE id = ? AND user_id = ?');
if (!$del) {
    echo json_encode(['ok' => false, 'message' => 'Database error.']);
    exit;
}

mysqli_stmt_bind_param($del, 'ii', $cartId, $userId);
mysqli_stmt_execute($del);
mysqli_stmt_close($del);

echo json_encode(['ok' => true, 'count' => getCartCount($conn)]);
