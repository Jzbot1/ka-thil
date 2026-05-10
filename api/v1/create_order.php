<?php
require_once __DIR__ . '/api_core.php';

$reseller = authenticate_api();

$product_id = $_POST['product_id'] ?? $_GET['product_id'] ?? '';
$game_user_id = $_POST['game_user_id'] ?? $_GET['game_user_id'] ?? '';
$game_zone_id = $_POST['game_zone_id'] ?? $_GET['game_zone_id'] ?? 'none';
$partner_order_id = $_POST['partner_order_id'] ?? $_GET['partner_order_id'] ?? '';

if (empty($product_id) || empty($game_user_id)) {
    send_api_response(false, "Missing required fields: product_id, game_user_id");
}

// 1. Get Product Details
$stmt = $conn->prepare("SELECT d.*, g.title as game_name FROM diamonds d JOIN games g ON d.game_id = g.id WHERE d.product_id = ? LIMIT 1");
$stmt->bind_param("s", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) send_api_response(false, "Invalid product_id");

$price = (float)($product['reseller_price'] > 0 ? $product['reseller_price'] : $product['original_price']);

// 2. Transaction Start
$conn->begin_transaction();

try {
    // 3. Check Balance with Lock
    $stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $reseller['id']);
    $stmt->execute();
    $current_balance = (float)$stmt->get_result()->fetch_assoc()['wallet_balance'];
    $stmt->close();

    if ($current_balance < $price) {
        $conn->rollback();
        send_api_response(false, "Insufficient wallet balance. Price: $price, Balance: $current_balance");
    }

    // 4. Deduct Balance
    $new_balance = $current_balance - $price;
    $stmt = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
    $stmt->bind_param("di", $new_balance, $reseller['id']);
    $stmt->execute();
    $stmt->close();

    // 5. Create Order
    $system_order_id = 'API_' . strtoupper(uniqid());
    $status = 'processing';
    
    $stmt = $conn->prepare("INSERT INTO orders (order_id, user_id, email, game_user_id, game_zone_id, product_id, product_name, game_name, price, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'API_WALLET', ?)");
    $stmt->bind_param("sissssssds", $system_order_id, $reseller['id'], $reseller['email'], $game_user_id, $game_zone_id, $product_id, $product['name'], $product['game_name'], $price, $status);
    $stmt->execute();
    $stmt->close();

    // 6. Log Wallet Transaction
    $desc = "API Order Checkout: $system_order_id ($product[name])";
    $stmt = $conn->prepare("INSERT INTO wallet_logs (user_id, order_id, type, amount, balance_before, balance_after, description) VALUES (?, ?, 'debit', ?, ?, ?, ?)");
    $stmt->bind_param("isddds", $reseller['id'], $system_order_id, $price, $current_balance, $new_balance, $desc);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // 7. Trigger Fulfillment
    require_once dirname(dirname(__DIR__)) . '/fulfillment_helper.php';
    $fulfillment_result = processFulfillment($system_order_id);

    send_api_response(true, "Order created successfully", [
        'order_id' => $system_order_id,
        'partner_order_id' => $partner_order_id,
        'status' => $fulfillment_result['status'] ?? 'processing',
        'price_deducted' => $price,
        'remaining_balance' => $new_balance
    ]);

} catch (Exception $e) {
    $conn->rollback();
    send_api_response(false, "System error: " . $e->getMessage());
}
?>
