<?php
require_once __DIR__ . '/../includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) { flash_set('error', 'Order not found.'); redirect('/admin/orders.php'); }

$validStatuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $newStatus = $_POST['status'] ?? '';
    if (in_array($newStatus, $validStatuses, true)) {
        db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $order['id']]);
        flash_set('success', 'Order status updated to ' . ucfirst($newStatus) . '.');
        redirect('/admin/order_detail.php?id=' . $order['id']);
    }
}

$itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$itemsStmt->execute([$order['id']]);
$items = $itemsStmt->fetchAll();

$customer = null;
if ($order['user_id']) {
    $custStmt = db()->prepare('SELECT id, name, email, phone FROM users WHERE id = ?');
    $custStmt->execute([$order['user_id']]);
    $customer = $custStmt->fetch();
}

$pageTitle = 'Order ' . $order['order_number'];
require __DIR__ . '/includes/header.php';
?>

<div class="panel">
  <div class="panel-head"><h2>Order items</h2></div>
  <table class="admin-table">
    <thead><tr><th>Item</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr><td><?= e($it['product_name']) ?></td><td class="mono"><?= money($it['price']) ?></td><td><?= (int)$it['quantity'] ?></td><td class="mono"><?= money($it['subtotal']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="panel-body" style="border-top:1px solid var(--line);">
    <div style="display:flex;justify-content:flex-end;gap:26px;font-size:0.92rem;">
      <div>Subtotal: <strong class="mono"><?= money($order['subtotal']) ?></strong></div>
      <div>Shipping (<?= e(delivery_area_label($order['delivery_area'])) ?>): <strong class="mono"><?= $order['shipping_fee'] > 0 ? money($order['shipping_fee']) : 'Free' ?></strong></div>
      <div>Total: <strong class="mono"><?= money($order['total']) ?></strong></div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
  <div class="panel">
    <div class="panel-head"><h2>Shipping details</h2></div>
    <div class="panel-body">
      <p><strong><?= e($order['shipping_name']) ?></strong><br>
      <?= e($order['shipping_phone']) ?><br>
      <?= e($order['shipping_line1']) ?><br>
      <?= e($order['shipping_city']) ?><?= $order['shipping_state'] ? ', ' . e($order['shipping_state']) : '' ?><?= $order['shipping_zip'] ? ' ' . e($order['shipping_zip']) : '' ?></p>
      <?php if ($order['notes']): ?><p><strong>Notes:</strong> <?= e($order['notes']) ?></p><?php endif; ?>
      <p style="color:var(--ink-faint);font-size:0.85rem;">Payment method: <?= $order['payment_method'] === 'cod' ? 'Cash on delivery' : 'Bank transfer' ?></p>
      <?php if ($customer): ?><p style="color:var(--ink-faint);font-size:0.85rem;">Account: <?= e($customer['name']) ?> (<?= e($customer['email']) ?>)</p>
      <?php else: ?><p style="color:var(--ink-faint);font-size:0.85rem;">Guest checkout</p><?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Update status</h2></div>
    <div class="panel-body">
      <form method="post">
        <?= csrf_field() ?>
        <div class="field">
          <label for="status">Order status</label>
          <select id="status" name="status">
            <?php foreach ($validStatuses as $s): ?>
              <option value="<?= e($s) ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">Update status</button>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
