<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.slug = ? AND p.is_active = 1"
);
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="wrap section"><div class="empty-state"><h2>Product not found</h2><p>This item may be sold out permanently or removed.</p><a class="btn btn-primary" href="/">Back to shop</a></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$imgStmt = db()->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order');
$imgStmt->execute([$product['id']]);
$gallery = array_column($imgStmt->fetchAll(), 'image_path');
if ($product['image_main']) {
    array_unshift($gallery, $product['image_main']);
}
$gallery = array_values(array_unique($gallery));
if (!$gallery) { $gallery = [null]; }

$relStmt = db()->prepare(
    "SELECT p.*, c.name AS category_name FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
     ORDER BY RAND() LIMIT 4"
);
$relStmt->execute([$product['category_id'], $product['id']]);
$related = $relStmt->fetchAll();

$__user = current_user();
$__favIds = $__user ? favorite_ids_for_user($__user['id']) : [];
$isFav = in_array((int)$product['id'], $__favIds, true);
$onSale = !empty($product['compare_price']) && $product['compare_price'] > $product['price'];

$pageTitle = $product['name'];
$pageDescription = $product['short_desc'] ?: $product['name'];
require __DIR__ . '/includes/header.php';
?>

<div class="wrap">
  <div class="breadcrumb">
    <a href="/">Home</a> /
    <?php if ($product['category_slug']): ?>
      <a href="/category.php?slug=<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a> /
    <?php endif; ?>
    <?= e($product['name']) ?>
  </div>
</div>

<div class="wrap product-view">
  <div>
    <div class="gallery-main">
      <img src="<?= e(product_image_src($gallery[0])) ?>" alt="<?= e($product['name']) ?>">
    </div>
    <?php if (count($gallery) > 1): ?>
      <div class="gallery-thumbs">
        <?php foreach ($gallery as $i => $img): ?>
          <img src="<?= e(product_image_src($img)) ?>" data-full="<?= e(product_image_src($img)) ?>" class="<?= $i === 0 ? 'active' : '' ?>" alt="">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="product-info">
    <span class="sku">SKU <?= e($product['sku'] ?: '—') ?></span>
    <h1><?= e($product['name']) ?></h1>
    <div class="price-row">
      <span class="price"><?= money($product['price']) ?></span>
      <?php if ($onSale): ?><span class="compare"><?= money($product['compare_price']) ?></span><span class="pill pill-rust">Sale</span><?php endif; ?>
    </div>

    <?php if ($product['short_desc']): ?><p class="desc"><?= e($product['short_desc']) ?></p><?php endif; ?>

    <div class="stock-line">
      <?php if ($product['stock'] > 10): ?>
        <span class="pill pill-sage">In stock</span>
      <?php elseif ($product['stock'] > 0): ?>
        <span class="pill pill-rust">Only <?= (int)$product['stock'] ?> left</span>
      <?php else: ?>
        <span class="pill pill-ink">Out of stock</span>
      <?php endif; ?>
    </div>

    <?php if ($product['stock'] > 0): ?>
      <form class="js-add-cart" method="post">
        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
        <div class="qty-row">
          <div class="qty-stepper">
            <button type="button" class="minus" aria-label="Decrease">−</button>
            <input type="number" name="quantity" value="1" min="1" max="<?= (int)$product['stock'] ?>">
            <button type="button" class="plus" aria-label="Increase">+</button>
          </div>
        </div>
        <div class="product-actions">
          <button type="submit" class="btn btn-primary">Add to cart</button>
          <button type="button" class="btn btn-outline js-fav-toggle <?= $isFav ? 'active' : '' ?>" data-product-id="<?= (int)$product['id'] ?>">
            <?= $isFav ? '♥ Saved' : '♡ Save for later' ?>
          </button>
        </div>
      </form>
    <?php else: ?>
      <div class="product-actions">
        <button class="btn btn-primary" disabled>Out of stock</button>
        <button type="button" class="btn btn-outline js-fav-toggle <?= $isFav ? 'active' : '' ?>" data-product-id="<?= (int)$product['id'] ?>">
          <?= $isFav ? '♥ Saved' : '♡ Notify me / save' ?>
        </button>
      </div>
    <?php endif; ?>

    <?php if ($product['description']): ?>
      <div class="desc"><?= nl2br(e($product['description'])) ?></div>
    <?php endif; ?>

    <div class="meta-list">
      <div><b>Category:</b> <?= e($product['category_name'] ?? 'Uncategorized') ?></div>
      <div><b>Shipping:</b> Free over <?= money(FREE_SHIPPING_THRESHOLD) ?>, otherwise flat <?= money(SHIPPING_FLAT_FEE) ?></div>
      <div><b>Returns:</b> 7-day no-questions returns on unused items</div>
    </div>
  </div>
</div>

<?php if ($related): ?>
<section class="section section-alt">
  <div class="wrap">
    <div class="section-head"><div><span class="tag">You might also like</span><h2>Related products</h2></div></div>
    <div class="product-grid">
      <?php foreach ($related as $p): include __DIR__ . '/includes/product_card.php'; endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
