<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/invoice.php';
require_login();

$user = current_user();
$orderNumber = $_GET['order'] ?? '';
$stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ? AND user_id = ?');
$stmt->execute([$orderNumber, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
$itemsStmt->execute([$order['id']]);
$items = $itemsStmt->fetchAll();

$history = order_status_history($order['id']);
output_order_invoice($order, $items, $history, 'I');
