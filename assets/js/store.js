(function () {
  function toast(message, isError) {
    let el = document.getElementById('global-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'global-toast';
      el.className = 'toast';
      document.body.appendChild(el);
    }

    el.textContent = message;
    el.style.background = isError ? '#991b1b' : '#0f172a';
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 1800);
  }

  async function refreshCartCount() {
    const badge = document.getElementById('cart-count');
    if (!badge) {
      return;
    }

    try {
      const res = await fetch('api/cart_count.php', { credentials: 'same-origin' });
      const data = await res.json();
      if (data && data.ok) {
        badge.textContent = String(data.count);
      }
    } catch (err) {
      // keep silent on count refresh failures
    }
  }

  async function addToCart(productId, quantity) {
    const form = new FormData();
    form.append('product_id', String(productId));
    form.append('quantity', String(quantity || 1));

    const res = await fetch('api/cart_add.php', {
      method: 'POST',
      body: form,
      credentials: 'same-origin'
    });

    let data = null;
    try {
      data = await res.json();
    } catch (err) {
      throw new Error('Unexpected response from server.');
    }

    if (!data.ok) {
      throw new Error(data.message || 'Failed to add item to cart.');
    }

    const cart = document.getElementById('cart-link');
    if (cart) {
      cart.classList.remove('bump');
      void cart.offsetWidth;
      cart.classList.add('bump');
    }

    await refreshCartCount();
    toast(data.message || 'Added to cart');
  }

  document.addEventListener('click', async function (event) {
    const wishBtn = event.target.closest('[data-wishlist-toggle]');
    if (wishBtn) {
      event.preventDefault();
      const productId = Number(wishBtn.getAttribute('data-product-id') || '0');
      if (productId <= 0) {
        return;
      }
      const form = new FormData();
      form.append('product_id', String(productId));
      wishBtn.disabled = true;
      try {
        const res = await fetch('api/wishlist_toggle.php', { method: 'POST', body: form, credentials: 'same-origin' });
        const data = await res.json();
        if (!data.ok) {
          toast(data.message || 'Could not update wishlist.', true);
          if ((data.message || '').toLowerCase().includes('login')) {
            window.location.href = 'login.php?error=' + encodeURIComponent('Please login to use your wishlist.');
          }
          return;
        }
        wishBtn.classList.toggle('in-wishlist', data.inWishlist);
        wishBtn.setAttribute('aria-pressed', data.inWishlist ? 'true' : 'false');
        wishBtn.textContent = data.inWishlist ? '♥' : '♡';
        toast(data.message || (data.inWishlist ? 'Added to wishlist' : 'Removed from wishlist'));
      } catch (err) {
        toast('Could not update wishlist.', true);
      } finally {
        wishBtn.disabled = false;
      }
      return;
    }

    const btn = event.target.closest('[data-add-cart]');
    if (!btn) {
      return;
    }

    event.preventDefault();
    const productId = Number(btn.getAttribute('data-product-id') || '0');
    const qty = Number(btn.getAttribute('data-qty') || '1');
    if (productId <= 0) {
      toast('Invalid product.', true);
      return;
    }

    const original = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Adding...';

    try {
      await addToCart(productId, qty);
      btn.textContent = 'Added';
      setTimeout(() => {
        btn.textContent = original;
        btn.disabled = false;
      }, 800);
    } catch (err) {
      toast(err.message || 'Could not add item.', true);
      btn.textContent = original;
      btn.disabled = false;
      if ((err.message || '').toLowerCase().includes('login')) {
        window.location.href = 'login.php?error=' + encodeURIComponent('Please login to add items to cart.');
      }
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    refreshCartCount();
  });

  window.SmartShop = {
    refreshCartCount,
    toast
  };
})();