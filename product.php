<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

ensureCoreSchema($conn);

$activePage = 'shop';
$cartCount = getCartCount($conn);

$productId = (int)($_GET['id'] ?? 0);

$product = null;
$hasDescription = true;

if ($productId > 0) {
    $sql = 'SELECT p.id, p.product_name, p.price, p.compare_at_price, p.quantity, p.image, p.rating, p.sold_count,
                   p.category_id, p.description, c.category_name
              FROM products p
              LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id = ?
             LIMIT 1';

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        // Fallback in case the "description" column doesn't exist in this environment.
        $hasDescription = false;
        $sql = 'SELECT p.id, p.product_name, p.price, p.compare_at_price, p.quantity, p.image, p.rating, p.sold_count,
                       p.category_id, c.category_name
                  FROM products p
                  LEFT JOIN categories c ON c.id = p.category_id
                 WHERE p.id = ?
                 LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
    }

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $productId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $product = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
    }

    if ($product && !$hasDescription) {
        $product['description'] = null;
    }
}

$related = [];
if ($product && !empty($product['category_id'])) {
    $catId = (int)$product['category_id'];
    $relStmt = mysqli_prepare(
        $conn,
        'SELECT p.id, p.product_name, p.price, p.compare_at_price, p.quantity, p.image, p.rating, c.category_name
           FROM products p
           LEFT JOIN categories c ON c.id = p.category_id
          WHERE p.category_id = ? AND p.id != ?
          ORDER BY p.created_at DESC
          LIMIT 4'
    );

    if ($relStmt) {
        mysqli_stmt_bind_param($relStmt, 'ii', $catId, $productId);
        mysqli_stmt_execute($relStmt);
        $relRes = mysqli_stmt_get_result($relStmt);
        while ($relRes && ($row = mysqli_fetch_assoc($relRes))) {
            $related[] = $row;
        }
        mysqli_stmt_close($relStmt);
    }
}

$pageTitle = $product ? ((string)$product['product_name'] . ' - SmartShop') : 'Product Not Found - SmartShop';

ensureWishlistTable($conn);
$wishlistIds = isLoggedIn() ? fetchWishlistProductIds($conn, (int)($_SESSION['user_id'] ?? 0)) : [];

require __DIR__ . '/includes/header.php';
?>

<?php if (!$product): ?>

  <section class="section">
    <div class="notice error">Sorry, we couldn't find that product.</div>
    <div class="hero-actions">
      <a class="btn primary" href="shop.php">Back to Shop</a>
    </div>
  </section>

<?php else:
  $stock = (int)$product['quantity'];
  $price = (float)$product['price'];
  $rating = (float)($product['rating'] ?? 0);
  $discountPct = productDiscountPercent($price, isset($product['compare_at_price']) ? (float)$product['compare_at_price'] : null);
  $ratingRounded = (int)round($rating);
  $stars = str_repeat('★', max(0, min(5, $ratingRounded))) . str_repeat('☆', 5 - max(0, min(5, $ratingRounded)));
