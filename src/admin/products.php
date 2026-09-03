<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Products';

$q = trim($_GET['q'] ?? '');
$where = '1=1';
$params = [];
if ($q !== '') {
    $where = '(p.name LIKE ? OR p.sku LIKE ?)';
    $params = ["%$q%", "%$q%"];
}
$stmt = db()->prepare(
    "SELECT p.*, c.name AS category_name FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE $where ORDER BY p.created_at DESC"
);
$stmt->execute($params);
$products = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="panel">
  <div class="panel-head">
    <h2>All products (<?= count($products) ?>)</h2>
    <a href="/admin/product_form.php" class="btn btn-primary">+ Add product</a>
  </div>
  <div class="panel-body" style="padding-bottom:0;">
    <form method="get" style="max-width:320px;">
      <input type="search" name="q" placeholder="Search by name or SKU…" value="<?= e($q) ?>" style="width:100%;padding:9px 12px;border:1px solid var(--line);border-radius:6px;">
    </form>
  </div>
  <table class="admin-table">
    <thead><tr><th></th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if (!$products): ?>
        <tr class="empty-row"><td colspan="7">No products found.</td></tr>
      <?php endif; ?>
      <?php foreach ($products as $p): ?>
        <tr>
          <td><img class="thumb" src="<?= e(product_image_src($p['image_main'])) ?>" alt=""></td>
          <td><?= e($p['name']) ?><?php if ($p['is_featured']): ?> <span class="pill pill-sage" style="font-size:0.6rem;">Featured</span><?php endif; ?><?php if (!empty($p['youtube_url'])): ?> <span class="pill pill-rust" style="font-size:0.6rem;" title="<?= e($p['youtube_url']) ?>">▶ Video</span><?php endif; ?></td>
          <td><?= e($p['category_name'] ?? '—') ?></td>
          <td class="mono"><?= money($p['price']) ?></td>
          <td class="<?= $p['stock'] <= 5 ? 'stock-low' : '' ?>"><?= (int)$p['stock'] ?></td>
          <td><?= $p['is_active'] ? '<span class="pill pill-sage">Active</span>' : '<span class="pill pill-ink">Hidden</span>' ?></td>
          <td style="white-space:nowrap;">
            <a href="/admin/product_form.php?id=<?= (int)$p['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
            <form method="post" action="/admin/product_delete.php" style="display:inline;" onsubmit="return confirm('Delete this product? This cannot be undone.');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
