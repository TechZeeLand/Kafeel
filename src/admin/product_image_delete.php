<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $imageId = (int) ($_POST['image_id'] ?? 0);
    $productId = (int) ($_POST['product_id'] ?? 0);
    db()->prepare('DELETE FROM product_images WHERE id = ?')->execute([$imageId]);
    redirect('/admin/product_form.php?id=' . $productId);
}
redirect('/admin/products.php');
