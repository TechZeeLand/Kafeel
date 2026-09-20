</main>
<?php
$__store = $__store ?? store_info();
$__theme = $__theme ?? theme_settings();
$__cartCount = $__cartCount ?? cart_count();
$__user = $__user ?? current_user();
$__categories = $__categories ?? [];
$__currentPath = $__currentPath ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$__assetV = $__assetV ?? fn (string $f) => (int) @filemtime(__DIR__ . '/../assets/' . $f);
?>
<footer class="site-footer">
  <div class="wrap">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="/" class="brand"><?= brand_inner('footer') ?></a>
        <?php if ($__store['description'] !== ''): ?><p><?= e($__store['description']) ?></p><?php endif; ?>
        <ul class="footer-contact">
          <?php if ($__store['address'] !== ''): ?><li><?= ui_icon('pin', 16) ?><span><?= nl2br(e($__store['address'])) ?></span></li><?php endif; ?>
          <?php if ($__store['phone'] !== ''): ?><li><?= ui_icon('phone', 16) ?><a href="<?= e(tel_href($__store['phone'])) ?>"><?= e($__store['phone']) ?></a></li><?php endif; ?>
          <?php if ($__store['email'] !== ''): ?><li><?= ui_icon('mail', 16) ?><a href="mailto:<?= e($__store['email']) ?>"><?= e($__store['email']) ?></a></li><?php endif; ?>
        </ul>
        <?= social_row_html() ?>
      </div>
      <div>
        <h4>Shop</h4>
        <ul>
          <?php foreach ($__categories as $c): ?>
            <li><a href="/category.php?slug=<?= e($c['slug']) ?>"><?= e($c['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h4>Support</h4>
        <ul>
          <li><a href="/contact.php">Contact us</a></li>
          <li><a href="/about.php">About the shop</a></li>
          <li><a href="/orders.php">Track an order</a></li>
          <li><a href="<?= is_logged_in() ? '/account.php' : '/login.php' ?>">My account</a></li>
        </ul>
      </div>
      <div>
        <h4>Legal</h4>
        <ul>
          <li><a href="/privacy-policy.php">Privacy policy</a></li>
          <li><a href="/terms.php">Terms of service</a></li>
          <li><a href="/refund-policy.php">Refund & return policy</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> <?= e($__store['name']) ?>. All rights reserved.</span>
      <span>Delivered in <?= (int)DELIVERY_DAYS_MIN ?>–<?= (int)DELIVERY_DAYS_MAX ?> days · Cash on delivery only</span>
    </div>
  </div>
</footer>

<!-- Mobile bottom tab bar -->
<nav class="tabbar" aria-label="Quick navigation">
  <a href="/" class="tab <?= ($__currentPath === '/' || $__currentPath === '/index.php') ? 'active' : '' ?>"><?= ui_icon('home', 22) ?><span>Home</span></a>
  <button type="button" class="tab" data-open-search><?= ui_icon('search', 22) ?><span>Search</span></button>
  <a href="/cart.php" class="tab <?= $__currentPath === '/cart.php' ? 'active' : '' ?>"><?= ui_icon('cart', 22) ?><span>Cart</span><span class="badge" data-cart-badge<?= $__cartCount > 0 ? '' : ' hidden' ?>><?= (int) $__cartCount ?></span></a>
  <a href="<?= $__user ? '/wishlist.php' : '/login.php' ?>" class="tab <?= $__currentPath === '/wishlist.php' ? 'active' : '' ?>"><?= ui_icon('heart', 22) ?><span>Saved</span></a>
  <a href="<?= $__user ? '/account.php' : '/login.php' ?>" class="tab <?= in_array($__currentPath, ['/account.php', '/login.php', '/register.php', '/orders.php', '/addresses.php'], true) ? 'active' : '' ?>"><?= ui_icon('user', 22) ?><span><?= $__user ? 'Account' : 'Log in' ?></span></a>
</nav>

<div id="toast" role="status" aria-live="polite"></div>
<script src="/assets/js/main.js?v=<?= $__assetV('js/main.js') ?>"></script>
<?php if (!empty($__theme['seasonal_enabled'])): ?>
<script src="/assets/js/seasonal.js?v=<?= $__assetV('js/seasonal.js') ?>" data-effect="<?= e($__theme['seasonal_effect']) ?>"></script>
<?php endif; ?>
</body>
</html>
