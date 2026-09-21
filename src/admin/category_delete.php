<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $ci = db()->prepare('SELECT name FROM categories WHERE id = ?');
    $ci->execute([$id]);
    $gone = $ci->fetchColumn();
    db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
    if ($gone !== false) admin_log('category.delete', 'Deleted category "' . $gone . '"', 'category', $id);
    flash_set('success', 'Category deleted.');
}
redirect('/admin/categories.php');
