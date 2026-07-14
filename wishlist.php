<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

requireLogin();
ensureCoreSchema($conn);
ensureWishlistTable($conn);

$activePage = 'wishlist';
$pageTitle = 'My Wishlist - SmartShop';
$cartCount = getCartCount($conn);

$userId = (int)($_SESSION['user_id'] ?? 0);

$stmt = mysqli_prepare(
    $conn,
    'SELECT p.id, p.product_name, p.price, p.compare_at_price, p.image, p.rating, p.quantity, c.category_name
       FROM wishlist w
       JOIN products p ON p.id = w.product_id
       LEFT JOIN categories c ON c.id = p.category_id
      WHERE w.user_id = ?
      ORDER BY w.created_at DESC'
);

$items = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);
}

require __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="section-head">
    <div>
      <h2>My Wishlist</h2>
      <small><?= count($items) ?> item(s) saved</small>
    </div>
  </div>

  <?php if (!$items): ?>
    <div class="notice">Your wishlist is empty. Tap the heart on any product to save it here.</div>
    <div class="hero-actions"><a class="btn primary" href="shop.php">Browse Products</a></div>
  <?php else: ?>
    <div class="product-grid">
      <?php foreach ($items as $product):
        $inStock = (int)$product['quantity'] > 0;
        $discountPct = productDiscountPercent((float)$product['price'], isset($product['compare_at_price']) ? (float)$product['compare_at_price'] : null);
      ?>
        <article class="product-card" id="wishlist-card-<?= (int)$product['id'] ?>">
          <div class="product-image-wrap">
            <img class="product-image" src="<?= h(productImageUrl((string)($product['image'] ?? ''))) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt="<?= h((string)$product['product_name']) ?>">
            <?php if ($discountPct > 0): ?>
              <span class="discount-badge">-<?= $discountPct ?>%</span>
            <?php endif; ?>
            <?php if (!$inStock): ?>
              <div class="outofstock-badge">Out of Stock</div>
            <?php endif; ?>
          </div>
          <div class="product-body">
            <div class="product-name"><?= h((string)$product['product_name']) ?></div>
            <div class="product-meta"><?= h((string)($product['category_name'] ?? 'Uncategorized')) ?> · Stock: <?= (int)$product['quantity'] ?> · Rating: <?= number_format((float)($product['rating'] ?? 0), 1) ?>/5</div>
            <div class="product-price">
              <?php if ($discountPct > 0): ?><span class="compare-at-price"><?= money($conn, (float)$product['compare_at_price']) ?></span><?php endif; ?>
              <?= money($conn, (float)((float)$product['price'])) ?>
            </div>
            <div class="product-actions">
              <?php if ($inStock): ?>
                <button class="add-cart-btn" data-add-cart data-product-id="<?= (int)$product['id'] ?>" data-qty="1">Add to Cart</button>
              <?php else: ?>
                <button class="add-cart-btn" disabled>Out of Stock</button>
              <?php endif; ?>
              <a class="details-btn" href="product.php?id=<?= (int)$product['id'] ?>">View Details</a>
              <button class="details-btn" type="button" data-wishlist-remove data-product-id="<?= (int)$product['id'] ?>">Remove</button>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<script>
document.addEventListener('click', async function (event) {
  const btn = event.target.closest('[data-wishlist-remove]');
  if (!btn) return;
  event.preventDefault();
  const productId = btn.getAttribute('data-product-id');
  const form = new FormData();
  form.append('product_id', productId);
  try {
    const res = await fetch('api/wishlist_toggle.php', { method: 'POST', body: form, credentials: 'same-origin' });
    const data = await res.json();
    if (data.ok) {
      const card = document.getElementById('wishlist-card-' + productId);
      if (card) card.remove();
      if (window.SmartShop) window.SmartShop.toast(data.message || 'Removed from wishlist');
    }
  } catch (err) {
    if (window.SmartShop) window.SmartShop.toast('Could not update wishlist.', true);
  }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>