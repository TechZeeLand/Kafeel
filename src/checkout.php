<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$totals = cart_totals();
if (!$totals['items']) {
    redirect('/cart.php');
}

$__user = current_user();
$errors = [];

$defaultAddress = null;
if ($__user) {
    $stmt = db()->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1');
    $stmt->execute([$__user['id']]);
    $defaultAddress = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim($_POST['shipping_name'] ?? '');
    $phone = trim($_POST['shipping_phone'] ?? '');
    $line1 = trim($_POST['shipping_line1'] ?? '');
    $city = trim($_POST['shipping_city'] ?? '');
    $state = trim($_POST['shipping_state'] ?? '');
    $zip = trim($_POST['shipping_zip'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $payment = ($_POST['payment_method'] ?? 'cod') === 'bank_transfer' ? 'bank_transfer' : 'cod';
    $saveAddress = !empty($_POST['save_address']);

    if ($name === '' || strlen($name) < 2) $errors[] = 'Please enter the recipient\'s full name.';
    if ($phone === '' || strlen($phone) < 6) $errors[] = 'Please enter a valid phone number.';
    if ($line1 === '') $errors[] = 'Please enter your street address.';
    if ($city === '') $errors[] = 'Please enter your city.';

    // Re-verify current cart & stock right before committing the order.
    $freshTotals = cart_totals();
    if (!$freshTotals['items']) {
        $errors[] = 'Your cart is empty.';
    }
    foreach ($freshTotals['items'] as $it) {
        if ($it['quantity'] > $it['stock']) {
            $errors[] = e($it['name']) . ' only has ' . (int)$it['stock'] . ' left in stock.';
        }
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $orderNumber = 'ED-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $ins = $pdo->prepare(
                'INSERT INTO orders (order_number, user_id, status, payment_method, subtotal, shipping_fee, total,
                 shipping_name, shipping_phone, shipping_line1, shipping_city, shipping_state, shipping_zip, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $ins->execute([
                $orderNumber, $__user['id'] ?? null, 'pending', $payment,
                $freshTotals['subtotal'], $freshTotals['shipping'], $freshTotals['total'],
                $name, $phone, $line1, $city, $state ?: null, $zip ?: null, $notes ?: null,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal) VALUES (?,?,?,?,?,?)'
            );
            $stockStmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
            foreach ($freshTotals['items'] as $it) {
                $itemStmt->execute([$orderId, $it['product_id'], $it['name'], $it['price'], $it['quantity'], $it['price'] * $it['quantity']]);
                $stockStmt->execute([$it['quantity'], $it['product_id'], $it['quantity']]);
            }

            if ($__user && $saveAddress) {
                $pdo->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([$__user['id']]);
                $pdo->prepare(
                    'INSERT INTO addresses (user_id, label, full_name, phone, line1, city, state, zip, is_default) VALUES (?,?,?,?,?,?,?,?,1)'
                )->execute([$__user['id'], 'Home', $name, $phone, $line1, $city, $state ?: null, $zip ?: null]);
            }

            $pdo->commit();
            cart_clear();
            $_SESSION['last_order_number'] = $orderNumber;
            redirect('/order-success.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong placing your order. Please try again.';
        }
    }
}

$pageTitle = 'Checkout';
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

    <form method="post">
      <?= csrf_field() ?>
      <div class="field-row">
        <div class="field">
          <label for="shipping_name">Full name</label>
          <input id="shipping_name" name="shipping_name" required value="<?= e($_POST['shipping_name'] ?? ($defaultAddress['full_name'] ?? ($__user['name'] ?? ''))) ?>">
        </div>
        <div class="field">
          <label for="shipping_phone">Phone number</label>
          <input id="shipping_phone" name="shipping_phone" required value="<?= e($_POST['shipping_phone'] ?? ($defaultAddress['phone'] ?? ($__user['phone'] ?? ''))) ?>">
        </div>
      </div>
      <div class="field">
        <label for="shipping_line1">Street address</label>
        <input id="shipping_line1" name="shipping_line1" required value="<?= e($_POST['shipping_line1'] ?? ($defaultAddress['line1'] ?? '')) ?>">
      </div>
      <div class="field-row">
        <div class="field">
          <label for="shipping_city">City</label>
          <input id="shipping_city" name="shipping_city" required value="<?= e($_POST['shipping_city'] ?? ($defaultAddress['city'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="shipping_state">State / Division</label>
          <input id="shipping_state" name="shipping_state" value="<?= e($_POST['shipping_state'] ?? ($defaultAddress['state'] ?? '')) ?>">
        </div>
      </div>
      <div class="field">
        <label for="shipping_zip">ZIP / postal code</label>
        <input id="shipping_zip" name="shipping_zip" value="<?= e($_POST['shipping_zip'] ?? ($defaultAddress['zip'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="notes">Order notes (optional)</label>
        <textarea id="notes" name="notes" rows="3" placeholder="Delivery instructions, gift note, etc."><?= e($_POST['notes'] ?? '') ?></textarea>
      </div>

      <div class="field">
        <label>Payment method</label>
        <div class="checkbox-row" style="margin-bottom:8px;">
          <input type="radio" name="payment_method" value="cod" id="pm_cod" checked>
          <label for="pm_cod" style="margin:0;font-weight:400;">Cash on delivery</label>
        </div>
        <div class="checkbox-row">
          <input type="radio" name="payment_method" value="bank_transfer" id="pm_bank">
          <label for="pm_bank" style="margin:0;font-weight:400;">Bank transfer (details sent after order)</label>
        </div>
      </div>

      <?php if ($__user): ?>
        <div class="checkbox-row" style="margin-bottom:18px;">
          <input type="checkbox" name="save_address" id="save_address" checked>
          <label for="save_address" style="margin:0;font-weight:400;">Save this address to my account</label>
        </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary btn-block">Place order — <?= money($totals['total']) ?></button>
    </form>
  </div>

  <div class="summary-card">
    <h3>Order summary</h3>
    <?php foreach ($totals['items'] as $it): ?>
      <div class="summary-row"><span><?= e($it['name']) ?> × <?= (int)$it['quantity'] ?></span><span class="val"><?= money($it['price'] * $it['quantity']) ?></span></div>
    <?php endforeach; ?>
    <div class="summary-row"><span>Subtotal</span><span class="val"><?= money($totals['subtotal']) ?></span></div>
    <div class="summary-row"><span>Shipping</span><span class="val"><?= $totals['shipping'] > 0 ? money($totals['shipping']) : 'Free' ?></span></div>
    <div class="summary-row total"><span>Total</span><span class="val"><?= money($totals['total']) ?></span></div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
