<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Your cart';
$totals = cart_totals();
require __DIR__ . '/includes/header.php';
?>

<div class="page-header wrap">
  <span class="eyebrow">Cart</span>
  <h1>Your cart</h1>
</div>

<div class="wrap">
<?php if (!$totals['items']): ?>
  <div class="empty-state">
    <div class="icon">🛒</div>
    <h2>Your cart is empty</h2>
    <p>Looks like you haven't added anything yet.</p>
    <a class="btn btn-primary" href="/">Start shopping</a>
  </div>
<?php else: ?>
  <div class="cart-layout">
    <div>
      <?php foreach ($totals['items'] as $it): ?>
        <div class="cart-line">
          <a href="/product.php?slug=<?= e($it['slug']) ?>"><img src="<?= e(product_image_src($it['image_main'])) ?>" alt="<?= e($it['name']) ?>"></a>
          <div>
            <div class="name"><a href="/product.php?slug=<?= e($it['slug']) ?>"><?= e($it['name']) ?></a></div>
            <?php if ($it['variant_label']): ?><div class="unit" style="color:var(--ink-faint);"><?= e($it['variant_label']) ?></div><?php endif; ?>
            <div class="unit"><?= money($it['price']) ?> each</div>
            <button type="button" class="remove-btn js-cart-remove" data-item-id="<?= (int)$it['id'] ?>">Remove</button>
          </div>
          <div class="qty-stepper">
            <button type="button" class="minus" aria-label="Decrease">−</button>
            <input type="number" class="js-cart-qty" data-item-id="<?= (int)$it['id'] ?>" value="<?= (int)$it['quantity'] ?>" min="1" max="<?= (int)$it['stock'] ?>">
            <button type="button" class="plus" aria-label="Increase">+</button>
          </div>
          <div class="line-total"><?= money($it['price'] * $it['quantity']) ?></div>
        </div>
      <?php endforeach; ?>
      <div style="padding-top:18px;">
        <a href="/" class="btn btn-outline btn-sm">← Continue shopping</a>
      </div>
    </div>

    <div class="summary-card">
      <h3>Order summary</h3>
      <div class="summary-row"><span>Subtotal</span><span class="val"><?= money($totals['subtotal']) ?></span></div>
      <div class="summary-row"><span>Shipping</span><span class="val">Calculated at checkout</span></div>
      <p style="font-size:0.8rem;color:var(--ink-soft);margin-top:8px;">
        <?= money(SHIPPING_INSIDE_DHAKA_FEE) ?> inside Dhaka · <?= money(SHIPPING_SUBURBS_FEE) ?> suburbs · <?= money(SHIPPING_OUTSIDE_DHAKA_FEE) ?> outside Dhaka
        (+<?= money(SHIPPING_EXTRA_PER_KG) ?>/kg over <?= (int)SHIPPING_FREE_WEIGHT_KG ?>kg)
      </p>
      <a href="/checkout.php" class="btn btn-primary btn-block" style="margin-top:16px;">Proceed to checkout</a>
    </div>
  </div>
<?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
