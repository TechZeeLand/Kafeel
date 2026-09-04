<?php
/**
 * Generates an order invoice as a PDF using mPDF. Nothing is cached to
 * disk — the PDF is rendered fresh from the database on every request, so
 * it always reflects the order's current status (and full status
 * timeline) automatically as it moves from pending -> ... -> completed.
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * @param array  $order   Row from `orders`.
 * @param array  $items   Rows from `order_items`.
 * @param array  $history Rows from order_status_history() (chronological).
 * @param string $mode    'I' = stream inline in the browser, 'D' = force download.
 */
function output_order_invoice(array $order, array $items, array $history, string $mode = 'I'): void {
    $rowsHtml = '';
    foreach ($items as $it) {
        $label = e($it['product_name']);
        if (!empty($it['variant_label'])) {
            $label .= '<br><span style="color:#8791a6;font-size:9px;">' . e($it['variant_label']) . '</span>';
        }
        $rowsHtml .= '<tr>'
            . '<td>' . $label . '</td>'
            . '<td style="text-align:right;">' . e(money($it['price'])) . '</td>'
            . '<td style="text-align:center;">' . (int) $it['quantity'] . '</td>'
            . '<td style="text-align:right;">' . e(money($it['subtotal'])) . '</td>'
            . '</tr>';
    }

    $statusLabels = ['pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    $timelineHtml = '';
    foreach ($history as $h) {
        $timelineHtml .= '<tr>'
            . '<td style="color:#8791a6;">' . e(date('d M Y, g:i A', strtotime($h['changed_at']))) . '</td>'
            . '<td><strong>' . e($statusLabels[$h['status']] ?? ucfirst($h['status'])) . '</strong>' . ($h['note'] ? ' — ' . e($h['note']) : '') . '</td>'
            . '</tr>';
    }
    if (!$timelineHtml) {
        $timelineHtml = '<tr><td colspan="2" style="color:#8791a6;">No status history recorded yet.</td></tr>';
    }

    $address = e($order['shipping_line1']) . ', ' . e($order['shipping_city'])
        . ($order['shipping_state'] ? ', ' . e($order['shipping_state']) : '')
        . ($order['shipping_zip'] ? ' ' . e($order['shipping_zip']) : '');

    $html = '<html><head><style>
        body { font-family: sans-serif; font-size: 11px; color: #20293b; }
        h1 { font-size: 20px; margin-bottom: 0; }
        .muted { color: #8791a6; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { text-align: left; background: #efece2; padding: 6px 8px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; }
        td { padding: 6px 8px; border-bottom: 1px solid #e6e2d3; }
        .totals td { border: none; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 10px; background: #e9d5a8; color: #8a6427; font-weight: bold; font-size: 10px; }
        .section-title { font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #a97c34; margin: 18px 0 4px; }
    </style></head><body>
    <table style="border:none;"><tr style="border:none;">
        <td style="border:none;width:60%;"><h1>' . e(SITE_NAME) . '</h1><div class="muted">Invoice</div></td>
        <td style="border:none;text-align:right;">
            <div><strong>Invoice / Order #' . e($order['order_number']) . '</strong></div>
            <div class="muted">Placed ' . e(date('d M Y', strtotime($order['created_at']))) . '</div>
            <div class="muted">Payment: Cash on delivery</div>
            <div style="margin-top:6px;"><span class="status-badge">' . e($statusLabels[$order['status']] ?? ucfirst($order['status'])) . '</span></div>
        </td>
    </tr></table>

    <div class="section-title">Ship to</div>
    <div><strong>' . e($order['shipping_name']) . '</strong> &middot; ' . e($order['shipping_phone']) . '</div>
    <div>' . $address . '</div>

    <div class="section-title">Items</div>
    <table>
        <tr><th>Item</th><th style="text-align:right;">Price</th><th style="text-align:center;">Qty</th><th style="text-align:right;">Subtotal</th></tr>
        ' . $rowsHtml . '
    </table>
    <table class="totals" style="width:280px;margin-left:auto;">
        <tr><td>Subtotal</td><td style="text-align:right;">' . e(money($order['subtotal'])) . '</td></tr>
        <tr><td>Shipping (' . e(delivery_area_label($order['delivery_area'])) . ')</td><td style="text-align:right;">' . e($order['shipping_fee'] > 0 ? money($order['shipping_fee']) : 'Free') . '</td></tr>
        <tr><td><strong>Total</strong></td><td style="text-align:right;"><strong>' . e(money($order['total'])) . '</strong></td></tr>
    </table>

    <div class="section-title">Order status timeline</div>
    <table>' . $timelineHtml . '</table>

    <div style="margin-top:26px;font-size:9px;" class="muted">' . e(SITE_NAME) . ' &middot; ' . e(CONTACT_EMAIL) . ' &middot; Delivered in ' . (int) DELIVERY_DAYS_MIN . '&ndash;' . (int) DELIVERY_DAYS_MAX . ' days &middot; Cash on delivery</div>
    </body></html>';

    $mpdf = new Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'A4', 'margin_top' => 16, 'margin_bottom' => 16]);
    $mpdf->SetTitle('Invoice ' . $order['order_number']);
    $mpdf->WriteHTML($html);
    $mpdf->Output('invoice-' . $order['order_number'] . '.pdf', $mode === 'D' ? Destination::DOWNLOAD : Destination::INLINE);
}
