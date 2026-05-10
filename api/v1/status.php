<?php
require_once __DIR__ . '/api_core.php';

$reseller = authenticate_api();

$order_id = $_POST['order_id'] ?? $_GET['order_id'] ?? '';

if (empty($order_id)) {
    send_api_response(false, "Missing order_id");
}

$stmt = $conn->prepare("SELECT order_id, status, product_name, price, created_at, provider_id FROM orders WHERE order_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("si", $order_id, $reseller['id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    send_api_response(false, "Order not found or does not belong to this account");
}

send_api_response(true, "Order status retrieved", [
    'order_id' => $order['order_id'],
    'status' => $order['status'],
    'product' => $order['product_name'],
    'price' => (float)$order['price'],
    'provider_reference' => $order['provider_id'] ?? null,
    'date' => $order['created_at']
]);
?>
