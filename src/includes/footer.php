</main>

<footer class="site-footer">
  <div class="wrap">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="brand" style="color:#fff;margin-bottom:10px;"><span class="mark">ك</span> <?= e(SITE_NAME) ?></div>
        <p>Thoughtfully made EDC gear, bags and leather goods — built to be used daily and to last.</p>
        <div class="social-row">
          <a href="<?= e(SOCIAL_FACEBOOK) ?>" target="_blank" rel="noopener" aria-label="Facebook" title="Facebook">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.51 1.49-3.9 3.77-3.9 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.89h2.78l-.45 2.91h-2.33V22c4.78-.76 8.44-4.92 8.44-9.94z"/></svg>
          </a>
          <a href="<?= e(SOCIAL_INSTAGRAM) ?>" target="_blank" rel="noopener" aria-label="Instagram" title="Instagram">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.4" cy="6.6" r="1.1" fill="currentColor" stroke="none"/></svg>
          </a>
          <a href="<?= e(SOCIAL_YOUTUBE) ?>" target="_blank" rel="noopener" aria-label="YouTube" title="YouTube">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M23 12s0-3.6-.46-5.3a3 3 0 0 0-2.1-2.1C18.6 4 12 4 12 4s-6.6 0-8.44.6a3 3 0 0 0-2.1 2.1C1 8.4 1 12 1 12s0 3.6.46 5.3a3 3 0 0 0 2.1 2.1C5.4 20 12 20 12 20s6.6 0 8.44-.6a3 3 0 0 0 2.1-2.1C23 15.6 23 12 23 12z"/><path d="M9.8 8.6v6.8L15.8 12z" fill="var(--ink)"/></svg>
          </a>
        </div>
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
      <span>© <?= date('Y') ?> <?= e(SITE_NAME) ?>. All rights reserved.</span>
      <span>Delivered in <?= (int)DELIVERY_DAYS_MIN ?>–<?= (int)DELIVERY_DAYS_MAX ?> days · Cash on delivery only</span>
    </div>
  </div>
</footer>

<div id="toast"></div>
<script src="/assets/js/main.js"></script>
</body>
</html>
