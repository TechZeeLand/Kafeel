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
        <li>Cash on delivery available nationwide</li>
        <li>Free shipping over <?= money(FREE_SHIPPING_THRESHOLD) ?></li>
        <li>Simple returns within 7 days</li>
      </ul>
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
