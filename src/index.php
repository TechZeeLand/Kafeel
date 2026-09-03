<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'EDC gear, bags & leather goods';
$__user = current_user();
$__favIds = $__user ? favorite_ids_for_user($__user['id']) : [];

$featured = db()->query(
    "SELECT p.*, c.name AS category_name FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.is_active = 1 AND p.is_featured = 1 ORDER BY p.created_at DESC LIMIT 8"
)->fetchAll();

$newest = db()->query(
    "SELECT p.*, c.name AS category_name FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.is_active = 1 ORDER BY p.created_at DESC LIMIT 8"
)->fetchAll();

$categoriesWithCount = db()->query(
    "SELECT c.id, c.name, c.slug, c.image, COUNT(p.id) AS product_count
     FROM categories c LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
     WHERE c.is_active = 1 GROUP BY c.id ORDER BY c.sort_order, c.name"
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="wrap">
    <div>
      <span class="hero-eyebrow">No. 001 — Field-tested essentials</span>
      <h1>Gear for the pocket, the bag, and everywhere in between.</h1>
      <p class="lead">Carefully chosen EDC gear, bags and full-grain leather goods — the kind of things you reach for daily and never think to replace.</p>
      <div class="hero-actions">
        <a href="/category.php?slug=edc-gear" class="btn btn-primary">Shop EDC gear</a>
        <a href="/category.php?slug=leather-goods" class="btn btn-outline">Shop leather goods</a>
      </div>
    </div>
    <div class="hero-card">
      <span class="stamp">EST.<br>TODAY</span>
      <h3>Why people keep coming back</h3>
      <ul>
        <li>Hand-picked catalog, no filler products</li>
        <li>Cash on delivery, nothing to pay upfront</li>
        <li>Delivered in <?= (int)DELIVERY_DAYS_MIN ?>–<?= (int)DELIVERY_DAYS_MAX ?> days nationwide</li>
        <li>Simple returns within 7 days</li>
      </ul>
    </div>
  </div>
</section>

<section class="wrap">
  <div class="trust-strip">
    <div class="item">
      <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l4-8h10l4 8"/><path d="M3 12v6a1 1 0 0 0 1 1h1"/><path d="M21 12v6a1 1 0 0 1-1 1h-1"/><circle cx="8" cy="19" r="2"/><circle cx="16" cy="19" r="2"/><path d="M5 19h1M18 19h1"/></svg></span>
      Fast delivery
    </div>
    <div class="item">
      <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/></svg></span>
      Cash on delivery
    </div>
    <div class="item">
      <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4a2 2 0 0 0 1-1.7z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/></svg></span>
      Safe packaging
    </div>
    <div class="item">
      <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 4 5v6c0 5 3.4 8.7 8 11 4.6-2.3 8-6 8-11V5z"/></svg></span>
      Secure payment
    </div>
    <div class="item">
      <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="5"/><path d="M8.2 13 7 22l5-3 5 3-1.2-9"/></svg></span>
      Best quality
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="tag">Browse by category</span>
        <h2>Shop the collection</h2>
      </div>
    </div>
    <div class="cat-grid">
      <?php foreach ($categoriesWithCount as $c): ?>
        <a class="cat-tile" href="/category.php?slug=<?= e($c['slug']) ?>">
          <img src="<?= e(product_image_src($c['image'])) ?>" alt="<?= e($c['name']) ?>">
          <div class="label">
            <div class="name"><?= e($c['name']) ?></div>
            <div class="count"><?= (int)$c['product_count'] ?> items</div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-alt">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="tag">The Kafeel promise</span>
        <h2>Why choose <?= e(SITE_NAME) ?></h2>
      </div>
    </div>
    <div class="why-grid">
      <div class="why-card">
        <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 3 6v6c0 5.5 3.8 9.7 9 11 5.2-1.3 9-5.5 9-11V6z"/><path d="M9 12l2 2 4-4"/></svg></span>
        <h3>Curated, not crowded</h3>
        <p>Every product is chosen for how it holds up in daily use — no filler, no drop-shipped junk.</p>
      </div>
      <div class="why-card">
        <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/></svg></span>
        <h3>Pay when it arrives</h3>
        <p>Cash on delivery on every order — see and check your item before you pay a taka.</p>
      </div>
      <div class="why-card">
        <span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l4-8h10l4 8"/><path d="M3 12v6a1 1 0 0 0 1 1h1"/><path d="M21 12v6a1 1 0 0 1-1 1h-1"/><circle cx="8" cy="19" r="2"/><circle cx="16" cy="19" r="2"/></svg></span>
        <h3>Delivered fast</h3>
        <p>Orders reach you in <?= (int)DELIVERY_DAYS_MIN ?>–<?= (int)DELIVERY_DAYS_MAX ?> days, carefully packed so nothing shifts in transit.</p>
      </div>
    </div>
  </div>
</section>

<?php if ($featured): ?>
<section class="section section-alt">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="tag">Staff picks</span>
        <h2>Featured products</h2>
      </div>
      <a class="view-all" href="/search.php?featured=1">View all →</a>
    </div>
    <div class="product-grid">
      <?php foreach ($featured as $p): include __DIR__ . '/includes/product_card.php'; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="wrap">
    <div class="section-head">
      <div>
        <span class="tag">Just landed</span>
        <h2>New arrivals</h2>
      </div>
      <a class="view-all" href="/search.php?sort=newest">View all →</a>
    </div>
    <div class="product-grid">
      <?php foreach ($newest as $p): include __DIR__ . '/includes/product_card.php'; endforeach; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
