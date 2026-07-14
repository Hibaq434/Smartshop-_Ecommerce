<?php
$activePage = $activePage ?? '';
$cartCount = $cartCount ?? 0;
?>
<nav class="site-nav">
  <a class="brand" href="index.php">SmartShop</a>

  <div class="nav-links">
    <a class="nav-link<?= $activePage === 'home' ? ' active' : '' ?>" href="index.php">Home</a>
    <a class="nav-link<?= $activePage === 'shop' ? ' active' : '' ?>" href="shop.php">Shop</a>

    <?php if (isAdmin()): ?>
      <a class="nav-link<?= $activePage === 'dashboard' ? ' active' : '' ?>" href="dashboard.php">Dashboard</a>
      <a class="nav-link" href="dashboard.php?section=products">Products</a>
      <a class="nav-link" href="dashboard.php?section=orders">Orders</a>
      <a class="nav-link" href="dashboard.php?section=users">Users</a>
      <a class="nav-link" href="dashboard.php?section=categories">Categories</a>
    <?php endif; ?>
  </div>

  <div class="nav-right">
    <?php if (isLoggedIn()): ?>
      <div class="dropdown" id="cart-dropdown">
        <button class="cart-link cart-btn dropdown-trigger" type="button" id="cart-trigger" aria-haspopup="true" aria-expanded="false">
          <span class="cart-icon">🛒 Cart</span>
          <span class="cart-badge" id="cart-count"><?= (int)$cartCount ?></span>
        </button>
        <div class="dropdown-panel mini-cart-panel" id="mini-cart-panel"></div>
      </div>

      <div class="dropdown" id="user-dropdown">
        <button class="user-chip dropdown-trigger" type="button" id="user-trigger" aria-haspopup="true" aria-expanded="false">
          <span class="user-avatar"><?= strtoupper(substr(currentUsername(), 0, 1)) ?></span>
          <span><?= h(currentUsername()) ?></span>
          <span class="role-pill <?= isAdmin() ? 'admin' : 'user' ?>"><?= h(currentRoleLabel()) ?></span>
          <span class="dropdown-caret">▼</span>
        </button>
        <div class="dropdown-panel" id="user-panel">
          <a href="profile.php">👤 Profile</a>
          <a href="my_orders.php">🛍 My Orders</a>
          <a href="wishlist.php">❤️ Wishlist</a>
          <a href="settings.php">⚙ Settings</a>
          <?php if (isAdmin()): ?>
            <div class="dropdown-divider"></div>
            <a href="dashboard.php">📊 Dashboard</a>
          <?php endif; ?>
          <div class="dropdown-divider"></div>
          <a href="logout.php">🚪 Logout</a>
        </div>
      </div>
    <?php else: ?>
      <a class="btn primary" href="login.php">Login</a>
    <?php endif; ?>
  </div>
</nav>

<?php if (isLoggedIn()): ?>
<script>
document.querySelectorAll('.dropdown').forEach(function (dropdown) {
  const trigger = dropdown.querySelector('.dropdown-trigger');
  const panel = dropdown.querySelector('.dropdown-panel');
  if (!trigger || !panel) { return; }

  trigger.addEventListener('click', function (e) {
    e.stopPropagation();
    const isOpen = panel.classList.contains('open');
    document.querySelectorAll('.dropdown-panel.open').forEach(p => p.classList.remove('open'));
    document.querySelectorAll('.dropdown-trigger').forEach(t => t.setAttribute('aria-expanded', 'false'));
    if (!isOpen) {
      panel.classList.add('open');
      trigger.setAttribute('aria-expanded', 'true');
      if (dropdown.id === 'cart-dropdown' && typeof loadMiniCart === 'function') {
        loadMiniCart();
      }
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
  if (!panel) { return; }
  panel.innerHTML = '<div class="mini-cart-empty">Loading...</div>';
  try {
    const res = await fetch('api/mini_cart.php', { credentials: 'same-origin' });
    const data = await res.json();
    if (!data.items || data.items.length === 0) {
      panel.innerHTML = '<div class="mini-cart-empty">' + (data.message || 'Your cart is empty.') + '</div>';
      return;
    }
    let html = '';
    data.items.forEach(function (item) {
      html += '<div class="mini-cart-item">' +
        '<img src="' + item.image + '" alt="" onerror="this.onerror=null;this.src=\'assets/images/products/default.jpg\';">' +
        '<div><div style="font-size:12px;font-weight:600">' + item.name + '</div>' +
        '<div style="font-size:11px;color:var(--muted)">Qty: ' + item.qty + ' &middot; ' + formatMoney(item.subtotal) + '</div></div>' +
        '</div>';
    });
    html += '<div class="dropdown-divider"></div>' +
      '<div style="display:flex;justify-content:space-between;padding:6px 10px;font-size:12px;font-weight:700"><span>Total</span><span>' + formatMoney(data.total) + '</span></div>' +
      '<a href="cart.php" class="btn outline" style="width:100%;justify-content:center;display:flex;margin-top:6px">View Cart</a>';
    panel.innerHTML = html;
  } catch (e) {
    panel.innerHTML = '<div class="mini-cart-empty">Could not load your cart.</div>';
  }
}
</script>
<?php endif; ?>