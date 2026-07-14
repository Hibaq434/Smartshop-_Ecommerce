<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

ensureCoreSchema($conn);

requireLogin();

$activePage = 'cart';
$pageTitle = 'Your Cart - SmartShop';
$cartCount = getCartCount($conn);

$userId = (int)($_SESSION['user_id'] ?? 0);

$sql = 'SELECT c.id AS cart_id, c.quantity AS qty,
               p.id AS product_id, p.product_name, p.price, p.image, p.quantity AS stock
          FROM cart c
          JOIN products p ON p.id = c.product_id
         WHERE c.user_id = ?
         ORDER BY c.id DESC';

$cartItems = [];
$grandTotal = 0.0;

$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $row['subtotal'] = (float)$row['price'] * (int)$row['qty'];
        $grandTotal += $row['subtotal'];
        $cartItems[] = $row;
    }
    mysqli_stmt_close($stmt);
}

require __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="section-head">
    <div>
      <h2>Your Cart</h2>
      <small><?= count($cartItems) ?> item(s) in cart</small>
    </div>
  </div>

  <?php if (!$cartItems): ?>
    <div class="notice">Your cart is empty. Browse the shop to find something you like.</div>
    <div class="hero-actions">
      <a class="btn primary" href="shop.php">Continue Shopping</a>
    </div>
  <?php else: ?>

    <table class="cart-table" id="cart-table">
      <thead>
        <tr>
          <th>Product</th>
          <th>Unit Price</th>
          <th>Quantity</th>
          <th>Subtotal</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="cart-table-body">
        <?php foreach ($cartItems as $item):
          $stock = (int)$item['stock'];
          $qty = (int)$item['qty'];
          $price = (float)$item['price'];
        ?>
          <tr class="cart-row"
              data-cart-id="<?= (int)$item['cart_id'] ?>"
              data-price="<?= h(number_format($price, 2, '.', '')) ?>"
              data-stock="<?= $stock ?>">
            <td class="cart-product-cell">
              <div style="display:flex; align-items:center; gap:0.6rem;">
                <img src="<?= h(productImageUrl((string)($item['image'] ?? ''))) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';"
                     alt="<?= h((string)$item['product_name']) ?>"
                     style="width:56px; height:56px; object-fit:cover; border-radius:0.5rem; border:1px solid var(--line);">
                <a href="product.php?id=<?= (int)$item['product_id'] ?>" style="font-weight:700;">
                  <?= h((string)$item['product_name']) ?>
                </a>
              </div>
            </td>
            <td><?= money($conn, (float)($price)) ?></td>
            <td>
              <div class="qty-actions">
                <button type="button" class="qty-btn" data-action="dec">&minus;</button>
                <span class="cart-qty-value"><?= $qty ?></span>
                <button type="button" class="qty-btn" data-action="inc" <?= ($stock > 0 && $qty >= $stock) ? 'disabled' : '' ?>>&plus;</button>
              </div>
            </td>
            <td class="cart-subtotal-cell" data-value="<?= h(number_format($item['subtotal'], 2, '.', '')) ?>">
              <?= money($conn, (float)($item['subtotal'])) ?>
            </td>
            <td>
              <button type="button" class="btn outline cart-remove-btn">Remove</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="section" style="display:flex; justify-content:flex-end;">
      <div style="min-width:280px; background:#fff; border:1px solid var(--line); border-radius:0.8rem; padding:1rem;">
        <div style="display:flex; justify-content:space-between; margin-bottom:0.4rem;">
          <span>Cart Subtotal</span>
          <strong id="cart-subtotal"><?= money($conn, (float)($grandTotal)) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; color:var(--muted); font-size:0.85rem; margin-bottom:0.6rem;">
          <span>Shipping</span>
          <span>Calculated at checkout</span>
        </div>
        <hr style="border:none; border-top:1px solid var(--line); margin:0.6rem 0;">
        <div style="display:flex; justify-content:space-between; font-size:1.1rem; margin-bottom:1rem;">
          <span>Grand Total</span>
          <strong id="cart-grandtotal"><?= money($conn, (float)($grandTotal)) ?></strong>
        </div>
        <div style="display:flex; flex-direction:column; gap:0.5rem;">
          <a class="btn primary" href="checkout.php" id="checkout-btn">Proceed to Checkout</a>
          <a class="btn outline" href="shop.php">Continue Shopping</a>
        </div>
      </div>
    </div>

  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

