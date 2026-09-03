<?php
/** Expects $p (product array) in scope. Optional $__favIds (array of favorited product ids). */
$__isFav = isset($__favIds) && in_array((int)$p['id'], $__favIds, true);
$__onSale = !empty($p['compare_price']) && $p['compare_price'] > $p['price'];
?>
<div class="card-product">
  <div class="thumb">
    <span class="grommet"></span>
    <a href="/product.php?slug=<?= e($p['slug']) ?>">
      <img src="<?= e(product_image_src($p['image_main'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
    </a>
    <button class="fav-btn js-fav-toggle <?= $__isFav ? 'active' : '' ?>" data-product-id="<?= (int)$p['id'] ?>" aria-label="Save to wishlist" type="button">
      <svg viewBox="0 0 24 24" width="17" height="17" fill="<?= $__isFav ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
    </button>
    <?php if ($__onSale): ?><span class="sale-flag">Sale</span><?php endif; ?>
    <?php if ((int)$p['stock'] <= 0): ?><span class="out-flag">Out of stock</span><?php endif; ?>
    <?php if (!empty($p['youtube_url'])): ?>
      <span class="video-flag" title="Video available"><svg viewBox="0 0 24 24" fill="#fff"><path d="M8 5v14l11-7z"/></svg></span>
    <?php endif; ?>
  </div>
  <div class="body">
    <span class="cat-label"><?= e($p['category_name'] ?? 'Shop') ?></span>
    <div class="name"><a href="/product.php?slug=<?= e($p['slug']) ?>"><?= e($p['name']) ?></a></div>
    <div class="price-row">
      <span class="price"><?= money($p['price']) ?></span>
      <?php if ($__onSale): ?><span class="compare"><?= money($p['compare_price']) ?></span><?php endif; ?>
    </div>
  </div>
  <?php if ((int)$p['stock'] > 0): ?>
  <form class="add-form js-add-cart" method="post">
    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
    <input type="hidden" name="quantity" value="1">
    <button type="submit" class="btn btn-primary btn-block btn-sm">Add to cart</button>
  </form>
  <?php endif; ?>
</div>
