<?php
/**
 * api/verify_order.php
 * Endpoint to manually verify order status against payment gateway
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../fulfillment_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$order_id = $_POST['orderId'] ?? null;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Missing Order ID']);
    exit;
}

// 1. Fetch Order from DB
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? LIMIT 1");
$stmt->bind_param("s", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

// If already completed, just return success
if ($order['status'] === 'completed') {
    echo json_encode(['success' => true, 'message' => 'Order is already completed', 'status' => 'completed']);
    exit;
}

$isPaid = false;
$gatewayStatus = 'Unknown';

if (strcasecmp($order['payment_method'], 'J-Coin') === 0) {
    // Wallet transactions are instantly paid internally, so we bypass gateway check
    $isPaid = true;
    $gatewayStatus = 'WALLET_PAID';
} else {
    // 2. Verify with Payment Gateway (Pay0)
    $pay0_postData = [
        "user_token" => PAY0_USER_TOKEN, 
        "order_id"   => $order_id
    ];

    $ch = curl_init(PAY_STATUS_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($pay0_postData));
    $response = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($response, true);
    
    // Log response for debugging if it fails
    if (!$responseData) {
        file_put_contents(__DIR__ . '/../payment/pay0_callback_log.txt', "[" . date('Y-m-d H:i:s') . "] Verify Order $order_id | Raw Response: " . $response . "\n", FILE_APPEND);
    }

    // Robust Payment Status Check
    $apiStatus = $responseData["status"] ?? false;
    $txnStatus = strtoupper($responseData["result"]["txnStatus"] ?? $responseData["result"]["status"] ?? '');

    $isPaid = ($apiStatus === true || strtoupper($apiStatus) === "SUCCESS" || strtoupper($apiStatus) === "COMPLETED") && 
              in_array($txnStatus, ["SUCCESS", "COMPLETED", "PAID"]);
    
    $gatewayStatus = $txnStatus ?: ($apiStatus ?: 'Unknown');
}

if ($isPaid) {
    // 3. Trigger Fulfillment
    $result = processFulfillment($order_id);
    echo json_encode($result);
} else {
    // Payment not confirmed by gateway yet
    echo json_encode([
        'success' => false, 
        'message' => 'Payment not yet confirmed by gateway.',
        'gateway_status' => $gatewayStatus
    ]);
}
