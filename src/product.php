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
    "SELECT p.*, c.name AS category_name" . PRODUCT_LIST_EXTRA . " FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
     ORDER BY RAND() LIMIT 4"
);
$relStmt->execute([$product['category_id'], $product['id']]);
$related = $relStmt->fetchAll();

$variants = product_variants_for((int) $product['id']);
$options = product_options_for((int) $product['id']);
// Only offer options that at least one active variant actually uses.
$usedColors = array_flip(array_filter(array_column($variants, 'color')));
$usedSizes = array_flip(array_filter(array_column($variants, 'size')));
$colorOpts = array_values(array_filter($options['color'], fn ($o) => isset($usedColors[$o['name']])));
$sizeOpts = array_values(array_filter($options['size'], fn ($o) => isset($usedSizes[$o['name']])));
$totalVariantStock = 0;
foreach ($variants as $v) { $totalVariantStock += (int) $v['stock']; }
$effectiveStock = $variants ? $totalVariantStock : (int) $product['stock'];
$tags = product_tags($product);

// Everything the page's picker script needs, in one JSON blob.
$pickerData = [
    'symbol' => STORE_CURRENCY_SYMBOL,
    'basePrice' => (float) $product['price'],
    'base' => [
        'weight' => (int) $product['weight_grams'],
        'h' => $product['height_mm'] !== null ? (int) $product['height_mm'] : null,
        'w' => $product['width_mm'] !== null ? (int) $product['width_mm'] : null,
        'd' => $product['depth_mm'] !== null ? (int) $product['depth_mm'] : null,
        'stock' => (int) $product['stock'],
        'image' => product_image_src($gallery[0]),
    ],
    'colors' => array_map(fn ($o) => ['name' => $o['name'], 'image' => $o['image'] ?: null], $colorOpts),
    'sizes' => array_map(fn ($o) => [
        'name' => $o['name'], 'image' => $o['image'] ?: null,
        'weight' => $o['weight_grams'] !== null ? (int) $o['weight_grams'] : null,
        'h' => $o['height_mm'] !== null ? (int) $o['height_mm'] : null,
        'w' => $o['width_mm'] !== null ? (int) $o['width_mm'] : null,
        'd' => $o['depth_mm'] !== null ? (int) $o['depth_mm'] : null,
    ], $sizeOpts),
    'variants' => array_map(fn ($v) => [
        'id' => (int) $v['id'], 'color' => $v['color'] ?: null, 'size' => $v['size'] ?: null,
        'delta' => (float) $v['price_delta'], 'stock' => (int) $v['stock'],
    ], $variants),
];
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

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
      <img id="galleryMainImg" src="<?= e(product_image_src($gallery[0])) ?>" alt="<?= e($product['name']) ?>">
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
      <span class="price" id="productPrice"><?= money($product['price']) ?></span>
      <?php if ($onSale): ?><span class="compare"><?= money($product['compare_price']) ?></span><span class="pill pill-rust">Sale</span><?php endif; ?>
    </div>

    <?php if ($product['short_desc']): ?><p class="desc"><?= e($product['short_desc']) ?></p><?php endif; ?>

    <?php if (!empty($product['youtube_url']) && is_youtube_url($product['youtube_url'])): ?>
      <a href="<?= e($product['youtube_url']) ?>" target="_blank" rel="noopener" class="video-btn" style="margin-bottom:18px;">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M23 12s0-3.6-.46-5.3a3 3 0 0 0-2.1-2.1C18.6 4 12 4 12 4s-6.6 0-8.44.6a3 3 0 0 0-2.1 2.1C1 8.4 1 12 1 12s0 3.6.46 5.3a3 3 0 0 0 2.1 2.1C5.4 20 12 20 12 20s6.6 0 8.44-.6a3 3 0 0 0 2.1-2.1C23 15.6 23 12 23 12z"/><path d="M9.8 8.6v6.8L15.8 12z" fill="#fff"/></svg>
        Watch video
      </a>
    <?php endif; ?>

    <div class="stock-line" id="stockLine">
      <?php if ($effectiveStock > 10): ?>
        <span class="pill pill-sage">In stock</span>
      <?php elseif ($effectiveStock > 0): ?>
        <span class="pill pill-rust">Only <?= (int)$effectiveStock ?> left</span>
      <?php else: ?>
        <span class="pill pill-ink">Out of stock</span>
      <?php endif; ?>
    </div>

    <?php
    // Renders one picker (color or size). Colors with a swatch color or photo
    // become round swatches; everything else is a text chip.
    $renderChoices = function (string $kind, array $opts) {
        foreach ($opts as $o):
            $isSwatch = $kind === 'color' && (!empty($o['swatch']) || !empty($o['image']));
            $style = $isSwatch && !empty($o['swatch']) ? ' style="--dot:' . e($o['swatch']) . '"' : '';
    ?>
        <?php if ($isSwatch): ?>
          <button type="button" class="swatch" data-kind="<?= $kind ?>" data-value="<?= e($o['name']) ?>" aria-label="<?= e($o['name']) ?>" title="<?= e($o['name']) ?>"<?= $style ?>><span class="dot"><?php if (empty($o['swatch']) && !empty($o['image'])): ?><img src="<?= e($o['image']) ?>" alt=""><?php endif; ?></span></button>
        <?php else: ?>
          <button type="button" class="chip" data-kind="<?= $kind ?>" data-value="<?= e($o['name']) ?>"><?= e($o['name']) ?></button>
        <?php endif; ?>
    <?php endforeach; };
    ?>
    <?php if ($colorOpts): ?>
      <div class="option-group" id="colorGroup">
        <div class="option-label">Color <span class="chosen" id="colorChosen"></span></div>
        <div class="option-choices" role="group" aria-label="Color"><?php $renderChoices('color', $colorOpts); ?></div>
      </div>
    <?php endif; ?>
    <?php if ($sizeOpts): ?>
      <div class="option-group" id="sizeGroup">
        <div class="option-label">Size <span class="chosen" id="sizeChosen"></span></div>
        <div class="option-choices" role="group" aria-label="Size"><?php $renderChoices('size', $sizeOpts); ?></div>
      </div>
    <?php endif; ?>

    <?php if ($effectiveStock > 0): ?>
      <form class="js-add-cart" method="post" id="addCartForm">
        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
        <?php if ($variants): ?><input type="hidden" name="variant_id" id="variantIdField" value=""><?php endif; ?>
        <div class="qty-row">
          <div class="qty-stepper">
            <button type="button" class="minus" aria-label="Decrease">−</button>
            <input type="number" name="quantity" id="qtyField" value="1" min="1" max="<?= (int)$effectiveStock ?>">
            <button type="button" class="plus" aria-label="Increase">+</button>
          </div>
        </div>
        <div class="product-actions">
          <button type="submit" class="btn btn-primary" id="addCartBtn" <?= $variants ? 'disabled' : '' ?>>Add to cart</button>
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
      <?php if ($product['color'] && !$colorOpts): ?><div><b>Color:</b> <?= e($product['color']) ?></div><?php endif; ?>
      <?php $hasAnyDims = $product['height_mm'] || $product['width_mm'] || $product['depth_mm'] || array_filter($sizeOpts, fn ($o) => $o['height_mm'] || $o['width_mm'] || $o['depth_mm']); ?>
      <?php if ($hasAnyDims): ?>
        <div id="metaDims"><b>Dimensions (H×W×D):</b> <span id="metaDimsVal">
          <?= $product['height_mm'] ? (int)$product['height_mm'] : '—' ?> ×
          <?= $product['width_mm'] ? (int)$product['width_mm'] : '—' ?> ×
          <?= $product['depth_mm'] ? (int)$product['depth_mm'] : '—' ?> mm</span>
        </div>
      <?php endif; ?>
      <div id="metaWeight"><b>Weight:</b> <span id="metaWeightVal"><?= (int)$product['weight_grams'] ?>g</span></div>
      <?php if ($tags): ?>
        <div><b>Tags:</b>
          <div class="tag-list"><?php foreach ($tags as $t): ?><a href="/search.php?q=<?= urlencode($t) ?>"><?= e($t) ?></a><?php endforeach; ?></div>
        </div>
      <?php endif; ?>
      <div><b>Shipping:</b> <?= money(SHIPPING_INSIDE_DHAKA_FEE) ?> inside Dhaka · <?= money(SHIPPING_SUBURBS_FEE) ?> suburbs · <?= money(SHIPPING_OUTSIDE_DHAKA_FEE) ?> outside Dhaka (+<?= money(SHIPPING_EXTRA_PER_KG) ?>/kg over <?= (int)SHIPPING_FREE_WEIGHT_KG ?>kg)</div>
      <div><b>Delivery time:</b> <?= (int)DELIVERY_DAYS_MIN ?>–<?= (int)DELIVERY_DAYS_MAX ?> days</div>
      <div><b>Payment:</b> Cash on delivery</div>
      <div><b>Returns:</b> 7-day no-questions returns on unused items</div>
    </div>
  </div>
</div>

<?php if ($variants): ?>
<script>window.KAFEEL_PRODUCT = <?= json_encode($pickerData, $jsonFlags) ?>;</script>
<script src="/assets/js/product.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/product.js') ?>"></script>
<?php endif; ?>

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
