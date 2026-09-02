<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';

$productCount = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
$categoryCount = (int) db()->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$userCount = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$pendingOrders = (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$revenue = (float) db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$lowStock = db()->query('SELECT id, name, stock FROM products WHERE stock <= 5 AND is_active = 1 ORDER BY stock ASC LIMIT 6')->fetchAll();
$recentOrders = db()->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 8')->fetchAll();
?>

<div class="stat-grid">
  <div class="stat-card"><div class="label">Total revenue</div><div class="value"><?= money($revenue) ?></div></div>
  <div class="stat-card"><div class="label">Pending orders</div><div class="value"><?= $pendingOrders ?></div></div>
  <div class="stat-card"><div class="label">Products</div><div class="value"><?= $productCount ?></div></div>
  <div class="stat-card"><div class="label">Customers</div><div class="value"><?= $userCount ?></div></div>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>Recent orders</h2>
    <a href="/admin/orders.php" class="btn btn-outline btn-sm">View all</a>
  </div>
  <table class="admin-table">
    <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Total</th><th>Date</th><th></th></tr></thead>
    <tbody>
      <?php if (!$recentOrders): ?>
        <tr class="empty-row"><td colspan="6">No orders yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($recentOrders as $o): ?>
        <tr>
          <td class="mono"><?= e($o['order_number']) ?></td>
          <td><?= e($o['shipping_name']) ?></td>
          <td><span class="status-pill status-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
          <td class="mono"><?= money($o['total']) ?></td>
          <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
          <td><a href="/admin/order_detail.php?id=<?= (int)$o['id'] ?>" class="btn btn-outline btn-sm">Manage</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="panel">
  <div class="panel-head"><h2>Low stock</h2><span style="color:var(--ink-faint);font-size:0.82rem;"><?= count($lowStock) ?> item(s) at or below 5 units</span></div>
  <table class="admin-table">
    <thead><tr><th>Product</th><th>Stock left</th><th></th></tr></thead>
    <tbody>
      <?php if (!$lowStock): ?>
        <tr class="empty-row"><td colspan="3">Everything is well stocked.</td></tr>
      <?php endif; ?>
      <?php foreach ($lowStock as $p): ?>
        <tr>
          <td><?= e($p['name']) ?></td>
          <td class="<?= $p['stock'] == 0 ? 'stock-low' : '' ?>"><?= (int)$p['stock'] ?></td>
          <td><a href="/admin/product_form.php?id=<?= (int)$p['id'] ?>" class="btn btn-outline btn-sm">Restock</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
