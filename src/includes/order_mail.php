<?php
/**
 * Order-related emails: confirmation when an order is placed, and the status
 * update sent when the shop moves an order along. Both work for guests too,
 * as long as they gave an email at checkout. Never throws.
 */
require_once __DIR__ . '/mail.php';

const ORDER_STATUS_LABELS = ['pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

/** The email to reach the customer on: what they typed at checkout, else their account's. */
function order_customer_email(array $order): ?string {
    if (!empty($order['customer_email'])) return $order['customer_email'];
    if (!empty($order['user_id'])) {
        $s = db()->prepare('SELECT email FROM users WHERE id = ?');
        $s->execute([$order['user_id']]);
        $e = $s->fetchColumn();
        if ($e) return $e;
    }
    return null;
}

/** Full https link for use inside an email (a relative "/order/…" does not work in a mail client). */
function mail_url(string $path): string {
    return preg_match('~^https?://~i', $path) ? $path : mail_base_url() . '/' . ltrim($path, '/');
}

/** For a guest order: a nudge to create an account (or log in) with the same email, which pulls this order into it. */
function order_guest_cta(array $order, string $email): string {
    if (!empty($order['user_id'])) return '';
    $q = db()->prepare('SELECT 1 FROM users WHERE email = ?');
    $q->execute([strtolower($email)]);
    $hasAccount = (bool) $q->fetchColumn();
    return '<p style="color:#4a5670;font-size:14px;margin-top:18px;">You checked out as a guest. '
        . ($hasAccount ? 'Log in' : 'Create an account') . ' with <strong>' . e($email)
        . '</strong> and this order is added to your account automatically, so you can track it. Or track it any time without an account at <a href="' . e(mail_url('/orders')) . '">' . e(mail_url('/orders')) . '</a> with your order number and this email.</p>'
        . order_email_button($hasAccount ? mail_url('/login') : mail_url('/register?email=' . rawurlencode($email)), $hasAccount ? 'Log in' : 'Create your account');
}

function order_email_button(string $href, string $label): string {
    $bg = theme_settings()['primary'];
    return '<p style="margin:20px 0;"><a href="' . e($href) . '" style="background:' . e($bg) . ';color:' . e(contrast_text($bg))
        . ';padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;">' . e($label) . '</a></p>';
}

function send_order_confirmation(int $orderId): void {
    try {
        $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) return;
        $to = order_customer_email($order);
        if (!$to) return;

        $itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $itemsStmt->execute([$orderId]);
        $rows = '';
        foreach ($itemsStmt->fetchAll() as $it) {
            $rows .= '<tr><td style="padding:6px 0;border-bottom:1px solid #eee;">' . e($it['product_name'])
                . ($it['variant_label'] ? '<br><span style="color:#8791a6;font-size:12px;">' . e($it['variant_label']) . '</span>' : '')
                . '</td><td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:center;">× ' . (int) $it['quantity']
                . '</td><td style="padding:6px 0;border-bottom:1px solid #eee;text-align:right;">' . e(money((float) $it['subtotal'])) . '</td></tr>';
        }
        $body = '<p>Hi ' . e(explode(' ', $order['shipping_name'])[0]) . ',</p>'
            . '<p>Thank you for your order! We\'ve received it and will get it ready. Your order number is <strong>#' . e($order['order_number']) . '</strong>.</p>'
            . '<table style="width:100%;border-collapse:collapse;font-size:14px;">' . $rows . '</table>'
            . '<p style="text-align:right;margin:10px 0 0;">'
            . (((float) ($order['discount'] ?? 0)) > 0 ? 'Discount' . (!empty($order['coupon_code']) ? ' (' . e($order['coupon_code']) . ')' : '') . ': &minus;' . e(money((float) $order['discount'])) . '<br>' : '')
            . 'Shipping (' . e(delivery_area_label($order['delivery_area'])) . '): ' . e(money((float) $order['shipping_fee'])) . '<br>'
            . '<strong>Total to pay on delivery: ' . e(money((float) $order['total'])) . '</strong></p>'
            . '<p style="color:#4a5670;font-size:14px;">Delivering to: ' . e($order['shipping_name']) . ', ' . e($order['shipping_line1']) . ', ' . e($order['shipping_city']) . '</p>'
            . (empty($order['billing_same_as_shipping']) ? '<p style="color:#4a5670;font-size:14px;">Billing to: ' . e($order['billing_name']) . ', ' . e($order['billing_line1']) . ', ' . e($order['billing_city']) . '</p>' : '')
            . (!empty($order['user_id']) ? order_email_button(mail_url(order_url($order['order_number'])), 'View your order') : order_guest_cta($order, $to));
        send_email($to, $order['shipping_name'], 'Order #' . $order['order_number'] . ' confirmed', email_wrap('Thanks for your order', $body), null, null, 'order');
    } catch (Throwable $e) {
        error_log('[order_mail] confirmation failed: ' . $e->getMessage());
    }
}

function send_order_status_email(array $order, string $newStatus, ?string $note): void {
    try {
        $to = order_customer_email($order);
        if (!$to) return;
        $label = ORDER_STATUS_LABELS[$newStatus] ?? ucfirst($newStatus);
        $body = '<p>Hi ' . e(explode(' ', $order['shipping_name'])[0]) . ',</p>'
            . '<p>Your order <strong>#' . e($order['order_number']) . '</strong> is now <strong>' . e($label) . '</strong>.</p>'
            . ($note ? '<p>' . e($note) . '</p>' : '')
            . (!empty($order['user_id']) ? order_email_button(mail_url(order_url($order['order_number'])), 'Track your order') : order_guest_cta($order, $to));
        send_email($to, $order['shipping_name'], 'Order #' . $order['order_number'] . ' — ' . $label, email_wrap('Order update', $body), null, null, 'order-status');
    } catch (Throwable $e) {
        error_log('[order_mail] status email failed: ' . $e->getMessage());
    }
}