?>

  <section class="section">
    <div class="section-head">
      <div>
        <small><a href="shop.php">Shop</a> / <?= h((string)($product['category_name'] ?? 'Uncategorized')) ?></small>
      </div>
    </div>

    <div class="detail-layout">
      <div>
        <div class="detail-main-image" style="position:relative;">
          <img src="<?= h(productImageUrl((string)($product['image'] ?? ''))) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt="<?= h((string)$product['product_name']) ?>">
          <?php if ($discountPct > 0): ?>
            <span class="discount-badge">-<?= $discountPct ?>%</span>
          <?php endif; ?>
          <?php $inWishlist = in_array((int)$product['id'], $wishlistIds, true); ?>
          <button type="button" class="wishlist-btn<?= $inWishlist ? ' in-wishlist' : '' ?>" data-wishlist-toggle data-product-id="<?= (int)$product['id'] ?>" aria-pressed="<?= $inWishlist ? 'true' : 'false' ?>" aria-label="Toggle wishlist" title="Toggle wishlist"><?= $inWishlist ? '♥' : '♡' ?></button>
        </div>
      </div>

      <div>
        <h1 style="margin-top:0;"><?= h((string)$product['product_name']) ?></h1>

        <div class="product-meta" style="font-size:0.95rem; margin-bottom:0.6rem;">
          <?= h((string)($product['category_name'] ?? 'Uncategorized')) ?>
        </div>

        <div style="margin-bottom:0.6rem;">
          <span style="color:#f59e0b; letter-spacing:2px;"><?= $stars ?></span>
          <span style="color:var(--muted); font-size:0.85rem;"> <?= number_format($rating, 1) ?>/5</span>
        </div>

        <div class="product-price" style="font-size:1.6rem; margin-bottom:0.6rem;">
          <?php if ($discountPct > 0): ?><span class="compare-at-price"><?= money($conn, (float)$product['compare_at_price']) ?></span><?php endif; ?>
          <?= money($conn, (float)($price)) ?>
        </div>

        <div style="margin-bottom:0.9rem;">
          <?php if ($stock > 0): ?>
            <span class="role-pill user">In Stock &middot; <?= $stock ?> available</span>
          <?php else: ?>
            <span class="role-pill" style="background:#fee2e2; color:#991b1b;">Out of Stock</span>
          <?php endif; ?>
        </div>

        <?php if (!empty($product['description'])): ?>
          <p style="color:var(--muted); line-height:1.6; margin-bottom:1rem;">
            <?= h((string)$product['description']) ?>
          </p>
        <?php endif; ?>

        <?php if ($stock > 0): ?>
          <div style="display:flex; align-items:center; gap:0.9rem; flex-wrap:wrap; margin-bottom:1rem;">
            <div class="qty-actions">
              <button type="button" class="qty-btn" id="qty-minus">&minus;</button>
              <input type="number" id="qty-input" class="input" style="width:70px; text-align:center;" value="1" min="1" max="<?= $stock ?>">
              <button type="button" class="qty-btn" id="qty-plus">&plus;</button>
            </div>

            <button class="add-cart-btn" id="add-to-cart-btn" style="padding:0.7rem 1.4rem;"
                    data-add-cart data-product-id="<?= (int)$product['id'] ?>" data-qty="1">
              Add to Cart
            </button>
          </div>
        <?php else: ?>
          <button class="add-cart-btn" style="padding:0.7rem 1.4rem; opacity:0.6; cursor:not-allowed;" disabled>
            Out of Stock
          </button>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php if ($related): ?>
    <section class="section">
      <div class="section-head">
        <div>
          <h2>Related Products</h2>
          <small>More from <?= h((string)($product['category_name'] ?? 'this category')) ?></small>
        </div>
      </div>

      <div class="product-grid">
        <?php foreach ($related as $rel):
          $relInStock = (int)$rel['quantity'] > 0;
          $relDiscountPct = productDiscountPercent((float)$rel['price'], isset($rel['compare_at_price']) ? (float)$rel['compare_at_price'] : null);
          $relInWishlist = in_array((int)$rel['id'], $wishlistIds, true);
        ?>
          <article class="product-card">
            <div class="product-image-wrap">
              <img class="product-image" src="<?= h(productImageUrl((string)($rel['image'] ?? ''))) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt="<?= h((string)$rel['product_name']) ?>">
              <?php if ($relDiscountPct > 0): ?>
                <span class="discount-badge">-<?= $relDiscountPct ?>%</span>
              <?php endif; ?>
              <?php if (!$relInStock): ?>
                <div class="outofstock-badge">Out of Stock</div>
              <?php endif; ?>
              <button type="button" class="wishlist-btn<?= $relInWishlist ? ' in-wishlist' : '' ?>" data-wishlist-toggle data-product-id="<?= (int)$rel['id'] ?>" aria-pressed="<?= $relInWishlist ? 'true' : 'false' ?>" aria-label="Toggle wishlist" title="Toggle wishlist"><?= $relInWishlist ? '♥' : '♡' ?></button>
            </div>
            <div class="product-body">
              <div class="product-name"><?= h((string)$rel['product_name']) ?></div>
              <div class="product-meta">
                <?= h((string)($rel['category_name'] ?? 'Uncategorized')) ?> · Stock: <?= (int)$rel['quantity'] ?> · Rating: <?= number_format((float)($rel['rating'] ?? 0), 1) ?>/5
              </div>
              <div class="product-price">
                <?php if ($relDiscountPct > 0): ?><span class="compare-at-price"><?= money($conn, (float)$rel['compare_at_price']) ?></span><?php endif; ?>
                <?= money($conn, (float)((float)$rel['price'])) ?>
              </div>
              <div class="product-actions">
                <?php if ($relInStock): ?>
                  <button class="add-cart-btn" data-add-cart data-product-id="<?= (int)$rel['id'] ?>" data-qty="1">Add to Cart</button>
                <?php else: ?>
                  <button class="add-cart-btn" disabled>Out of Stock</button>
                <?php endif; ?>
                <a class="details-btn" href="product.php?id=<?= (int)$rel['id'] ?>">View Details</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php if ($product && $stock > 0): ?>
<script>
(function () {
  var input = document.getElementById('qty-input');
  var minus = document.getElementById('qty-minus');
  var plus = document.getElementById('qty-plus');
  var addBtn = document.getElementById('add-to-cart-btn');
  var max = <?= (int)$stock ?>;

  function sync() {
    var value = parseInt(input.value, 10);
    if (isNaN(value) || value < 1) {
      value = 1;
    }
    if (max > 0 && value > max) {
      value = max;
    }
    input.value = String(value);
    if (addBtn) {
      addBtn.setAttribute('data-qty', String(value));
    }
  }

  if (input) {
    input.addEventListener('change', sync);
    input.addEventListener('input', sync);
  }
  if (minus) {
    minus.addEventListener('click', function () {
      input.value = String((parseInt(input.value, 10) || 1) - 1);
      sync();
    });
  }
  if (plus) {
    plus.addEventListener('click', function () {
      input.value = String((parseInt(input.value, 10) || 1) + 1);
      sync();
    });
  }

  sync();
})();
</script>
<?php endif; ?>