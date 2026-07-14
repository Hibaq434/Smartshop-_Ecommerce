<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

// Logged-in visitors never see the landing page — straight to Home.
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

ensureCoreSchema($conn);
ensureNewsletterTable($conn);

// ── Shop by Category: dynamic categories with live product counts ──
$categories = fetchCategoriesWithCounts($conn);
$catCardClasses = ['cat-electronics', 'cat-fashion', 'cat-home', 'cat-beauty'];

// ── Featured Products: latest products from the DB ───────────────
$featuredProducts = [];
$res = mysqli_query(
    $conn,
    'SELECT p.id, p.product_name, p.price, p.image, p.rating, c.category_name
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

$storeName    = getSetting($conn, 'store_name', 'SmartShop');
$storeEmail   = getSetting($conn, 'store_email', 'support@smartshop.test');
$storePhone   = getSetting($conn, 'store_phone', '');
$storeAddress = getSetting($conn, 'store_address', '');
$bannerText   = getSetting($conn, 'homepage_banner', '');

$testimonials = [
    ['name' => 'Amina K.', 'role' => 'Verified Buyer', 'quote' => 'Ordering was effortless and my package showed up faster than I expected. The whole experience felt genuinely premium.'],
    ['name' => 'David O.', 'role' => 'Verified Buyer', 'quote' => 'I was skeptical about buying electronics online, but the product matched the listing exactly and support answered my questions within minutes.'],
    ['name' => 'Grace W.', 'role' => 'Verified Buyer', 'quote' => 'The variety across categories is what keeps me coming back — I did my whole gift shopping in one place.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($storeName) ?> — Shop Smarter</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--blue:#2563EB;--blue-dark:#1d4ed8;--gray-50:#f8fafc;--gray-100:#f1f5f9;--gray-200:#e2e8f0;--gray-500:#64748b;--gray-700:#334155;--gray-900:#0f172a;--green:#16a34a;--red:#dc2626;--amber:#d97706}
body{font-family:'Segoe UI',system-ui,sans-serif;color:var(--gray-900);background:#fff;font-size:14px;line-height:1.5}
a{text-decoration:none}
.nav{display:flex;align-items:center;gap:18px;padding:0 24px;height:56px;border-bottom:1px solid var(--gray-200);background:#fff;position:sticky;top:0;z-index:100}
.nav-logo{font-size:18px;font-weight:800;color:var(--blue)}
.nav-links{margin-left:auto;display:flex;align-items:center;gap:10px}
.nav-link{font-size:13px;color:var(--gray-700);padding:6px 8px;border-radius:6px;cursor:pointer}
.nav-link:hover{color:var(--blue);background:var(--gray-100)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;border:none;transition:all .15s}
.btn-primary{background:var(--blue);color:#fff}.btn-primary:hover{background:var(--blue-dark)}
.btn-outline{background:#fff;color:var(--gray-700);border:1px solid var(--gray-200)}.btn-outline:hover{background:var(--gray-50)}
.hero{background:linear-gradient(135deg,#f0f7ff 0%,#e8f4fd 100%);padding:64px 24px;display:grid;grid-template-columns:1.1fr .9fr;gap:32px;align-items:center}
.hero-label{display:inline-flex;background:#dbeafe;color:#1e40af;font-size:11px;font-weight:800;padding:4px 10px;border-radius:999px;margin-bottom:14px;text-transform:uppercase;letter-spacing:.5px}
.hero-title{font-size:44px;font-weight:900;line-height:1.1;color:var(--gray-900);margin-bottom:14px}
.hero-title span{color:var(--blue)}
.hero-desc{font-size:15px;color:var(--gray-500);margin-bottom:24px;line-height:1.7;max-width:56ch}
.hero-btns{display:flex;gap:10px;flex-wrap:wrap}
.hero-card{min-height:280px;border-radius:24px;background:linear-gradient(135deg,#1e3a5f,#2563EB);display:flex;align-items:center;justify-content:center;font-size:100px;box-shadow:0 22px 50px rgba(37,99,235,.25)}
.banner{background:var(--gray-900);color:#fff;text-align:center;padding:9px 16px;font-size:12px;font-weight:600}
.section{padding:48px 24px}
.section-head{text-align:center;margin-bottom:28px}
.section-title{font-size:24px;font-weight:800;color:var(--gray-900)}
.section-note{font-size:13px;color:var(--gray-500);margin-top:6px}
.features-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
.feature-card{border:1px solid var(--gray-200);border-radius:16px;padding:22px 18px;text-align:center}
.feature-icon{font-size:30px;margin-bottom:10px}
.feature-title{font-size:14px;font-weight:800;margin-bottom:6px}
.feature-desc{font-size:12px;color:var(--gray-500);line-height:1.6}
.cat-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:10px;min-height:240px}
.cat-stack{display:grid;grid-template-rows:1fr 1fr;gap:10px}
.cat-card{border-radius:18px;display:flex;align-items:flex-end;padding:16px;color:#fff;font-weight:700;min-height:115px;background-size:cover;background-position:center}
.cat-electronics{background:linear-gradient(135deg,#1e3a5f,#2563EB)}
.cat-fashion{background:linear-gradient(135deg,#78350f,#d97706)}
.cat-home{background:linear-gradient(135deg,#14532d,#16a34a)}
.cat-beauty{background:linear-gradient(135deg,#5b21b6,#8b5cf6)}
.cat-label{background:rgba(0,0,0,.45);backdrop-filter:blur(4px);padding:6px 10px;border-radius:999px;font-size:12px}
.cat-count{display:block;font-size:11px;font-weight:600;opacity:.9;margin-top:2px}
.featured-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.prod-card{border:1px solid var(--gray-200);border-radius:16px;overflow:hidden;background:#fff;transition:transform .15s, box-shadow .15s}
.prod-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(15,23,42,.08)}
.prod-img{height:150px;display:flex;align-items:center;justify-content:center;font-size:40px;position:relative;color:#fff;overflow:hidden;background:var(--gray-100)}
.prod-img img{width:100%;height:100%;object-fit:cover;display:block}
.prod-info{padding:14px}
.prod-brand{font-size:10px;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px}
.prod-name{font-size:14px;font-weight:700;margin:3px 0 6px}
.prod-stars{color:#f59e0b;font-size:11px;margin-bottom:6px}
.prod-price{font-size:16px;font-weight:800;color:var(--blue)}
.notice{background:#fff;border:1px solid var(--gray-200);border-left:4px solid var(--blue);border-radius:12px;padding:12px 14px;font-size:12px;color:var(--gray-700)}
.testimonial-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.testimonial-card{background:var(--gray-50);border:1px solid var(--gray-200);border-radius:16px;padding:22px}
.testimonial-quote{font-size:13px;color:var(--gray-700);line-height:1.7;margin-bottom:16px}
.testimonial-quote::before{content:"\201C";color:var(--blue);font-size:24px;font-weight:900;margin-right:2px}
.testimonial-name{font-size:13px;font-weight:800}
.testimonial-role{font-size:11px;color:var(--gray-500)}
.about-wrap{display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:center;background:var(--gray-50);border-radius:20px;padding:40px}
.about-title{font-size:22px;font-weight:800;margin-bottom:12px}
.about-desc{font-size:13px;color:var(--gray-700);line-height:1.8;margin-bottom:10px}
.about-stats{display:flex;gap:28px;margin-top:18px}
.about-stat-value{font-size:22px;font-weight:900;color:var(--blue)}
.about-stat-label{font-size:11px;color:var(--gray-500)}
.about-card{min-height:220px;border-radius:20px;background:linear-gradient(135deg,#5b21b6,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:80px}
.newsletter-section{background:var(--blue);border-radius:20px;padding:40px;text-align:center;color:#fff}
.newsletter-title{font-size:22px;font-weight:800;margin-bottom:8px}
.newsletter-desc{font-size:13px;opacity:.9;margin-bottom:20px}
.newsletter-form{display:flex;gap:10px;max-width:420px;margin:0 auto;flex-wrap:wrap}
.newsletter-input{flex:1;min-width:200px;padding:11px 14px;border-radius:8px;border:none;font-size:13px}
.contact-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.contact-card{border:1px solid var(--gray-200);border-radius:16px;padding:20px;text-align:center}
.contact-icon{font-size:22px;margin-bottom:8px}
.contact-label{font-size:11px;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.contact-value{font-size:13px;font-weight:700}
footer{background:var(--gray-900);color:#fff;padding:32px 24px 16px;margin-top:8px}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:32px;margin-bottom:24px}
.footer-logo{font-size:20px;font-weight:800;margin-bottom:8px}
.footer-desc{font-size:12px;color:rgba(255,255,255,.55);line-height:1.6}
.footer-heading{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:rgba(255,255,255,.45);margin-bottom:12px}
.footer-link{display:block;font-size:12px;color:rgba(255,255,255,.65);margin-bottom:6px}
.footer-bottom{border-top:1px solid rgba(255,255,255,.1);padding-top:16px;font-size:11px;color:rgba(255,255,255,.4);text-align:center}
@media(max-width:980px){.hero,.about-wrap,.footer-grid{grid-template-columns:1fr}.features-grid,.featured-grid,.testimonial-grid,.contact-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:620px){.features-grid,.featured-grid,.testimonial-grid,.contact-grid,.footer-grid{grid-template-columns:1fr}.hero-title{font-size:32px}.cat-grid{grid-template-columns:1fr}.nav{gap:10px}}
</style>
</head>
<body>

<nav class="nav">
  <span class="nav-logo"><?= h($storeName) ?></span>
  <div class="nav-links">
    <a class="nav-link" href="shop.php">Browse Products</a>
    <a class="btn btn-outline" href="login.php">Login</a>
    <a class="btn btn-primary" href="register.php">Register</a>
  </div>
</nav>

<?php if ($bannerText !== ''): ?>
  <div class="banner"><?= h($bannerText) ?></div>
<?php endif; ?>

<section class="hero">
  <div>
    <div class="hero-label">Welcome to <?= h($storeName) ?></div>
    <h1 class="hero-title">Shop smarter, <span>live better.</span></h1>
    <p class="hero-desc">Electronics, fashion, home essentials, and beauty — all in one place, with fast delivery and prices that make sense. Join thousands of happy shoppers today.</p>
    <div class="hero-btns">
      <a class="btn btn-primary" href="register.php">Create Free Account</a>
      <a class="btn btn-outline" href="login.php">Login</a>
      <a class="btn btn-outline" href="shop.php">Browse Products</a>
    </div>
  </div>
  <div class="hero-card">🛍️</div>
</section>

<section class="section">
  <div class="section-head">
    <div class="section-title">Why Shop With Us</div>
    <div class="section-note">Everything you'd expect from a modern store, without the markup.</div>
  </div>
  <div class="features-grid">
    <div class="feature-card">
      <div class="feature-icon">🚚</div>
      <div class="feature-title">Fast Delivery</div>
      <div class="feature-desc">Orders are packed and shipped quickly, with tracking every step of the way.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">🔒</div>
      <div class="feature-title">Secure Checkout</div>
      <div class="feature-desc">Your details are protected with industry-standard security on every order.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">⭐</div>
      <div class="feature-title">Quality Products</div>
      <div class="feature-desc">Every item is checked for quality before it's listed — no surprises on arrival.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon">💬</div>
      <div class="feature-title">Real Support</div>
      <div class="feature-desc">Questions before or after you buy? Our team actually replies, and quickly.</div>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="section-head">
    <div class="section-title">Shop by Category</div>
    <div class="section-note">Find exactly what you're looking for.</div>
  </div>
  <?php if (!$categories): ?>
    <div class="notice">No categories yet. Check back soon.</div>
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
    <div class="section-title">Featured Products</div>
    <div class="section-note">Fresh arrivals our customers are loving right now.</div>
  </div>
  <div class="featured-grid">
    <?php if (!$featuredProducts): ?>
      <div class="notice">No products yet. Check back soon.</div>
    <?php endif; ?>
    <?php foreach ($featuredProducts as $product):
      $rating = (float)($product['rating'] ?? 0);
      $stars = str_repeat('★', (int)round($rating)) . str_repeat('☆', 5 - (int)round($rating));
    ?>
      <a class="prod-card" href="login.php" style="color:inherit">
        <div class="prod-img">
          <img src="<?= h(productImageUrl((string)($product['image'] ?? ''))) ?>" onerror="this.onerror=null;this.src='assets/images/products/default.jpg';" alt="<?= h((string)$product['product_name']) ?>">
        </div>
        <div class="prod-info">
          <div class="prod-brand"><?= h((string)($product['category_name'] ?? 'SmartShop')) ?></div>
          <div class="prod-name"><?= h((string)$product['product_name']) ?></div>
          <div class="prod-stars"><?= h($stars) ?> <span style="color:var(--gray-500);font-size:11px"><?= number_format($rating, 1) ?></span></div>
          <div class="prod-price"><?= h(money($conn, (float)$product['price'])) ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <div style="text-align:center;margin-top:24px">
    <a class="btn btn-primary" href="register.php">Sign Up to Shop</a>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="section-head">
    <div class="section-title">What Our Customers Say</div>
    <div class="section-note">Real feedback from real SmartShop orders.</div>
  </div>
  <div class="testimonial-grid">
    <?php foreach ($testimonials as $t): ?>
      <div class="testimonial-card">
        <div class="testimonial-quote"><?= h($t['quote']) ?></div>
        <div class="testimonial-name"><?= h($t['name']) ?></div>
        <div class="testimonial-role"><?= h($t['role']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="newsletter-section">
    <div class="newsletter-title">Get the Best Deals First</div>
    <div class="newsletter-desc">Subscribe for exclusive offers, new arrivals, and restock alerts.</div>
    <form class="newsletter-form" id="newsletter-form">
      <input class="newsletter-input" id="newsletter-email" name="email" type="email" placeholder="Your email address" required>
      <button class="btn btn-outline" style="background:#fff" type="submit">Subscribe</button>
    </form>
    <div id="newsletter-msg" style="font-size:12px;margin-top:10px;min-height:16px"></div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="about-wrap">
    <div class="about-card">🏬</div>
    <div>
      <div class="about-title">About <?= h($storeName) ?></div>
      <p class="about-desc">We started <?= h($storeName) ?> to make everyday online shopping simple — real products, honest prices, and a checkout that doesn't fight you.</p>
      <p class="about-desc">From electronics to beauty essentials, every category is curated and kept in stock, with an admin team that actually reviews what goes on the shelf.</p>
      <div class="about-stats">
        <div><div class="about-stat-value"><?= (int)array_sum(array_column($categories, 'product_count')) ?>+</div><div class="about-stat-label">Products Listed</div></div>
        <div><div class="about-stat-value"><?= count($categories) ?></div><div class="about-stat-label">Categories</div></div>
        <div><div class="about-stat-value">24/7</div><div class="about-stat-label">Support</div></div>
      </div>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="section-head">
    <div class="section-title">Get in Touch</div>
    <div class="section-note">Questions, feedback, or partnership ideas — we're listening.</div>
  </div>
  <div class="contact-grid">
    <div class="contact-card">
      <div class="contact-icon">✉️</div>
      <div class="contact-label">Email</div>
      <div class="contact-value"><?= h($storeEmail) ?></div>
    </div>
    <?php if ($storePhone !== ''): ?>
      <div class="contact-card">
        <div class="contact-icon">📞</div>
        <div class="contact-label">Phone</div>
        <div class="contact-value"><?= h($storePhone) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($storeAddress !== ''): ?>
      <div class="contact-card">
        <div class="contact-icon">📍</div>
        <div class="contact-label">Address</div>
        <div class="contact-value"><?= h($storeAddress) ?></div>
      </div>
    <?php endif; ?>
  </div>
  <div style="text-align:center;margin-top:20px">
    <a class="btn btn-outline" href="contact.php">Open Contact Form</a>
  </div>
</section>

<footer>
  <div class="footer-grid">
    <div>
      <div class="footer-logo"><?= h($storeName) ?></div>
      <div class="footer-desc">The future of frictionless commerce — premium products, honest prices, delivered daily.</div>
    </div>
    <div>
      <div class="footer-heading">Shop</div>
      <a class="footer-link" href="shop.php">Browse Products</a>
      <a class="footer-link" href="register.php">Create Account</a>
      <a class="footer-link" href="login.php">Login</a>
    </div>
    <div>
      <div class="footer-heading">Support</div>
      <a class="footer-link" href="contact.php">Contact Us</a>
      <span class="footer-link"><?= h($storeEmail) ?></span>
    </div>
  </div>
  <div class="footer-bottom">&copy; <?= h(date('Y')) ?> <?= h($storeName) ?>. All rights reserved.</div>
</footer>

<script>
const newsletterForm = document.getElementById('newsletter-form');
if (newsletterForm) {
  newsletterForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const msgEl = document.getElementById('newsletter-msg');
    const emailInput = document.getElementById('newsletter-email');
    msgEl.textContent = 'Submitting...';
    try {
      const form = new FormData();
      form.append('email', emailInput.value);
      const res = await fetch('api/newsletter_subscribe.php', { method: 'POST', body: form, credentials: 'same-origin' });
      const data = await res.json();
      msgEl.textContent = data.message || (data.ok ? 'Subscribed!' : 'Something went wrong.');
      if (data.ok) { emailInput.value = ''; }
    } catch (err) {
      msgEl.textContent = 'Could not subscribe right now. Please try again.';
    }
  });
}
</script>

</body>
</html>