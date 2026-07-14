<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

ensureCoreSchema($conn);

requireLogin();

$activePage = 'checkout';
$pageTitle = 'Checkout - SmartShop';
$cartCount = getCartCount($conn);
$userId = (int)($_SESSION['user_id'] ?? 0);

$errors = [];

/**
 * Fetch the current user's cart, joined with live product data.
 * Reused for both the order-summary display and for building the order itself.
 */
function fetchCartForCheckout(mysqli $conn, int $userId): array
{
    $items = [];
    $sql = 'SELECT c.id AS cart_id, c.quantity AS qty,
                   p.id AS product_id, p.product_name, p.price, p.image, p.quantity AS stock
              FROM cart c
              JOIN products p ON p.id = c.product_id
             WHERE c.user_id = ?
             ORDER BY c.id ASC';

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $row['subtotal'] = (float)$row['price'] * (int)$row['qty'];
            $items[] = $row;
        }
        mysqli_stmt_close($stmt);
    }

    return $items;
}

// ── Handle "order placed" confirmation view ──────────────────────────
$successOrder = null;
$successItems = [];

if (isset($_GET['success']) && (int)($_GET['order_id'] ?? 0) > 0) {
    $orderId = (int)$_GET['order_id'];

    $ordStmt = mysqli_prepare(
        $conn,
        'SELECT id, total_amount, shipping_name, shipping_phone, shipping_address, shipping_city,
                payment_method, payment_status, status, created_at
           FROM orders
          WHERE id = ? AND user_id = ?
          LIMIT 1'
    );
    if ($ordStmt) {
        mysqli_stmt_bind_param($ordStmt, 'ii', $orderId, $userId);
        mysqli_stmt_execute($ordStmt);
        $ordRes = mysqli_stmt_get_result($ordStmt);
        $successOrder = $ordRes ? mysqli_fetch_assoc($ordRes) : null;
        mysqli_stmt_close($ordStmt);
    }

    if ($successOrder) {
        $itemStmt = mysqli_prepare(
            $conn,
            'SELECT oi.quantity, oi.unit_price, p.product_name, p.image
               FROM order_items oi
               JOIN products p ON p.id = oi.product_id
              WHERE oi.order_id = ?
              ORDER BY oi.id ASC'
        );
        if ($itemStmt) {
            mysqli_stmt_bind_param($itemStmt, 'i', $orderId);
            mysqli_stmt_execute($itemStmt);
            $itemRes = mysqli_stmt_get_result($itemStmt);
            while ($itemRes && ($row = mysqli_fetch_assoc($itemRes))) {
                $successItems[] = $row;
            }
            mysqli_stmt_close($itemStmt);
        }
    }
}

// ── Handle order submission ──────────────────────────────────────────
$formValues = [
    'shipping_name' => currentFullName(),
    'shipping_phone' => '',
    'shipping_address' => '',
    'shipping_city' => '',
    'payment_method' => 'mpesa',
    'notes' => '',
];

