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
$validAreas = ['inside_dhaka', 'suburbs', 'outside_dhaka'];
$detailErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'edit_details') {
    require_csrf();
    $d = [
        'shipping_name' => trim($_POST['shipping_name'] ?? ''),
        'shipping_phone' => trim($_POST['shipping_phone'] ?? ''),
        'customer_email' => trim($_POST['customer_email'] ?? ''),
        'shipping_line1' => trim($_POST['shipping_line1'] ?? ''),
        'shipping_city' => trim($_POST['shipping_city'] ?? ''),
        'shipping_state' => trim($_POST['shipping_state'] ?? ''),
        'shipping_zip' => trim($_POST['shipping_zip'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
    ];
    $deliveryArea = in_array($_POST['delivery_area'] ?? '', $validAreas, true) ? $_POST['delivery_area'] : $order['delivery_area'];
    $shippingFee = is_numeric($_POST['shipping_fee'] ?? null) ? max(0, round((float) $_POST['shipping_fee'], 2)) : (float) $order['shipping_fee'];

    if ($d['shipping_name'] === '') $detailErrors[] = 'Recipient name can\'t be empty.';
    if ($d['shipping_phone'] === '') $detailErrors[] = 'Phone can\'t be empty.';
    if ($d['customer_email'] !== '' && !filter_var($d['customer_email'], FILTER_VALIDATE_EMAIL)) $detailErrors[] = 'That email address doesn\'t look valid.';
    if ($d['shipping_line1'] === '') $detailErrors[] = 'Address line can\'t be empty.';
    if ($d['shipping_city'] === '') $detailErrors[] = 'City can\'t be empty.';

    if (!$detailErrors) {
        $newTotal = round((float) $order['subtotal'] - (float) $order['discount'] + $shippingFee, 2);
        db()->prepare(
            'UPDATE orders SET shipping_name=?, shipping_phone=?, customer_email=?, shipping_line1=?, shipping_city=?, shipping_state=?, shipping_zip=?, delivery_area=?, shipping_fee=?, total=?, notes=? WHERE id=?'
        )->execute([$d['shipping_name'], $d['shipping_phone'], $d['customer_email'] ?: null, $d['shipping_line1'], $d['shipping_city'], $d['shipping_state'] ?: null, $d['shipping_zip'] ?: null, $deliveryArea, $shippingFee, $newTotal, $d['notes'] ?: null, $order['id']]);

        $diff = admin_log_diff(
            ['name' => $order['shipping_name'], 'phone' => $order['shipping_phone'], 'email' => $order['customer_email'], 'line1' => $order['shipping_line1'], 'city' => $order['shipping_city'], 'state' => $order['shipping_state'], 'zip' => $order['shipping_zip'], 'area' => delivery_area_label($order['delivery_area']), 'shipping_fee' => money((float) $order['shipping_fee']), 'notes' => $order['notes']],
            ['name' => $d['shipping_name'], 'phone' => $d['shipping_phone'], 'email' => $d['customer_email'], 'line1' => $d['shipping_line1'], 'city' => $d['shipping_city'], 'state' => $d['shipping_state'], 'zip' => $d['shipping_zip'], 'area' => delivery_area_label($deliveryArea), 'shipping_fee' => money($shippingFee), 'notes' => $d['notes']],
            ['name' => 'Recipient name', 'phone' => 'Phone', 'email' => 'Email', 'line1' => 'Address', 'city' => 'City', 'state' => 'State/area', 'zip' => 'ZIP', 'area' => 'Delivery area', 'shipping_fee' => 'Shipping fee', 'notes' => 'Notes']
        );
        admin_log('order.edit_details', 'Order ' . $order['order_number'] . ' details edited' . ($diff ? ': ' . admin_log_diff_summary($diff) : ' (saved, nothing changed)'), 'order', (int) $order['id'], $diff ? ['changes' => $diff] : []);
        flash_set('success', 'Order details updated.');
        redirect('/admin/order_detail.php?id=' . $order['id']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') !== 'edit_details') {
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
        <tr><td><?= e($it['product_name']) ?><?php if (!empty($it['variant_label'])): ?><br><span style="color:var(--ink-faint);font-size:0.82rem;"><?= e($it['variant_label']) ?></span><?php endif; ?><?php if ($w = warranty_label($it['warranty_days'] ?? null)): ?><br><span style="color:var(--ink-faint);font-size:0.82rem;"><?= e($w) ?></span><?php endif; ?></td><td class="mono"><?= money($it['price']) ?></td><td><?= (int)$it['quantity'] ?></td><td class="mono"><?= money($it['subtotal']) ?></td></tr>
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
      <p style="color:var(--ink-faint);font-size:0.85rem;">Payment method: <?= e(payment_method_label($order['payment_method'])) ?></p>
      <?php if ($customer): ?><p style="color:var(--ink-faint);font-size:0.85rem;">Account: <?= e($customer['name']) ?> (<?= e($customer['email']) ?>)</p>
      <?php else: ?><p style="color:var(--ink-faint);font-size:0.85rem;">Guest checkout</p><?php endif; ?>

      <details class="log-details">
        <summary>Edit shipping details</summary>
        <form method="post" style="margin-top:12px;">
          <?= csrf_field() ?>
          <input type="hidden" name="form_action" value="edit_details">
          <?php if ($detailErrors): ?><div class="alert alert-error"><?php foreach ($detailErrors as $e2): ?><div><?= e($e2) ?></div><?php endforeach; ?></div><?php endif; ?>
          <div class="field-row">
            <div class="field"><label for="shipping_name">Recipient name</label><input id="shipping_name" name="shipping_name" value="<?= e($order['shipping_name']) ?>"></div>
            <div class="field"><label for="shipping_phone">Phone</label><input id="shipping_phone" name="shipping_phone" value="<?= e($order['shipping_phone']) ?>"></div>
          </div>
          <div class="field"><label for="customer_email">Email <span class="muted" style="font-weight:400;">(optional)</span></label><input type="email" id="customer_email" name="customer_email" value="<?= e($order['customer_email'] ?? '') ?>"></div>
          <div class="field"><label for="shipping_line1">Address</label><input id="shipping_line1" name="shipping_line1" value="<?= e($order['shipping_line1']) ?>"></div>
          <div class="field-row">
            <div class="field"><label for="shipping_city">City</label><input id="shipping_city" name="shipping_city" value="<?= e($order['shipping_city']) ?>"></div>
            <div class="field"><label for="shipping_state">State/area <span class="muted" style="font-weight:400;">(optional)</span></label><input id="shipping_state" name="shipping_state" value="<?= e($order['shipping_state'] ?? '') ?>"></div>
          </div>
          <div class="field-row">
            <div class="field"><label for="shipping_zip">ZIP <span class="muted" style="font-weight:400;">(optional)</span></label><input id="shipping_zip" name="shipping_zip" value="<?= e($order['shipping_zip'] ?? '') ?>"></div>
            <div class="field">
              <label for="delivery_area">Delivery area</label>
              <select id="delivery_area" name="delivery_area">
                <?php foreach ($validAreas as $a): ?><option value="<?= e($a) ?>" <?= $order['delivery_area'] === $a ? 'selected' : '' ?>><?= e(delivery_area_label($a)) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="field">
            <label for="shipping_fee">Shipping fee</label>
            <input type="number" min="0" step="0.01" id="shipping_fee" name="shipping_fee" value="<?= e($order['shipping_fee']) ?>">
            <div class="hint">Changing the delivery area above doesn't recalculate this on its own — adjust it here if the fee should change too. The order total is recalculated from this automatically.</div>
          </div>
          <div class="field"><label for="notes">Notes <span class="muted" style="font-weight:400;">(optional)</span></label><input id="notes" name="notes" value="<?= e($order['notes'] ?? '') ?>"></div>
          <button type="submit" class="btn btn-outline">Save details</button>
        </form>
      </details>
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
