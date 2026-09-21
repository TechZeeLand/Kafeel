<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $c = coupon_find($id);
    if ($c) {
        $used = (int) db()->query('SELECT COUNT(*) FROM orders WHERE coupon_id = ' . (int) $id)->fetchColumn();
        // Orders that used it keep their code and the discount they got (coupon_id is set to NULL by the foreign key).
        db()->prepare('DELETE FROM coupons WHERE id = ?')->execute([$id]);
        admin_log('coupon.delete', 'Deleted coupon ' . $c['code'] . ' (' . coupon_describe($c) . ')' . ($used ? ', used on ' . $used . ' order(s)' : ''), 'coupon', $id,
            ['code' => $c['code'], 'discount' => coupon_describe($c), 'orders_that_used_it' => $used]);
        flash_set('success', 'Coupon ' . $c['code'] . ' deleted.');
    }
}
redirect('/admin/coupons.php');
