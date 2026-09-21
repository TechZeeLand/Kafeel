<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/order_mail.php';

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
            $pdo = db();
            $admin = current_admin();
            $fromStatus = $order['status'];
            $pdo->beginTransaction();
            try {
                // Re-read the status under a lock so two admins acting at once can't both "move" the same order,
                // and so the recorded "from" status is what the order really was.
                $lock = $pdo->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
                $lock->execute([$order['id']]);
                $fromStatus = (string) $lock->fetchColumn();
                if ($fromStatus === $newStatus) {
                    $pdo->rollBack();
                    flash_set('info', 'Someone else already set this order to ' . ucfirst($newStatus) . '.');
                    redirect('/admin/order_detail.php?id=' . $order['id']);
                }
                $wasCancelled = $fromStatus === 'cancelled';
                $nowCancelled = $newStatus === 'cancelled';
                // Cancelling returns the items to stock; reviving a cancelled order takes them out again.
                if ($nowCancelled && !$wasCancelled) {
                    order_stock_adjust($pdo, (int) $order['id'], +1);
                } elseif ($wasCancelled && !$nowCancelled && !order_stock_adjust($pdo, (int) $order['id'], -1)) {
                    throw new RuntimeException('Not enough stock left to reactivate this order.');
                }
                $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $order['id']]);
                // Records what it changed from and who did it — shown in this admin page only.
                order_status_add((int) $order['id'], $newStatus, $note ?: null, $fromStatus, ['id' => (int) $admin['id'], 'name' => $admin['name']]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash_set('error', $e->getMessage());
                redirect('/admin/order_detail.php?id=' . $order['id']);
            }
            admin_log('order.status', 'Order ' . $order['order_number'] . ': ' . (ORDER_STATUS_LABELS[$fromStatus] ?? $fromStatus) . ' → ' . (ORDER_STATUS_LABELS[$newStatus] ?? $newStatus),
                'order', (int) $order['id'], array_filter(['from' => $fromStatus, 'to' => $newStatus, 'note' => $note ?: null]));

            // Email the customer (registered or guest, whichever email we have).
            require_once __DIR__ . '/../includes/order_mail.php';
            send_order_status_email($order, $newStatus, $note ?: null);
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

// Admin view: includes what each change was from and who made it.
$history = order_status_history((int) $order['id'], true);
// Older rows (from before this was tracked) have no 'from': work it out from the row before.
$__prev = null;
foreach ($history as &$__h) { if (empty($__h['from_status']) && $__prev !== null) $__h['from_status'] = $__prev; $__prev = $__h['status']; }
unset($__h);
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
      <?php if ((float) $order['discount'] > 0): ?><div>Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?>: <strong class="mono" style="color:var(--sage);">&minus;<?= money($order['discount']) ?></strong></div><?php endif; ?>
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
      <?php $__em = order_customer_email($order); if ($__em): ?><?= e($__em) ?><br><?php endif; ?>
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
        <p class="help" style="margin-top:-4px;">Cancelling an order puts its items back in stock. The customer is emailed automatically if we have their email.</p>
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
            <?php if (!empty($h['from_status'])): ?>
              <span style="color:var(--ink-faint);"><?= e($statusLabels[$h['from_status']] ?? ucfirst($h['from_status'])) ?> →</span>
            <?php endif; ?>
            <strong><?= e($statusLabels[$h['status']] ?? ucfirst($h['status'])) ?></strong>
            <span style="color:var(--ink-faint);"> — <?= e(fmt_dt($h['changed_at'], 'j M Y, g:i A')) ?></span>
            <div class="tl-by">
              <?php if (!empty($h['changed_by_name'])): ?>Updated by <strong><?= e($h['changed_by_name']) ?></strong>
              <?php elseif (empty($h['from_status'])): ?>Order placed by the customer
              <?php else: ?>Updated by — <span title="Recorded before admin names were tracked">(not recorded)</span><?php endif; ?>
            </div>
            <?php if ($h['note']): ?><div style="color:var(--ink-faint);font-size:0.85rem;">Note: <?= e($h['note']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
