<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/product_admin.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);

    // Remember the photos so the files can be cleaned up once the rows are gone.
    $paths = [];
    $s = db()->prepare('SELECT image_main AS p FROM products WHERE id = ? UNION SELECT image_path FROM product_images WHERE product_id = ? UNION SELECT image FROM product_options WHERE product_id = ?');
    $s->execute([$id, $id, $id]);
    foreach ($s->fetchAll() as $r) if ($r['p']) $paths[] = $r['p'];

    $pi = db()->prepare('SELECT name, sku FROM products WHERE id = ?');
    $pi->execute([$id]);
    $gone = $pi->fetch();

    db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    foreach ($paths as $p) delete_upload_if_unused($p);
    if ($gone) admin_log('product.delete', 'Deleted product "' . $gone['name'] . '"', 'product', $id, array_filter(['sku' => $gone['sku']]));
    flash_set('success', 'Product deleted.');
}
redirect('/admin/products.php');