<script>
(function () {
  var tbody = document.getElementById('cart-table-body');
  if (!tbody) {
    return;
  }

  var CURRENCY_SYMBOL = <?= json_encode(currencySymbol($conn), JSON_UNESCAPED_UNICODE) ?>;
  var CURRENCY_SPACED = <?= json_encode(!in_array(currencySymbol($conn), ['$', '€', '£'], true)) ?>;

  function money(n) {
    return CURRENCY_SPACED ? (CURRENCY_SYMBOL + ' ' + n.toFixed(2)) : (CURRENCY_SYMBOL + n.toFixed(2));
  }

  function notify(message, isError) {
    if (window.SmartShop && typeof window.SmartShop.toast === 'function') {
      window.SmartShop.toast(message, isError);
    } else if (isError) {
      alert(message);
    }
  }

  function refreshBadge() {
    if (window.SmartShop && typeof window.SmartShop.refreshCartCount === 'function') {
      window.SmartShop.refreshCartCount();
    }
  }

  function recalcGrandTotal() {
    var rows = tbody.querySelectorAll('.cart-row');
    var total = 0;
    rows.forEach(function (row) {
      var cell = row.querySelector('.cart-subtotal-cell');
      total += parseFloat(cell.getAttribute('data-value') || '0');
    });

    var subtotalEl = document.getElementById('cart-subtotal');
    var grandEl = document.getElementById('cart-grandtotal');
    if (subtotalEl) subtotalEl.textContent = money(total);
    if (grandEl) grandEl.textContent = money(total);

    if (rows.length === 0) {
      // No items left — reload to show the empty-cart state rendered by PHP.
      window.location.reload();
    }
  }

  function removeRow(row) {
    row.parentNode.removeChild(row);
    recalcGrandTotal();
  }

  async function handleUpdate(row, delta) {
    var cartId = row.getAttribute('data-cart-id');
    var qtyEl = row.querySelector('.cart-qty-value');
    var currentQty = parseInt(qtyEl.textContent, 10) || 1;
    var stock = parseInt(row.getAttribute('data-stock'), 10) || 0;

    if (delta > 0 && stock > 0 && currentQty >= stock) {
      notify('No more stock available for this item.', true);
      return;
    }

    var form = new FormData();
    form.append('cart_id', cartId);
    form.append('delta', String(delta));

    try {
      var res = await fetch('api/cart_update.php', {
        method: 'POST',
        body: form,
        credentials: 'same-origin'
      });
      var data = await res.json();

      if (!data.ok) {
        notify(data.message || 'Could not update cart.', true);
        return;
      }

      var newQty = currentQty + delta;

      if (newQty <= 0) {
        // The API deletes the row once quantity reaches zero.
        removeRow(row);
      } else {
        qtyEl.textContent = String(newQty);

        var price = parseFloat(row.getAttribute('data-price')) || 0;
        var subtotal = price * newQty;
        var subtotalCell = row.querySelector('.cart-subtotal-cell');
        subtotalCell.setAttribute('data-value', String(subtotal));
        subtotalCell.textContent = money(subtotal);

        var incBtn = row.querySelector('[data-action="inc"]');
        if (incBtn) {
          incBtn.disabled = stock > 0 && newQty >= stock;
        }

        recalcGrandTotal();
      }

      refreshBadge();
    } catch (err) {
      notify('Network error while updating your cart.', true);
    }
  }

  async function handleRemove(row) {
    var cartId = row.getAttribute('data-cart-id');
    var form = new FormData();
    form.append('cart_id', cartId);

    try {
      var res = await fetch('api/cart_remove.php', {
        method: 'POST',
        body: form,
        credentials: 'same-origin'
      });
      var data = await res.json();

      if (!data.ok) {
        notify(data.message || 'Could not remove item.', true);
        return;
      }

      removeRow(row);
      refreshBadge();
    } catch (err) {
      notify('Network error while removing item.', true);
    }
  }

  tbody.addEventListener('click', function (event) {
    var row = event.target.closest('.cart-row');
    if (!row) {
      return;
    }

    if (event.target.closest('.cart-remove-btn')) {
      handleRemove(row);
      return;
    }

    var incBtn = event.target.closest('[data-action="inc"]');
    var decBtn = event.target.closest('[data-action="dec"]');
    if (incBtn) {
      handleUpdate(row, 1);
    } else if (decBtn) {
      handleUpdate(row, -1);
    }
  });
})();
</script>