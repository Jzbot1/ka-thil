<?php
/**
 * Secure Payment Callback & Fulfillment Script - Integrated with orders.sql and MooGold API
 */
ini_set('display_errors', 0); 
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once 'generate_sign.php';

// 1. GET DATA (Supports JSON and URL Parameters)
$json_raw = file_get_contents('php://input');
$json_data = json_decode($json_raw, true) ?? [];

$order_id = $_REQUEST['orderId'] ?? $json_data['orderId'] ?? null;
$token    = $_REQUEST['token'] ?? $json_data['token'] ?? null;

// Audit Logging
$log_entry = sprintf("[%s] Order: %s | Method: %s\n", date('Y-m-d H:i:s'), $order_id ?? 'N/A', $_SERVER['REQUEST_METHOD']);
file_put_contents('pay0_callback_log.txt', $log_entry, FILE_APPEND);

if (!$order_id) {
    http_response_code(400);
    die("Invalid request: Missing Order ID.");
}

try {
    // 2. FETCH ORDER
    $stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? LIMIT 1");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new Exception("Order record not found: " . $order_id);
    }

    // 3. SECURITY CHECK
    if ($token) {
        $expected_token = hash_hmac('sha256', $order_id . $order['price'], CALLBACK_SECRET);
        if (!hash_equals($expected_token, $token)) {
            throw new Exception("Security token mismatch.");
        }
    }

    // 4. CHECK IF ALREADY PROCESSED
    if ($order['status'] === 'completed') {
        header("Location: ../payment/receipt?orderId=" . urlencode($order_id));
        exit();
    }

    // 5. SERVER-SIDE STATUS VERIFICATION WITH GATEWAY (Pay0)
    $pay0_postData = ["user_token" => PAY0_USER_TOKEN, "order_id" => $order_id];
    $ch = curl_init(PAY_STATUS_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($pay0_postData));
    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    $isSuccess = ($responseData["status"] ?? false) === true || 
                 in_array(strtoupper($responseData["result"]["txnStatus"] ?? ''), ["SUCCESS", "COMPLETED", "PAID"]);

    if ($isSuccess) {
        require_once __DIR__ . '/../fulfillment_helper.php';
        processFulfillment($order_id);
    }

} catch (Exception $e) {
    file_put_contents('pay0_callback_log.txt', "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
}

// 9. REDIRECT
header("Location: ../payment/receipt/" . urlencode($order_id));
exit();