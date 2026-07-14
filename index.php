<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

// Guests see the public landing page first; only logged-in users get Home.
if (!isLoggedIn()) {
    header('Location: landing.php');
    exit;
}

ensureCoreSchema($conn);
ensureNewsletterTable($conn);
ensureWishlistTable($conn);

$cartCount = getCartCount($conn);
$wishlistIds = isLoggedIn() ? fetchWishlistProductIds($conn, (int)($_SESSION['user_id'] ?? 0)) : [];

$page = (string)($_GET['p'] ?? 'home');

if ($page === 'admin') {
    $targetSection = (string)($_GET['section'] ?? 'dashboard');
    header('Location: dashboard.php?section=' . urlencode($targetSection));
    exit;
}

$allowedPages = ['home', 'shop'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'home';
}

$flash = (string)($_GET['msg'] ?? '');

// ── Shop tab: full product list (real DB data) ──────────────────
$products = [];
$res = mysqli_query(
    $conn,
    'SELECT p.id, p.product_name, p.price, p.compare_at_price, p.quantity, p.image, p.rating, p.sold_count, c.category_name
       FROM products p
       LEFT JOIN categories c ON c.id = p.category_id
      ORDER BY p.id DESC'
);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $products[] = $row;
    }
}
$shopProducts = array_slice($products, 0, 8);

// ── Shop by Category: dynamic categories with live product counts ──
$categories = fetchCategoriesWithCounts($conn);
$catCardClasses = ['cat-electronics', 'cat-fashion', 'cat-home', 'cat-beauty'];

// ── Featured Collections: latest products from the DB ───────────
$featuredProducts = [];
$res = mysqli_query(
    $conn,
    'SELECT p.id, p.product_name, p.price, p.compare_at_price, p.quantity, p.image, p.rating, c.category_name
       FROM products p
       LEFT JOIN categories c ON c.id = p.category_id
      ORDER BY p.created_at DESC
      LIMIT 4'
);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $featuredProducts[] = $row;
    }
}

