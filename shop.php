<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

ensureCoreSchema($conn);

$activePage = 'shop';
$pageTitle = 'Shop - SmartShop';
$cartCount = getCartCount($conn);

$search = trim((string)($_GET['q'] ?? ''));
$categoryId = (int)($_GET['category'] ?? 0);
$sort = (string)($_GET['sort'] ?? 'newest');
[$orderBy, $sortLabel] = resolveSortOption($sort);

$categories = fetchCategories($conn);
ensureWishlistTable($conn);
$wishlistIds = isLoggedIn() ? fetchWishlistProductIds($conn, (int)($_SESSION['user_id'] ?? 0)) : [];

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = 'p.product_name LIKE ?';
    $params[] = '%' . $search . '%';
    $types .= 's';
}
if ($categoryId > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $categoryId;
    $types .= 'i';
}

$sql = 'SELECT p.id, p.product_name, p.price, p.compare_at_price, p.quantity, p.image, p.rating, p.sold_count, p.created_at, c.category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY ' . $orderBy;

$stmt = mysqli_prepare($conn, $sql);
$products = [];
if ($stmt) {
    if ($params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $products[] = $row;
    }
    mysqli_stmt_close($stmt);
}

require __DIR__ . '/includes/header.php';
?>

<section class="section">
  <div class="section-head">
    <div>
      <h2>Shop Products</h2>
      <small><?= h($sortLabel) ?> · <?= count($products) ?> item(s)</small>
    </div>
  </div>

  <div class="shop-layout">
    <aside class="filter-panel">
      <h3>Filters</h3>
      <form method="GET" action="shop.php">
        <div class="filter-group">
          <label for="q">Search by product name</label>
          <input class="input" id="q" type="text" name="q" value="<?= h($search) ?>" placeholder="Search products...">
        </div>

        <div class="filter-group">
          <label for="category">Category</label>
          <select class="select" id="category" name="category">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int)$category['id'] ?>" <?= $categoryId === (int)$category['id'] ? 'selected' : '' ?>>
                <?= h((string)$category['category_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <label for="sort">Sort</label>
          <select class="select" id="sort" name="sort">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="best_selling" <?= $sort === 'best_selling' ? 'selected' : '' ?>>Best Selling</option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price Low to High</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price High to Low</option>
          </select>
        </div>

        <button class="btn primary" type="submit">Apply Filters</button>
      </form>
    </aside>

    <div>
      <?php if (!$products): ?>
        <div class="notice error">No products found for your current filters.</div>
      <?php endif; ?>

      <div class="product-grid">
        <?php foreach ($products as $product):
          $inStock = (int)$product['quantity'] > 0;
          $discountPct = productDiscountPercent((float)$product['price'], isset($product['compare_at_price']) ? (float)$product['compare_at_price'] : null);
          $inWishlist = in_array((int)$product['id'], $wishlistIds, true);
        ?>
          <article class="product-card">
            <div class="product-image-wrap">
              <img class="product-image" src="<?= h(productImageUrl((string)($product['image'] ?? ''))) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt="<?= h((string)$product['product_name']) ?>">
              <?php if ($discountPct > 0): ?>
                <span class="discount-badge">-<?= $discountPct ?>%</span>
              <?php endif; ?>
              <?php if (!$inStock): ?>
                <div class="outofstock-badge">Out of Stock</div>
              <?php endif; ?>
              <button type="button" class="wishlist-btn<?= $inWishlist ? ' in-wishlist' : '' ?>" data-wishlist-toggle data-product-id="<?= (int)$product['id'] ?>" aria-pressed="<?= $inWishlist ? 'true' : 'false' ?>" aria-label="Toggle wishlist" title="Toggle wishlist"><?= $inWishlist ? '♥' : '♡' ?></button>
            </div>
            <div class="product-body">
              <div class="product-name"><?= h((string)$product['product_name']) ?></div>
              <div class="product-meta">
                <?= h((string)($product['category_name'] ?? 'Uncategorized')) ?> · Stock: <?= (int)$product['quantity'] ?> · Rating: <?= number_format((float)($product['rating'] ?? 0), 1) ?>/5
              </div>
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
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>