if (!$successOrder && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['shipping_name'] = trim((string)($_POST['shipping_name'] ?? ''));
    $formValues['shipping_phone'] = trim((string)($_POST['shipping_phone'] ?? ''));
    $formValues['shipping_address'] = trim((string)($_POST['shipping_address'] ?? ''));
    $formValues['shipping_city'] = trim((string)($_POST['shipping_city'] ?? ''));
    $formValues['payment_method'] = trim((string)($_POST['payment_method'] ?? ''));
    $formValues['notes'] = trim((string)($_POST['notes'] ?? ''));

    $allowedPayments = ['cod' => 'Cash on Delivery', 'mpesa' => 'M-Pesa', 'card' => 'Card'];

    if ($formValues['shipping_name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if ($formValues['shipping_phone'] === '') {
        $errors[] = 'Phone number is required.';
    }
    if ($formValues['shipping_city'] === '') {
        $errors[] = 'City is required.';
    }
    if (!array_key_exists($formValues['payment_method'], $allowedPayments)) {
        $errors[] = 'Please select a valid payment method.';
    }

    $cartItems = fetchCartForCheckout($conn, $userId);
    if (!$cartItems) {
        $errors[] = 'Your cart is empty.';
    }

    foreach ($cartItems as $item) {
        if ((int)$item['qty'] > (int)$item['stock']) {
            $errors[] = h((string)$item['product_name']) . ' only has ' . (int)$item['stock'] . ' unit(s) left in stock.';
        }
    }

    if (!$errors) {
        $total = 0.0;
        foreach ($cartItems as $item) {
            $total += $item['subtotal'];
        }

        mysqli_begin_transaction($conn);
        $failure = null;
        $newOrderId = 0;

        $insOrder = mysqli_prepare(
            $conn,
            'INSERT INTO orders
                (user_id, status, total_amount, shipping_name, shipping_phone, shipping_address, shipping_city, payment_method, payment_status, notes)
             VALUES (?, "pending", ?, ?, ?, ?, ?, ?, "unpaid", ?)'
        );

        if (!$insOrder) {
            $failure = 'Could not create your order. Please try again.';
        } else {
            $addressParam = $formValues['shipping_address'] !== '' ? $formValues['shipping_address'] : null;
            $notesParam = $formValues['notes'] !== '' ? $formValues['notes'] : null;

            mysqli_stmt_bind_param(
                $insOrder,
                'idssssss',
                $userId,
                $total,
                $formValues['shipping_name'],
                $formValues['shipping_phone'],
                $addressParam,
                $formValues['shipping_city'],
                $formValues['payment_method'],
                $notesParam
            );

            if (!mysqli_stmt_execute($insOrder)) {
                $failure = 'Could not create your order. Please try again.';
            } else {
                $newOrderId = (int)mysqli_insert_id($conn);
            }
            mysqli_stmt_close($insOrder);
        }

        if (!$failure && $newOrderId > 0) {
            // Stock is NOT deducted here. It's only decremented once an admin
            // marks the order as "shipped" (see dashboard.php orders panel),
            // so pending/unfulfilled orders don't tie up inventory.
            foreach ($cartItems as $item) {
                $productId = (int)$item['product_id'];
                $qty = (int)$item['qty'];
                $unitPrice = (float)$item['price'];

                $insItem = mysqli_prepare(
                    $conn,
                    'INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)'
                );
                if (!$insItem) {
                    $failure = 'Could not save your order items.';
                    break;
                }
                mysqli_stmt_bind_param($insItem, 'iiid', $newOrderId, $productId, $qty, $unitPrice);
                $itemOk = mysqli_stmt_execute($insItem);
                mysqli_stmt_close($insItem);

                if (!$itemOk) {
                    $failure = 'Could not save your order items.';
                    break;
                }
            }
        }

        if (!$failure && $newOrderId > 0) {
            $delCart = mysqli_prepare($conn, 'DELETE FROM cart WHERE user_id = ?');
            if ($delCart) {
                mysqli_stmt_bind_param($delCart, 'i', $userId);
                mysqli_stmt_execute($delCart);
                mysqli_stmt_close($delCart);
            }
        }

        if ($failure) {
            mysqli_rollback($conn);
            $errors[] = $failure;
        } else {
            mysqli_commit($conn);
            header('Location: checkout.php?success=1&order_id=' . $newOrderId);
            exit;
        }
    }
}

$cartItems = $successOrder ? [] : fetchCartForCheckout($conn, $userId);
$grandTotal = 0.0;
foreach ($cartItems as $item) {
    $grandTotal += $item['subtotal'];
}

require __DIR__ . '/includes/header.php';
?>

<section class="section">

  <?php if ($successOrder): ?>

    <div class="section-head">
      <div>
        <h2>Order Placed Successfully</h2>
        <small>Thank you, <?= h((string)$successOrder['shipping_name']) ?> — your order has been received.</small>
      </div>
    </div>

    <div class="notice">
      Order #<?= (int)$successOrder['id'] ?> &middot; Status: <?= h(ucfirst((string)$successOrder['status'])) ?>
      &middot; Payment: <?= h(ucfirst((string)$successOrder['payment_status'])) ?> (<?= h(strtoupper((string)$successOrder['payment_method'])) ?>)
    </div>

    <table class="cart-table">
      <thead>
        <tr>
          <th>Product</th>
          <th>Unit Price</th>
          <th>Quantity</th>
          <th>Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($successItems as $item): ?>
          <tr>
            <td>
              <div style="display:flex; align-items:center; gap:0.6rem;">
                <img src="<?= h(productImageUrl((string)($item['image'] ?? ''))) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt="<?= h((string)$item['product_name']) ?>"
                     style="width:48px; height:48px; object-fit:cover; border-radius:0.5rem; border:1px solid var(--line);">
                <span><?= h((string)$item['product_name']) ?></span>
              </div>
            </td>
            <td><?= money($conn, (float)((float)$item['unit_price'])) ?></td>
            <td><?= (int)$item['quantity'] ?></td>
            <td><?= money($conn, (float)((float)$item['unit_price'] * (int)$item['quantity'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div style="display:flex; justify-content:flex-end; margin-top:1rem;">
      <div style="min-width:280px; background:#fff; border:1px solid var(--line); border-radius:0.8rem; padding:1rem;">
        <div style="display:flex; justify-content:space-between; font-size:1.1rem;">
          <span>Total Paid</span>
          <strong><?= money($conn, (float)((float)$successOrder['total_amount'])) ?></strong>
        </div>
      </div>
    </div>

    <div class="hero-actions" style="margin-top:1rem;">
      <a class="btn primary" href="shop.php">Continue Shopping</a>
    </div>

  <?php elseif (!$cartItems): ?>

    <div class="section-head">
      <div><h2>Checkout</h2></div>
    </div>
    <div class="notice">Your cart is empty. Add some products before checking out.</div>
    <div class="hero-actions">
      <a class="btn primary" href="shop.php">Browse Products</a>
    </div>

  <?php else: ?>

    <div class="section-head">
      <div>
        <h2>Checkout</h2>
        <small><?= count($cartItems) ?> item(s) &middot; Total <?= money($conn, (float)($grandTotal)) ?></small>
      </div>
    </div>

    <?php if ($errors): ?>
      <div class="notice error">
        <?php foreach ($errors as $error): ?>
          <div><?= $error ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="checkout.php">
      <div class="detail-layout">

        <div class="filter-panel" style="height:auto;">
          <h3>Shipping Details</h3>

          <div class="filter-group">
            <label for="shipping_name">Full Name</label>
            <input class="input" type="text" id="shipping_name" name="shipping_name"
                   value="<?= h($formValues['shipping_name']) ?>" required>
          </div>

          <div class="filter-group">
            <label for="shipping_phone">Phone Number</label>
            <input class="input" type="text" id="shipping_phone" name="shipping_phone"
                   value="<?= h($formValues['shipping_phone']) ?>" required>
          </div>

          <div class="filter-group">
            <label for="shipping_city">City</label>
            <input class="input" type="text" id="shipping_city" name="shipping_city"
                   value="<?= h($formValues['shipping_city']) ?>" required>
          </div>

          <div class="filter-group">
            <label for="shipping_address">Delivery Address (optional)</label>
            <textarea class="input" id="shipping_address" name="shipping_address" rows="3"><?= h($formValues['shipping_address']) ?></textarea>
          </div>

          <div class="filter-group">
            <label for="payment_method">Payment Method</label>
            <select class="select" id="payment_method" name="payment_method">
              <option value="mpesa" <?= $formValues['payment_method'] === 'mpesa' ? 'selected' : '' ?>>M-Pesa</option>
              <option value="cod" <?= $formValues['payment_method'] === 'cod' ? 'selected' : '' ?>>Cash on Delivery</option>
              <option value="card" <?= $formValues['payment_method'] === 'card' ? 'selected' : '' ?>>Card</option>
            </select>
          </div>

          <div class="filter-group">
            <label for="notes">Order Notes (optional)</label>
            <textarea class="input" id="notes" name="notes" rows="2"><?= h($formValues['notes']) ?></textarea>
          </div>
        </div>

        <div>
          <div style="background:#fff; border:1px solid var(--line); border-radius:0.8rem; padding:1rem; margin-bottom:1rem;">
            <h3 style="margin-top:0;">Order Summary</h3>
            <?php foreach ($cartItems as $item): ?>
              <div style="display:flex; justify-content:space-between; gap:0.6rem; padding:0.5rem 0; border-bottom:1px solid var(--line);">
                <div style="display:flex; align-items:center; gap:0.6rem;">
                  <img src="<?= h(productImageUrl((string)($item['image'] ?? ''))) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt="<?= h((string)$item['product_name']) ?>"
                       style="width:44px; height:44px; object-fit:cover; border-radius:0.5rem; border:1px solid var(--line);">
                  <div>
                    <div style="font-weight:600;"><?= h((string)$item['product_name']) ?></div>
                    <div style="color:var(--muted); font-size:0.8rem;">Qty: <?= (int)$item['qty'] ?> &times; <?= money($conn, (float)((float)$item['price'])) ?></div>
                  </div>
                </div>
                <div style="font-weight:700;"><?= money($conn, (float)($item['subtotal'])) ?></div>
              </div>
            <?php endforeach; ?>

            <div style="display:flex; justify-content:space-between; margin-top:0.8rem; font-size:1.1rem;">
              <span>Grand Total</span>
              <strong><?= money($conn, (float)($grandTotal)) ?></strong>
            </div>
          </div>

          <button type="submit" class="btn primary" style="width:100%; padding:0.7rem;">Place Order</button>
          <a class="btn outline" style="width:100%; text-align:center; display:block; margin-top:0.5rem;" href="cart.php">Back to Cart</a>
        </div>

      </div>
    </form>

  <?php endif; ?>

</section>

<?php require __DIR__ . '/includes/footer.php'; ?>