// ── Trending Now: best sellers by sold_count ─────────────────────
$trendingProducts = [];
$res = mysqli_query(
    $conn,
    'SELECT id, product_name, price, image, sold_count
       FROM products
      ORDER BY sold_count DESC
      LIMIT 8'
);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $trendingProducts[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SmartShop — E-Commerce Platform</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--blue:#2563EB;--blue-dark:#1d4ed8;--gray-50:#f8fafc;--gray-100:#f1f5f9;--gray-200:#e2e8f0;--gray-500:#64748b;--gray-700:#334155;--gray-900:#0f172a;--green:#16a34a;--red:#dc2626;--amber:#d97706}
body{font-family:'Segoe UI',system-ui,sans-serif;color:var(--gray-900);background:#fff;font-size:14px;line-height:1.5}
a{text-decoration:none}
.nav{display:flex;align-items:center;gap:18px;padding:0 24px;height:56px;border-bottom:1px solid var(--gray-200);background:#fff;position:sticky;top:0;z-index:100}
.nav-logo{font-size:18px;font-weight:800;color:var(--blue)}
.nav-link{font-size:13px;color:var(--gray-700);padding:6px 8px;border-radius:6px;cursor:pointer}
.nav-link:hover,.nav-link.active{color:var(--blue);background:var(--gray-100)}
.nav-right{margin-left:auto;display:flex;align-items:center;gap:12px}
.user-chip{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--gray-700)}
.user-avatar{width:28px;height:28px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#fff}
.pill{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
.pill-admin{background:#dbeafe;color:#1e40af}.pill-user{background:#dcfce7;color:#15803d}
.nav-search{display:flex;align-items:center}
.nav-search input{padding:7px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:12px;width:180px;outline:none}
.nav-search input:focus{border-color:var(--blue);width:220px;transition:width .15s}
.dropdown{position:relative}
.dropdown-trigger{cursor:pointer;background:none;border:none;font:inherit;display:inline-flex;align-items:center;gap:8px}
.dropdown-panel{display:none;position:absolute;right:0;top:calc(100% + 10px);background:#fff;border:1px solid var(--gray-200);border-radius:12px;box-shadow:0 16px 40px rgba(15,23,42,.14);min-width:220px;padding:6px;z-index:300}
.dropdown-panel.open{display:block}
.dropdown-panel a,.dropdown-panel button{display:flex;width:100%;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;font-size:13px;color:var(--gray-700);background:none;border:none;font:inherit;text-align:left;cursor:pointer}
.dropdown-panel a:hover,.dropdown-panel button:hover{background:var(--gray-50)}
.dropdown-divider{height:1px;background:var(--gray-100);margin:5px 6px}
.cart-btn{position:relative;background:none;border:1px solid var(--gray-200);border-radius:8px;padding:7px 12px;font-size:13px;font-weight:600;color:var(--gray-700);cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.cart-btn:hover{background:var(--gray-50)}
.cart-badge{background:var(--blue);color:#fff;font-size:10px;font-weight:800;border-radius:999px;padding:1px 6px;min-width:16px;text-align:center}
.mini-cart-panel{width:300px}
.mini-cart-item{display:flex;gap:10px;padding:8px 6px;align-items:center}
.mini-cart-item img{width:42px;height:42px;object-fit:cover;border-radius:8px;background:var(--gray-100)}
.mini-cart-empty{padding:14px 10px;font-size:12px;color:var(--gray-500);text-align:center}
.cat-card{position:relative;text-decoration:none}
.cat-count{display:block;font-size:11px;font-weight:600;opacity:.9;margin-top:2px}
.toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%) translateY(10px);background:var(--gray-900);color:#fff;padding:10px 16px;border-radius:8px;font-size:12px;font-weight:600;opacity:0;pointer-events:none;transition:opacity .2s,transform .2s;z-index:999}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .15s}
.btn-primary{background:var(--blue);color:#fff}.btn-primary:hover{background:var(--blue-dark)}
.btn-outline{background:#fff;color:var(--gray-700);border:1px solid var(--gray-200)}.btn-outline:hover{background:var(--gray-50)}
.page{display:none}.page.active{display:block}
.hero{background:linear-gradient(135deg,#f0f7ff 0%,#e8f4fd 100%);padding:56px 24px;display:grid;grid-template-columns:1.1fr .9fr;gap:32px;align-items:center}
.hero-label{display:inline-flex;background:#dbeafe;color:#1e40af;font-size:11px;font-weight:800;padding:4px 10px;border-radius:999px;margin-bottom:14px;text-transform:uppercase;letter-spacing:.5px}
.hero-title{font-size:40px;font-weight:900;line-height:1.1;color:var(--gray-900);margin-bottom:12px}
.hero-title span{color:var(--blue)}
.hero-desc{font-size:15px;color:var(--gray-500);margin-bottom:22px;line-height:1.7;max-width:56ch}
.hero-btns{display:flex;gap:10px;flex-wrap:wrap}
.hero-card{min-height:260px;border-radius:24px;background:linear-gradient(135deg,#1e3a5f,#2563EB);display:flex;align-items:center;justify-content:center;font-size:92px;box-shadow:0 22px 50px rgba(37,99,235,.25)}
.section{padding:40px 24px}
.section-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;gap:12px}
.section-title{font-size:18px;font-weight:800;color:var(--gray-900)}
.section-note{font-size:12px;color:var(--gray-500)}
.cat-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:10px;min-height:240px}
.cat-stack{display:grid;grid-template-rows:1fr 1fr;gap:10px}
.cat-card{border-radius:18px;display:flex;align-items:flex-end;padding:16px;color:#fff;font-weight:700;min-height:115px;background-size:cover;background-position:center}
.cat-electronics{background:linear-gradient(135deg,#1e3a5f,#2563EB)}
.cat-fashion{background:linear-gradient(135deg,#78350f,#d97706)}
.cat-home{background:linear-gradient(135deg,#14532d,#16a34a)}
.cat-beauty{background:linear-gradient(135deg,#5b21b6,#8b5cf6)}
.cat-label{background:rgba(0,0,0,.45);backdrop-filter:blur(4px);padding:6px 10px;border-radius:999px;font-size:12px}
.featured-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.prod-card{border:1px solid var(--gray-200);border-radius:16px;overflow:hidden;background:#fff;transition:transform .15s, box-shadow .15s}
.prod-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(15,23,42,.08)}
.prod-img{height:150px;display:flex;align-items:center;justify-content:center;font-size:40px;position:relative;color:#fff;overflow:hidden;background:var(--gray-100)}
.wishlist-btn{position:absolute;top:8px;right:8px;width:30px;height:30px;border-radius:50%;background:#fff;border:1px solid var(--gray-200);display:flex;align-items:center;justify-content:center;font-size:15px;line-height:1;color:var(--gray-500);cursor:pointer;z-index:2}
.wishlist-btn:hover{background:var(--gray-50)}
.wishlist-btn.in-wishlist{color:#dc2626;border-color:#fecaca;background:#fef2f2}
.discount-badge{position:absolute;top:8px;left:8px;background:#dc2626;color:#fff;font-size:11px;font-weight:800;padding:3px 8px;border-radius:999px;z-index:2}
.outofstock-badge{position:absolute;inset:0;background:rgba(15,23,42,.55);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;z-index:1}
.prod-img img{width:100%;height:100%;object-fit:cover;display:block}
.prod-img a{display:block;width:100%;height:100%}
.trend-img img{width:100%;height:100%;object-fit:cover;border-radius:10px}
.prod-badge{position:absolute;top:10px;left:10px;font-size:10px;font-weight:800;padding:3px 8px;border-radius:999px}
.badge-blue{background:#dbeafe;color:#1e40af}.badge-green{background:#dcfce7;color:#15803d}.badge-amber{background:#fef3c7;color:#92400e}.badge-red{background:#fee2e2;color:#991b1b}
.prod-info{padding:14px}
.prod-brand{font-size:10px;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px}
.prod-name{font-size:14px;font-weight:700;margin:3px 0 6px}
.prod-stars{color:#f59e0b;font-size:11px;margin-bottom:2px}
.prod-price{font-size:16px;font-weight:800;color:var(--blue)}
.prod-old-price{font-size:12px;color:var(--gray-500);text-decoration:line-through;margin-left:5px}
.add-cart{width:100%;margin-top:10px;padding:8px;background:var(--blue);color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer}.add-cart:hover{background:var(--blue-dark)}
.trending{display:flex;gap:12px;overflow-x:auto;padding-bottom:4px}
.trend-card{flex:0 0 180px;border:1px solid var(--gray-200);border-radius:14px;padding:12px;display:flex;align-items:center;gap:12px;background:#fff}
.trend-img{width:44px;height:44px;border-radius:10px;background:var(--gray-100);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.trend-name{font-size:12px;font-weight:700;line-height:1.3}.trend-price{font-size:13px;font-weight:800;color:var(--blue)}.trend-sold{font-size:10px;color:var(--gray-500)}
.shop-layout{display:grid;grid-template-columns:220px 1fr;gap:24px;padding:24px}
.sidebar{background:#fff;border:1px solid var(--gray-200);border-radius:16px;padding:16px;height:fit-content}
.sidebar-title{font-size:14px;font-weight:800;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--gray-100)}
.filter-group{margin-bottom:20px}.filter-label{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--gray-500);margin-bottom:8px}
.filter-item{display:flex;align-items:center;gap:8px;margin-bottom:6px;cursor:pointer;font-size:13px}
.shop-header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.shop-search{padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;width:260px;outline:none}.shop-search:focus{border-color:var(--blue)}
.shop-count{font-size:13px;color:var(--gray-500);margin-top:4px}
.sort-select{padding:8px 10px;border:1px solid var(--gray-200);border-radius:8px;font-size:12px;background:#fff;cursor:pointer;outline:none}
.prod-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.notice{background:#fff;border:1px solid var(--gray-200);border-left:4px solid var(--blue);border-radius:12px;padding:12px 14px;margin-bottom:12px;font-size:12px;color:var(--gray-700)}
.flash{background:#dcfce7;border-bottom:1px solid #bbf7d0;color:#15803d;padding:10px 24px;font-size:13px;text-align:center;font-weight:600}
footer{background:var(--gray-900);color:#fff;padding:32px 24px 16px;margin-top:40px}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1.5fr;gap:32px;margin-bottom:24px}
.footer-logo{font-size:20px;font-weight:800;margin-bottom:8px}
.footer-desc{font-size:12px;color:rgba(255,255,255,.55);line-height:1.6}
.footer-heading{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:rgba(255,255,255,.45);margin-bottom:12px}
.footer-link{display:block;font-size:12px;color:rgba(255,255,255,.65);margin-bottom:6px}
.footer-newsletter{display:flex;gap:8px;margin-top:8px}.footer-input{flex:1;padding:8px 12px;border-radius:8px;border:none;background:rgba(255,255,255,.1);color:#fff;font-size:12px}
.footer-bottom{border-top:1px solid rgba(255,255,255,.1);padding-top:16px;font-size:11px;color:rgba(255,255,255,.4);text-align:center}
@media(max-width:900px){.hero,.shop-layout,.footer-grid{grid-template-columns:1fr}.featured-grid,.prod-grid-3{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){.featured-grid,.prod-grid-3,.footer-grid{grid-template-columns:1fr}.hero-title{font-size:32px}.cat-grid{grid-template-columns:1fr}.nav{gap:10px;overflow-x:auto}.nav-right{margin-left:0}.shop-layout{padding:16px}.sidebar{display:none}}
</style>
</head>
<body>

<nav class="nav">
  <span class="nav-logo">SmartShop</span>
  <a class="nav-link<?= $page === 'home' ? ' active' : '' ?>" href="index.php?p=home" onclick="gotoPage('home', this); return false;" data-page="home">Home</a>
  <a class="nav-link<?= $page === 'shop' ? ' active' : '' ?>" href="index.php?p=shop" onclick="gotoPage('shop', this); return false;" data-page="shop">Shop</a>
  <?php if (isAdmin()): ?>
    <a class="nav-link" href="dashboard.php">Dashboard</a>
    <a class="nav-link" href="dashboard.php?section=products">Products</a>
    <a class="nav-link" href="dashboard.php?section=orders">Orders</a>
    <a class="nav-link" href="dashboard.php?section=users">Users</a>
    <a class="nav-link" href="dashboard.php?section=categories">Categories</a>
  <?php endif; ?>
  <form class="nav-search" action="shop.php" method="GET">
    <input type="text" name="q" placeholder="Search products..." aria-label="Search products">
  </form>
  <div class="nav-right">
    <?php if (isLoggedIn()): ?>
      <div class="dropdown" id="cart-dropdown">
        <button class="cart-btn dropdown-trigger" type="button" id="cart-trigger" aria-haspopup="true" aria-expanded="false">
          🛍 Cart <span class="cart-badge" id="cart-count"><?= (int)$cartCount ?></span>
        </button>
        <div class="dropdown-panel mini-cart-panel" id="mini-cart-panel"></div>
      </div>
      <div class="dropdown" id="user-dropdown">
        <button class="user-chip dropdown-trigger" type="button" id="user-trigger" aria-haspopup="true" aria-expanded="false">
          <div class="user-avatar"><?= strtoupper(substr(currentUsername(), 0, 1)) ?></div>
          <span><?= h(currentUsername()) ?></span>
          <span class="pill <?= isAdmin() ? 'pill-admin' : 'pill-user' ?>"><?= h(currentRoleLabel()) ?></span>
        </button>
        <div class="dropdown-panel" id="user-panel">
          <a href="profile.php">👤 My Profile</a>
          <a href="my_orders.php">🛒 My Orders</a>
          <a href="wishlist.php">❤️ Wishlist</a>
          <a href="cart.php">🛍 Cart</a>
          <a href="settings.php">⚙ Account Settings</a>
          <?php if (isAdmin()): ?>
            <div class="dropdown-divider"></div>
            <a href="dashboard.php">📊 Dashboard</a>
          <?php endif; ?>
          <div class="dropdown-divider"></div>
          <a href="logout.php">🚪 Logout</a>
        </div>
      </div>
    <?php else: ?>
      <a class="btn btn-primary" href="login.php">Login</a>
    <?php endif; ?>
  </div>
</nav>

<?php if ($flash !== ''): ?>
  <div class="flash" id="flash-banner"><?= h($flash) ?></div>
  <script>
    setTimeout(function () {
      var banner = document.getElementById('flash-banner');
      if (banner) {
        banner.style.opacity = '0';
        banner.style.transition = 'opacity .5s ease';
        setTimeout(function () { banner.style.display = 'none'; }, 500);
      }
    }, 3500);
  </script>
<?php endif; ?>

<div id="home" class="page">
  <section class="hero">
    <div>
      <span class="hero-label">New Arrival 2026</span>
      <h1 class="hero-title">Elevate your living<br><span>with intelligence.</span></h1>
      <p class="hero-desc">Discover a curated selection of smart home gadgets and premium lifestyle electronics designed for modern shopping.</p>
      <div class="hero-btns">
        <a class="btn btn-primary" href="shop.php">Shop Now</a>
        <a class="btn btn-outline" href="#featured" onclick="return smoothScrollTo('featured');">Browse Products</a>
      </div>
    </div>
    <div class="hero-card">⌚</div>
  </section>

  <section class="section">
    <div class="section-head">
      <div>
        <div class="section-title">Shop by Category</div>
        <div class="section-note">A clean split between key shopping paths.</div>
      </div>
      <a class="section-note" href="shop.php">View all →</a>
    </div>
    <?php if (!$categories): ?>
      <div class="notice">No categories yet. Add some in the admin dashboard.</div>
    <?php else: ?>
      <div class="cat-grid">
        <?php $firstCat = $categories[0]; ?>
        <a class="cat-card <?= h($catCardClasses[0]) ?>" href="shop.php?category=<?= (int)$firstCat['id'] ?>">
          <span class="cat-label"><?= h((string)$firstCat['category_name']) ?><span class="cat-count"><?= (int)$firstCat['product_count'] ?> Products</span></span>
        </a>
        <div class="cat-stack">
          <?php if (isset($categories[1])): $cat = $categories[1]; ?>
            <a class="cat-card <?= h($catCardClasses[1]) ?>" href="shop.php?category=<?= (int)$cat['id'] ?>">
              <span class="cat-label"><?= h((string)$cat['category_name']) ?><span class="cat-count"><?= (int)$cat['product_count'] ?> Products</span></span>
            </a>
          <?php endif; ?>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <?php for ($i = 2; $i <= 3; $i++): if (!isset($categories[$i])) continue; $cat = $categories[$i]; ?>
              <a class="cat-card <?= h($catCardClasses[$i]) ?>" href="shop.php?category=<?= (int)$cat['id'] ?>">
                <span class="cat-label"><?= h((string)$cat['category_name']) ?><span class="cat-count"><?= (int)$cat['product_count'] ?> Products</span></span>
              </a>
            <?php endfor; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <section class="section" style="padding-top:0">
    <div class="section-head">
      <div>
        <div class="section-title">Featured Collections</div>
        <div class="section-note">Compact showcase cards for quick browsing.</div>
      </div>
    </div>
    <div class="featured-grid" id="featured">
      <?php if (!$featuredProducts): ?>
        <div class="notice">No products yet. Add some in the admin dashboard.</div>
      <?php endif; ?>
      <?php foreach ($featuredProducts as $product):
        $rating = (float)($product['rating'] ?? 0);
        $stars = str_repeat('★', (int)round($rating)) . str_repeat('☆', 5 - (int)round($rating));
        $inWishlist = in_array((int)$product['id'], $wishlistIds, true);
        $inStock = (int)$product['quantity'] > 0;
        $discountPct = productDiscountPercent((float)$product['price'], isset($product['compare_at_price']) ? (float)$product['compare_at_price'] : null);
      ?>
        <div class="prod-card">
          <div class="prod-img">
            <a href="product.php?id=<?= (int)$product['id'] ?>">
              <img src="<?= h(productImageUrl((string)($product['image'] ?? ''))) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt="<?= h((string)$product['product_name']) ?>">
            </a>
            <?php if ($discountPct > 0): ?>
              <span class="discount-badge">-<?= $discountPct ?>%</span>
            <?php endif; ?>
            <?php if (!$inStock): ?>
              <div class="outofstock-badge">Out of Stock</div>
            <?php endif; ?>
            <button type="button" class="wishlist-btn<?= $inWishlist ? ' in-wishlist' : '' ?>" data-wishlist-toggle data-product-id="<?= (int)$product['id'] ?>" aria-pressed="<?= $inWishlist ? 'true' : 'false' ?>" aria-label="Toggle wishlist" title="Toggle wishlist"><?= $inWishlist ? '♥' : '♡' ?></button>
          </div>
          <div class="prod-info">
            <div class="prod-brand"><?= h((string)($product['category_name'] ?? 'SmartShop')) ?></div>
            <div class="prod-name"><?= h((string)$product['product_name']) ?></div>
            <div style="font-size:11px;color:var(--gray-500);margin-bottom:2px">Stock: <?= (int)$product['quantity'] ?></div>
            <div class="prod-stars"><?= h($stars) ?> <span style="color:var(--gray-500);font-size:11px"><?= number_format($rating, 1) ?></span></div>
            <div>
              <?php if ($discountPct > 0): ?><span class="compare-at-price" style="color:var(--gray-500);font-weight:500;font-size:0.82em;text-decoration:line-through;margin-right:6px"><?= h(money($conn, (float)$product['compare_at_price'])) ?></span><?php endif; ?>
              <span class="prod-price"><?= h(money($conn, (float)((float)$product['price']))) ?></span>
            </div>
            <?php if ($inStock): ?>
              <button class="add-cart" type="button" data-add-cart data-product-id="<?= (int)$product['id'] ?>" data-qty="1">Add to Cart</button>
            <?php else: ?>
              <button class="add-cart" type="button" disabled style="opacity:.55;cursor:not-allowed">Out of Stock</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="section" style="padding-top:0">
    <div class="section-head"><div><div class="section-title">Trending Now</div><div class="section-note">What people are clicking most.</div></div></div>
    <div class="trending">
      <?php if (!$trendingProducts): ?>
        <div class="notice">No trending products yet.</div>
      <?php endif; ?>
      <?php foreach ($trendingProducts as $product):
        $sold = (int)($product['sold_count'] ?? 0);
        $soldLabel = $sold >= 1000 ? number_format($sold / 1000, 1) . 'k Sold' : $sold . ' Sold';
      ?>
        <a class="trend-card" href="product.php?id=<?= (int)$product['id'] ?>" style="color:inherit">
          <div class="trend-img"><img src="<?= h(productImageUrl((string)($product['image'] ?? ''))) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt="<?= h((string)$product['product_name']) ?>"></div>
          <div>
            <div class="trend-name"><?= h((string)$product['product_name']) ?></div>
            <div class="trend-price"><?= h(money($conn, (float)((float)$product['price']))) ?></div>
            <div class="trend-sold"><?= h($soldLabel) ?></div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <footer>
    <div class="footer-grid">
      <div>
        <div class="footer-logo">SmartShop</div>
        <div class="footer-desc">The future of frictionless commerce and inventory management. Premium experiences delivered daily.</div>
      </div>
      <div>
        <div class="footer-heading">Quick Links</div>
        <span class="footer-link">Track Order</span>
        <span class="footer-link">Store Locator</span>
        <span class="footer-link">Gift Cards</span>
        <span class="footer-link">Wholesale</span>
      </div>
      <div>
        <div class="footer-heading">Support</div>
        <span class="footer-link">Help Center</span>
        <span class="footer-link">Shipping Policy</span>
        <span class="footer-link">Return &amp; Refund</span>
        <span class="footer-link">Privacy Policy</span>
      </div>
      <div>
        <div class="footer-heading">Newsletter</div>
        <div class="footer-desc">Subscribe to get special offers and stay updated.</div>
        <form class="footer-newsletter" id="newsletter-form">
          <input class="footer-input" id="newsletter-email" name="email" type="email" placeholder="Your email address" required />
          <button class="btn btn-primary" style="padding:8px 14px;font-size:12px" type="submit">Join</button>
        </form>
        <div id="newsletter-msg" style="font-size:11px;margin-top:6px;min-height:14px"></div>
      </div>
    </div>
    <div class="footer-bottom">© 2026 SmartShop Ecosystem. All rights reserved.</div>
  </footer>
</div>

<div id="shop" class="page">
  <section class="shop-layout">
    <aside class="sidebar">
      <div class="sidebar-title">Filters</div>
      <div class="filter-group">
        <div class="filter-label">Categories</div>
        <?php if (!$categories): ?>
          <div style="font-size:12px;color:var(--gray-500)">No categories yet.</div>
        <?php endif; ?>
        <?php foreach ($categories as $i => $cat): ?>
          <label class="filter-item"><input type="radio" name="cat" <?= $i === 0 ? 'checked' : '' ?> onclick="window.location.href='shop.php?category=<?= (int)$cat['id'] ?>'"> <?= h((string)$cat['category_name']) ?> (<?= (int)$cat['product_count'] ?>)</label>
        <?php endforeach; ?>
      </div>
      <div class="filter-group">
        <div class="filter-label">Price Range</div>
        <?php $priceCurrency = h(currencySymbol($conn)); ?>
        <input type="range" class="range-input" min="0" max="2000" value="1200" oninput="document.getElementById('price-val').textContent='<?= $priceCurrency ?>' + this.value">
        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--gray-500);margin-top:4px"><span><?= $priceCurrency ?>0</span><span id="price-val"><?= $priceCurrency ?>1,200</span></div>
      </div>
      <div class="filter-group">
        <div class="filter-label">Brands</div>
        <label class="filter-item"><input type="checkbox" checked> SmartShop</label>
        <label class="filter-item"><input type="checkbox"> Nebula</label>
        <label class="filter-item"><input type="checkbox"> Quantix</label>
        <label class="filter-item"><input type="checkbox"> Titan Pro</label>
      </div>
    </aside>

    <div>
      <div class="shop-header">
        <div>
          <form action="shop.php" method="GET" style="display:inline">
            <input class="shop-search" name="q" placeholder="Search products..." type="text">
          </form>
          <div class="shop-count">Showing <?= count($shopProducts) ?> of <?= count($products) ?> products</div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <span style="font-size:12px;color:var(--gray-500)">Sort by:</span>
          <select class="sort-select" onchange="window.location.href='shop.php?sort='+this.value">
            <option value="best_selling">Popularity</option>
            <option value="price_asc">Price: Low to High</option>
            <option value="price_desc">Price: High to Low</option>
            <option value="newest">Newest</option>
          </select>
        </div>
      </div>

      <div class="prod-grid-3">
        <?php if (count($shopProducts) === 0): ?>
          <div class="notice">No products yet. Add some in the admin dashboard.</div>
        <?php endif; ?>

        <?php foreach ($shopProducts as $product):
          $rating = (float)($product['rating'] ?? 0);
          $stars = str_repeat('★', (int)round($rating)) . str_repeat('☆', 5 - (int)round($rating));
          $inWishlist = in_array((int)$product['id'], $wishlistIds, true);
          $inStock = (int)$product['quantity'] > 0;
          $discountPct = productDiscountPercent((float)$product['price'], isset($product['compare_at_price']) ? (float)$product['compare_at_price'] : null);
        ?>
          <div class="prod-card">
            <div class="prod-img">
              <a href="product.php?id=<?= (int)$product['id'] ?>">
                <img src="<?= h(productImageUrl((string)($product['image'] ?? ''))) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt="<?= h((string)$product['product_name']) ?>">
                <?php if (!$inStock): ?>
                  <span class="prod-badge badge-red">Out of Stock</span>
                <?php elseif ((int)$product['quantity'] <= 5): ?>
                  <span class="prod-badge badge-amber">Low</span>
                <?php else: ?>
                  <span class="prod-badge badge-green">In Stock</span>
                <?php endif; ?>
              </a>
              <?php if ($discountPct > 0): ?>
                <span class="discount-badge">-<?= $discountPct ?>%</span>
              <?php endif; ?>
              <?php if (!$inStock): ?>
                <div class="outofstock-badge">Out of Stock</div>
              <?php endif; ?>
              <button type="button" class="wishlist-btn<?= $inWishlist ? ' in-wishlist' : '' ?>" data-wishlist-toggle data-product-id="<?= (int)$product['id'] ?>" aria-pressed="<?= $inWishlist ? 'true' : 'false' ?>" aria-label="Toggle wishlist" title="Toggle wishlist"><?= $inWishlist ? '♥' : '♡' ?></button>
            </div>
            <div class="prod-info">
              <div class="prod-brand"><?= h((string)($product['category_name'] ?? 'SmartShop')) ?></div>
              <div class="prod-name"><?= h((string)$product['product_name']) ?></div>
              <div style="font-size:11px;color:var(--gray-500);margin-bottom:4px">Qty: <?= h((string)$product['quantity']) ?></div>
              <div class="prod-stars"><?= h($stars) ?> <span style="color:var(--gray-500);font-size:11px"><?= number_format($rating, 1) ?></span></div>
              <div>
                <?php if ($discountPct > 0): ?><span class="compare-at-price" style="color:var(--gray-500);font-weight:500;font-size:0.82em;text-decoration:line-through;margin-right:6px"><?= h(money($conn, (float)$product['compare_at_price'])) ?></span><?php endif; ?>
                <span class="prod-price"><?= h(money($conn, (float)((float)$product['price']))) ?></span>
              </div>
              <?php if ($inStock): ?>
                <button class="add-cart" type="button" data-add-cart data-product-id="<?= (int)$product['id'] ?>" data-qty="1">Add to Cart</button>
              <?php else: ?>
                <button class="add-cart" type="button" disabled style="opacity:.55;cursor:not-allowed">Out of Stock</button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</div>

<script>
function gotoPage(id, link) {
  const url = new URL(window.location.href);
  url.searchParams.set('p', id);
  window.history.replaceState({}, '', url.toString());
  showPage(id, link);
}

function showPage(id, link) {
  document.querySelectorAll('.page').forEach(page => page.classList.remove('active'));
  document.querySelectorAll('.nav-link').forEach(item => item.classList.remove('active'));
  const page = document.getElementById(id);
  if (page) {
    page.classList.add('active');
  }
  if (link) {
    link.classList.add('active');
  }
  window.scrollTo(0, 0);
}

(function initFromUrl() {
  const url = new URL(window.location.href);
  const p = url.searchParams.get('p') || <?= json_encode($page) ?>;
  const link = document.querySelector('.nav-link[data-page="' + p + '"]');
  showPage(p, link);
})();

function smoothScrollTo(id) {
  const el = document.getElementById(id);
  if (el && 'scrollBehavior' in document.documentElement.style) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    return false;
  }
  return true; // let the browser fall back to the plain #anchor jump
}

// ── Dropdown menus (profile + mini cart) ─────────────────────────
document.querySelectorAll('.dropdown').forEach(function (dropdown) {
  const trigger = dropdown.querySelector('.dropdown-trigger');
  const panel = dropdown.querySelector('.dropdown-panel');
  if (!trigger || !panel) return;

  trigger.addEventListener('click', function (e) {
    e.stopPropagation();
    const isOpen = panel.classList.contains('open');
    document.querySelectorAll('.dropdown-panel.open').forEach(p => p.classList.remove('open'));
    if (!isOpen) {
      panel.classList.add('open');
      trigger.setAttribute('aria-expanded', 'true');
      if (dropdown.id === 'cart-dropdown') {
        loadMiniCart();
      }
    } else {
      trigger.setAttribute('aria-expanded', 'false');
    }
  });
});

document.addEventListener('click', function () {
  document.querySelectorAll('.dropdown-panel.open').forEach(function (p) {
    p.classList.remove('open');
  });
  document.querySelectorAll('.dropdown-trigger').forEach(t => t.setAttribute('aria-expanded', 'false'));
});

const CURRENCY_SYMBOL = <?= json_encode(currencySymbol($conn), JSON_UNESCAPED_UNICODE) ?>;
const CURRENCY_SPACED = <?= json_encode(!in_array(currencySymbol($conn), ['$', '€', '£'], true)) ?>;
function formatMoney(value) {
  return CURRENCY_SPACED ? (CURRENCY_SYMBOL + ' ' + value) : (CURRENCY_SYMBOL + value);
}

async function loadMiniCart() {
  const panel = document.getElementById('mini-cart-panel');
  if (!panel) return;
  panel.innerHTML = '<div class="mini-cart-empty">Loading...</div>';
  try {
    const res = await fetch('api/mini_cart.php', { credentials: 'same-origin' });
    const data = await res.json();
    if (!data.ok || !data.items || data.items.length === 0) {
      panel.innerHTML = '<div class="mini-cart-empty">' + (data.message || 'Your cart is empty.') + '</div>';
      return;
    }
    let html = '';
    data.items.forEach(function (item) {
      html += '<div class="mini-cart-item">' +
        '<img src="' + item.image + '" alt="">' +
        '<div style="flex:1"><div style="font-size:12px;font-weight:700">' + item.name + '</div>' +
        '<div style="font-size:11px;color:var(--gray-500)">Qty: ' + item.qty + ' &middot; ' + formatMoney(item.subtotal) + '</div></div>' +
        '</div>';
    });
    html += '<div class="dropdown-divider"></div>' +
      '<div style="display:flex;justify-content:space-between;padding:6px 10px;font-size:12px;font-weight:700"><span>Total</span><span>' + formatMoney(data.total) + '</span></div>' +
      '<a href="checkout.php" class="btn btn-primary" style="width:100%;margin:4px 0;justify-content:center">Checkout</a>' +
      '<a href="cart.php" class="btn btn-outline" style="width:100%;justify-content:center">View Cart</a>';
    panel.innerHTML = html;
  } catch (err) {
    panel.innerHTML = '<div class="mini-cart-empty">Could not load your cart.</div>';
  }
}

// ── Newsletter subscribe ──────────────────────────────────────────
const newsletterForm = document.getElementById('newsletter-form');
if (newsletterForm) {
  newsletterForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const msgEl = document.getElementById('newsletter-msg');
    const emailInput = document.getElementById('newsletter-email');
    const form = new FormData();
    form.append('email', emailInput.value);
    msgEl.textContent = 'Submitting...';
    msgEl.style.color = 'rgba(255,255,255,.6)';
    try {
      const res = await fetch('api/newsletter_subscribe.php', { method: 'POST', body: form, credentials: 'same-origin' });
      const data = await res.json();
      msgEl.textContent = data.message || '';
      msgEl.style.color = data.ok ? '#4ade80' : '#f87171';
      if (data.ok) emailInput.value = '';
    } catch (err) {
      msgEl.textContent = 'Something went wrong. Please try again.';
      msgEl.style.color = '#f87171';
    }
  });
}
</script>
<script src="assets/js/store.js"></script>
</body>
</html>