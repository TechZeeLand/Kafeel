<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Orders';

$status = $_GET['status'] ?? '';
$validStatuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];
$where = '1=1';
$params = [];
if (in_array($status, $validStatuses, true)) {
    $where = 'status = ?';
    $params = [$status];
}
$stmt = db()->prepare("SELECT * FROM orders WHERE $where ORDER BY created_at DESC");
$stmt->execute($params);
$orders = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="panel">
  <div class="panel-head">
    <h2>Orders (<?= count($orders) ?>)</h2>
    <div style="display:flex;gap:6px;">
      <a href="/admin/orders.php" class="btn btn-sm <?= $status === '' ? 'btn-primary' : 'btn-outline' ?>">All</a>
      <?php foreach ($validStatuses as $s): ?>
        <a href="/admin/orders.php?status=<?= e($s) ?>" class="btn btn-sm <?= $status === $s ? 'btn-primary' : 'btn-outline' ?>"><?= ucfirst($s) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <table class="admin-table">
    <thead><tr><th>Order</th><th>Customer</th><th>Payment</th><th>Status</th><th>Total</th><th>Date</th><th></th></tr></thead>
    <tbody>
      <?php if (!$orders): ?>
        <tr class="empty-row"><td colspan="7">No orders found.</td></tr>
      <?php endif; ?>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td class="mono"><?= e($o['order_number']) ?></td>
          <td><?= e($o['shipping_name']) ?><br><span style="color:var(--ink-faint);font-size:0.78rem;"><?= e($o['shipping_phone']) ?></span></td>
          <td><?= $o['payment_method'] === 'cod' ? 'COD' : 'Bank transfer' ?></td>
          <td><span class="status-pill status-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
          <td class="mono"><?= money($o['total']) ?></td>
          <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
          <td><a href="/admin/order_detail.php?id=<?= (int)$o['id'] ?>" class="btn btn-outline btn-sm">Manage</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
