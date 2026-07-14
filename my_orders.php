<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

ensureCoreSchema($conn);

requireLogin();

$activePage = 'orders';
$pageTitle = 'My Orders - SmartShop';
$cartCount = getCartCount($conn);

$userId = (int)($_SESSION['user_id'] ?? 0);
$viewOrderId = (int)($_GET['view'] ?? 0);

$stmt = mysqli_prepare(
    $conn,
    'SELECT id, status, payment_status, total_amount, created_at
       FROM orders
      WHERE user_id = ?
      ORDER BY created_at DESC'
);

$orders = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $orders[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$viewOrder = null;
$viewItems = [];
if ($viewOrderId > 0) {
    $ordStmt = mysqli_prepare(
        $conn,
        'SELECT id, status, payment_status, payment_method, total_amount, shipping_name,
                shipping_phone, shipping_address, shipping_city, created_at
           FROM orders
          WHERE id = ? AND user_id = ?
          LIMIT 1'
    );
    if ($ordStmt) {
        mysqli_stmt_bind_param($ordStmt, 'ii', $viewOrderId, $userId);
        mysqli_stmt_execute($ordStmt);
        $ordRes = mysqli_stmt_get_result($ordStmt);
        $viewOrder = $ordRes ? mysqli_fetch_assoc($ordRes) : null;
        mysqli_stmt_close($ordStmt);
    }

    if ($viewOrder) {
        $itemStmt = mysqli_prepare(
            $conn,
            'SELECT oi.quantity, oi.unit_price, p.product_name, p.image
               FROM order_items oi
               JOIN products p ON p.id = oi.product_id
              WHERE oi.order_id = ?'
        );
        if ($itemStmt) {
            mysqli_stmt_bind_param($itemStmt, 'i', $viewOrderId);
            mysqli_stmt_execute($itemStmt);
            $itemRes = mysqli_stmt_get_result($itemStmt);
            while ($itemRes && ($row = mysqli_fetch_assoc($itemRes))) {
                $viewItems[] = $row;
            }
            mysqli_stmt_close($itemStmt);
        }
    }
}

require __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="section-head">
    <div>
      <h2>My Orders</h2>
      <small><?= count($orders) ?> order(s) placed</small>
    </div>
  </div>

  <?php if ($viewOrder): ?>
    <div class="notice">
      <strong>Order #<?= (int)$viewOrder['id'] ?></strong> &middot;
      Status: <?= h(ucfirst((string)$viewOrder['status'])) ?> &middot;
      Payment: <?= h(ucfirst((string)$viewOrder['payment_status'])) ?> &middot;
      Placed <?= h((string)$viewOrder['created_at']) ?>
    </div>
    <table class="cart-table">
      <thead><tr><th>Product</th><th>Unit Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($viewItems as $item): ?>
          <tr>
            <td style="display:flex;align-items:center;gap:0.6rem">
              <img src="<?= h(productImageUrl((string)($item['image'] ?? ''))) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:0.5rem">
              <?= h((string)$item['product_name']) ?>
            </td>
            <td><?= money($conn, (float)((float)$item['unit_price'])) ?></td>
            <td><?= (int)$item['quantity'] ?></td>
            <td><?= money($conn, (float)((float)$item['unit_price'] * (int)$item['quantity'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p style="margin-top:12px"><strong>Total: <?= money($conn, (float)((float)$viewOrder['total_amount'])) ?></strong></p>
    <a class="btn outline" href="my_orders.php" style="margin-top:12px;display:inline-block">← Back to all orders</a>
  <?php else: ?>
    <?php if (!$orders): ?>
      <div class="notice">You haven't placed any orders yet.</div>
      <div class="hero-actions"><a class="btn primary" href="shop.php">Start Shopping</a></div>
    <?php else: ?>
      <table class="cart-table">
        <thead>
          <tr><th>Order ID</th><th>Status</th><th>Payment Status</th><th>Date</th><th>Total</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td>#<?= (int)$order['id'] ?></td>
              <td><?= h(ucfirst((string)$order['status'])) ?></td>
              <td><?= h(ucfirst((string)$order['payment_status'])) ?></td>
              <td><?= h((string)$order['created_at']) ?></td>
              <td><?= money($conn, (float)((float)$order['total_amount'])) ?></td>
              <td><a class="btn outline" href="my_orders.php?view=<?= (int)$order['id'] ?>">View Details</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>