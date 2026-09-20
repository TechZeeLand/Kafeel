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
      var variantField = form.querySelector('[name=variant_id]');
      var variantId = variantField ? variantField.value : '';
      var qtyField = form.querySelector('[name=quantity]');
      var qty = qtyField ? parseInt(qtyField.value, 10) || 1 : 1;
      if (btn) { btn.disabled = true; }
      postJSON('/api/cart_add.php', { product_id: productId, variant_id: variantId || null, quantity: qty })
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
    stepper.querySelectorAll('button').forEach(function (btn) {
      btn.addEventListener('click', function () {
        // Read limits now, not at page load: the product page changes max when a variant is picked.
        var min = parseInt(input.getAttribute('min') || '1', 10);
        var max = parseInt(input.getAttribute('max') || '999', 10);
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

  /* ------------------------------------------------------ day / night --- */
  var root = document.documentElement;
  var themeBtn = document.getElementById('themeToggle');
  function currentTheme() { return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'; }
  function syncThemeBtn() {
    if (!themeBtn) return;
    var dark = currentTheme() === 'dark';
    var label = dark ? 'Switch to light mode' : 'Switch to dark mode';
    themeBtn.setAttribute('aria-label', label);
    themeBtn.setAttribute('title', label);
    themeBtn.setAttribute('aria-pressed', dark ? 'true' : 'false');
  }
  function setTheme(t, remember) {
    root.classList.add('theme-fade');
    root.setAttribute('data-theme', t);
    if (remember) { try { localStorage.setItem('kafeel-theme', t); } catch (e) {} }
    syncThemeBtn();
    setTimeout(function () { root.classList.remove('theme-fade'); }, 350);
  }
  if (themeBtn) {
    themeBtn.addEventListener('click', function () { setTheme(currentTheme() === 'dark' ? 'light' : 'dark', true); });
  }
  syncThemeBtn();
  // Until the visitor picks a side, follow the operating system live.
  if (window.matchMedia) {
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var follow = function (e) {
      var stored = null;
      try { stored = localStorage.getItem('kafeel-theme'); } catch (err) {}
      if (stored !== 'light' && stored !== 'dark') setTheme(e.matches ? 'dark' : 'light', false);
    };
    if (mq.addEventListener) mq.addEventListener('change', follow); else if (mq.addListener) mq.addListener(follow);
  }

  /* ------------------------------------------------- search suggestions -- */
  var searchForm = document.querySelector('form[data-suggest]');
  if (searchForm) {
    var sInput = searchForm.querySelector('input[name=q]');
    var sBox = document.getElementById('searchSuggest');
    var sTimer = null, sReq = 0, sActive = -1;

    var hideSuggest = function () { sBox.hidden = true; sBox.innerHTML = ''; sActive = -1; };

    var highlight = function (text, words) {
      var frag = document.createDocumentFragment();
      var lower = text.toLowerCase();
      var marks = [];
      words.forEach(function (w) {
        var i = lower.indexOf(w.toLowerCase());
        if (w && i > -1) marks.push([i, i + w.length]);
      });
      marks.sort(function (a, b) { return a[0] - b[0]; });
      var pos = 0;
      marks.forEach(function (m) {
        if (m[0] < pos) return;
        frag.appendChild(document.createTextNode(text.slice(pos, m[0])));
        var el = document.createElement('mark'); el.textContent = text.slice(m[0], m[1]); frag.appendChild(el);
        pos = m[1];
      });
      frag.appendChild(document.createTextNode(text.slice(pos)));
      return frag;
    };

    var renderSuggest = function (items, q) {
      sBox.innerHTML = '';
      if (!items.length) { hideSuggest(); return; }
      var words = q.split(/\s+/).filter(Boolean);
      items.forEach(function (it) {
        var a = document.createElement('a');
        a.href = '/product.php?slug=' + encodeURIComponent(it.slug);
        var img = document.createElement('img'); img.src = it.image; img.alt = ''; img.loading = 'lazy';
        var wrap = document.createElement('div');
        var name = document.createElement('div'); name.className = 's-name'; name.appendChild(highlight(it.name, words));
        var meta = document.createElement('div'); meta.className = 's-meta'; meta.textContent = it.price + (it.category ? ' · ' + it.category : '');
        wrap.appendChild(name); wrap.appendChild(meta);
        a.appendChild(img); a.appendChild(wrap);
        sBox.appendChild(a);
      });
      var all = document.createElement('a');
      all.href = '/search.php?q=' + encodeURIComponent(q); all.className = 's-all';
      all.textContent = 'See all results for “' + q + '”';
      sBox.appendChild(all);
      sBox.hidden = false; sActive = -1;
    };

    sInput.addEventListener('input', function () {
      clearTimeout(sTimer);
      var q = sInput.value.trim();
      if (q.length < 2) { hideSuggest(); return; }
      sTimer = setTimeout(function () {
        var req = ++sReq;
        fetch('/api/search_suggest.php?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); })
          .then(function (res) { if (req === sReq) renderSuggest(res.items || [], q); })
          .catch(function () { hideSuggest(); });
      }, 180);
    });

    sInput.addEventListener('keydown', function (e) {
      var links = sBox.hidden ? [] : Array.prototype.slice.call(sBox.querySelectorAll('a'));
      if (e.key === 'Escape') { hideSuggest(); return; }
      if (!links.length) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        sActive = (sActive + (e.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
        links.forEach(function (l, i) { l.classList.toggle('is-active', i === sActive); });
      } else if (e.key === 'Enter' && sActive > -1) {
        e.preventDefault();
        window.location.href = links[sActive].href;
      }
    });

    document.addEventListener('click', function (e) { if (!searchForm.contains(e.target)) hideSuggest(); });
    sInput.addEventListener('focus', function () { if (sBox.children.length) sBox.hidden = false; });
  }
})();
