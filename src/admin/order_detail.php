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
    $note = trim($_POST['note'] ?? '');
    if (in_array($newStatus, $validStatuses, true)) {
        if ($newStatus !== $order['status']) {
            db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $order['id']]);
            order_status_add($order['id'], $newStatus, $note ?: null);

            if ($order['user_id']) {
                $cStmt = db()->prepare('SELECT name, email FROM users WHERE id = ?');
                $cStmt->execute([$order['user_id']]);
                $c = $cStmt->fetch();
                if ($c) {
                    require_once __DIR__ . '/../includes/mail.php';
                    $statusLabels = ['pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
                    $link = rtrim(SITE_URL, '/') . '/order-detail.php?order=' . $order['order_number'];
                    $body = '<p>Hi ' . e(explode(' ', $c['name'])[0]) . ',</p>'
                        . '<p>Your order <strong>#' . e($order['order_number']) . '</strong> is now <strong>' . e($statusLabels[$newStatus] ?? ucfirst($newStatus)) . '</strong>.</p>'
                        . ($note ? '<p>' . e($note) . '</p>' : '')
                        . '<p style="margin:20px 0;"><a href="' . e($link) . '" style="background:#a97c34;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;">Track your order</a></p>';
                    send_email($c['email'], $c['name'], 'Order #' . $order['order_number'] . ' — ' . ($statusLabels[$newStatus] ?? ucfirst($newStatus)), email_wrap('Order update', $body));
                }
            }
            flash_set('success', 'Order status updated to ' . ucfirst($newStatus) . '.');
        } else {
            flash_set('info', 'Status unchanged.');
        }
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

$history = order_status_history($order['id']);
$statusLabels = ['pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

$pageTitle = 'Order ' . $order['order_number'];
require __DIR__ . '/includes/header.php';
?>

<div class="panel">
  <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center;">
    <h2>Order items</h2>
    <a href="/admin/invoice.php?id=<?= (int)$order['id'] ?>" target="_blank" class="btn btn-outline btn-sm">📄 View invoice</a>
  </div>
  <table class="admin-table">
    <thead><tr><th>Item</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr><td><?= e($it['product_name']) ?><?php if (!empty($it['variant_label'])): ?><br><span style="color:var(--ink-faint);font-size:0.82rem;"><?= e($it['variant_label']) ?></span><?php endif; ?></td><td class="mono"><?= money($it['price']) ?></td><td><?= (int)$it['quantity'] ?></td><td class="mono"><?= money($it['subtotal']) ?></td></tr>
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
        <div class="field">
          <label for="note">Note (optional, shown to customer)</label>
          <input id="note" name="note" placeholder="e.g. Handed to courier, tracking #...">
        </div>
        <button type="submit" class="btn btn-primary">Update status</button>
      </form>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Status timeline</h2></div>
  <div class="panel-body">
    <?php if (!$history): ?>
      <p style="color:var(--ink-faint);">No status changes recorded yet.</p>
    <?php else: ?>
      <ul class="timeline">
        <?php foreach ($history as $h): ?>
          <li>
            <strong><?= e($statusLabels[$h['status']] ?? ucfirst($h['status'])) ?></strong>
            <span style="color:var(--ink-faint);"> — <?= e(date('j M Y, g:i A', strtotime($h['changed_at']))) ?></span>
            <?php if ($h['note']): ?><div style="color:var(--ink-faint);font-size:0.85rem;"><?= e($h['note']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
