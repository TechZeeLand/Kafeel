<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$totals = cart_totals();
if (!$totals['items']) {
    redirect('/cart.php');
}

$shippingInside = shipping_fee_for_area('inside_dhaka', $totals['weight_grams']);
$shippingSuburbs = shipping_fee_for_area('suburbs', $totals['weight_grams']);
$shippingOutside = shipping_fee_for_area('outside_dhaka', $totals['weight_grams']);

$__user = current_user();
$errors = [];

// A coupon saved earlier in this visit is re-checked against the cart as it is now.
$__couponDrop = null;
$appliedCoupon = coupon_session_current($totals['subtotal'], coupon_shopper($__user), $__couponDrop);
// While browsing, a coupon that stopped working is dropped with a notice. But when the customer is
// pressing "Place order" it must NOT be dropped silently and the order placed at a higher price than
// the one they saw: the order is held back (see the POST handler) so they can confirm the new total.
if ($__couponDrop && $_SERVER['REQUEST_METHOD'] !== 'POST') flash_set('info', $__couponDrop);

$defaultAddress = null;
if ($__user) {
    $stmt = db()->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1');
    $stmt->execute([$__user['id']]);
    $defaultAddress = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if ($__couponDrop) $errors[] = $__couponDrop . ' Your total has changed — please check it and place the order again.';

    $name = trim($_POST['shipping_name'] ?? '');
    $phone = trim($_POST['shipping_phone'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');
    $line1 = trim($_POST['shipping_line1'] ?? '');
    $city = trim($_POST['shipping_city'] ?? '');
    $state = trim($_POST['shipping_state'] ?? '');
    $zip = trim($_POST['shipping_zip'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    // No online payment gateway is set up yet, so cash on delivery is the only method actually
    // processed for now — the "Online Payment" radio below is shown as a preview only.
    $payment = 'cod';
    $deliveryArea = in_array($_POST['delivery_area'] ?? '', ['inside_dhaka', 'suburbs', 'outside_dhaka'], true) ? $_POST['delivery_area'] : 'inside_dhaka';
    $saveAddress = !empty($_POST['save_address']);

    if ($name === '' || strlen($name) < 2) $errors[] = 'Please enter the recipient\'s full name.';
    if (!preg_match('/^\+?[0-9][0-9 ()\-]{5,20}$/', $phone) || strlen(preg_replace('/\D/', '', $phone)) < 7 || strlen(preg_replace('/\D/', '', $phone)) > 15) $errors[] = 'Please enter a valid phone number, e.g. 01XXXXXXXXX.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'That email address doesn\'t look right.';
    if ($line1 === '') $errors[] = 'Please enter your street address.';
    if ($city === '') $errors[] = 'Please enter your city.';

    // Re-verify current cart & stock right before committing the order.
    $freshTotals = cart_totals();
    if (!$freshTotals['items']) {
        $errors[] = 'Your cart is empty.';
    }
    foreach ($freshTotals['items'] as $it) {
        $label = $it['name'] . ($it['variant_label'] ? ' (' . $it['variant_label'] . ')' : '');
        if (!$it['available']) {
            $errors[] = $label . ' is no longer available — please remove it from your cart.';
        } elseif ($it['quantity'] > $it['stock']) {
            $errors[] = $label . ' only has ' . (int) $it['stock'] . ' left in stock.';
        }
    }

    if (!$errors) {
        $shippingFee = shipping_fee_for_area($deliveryArea, $freshTotals['weight_grams']);
        $pdo = db();
        try {
            $pdo->beginTransaction();
            // Coupon: locked, re-validated with everything we now know about the buyer, and priced
            // here on the server. Nothing the browser sent decides the discount.
            $coupon = null; $discount = 0.0;
            if (coupon_session_code() !== '') {
                [$coupon, $discount] = coupon_claim_for_order($pdo, coupon_session_code(), (float) $freshTotals['subtotal'], coupon_shopper($__user, $email, $phone));
            }
            $orderTotal = round((float) $freshTotals['subtotal'] - $discount + $shippingFee, 2);
            $orderNumber = 'RA-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $ins = $pdo->prepare(
                'INSERT INTO orders (order_number, user_id, status, payment_method, delivery_area, subtotal, discount, coupon_id, coupon_code, shipping_fee, total,
                 shipping_name, shipping_phone, customer_email, shipping_line1, shipping_city, shipping_state, shipping_zip, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $ins->execute([
                $orderNumber, $__user['id'] ?? null, 'pending', $payment, $deliveryArea,
                $freshTotals['subtotal'], $discount, $coupon['id'] ?? null, $coupon['code'] ?? null, $shippingFee, $orderTotal,
                $name, $phone, ($email ?: ($__user['email'] ?? null)) ?: null, $line1, $city, $state ?: null, $zip ?: null, $notes ?: null,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, variant_id, variant_label, product_name, price, quantity, subtotal, warranty_days) VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $productStockStmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
            $variantStockStmt = $pdo->prepare('UPDATE product_variants SET stock = stock - ? WHERE id = ? AND stock >= ?');
            foreach ($freshTotals['items'] as $it) {
                $itemStmt->execute([$orderId, $it['product_id'], $it['variant_id'], $it['variant_label'], $it['name'], $it['price'], $it['quantity'], $it['price'] * $it['quantity'], $it['warranty_days'] ?? null]);
                // Conditional UPDATE: if someone else bought the last units a moment ago
                // it matches no row, and we abort instead of overselling.
                $stmtStock = $it['variant_id'] ? $variantStockStmt : $productStockStmt;
                $stmtStock->execute([$it['quantity'], $it['variant_id'] ?: $it['product_id'], $it['quantity']]);
                if ($stmtStock->rowCount() < 1) {
                    throw new RuntimeException($it['name'] . ' just sold out — please review your cart.');
                }
            }
            order_status_add($orderId, 'pending');

            if ($__user && $saveAddress) {
                $pdo->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([$__user['id']]);
                $pdo->prepare(
                    'INSERT INTO addresses (user_id, label, full_name, phone, line1, city, state, zip, is_default) VALUES (?,?,?,?,?,?,?,?,1)'
                )->execute([$__user['id'], 'Home', $name, $phone, $line1, $city, $state ?: null, $zip ?: null]);
            }

            $pdo->commit();
            cart_clear();
            coupon_session_clear();
            $_SESSION['last_order_number'] = $orderNumber;
            $placedOrderId = (int) $orderId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // A coupon that just stopped working is dropped, so the shopper isn't stuck re-submitting it.
            if ($e instanceof CouponException) { coupon_session_clear(); $appliedCoupon = null; }
            $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Something went wrong placing your order. Please try again.';
            if (!($e instanceof RuntimeException)) error_log('[checkout] ' . $e->getMessage());
        }

        if (!empty($placedOrderId)) {
            // Send the shopper on to the confirmation page right away, then
            // email in the background so a slow mail server never delays them.
            header('Location: /order-success.php');
            session_write_close();
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
            require_once __DIR__ . '/includes/order_mail.php';
            send_order_confirmation($placedOrderId);
            exit;
        }
    }
}

$discount = $appliedCoupon ? (float) $appliedCoupon['discount'] : 0.0;
$pageTitle = 'Checkout';
$bodyClass = 'has-action-bar';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header wrap">
  <span class="eyebrow">Checkout</span>
  <h1>Shipping details</h1>
</div>

<div class="wrap cart-layout">
  <div class="form-card">
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

    <?php if (!$__user): ?>
      <div class="alert alert-info">Checking out as a guest. <a href="/login.php">Log in</a> to save this address and track your order later.</div>
    <?php endif; ?>

    <form method="post" id="checkoutForm">
      <?= csrf_field() ?>
      <div class="field-row">
        <div class="field">
          <label for="shipping_name">Full name</label>
          <input id="shipping_name" name="shipping_name" autocomplete="name" required value="<?= e($_POST['shipping_name'] ?? ($defaultAddress['full_name'] ?? ($__user['name'] ?? ''))) ?>">
        </div>
        <div class="field">
          <label for="shipping_phone">Phone number</label>
          <input id="shipping_phone" name="shipping_phone" type="tel" inputmode="tel" autocomplete="tel" required value="<?= e($_POST['shipping_phone'] ?? ($defaultAddress['phone'] ?? ($__user['phone'] ?? ''))) ?>">
        </div>
      </div>
      <div class="field">
        <label for="customer_email">Email <span style="font-weight:400;color:var(--ink-faint);">(for your order confirmation &amp; invoice)</span></label>
        <input type="email" id="customer_email" name="customer_email" autocomplete="email" value="<?= e($_POST['customer_email'] ?? ($__user['email'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="shipping_line1">Street address</label>
        <input id="shipping_line1" name="shipping_line1" autocomplete="address-line1" required value="<?= e($_POST['shipping_line1'] ?? ($defaultAddress['line1'] ?? '')) ?>">
      </div>
      <div class="field-row">
        <div class="field">
          <label for="shipping_city">City</label>
          <input id="shipping_city" name="shipping_city" autocomplete="address-level2" required value="<?= e($_POST['shipping_city'] ?? ($defaultAddress['city'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="shipping_state">State / Division</label>
          <input id="shipping_state" name="shipping_state" autocomplete="address-level1" value="<?= e($_POST['shipping_state'] ?? ($defaultAddress['state'] ?? '')) ?>">
        </div>
      </div>
      <div class="field">
        <label for="shipping_zip">ZIP / postal code</label>
        <input id="shipping_zip" name="shipping_zip" inputmode="numeric" autocomplete="postal-code" value="<?= e($_POST['shipping_zip'] ?? ($defaultAddress['zip'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="notes">Order notes (optional)</label>
        <textarea id="notes" name="notes" rows="3" placeholder="Delivery instructions, gift note, etc."><?= e($_POST['notes'] ?? '') ?></textarea>
      </div>

      <div class="field">
        <label>Delivery area</label>
        <label class="radio-option">
          <input type="radio" name="delivery_area" value="inside_dhaka" id="da_inside" data-fee="<?= e((string)$shippingInside) ?>" <?= ($_POST['delivery_area'] ?? 'inside_dhaka') === 'inside_dhaka' ? 'checked' : '' ?>>
          <span class="radio-option-label">Inside Dhaka — <?= money($shippingInside) ?></span>
        </label>
        <label class="radio-option">
          <input type="radio" name="delivery_area" value="suburbs" id="da_suburbs" data-fee="<?= e((string)$shippingSuburbs) ?>" <?= ($_POST['delivery_area'] ?? '') === 'suburbs' ? 'checked' : '' ?>>
          <span class="radio-option-label">Dhaka Suburbs — <?= money($shippingSuburbs) ?></span>
        </label>
        <label class="radio-option">
          <input type="radio" name="delivery_area" value="outside_dhaka" id="da_outside" data-fee="<?= e((string)$shippingOutside) ?>" <?= ($_POST['delivery_area'] ?? '') === 'outside_dhaka' ? 'checked' : '' ?>>
          <span class="radio-option-label">Outside Dhaka — <?= money($shippingOutside) ?></span>
        </label>
        <div class="hint">+<?= money(SHIPPING_EXTRA_PER_KG) ?> added per additional kg once your parcel passes <?= (int)SHIPPING_FREE_WEIGHT_KG ?>kg.</div>
      </div>

      <div class="field">
        <label>Payment method</label>
        <label class="radio-option">
          <input type="radio" name="payment_method" value="cod" id="pm_cod" checked disabled>
          <span class="radio-option-label">Cash on delivery — pay when your order arrives</span>
        </label>
        <label class="radio-option is-disabled" title="Not available yet">
          <input type="radio" id="pm_online" disabled>
          <span class="radio-option-label">Online Payment <span style="color:var(--ink-faint);font-size:.85em;">— coming soon</span></span>
        </label>
      </div>

      <?php if ($__user): ?>
        <div class="checkbox-row" style="margin-bottom:18px;">
          <input type="checkbox" name="save_address" id="save_address" checked>
          <label for="save_address" style="margin:0;font-weight:400;">Save this address to my account</label>
        </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary btn-block place-order-desktop">Place order — <span id="submitTotal"><?= money($totals['subtotal'] - $discount + $shippingInside) ?></span></button>
    </form>
  </div>

  <div class="summary-card">
    <h3>Order summary</h3>
    <?php foreach ($totals['items'] as $it): ?>
      <div class="summary-row"><span><?= e($it['name']) ?><?= $it['variant_label'] ? ' <span style="color:var(--ink-faint);">(' . e($it['variant_label']) . ')</span>' : '' ?> × <?= (int)$it['quantity'] ?></span><span class="val"><?= money($it['price'] * $it['quantity']) ?></span></div>
    <?php endforeach; ?>
    <div class="summary-row"><span>Subtotal</span><span class="val"><?= money($totals['subtotal']) ?></span></div>
    <div class="summary-row discount-row" id="summaryDiscountRow"<?= $discount > 0 ? '' : ' hidden' ?>><span>Discount<?= $appliedCoupon ? ' <small class="coupon-tag" id="summaryCouponCode">' . e($appliedCoupon['coupon']['code']) . '</small>' : ' <small class="coupon-tag" id="summaryCouponCode"></small>' ?></span><span class="val" id="summaryDiscount">&minus;<?= money($discount) ?></span></div>
    <div class="summary-row"><span>Shipping</span><span class="val" id="summaryShipping"><?= money($shippingInside) ?></span></div>
    <div class="summary-row total"><span>Total</span><span class="val" id="summaryTotal"><?= money($totals['subtotal'] - $discount + $shippingInside) ?></span></div>

    <div class="coupon-box" id="couponBox" data-discount="<?= e((string) $discount) ?>">
      <form class="coupon-form" id="couponForm" autocomplete="off"<?= $appliedCoupon ? ' hidden' : '' ?>>
        <label for="couponCode" class="coupon-label">Have a coupon code?</label>
        <div class="coupon-row">
          <input id="couponCode" type="text" placeholder="Enter code" maxlength="40" autocapitalize="characters" spellcheck="false" enterkeyhint="done">
          <button type="submit" class="btn btn-outline btn-sm" id="couponApply">Apply</button>
        </div>
      </form>
      <div class="coupon-applied" id="couponApplied"<?= $appliedCoupon ? '' : ' hidden' ?>>
        <span class="coupon-ok" aria-hidden="true">✓</span>
        <span class="coupon-applied-text"><strong id="couponAppliedCode"><?= $appliedCoupon ? e($appliedCoupon['coupon']['code']) : '' ?></strong> applied<small id="couponAppliedDesc"><?= $appliedCoupon ? e(coupon_describe($appliedCoupon['coupon'])) : '' ?></small></span>
        <button type="button" class="link-btn" id="couponRemove">Remove</button>
      </div>
      <div class="coupon-msg" id="couponMsg" role="status" aria-live="polite"></div>
    </div>
  </div>

  <div class="checkout-bar">
    <div class="bb-price"><small>Total</small><strong id="barTotal"><?= money($totals['subtotal'] - $discount + $shippingInside) ?></strong></div>
    <button type="submit" form="checkoutForm" class="btn btn-primary">Place order</button>
  </div>
</div>

<script>
(function () {
  var subtotal = <?= (float)$totals['subtotal'] ?>;
  var discount = <?= (float) $discount ?>;
  var symbol = <?= json_encode(STORE_CURRENCY_SYMBOL) ?>;
  var radios = document.querySelectorAll('input[name="delivery_area"]');
  var shippingEl = document.getElementById('summaryShipping');
  var totalEl = document.getElementById('summaryTotal');
  var submitEl = document.getElementById('submitTotal');
  var barEl = document.getElementById('barTotal');
  var discRow = document.getElementById('summaryDiscountRow');
  var discEl = document.getElementById('summaryDiscount');
  var discCode = document.getElementById('summaryCouponCode');

  function fmt(n) {
    return symbol + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  // Display only — the server recomputes the discount and total when the order is placed.
  function update() {
    var checked = document.querySelector('input[name="delivery_area"]:checked');
    var fee = checked ? parseFloat(checked.dataset.fee) : 0;
    var total = Math.max(0, subtotal - discount) + fee;
    shippingEl.textContent = fmt(fee);
    totalEl.textContent = fmt(total);
    submitEl.textContent = fmt(total);
    if (barEl) barEl.textContent = fmt(total);
    discRow.hidden = !(discount > 0);
    discEl.textContent = '\u2212' + fmt(discount);
  }

  radios.forEach(function (r) { r.addEventListener('change', update); });
  // main.js fires this when a coupon is applied or removed.
  document.addEventListener('coupon:changed', function (e) {
    discount = (e.detail && e.detail.discount) || 0;
    discCode.textContent = (e.detail && e.detail.code) || '';
    update();
  });
  update();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
