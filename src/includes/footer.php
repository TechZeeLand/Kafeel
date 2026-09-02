</main>

<footer class="site-footer">
  <div class="wrap">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="brand" style="color:#fff;margin-bottom:10px;"><span class="mark">خ</span> <?= e(SITE_NAME) ?></div>
        <p>Thoughtfully made EDC gear, bags and leather goods — built to be used daily and to last.</p>
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
          <li><a href="/cart.php">Your cart</a></li>
        </ul>
      </div>
      <div>
        <h4>Account</h4>
        <ul>
          <li><a href="<?= is_logged_in() ? '/account.php' : '/login.php' ?>">My account</a></li>
          <li><a href="<?= is_logged_in() ? '/wishlist.php' : '/login.php' ?>">Saved items</a></li>
          <li><a href="/register.php">Create account</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> <?= e(SITE_NAME) ?>. All rights reserved.</span>
      <span>Built with care for people who like good tools.</span>
    </div>
  </div>
</footer>

<div id="toast"></div>
<script src="/assets/js/main.js"></script>
</body>
</html>
