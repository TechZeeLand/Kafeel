<?php
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Categories';

$categories = db()->query(
    "SELECT c.*, COUNT(p.id) AS product_count FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id ORDER BY c.sort_order, c.name"
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="panel">
  <div class="panel-head">
    <h2>All categories (<?= count($categories) ?>)</h2>
    <a href="/admin/category_form.php" class="btn btn-primary">+ Add category</a>
  </div>
  <table class="admin-table">
    <thead><tr><th></th><th>Name</th><th>Slug</th><th>Products</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if (!$categories): ?>
        <tr class="empty-row"><td colspan="6">No categories yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($categories as $c): ?>
        <tr>
          <td><img class="thumb" src="<?= e(product_image_src($c['image'])) ?>" alt=""></td>
          <td><?= e($c['name']) ?></td>
          <td class="mono"><?= e($c['slug']) ?></td>
          <td><?= (int)$c['product_count'] ?></td>
          <td><?= $c['is_active'] ? '<span class="pill pill-sage">Active</span>' : '<span class="pill pill-ink">Hidden</span>' ?></td>
          <td style="white-space:nowrap;">
            <a href="/admin/category_form.php?id=<?= (int)$c['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
            <form method="post" action="/admin/category_delete.php" style="display:inline;" onsubmit="return confirm('Delete this category? Products in it will become uncategorized.');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
