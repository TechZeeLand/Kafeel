(function () {
  'use strict';

  /* ---------------------------------------------------------- toast --- */
  function toast(message) {
    var box = document.getElementById('toast');
    if (!box) return;
    var item = document.createElement('div');
    item.className = 'toast-item';
    item.textContent = message;
    box.appendChild(item);
    setTimeout(function () { item.remove(); }, 3200);
  }
  window.showToast = toast;

  /* ------------------------------------------------------ mobile nav --- */
  var navToggle = document.getElementById('navToggle');
  var navClose = document.getElementById('navClose');
  var mobileNav = document.getElementById('mobileNav');
  if (navToggle && mobileNav) {
    navToggle.addEventListener('click', function () { mobileNav.classList.add('open'); });
  }
  if (navClose && mobileNav) {
    navClose.addEventListener('click', function () { mobileNav.classList.remove('open'); });
  }
  if (mobileNav) {
    mobileNav.addEventListener('click', function (e) {
      if (e.target === mobileNav) mobileNav.classList.remove('open');
    });
  }

  /* ------------------------------------------------------- csrf token -- */
  function getCsrf() {
    var el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.getAttribute('content') : '';
  }

  function postJSON(url, data) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrf() },
      body: JSON.stringify(data)
    }).then(function (r) { return r.json(); });
  }

  /* ---------------------------------------------------- cart badge ----- */
  function updateCartBadge(count) {
    document.querySelectorAll('.icon-btn .badge').forEach(function (b) {
      if (b.closest('a').getAttribute('href') === '/cart.php') {
        b.textContent = count;
        b.style.display = count > 0 ? 'flex' : 'none';
      }
    });
    if (count > 0 && !document.querySelector('a[href="/cart.php"] .badge')) {
      var cartLink = document.querySelector('a[href="/cart.php"]');
      if (cartLink) {
        var span = document.createElement('span');
        span.className = 'badge';
        span.textContent = count;
        cartLink.appendChild(span);
      }
    }
  }

  /* ------------------------------------------------ add-to-cart forms -- */
  document.querySelectorAll('.js-add-cart').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = form.querySelector('button[type=submit]');
      var productId = form.querySelector('[name=product_id]').value;
      var qtyField = form.querySelector('[name=quantity]');
      var qty = qtyField ? parseInt(qtyField.value, 10) || 1 : 1;
      if (btn) { btn.disabled = true; }
      postJSON('/api/cart_add.php', { product_id: productId, quantity: qty })
        .then(function (res) {
          if (res.ok) {
            updateCartBadge(res.cart_count);
            toast(res.message || 'Added to cart');
          } else {
            toast(res.message || 'Could not add to cart');
            if (res.login_required) { window.location.href = '/login.php'; }
          }
        })
        .catch(function () { toast('Network error — please try again'); })
        .finally(function () { if (btn) { btn.disabled = false; } });
    });
  });

  /* -------------------------------------------------- favorite toggle -- */
  document.querySelectorAll('.js-fav-toggle').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var productId = btn.getAttribute('data-product-id');
      postJSON('/api/favorite_toggle.php', { product_id: productId })
        .then(function (res) {
          if (res.ok) {
            btn.classList.toggle('active', res.favorited);
            toast(res.favorited ? 'Saved to your wishlist' : 'Removed from wishlist');
          } else if (res.login_required) {
            window.location.href = '/login.php';
          } else {
            toast(res.message || 'Something went wrong');
          }
        });
    });
  });

  /* --------------------------------------------------------- qty steps -- */
  document.querySelectorAll('.qty-stepper').forEach(function (stepper) {
    var input = stepper.querySelector('input');
    var min = parseInt(input.getAttribute('min') || '1', 10);
    var max = parseInt(input.getAttribute('max') || '999', 10);
    stepper.querySelectorAll('button').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var val = parseInt(input.value, 10) || min;
        val = btn.classList.contains('minus') ? val - 1 : val + 1;
        val = Math.max(min, Math.min(max, val));
        input.value = val;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  });

  /* ------------------------------------------------- cart page updates -- */
  document.querySelectorAll('.js-cart-qty').forEach(function (input) {
    input.addEventListener('change', function () {
      var itemId = input.getAttribute('data-item-id');
      postJSON('/api/cart_update.php', { item_id: itemId, quantity: input.value })
        .then(function (res) {
          if (res.ok) { window.location.reload(); }
        });
    });
  });
  document.querySelectorAll('.js-cart-remove').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var itemId = btn.getAttribute('data-item-id');
      postJSON('/api/cart_remove.php', { item_id: itemId }).then(function (res) {
        if (res.ok) { window.location.reload(); }
      });
    });
  });

  /* -------------------------------------------------------- gallery ---- */
  document.querySelectorAll('.gallery-thumbs img').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
      var main = document.querySelector('.gallery-main img');
      if (!main) return;
      main.src = thumb.getAttribute('data-full') || thumb.src;
      document.querySelectorAll('.gallery-thumbs img').forEach(function (t) { t.classList.remove('active'); });
      thumb.classList.add('active');
    });
  });
})();
