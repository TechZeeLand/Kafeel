<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$orderNumber = $_GET['order'] ?? '';
$stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ? AND user_id = ?');
$stmt->execute([$orderNumber, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    $pageTitle = 'Order not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="wrap section"><div class="empty-state"><h2>Order not found</h2><a class="btn btn-primary" href="/orders.php">Back to orders</a></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$itemsStmt->execute([$order['id']]);
$items = $itemsStmt->fetchAll();

$__activeAccountTab = 'orders';
$pageTitle = 'Order ' . $order['order_number'];
require __DIR__ . '/includes/header.php';
?>

<div class="page-header wrap"><span class="eyebrow">Order</span><h1 class="mono" style="font-family:var(--font-mono);font-size:1.6rem;"><?= e($order['order_number']) ?></h1></div>

<div class="wrap account-layout">
  <?php include __DIR__ . '/includes/account_nav.php'; ?>

  <div>
    <div class="panel" style="padding:20px;margin-bottom:20px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:14px;">
      <div><div style="font-size:0.78rem;color:var(--ink-faint);">Status</div><span class="status-pill status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span></div>
      <div><div style="font-size:0.78rem;color:var(--ink-faint);">Placed on</div><?= date('d M Y, H:i', strtotime($order['created_at'])) ?></div>
      <div><div style="font-size:0.78rem;color:var(--ink-faint);">Payment</div><?= $order['payment_method'] === 'cod' ? 'Cash on delivery' : 'Bank transfer' ?></div>
      <div><div style="font-size:0.78rem;color:var(--ink-faint);">Total</div><strong class="mono"><?= money($order['total']) ?></strong></div>
    </div>

    <div class="form-card" style="margin-bottom:20px;">
      <h3 style="margin-bottom:14px;">Items</h3>
      <table class="data-table">
        <thead><tr><th>Item</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr><td><?= e($it['product_name']) ?></td><td class="mono"><?= money($it['price']) ?></td><td><?= (int)$it['quantity'] ?></td><td class="mono"><?= money($it['subtotal']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="summary-row"><span>Subtotal</span><span class="val"><?= money($order['subtotal']) ?></span></div>
      <div class="summary-row"><span>Shipping (<?= e(delivery_area_label($order['delivery_area'])) ?>)</span><span class="val"><?= $order['shipping_fee'] > 0 ? money($order['shipping_fee']) : 'Free' ?></span></div>
      <div class="summary-row total"><span>Total</span><span class="val"><?= money($order['total']) ?></span></div>
    </div>

    <div class="form-card">
      <h3 style="margin-bottom:10px;">Shipping to</h3>
      <p style="color:var(--ink-soft);"><?= e($order['shipping_name']) ?> · <?= e($order['shipping_phone']) ?><br>
      <?= e($order['shipping_line1']) ?>, <?= e($order['shipping_city']) ?><?= $order['shipping_state'] ? ', ' . e($order['shipping_state']) : '' ?><?= $order['shipping_zip'] ? ' ' . e($order['shipping_zip']) : '' ?></p>
      <?php if ($order['notes']): ?><p style="color:var(--ink-soft);"><strong>Notes:</strong> <?= e($order['notes']) ?></p><?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
