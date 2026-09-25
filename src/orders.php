<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$stmt = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();

$__activeAccountTab = 'orders';
$pageTitle = 'Order history';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header wrap"><span class="eyebrow">Account</span><h1>Order history</h1></div>

<div class="wrap account-layout">
  <?php include __DIR__ . '/includes/account_nav.php'; ?>

  <div>
    <?php if (!$orders): ?>
      <div class="empty-state">
        <div class="icon"><?= ui_icon('box', 40) ?></div>
        <h2>No orders yet</h2>
        <p>Once you place an order, it'll show up here.</p>
        <a class="btn btn-primary" href="/">Start shopping</a>
      </div>
    <?php else: ?>
      <div class="table-scroll"><table class="data-table stack">
        <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Total</th><th><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td class="mono cell-order" data-label="Order"><a href="<?= e(order_url($o['order_number'])) ?>"><?= e($o['order_number']) ?></a></td>
              <td class="cell-date" data-label="Date"><?= fmt_dt($o['created_at'], 'd M Y') ?></td>
              <td class="cell-status" data-label="Status"><span class="status-pill status-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
              <td class="mono cell-total" data-label="Total"><?= money($o['total']) ?></td>
              <td class="cell-action"><a href="<?= e(order_url($o['order_number'])) ?>" class="btn btn-outline btn-sm">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
