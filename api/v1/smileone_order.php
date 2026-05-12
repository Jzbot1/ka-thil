<?php
/**
 * api/v1/smileone_order.php
 * Endpoint for partners to create orders fulfilled specifically via SmileOne API.
 */

require_once __DIR__ . '/api_core.php';
require_once dirname(dirname(__DIR__)) . '/fulfillment_helper.php';

// 1. Authenticate Request
$reseller = authenticate_api();

// 2. Get Input Data
$product_id       = $_POST['product_id'] ?? '';
$game_user_id     = $_POST['game_user_id'] ?? '';
$game_zone_id     = $_POST['game_zone_id'] ?? 'none';
$partner_order_id = $_POST['partner_order_id'] ?? '';

if (empty($product_id) || empty($game_user_id)) {
    send_api_response(false, "Missing required fields: product_id, game_user_id");
}

// 3. Fetch Product Details
$stmt = $conn->prepare("SELECT d.*, g.name as game_name, g.provider 
                        FROM diamonds d 
                        JOIN games g ON d.game_id = g.id 
                        WHERE d.product_id = ? LIMIT 1");
$stmt->bind_param("s", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    send_api_response(false, "Invalid product_id.");
}

if ($product['provider'] !== 'smileone') {
    send_api_response(false, "This product is not fulfilled via SmileOne.");
}

$price = (float)($product['reseller_price'] > 0 ? $product['reseller_price'] : $product['price']);

// 4. Transaction Start
$conn->begin_transaction();

try {
    // Check & Lock Balance
    $stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $reseller['id']);
    $stmt->execute();
    $current_balance = (float)$stmt->get_result()->fetch_assoc()['wallet_balance'];
    $stmt->close();

    if ($current_balance < $price) {
        $conn->rollback();
        send_api_response(false, "Insufficient balance. Required: $price, Available: $current_balance");
    }

    // Deduct Balance
    $new_balance = $current_balance - $price;
    $stmt = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
    $stmt->bind_param("di", $new_balance, $reseller['id']);
    $stmt->execute();
    $stmt->close();

    // 5. Create System Order
    $system_order_id = 'SMILE_' . strtoupper(uniqid());
    $status = 'processing';
    
    $stmt = $conn->prepare("INSERT INTO orders 
        (order_id, user_id, email, game_user_id, game_zone_id, product_id, product_name, game_name, price, payment_method, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'API_WALLET', ?)");
    $stmt->bind_param("sissssssds", 
        $system_order_id, 
        $reseller['id'], 
        $reseller['email'], 
        $game_user_id, 
        $game_zone_id, 
        $product_id, 
        $product['spu'], 
        $product['game_name'], 
        $price, 
        $status
    );
    $stmt->execute();
    $stmt->close();

    // Log Wallet Transaction
    $desc = "SmileOne API Order: $system_order_id (" . $product['spu'] . ")";
    $stmt = $conn->prepare("INSERT INTO wallet_logs (user_id, order_id, type, amount, balance_before, balance_after, description) VALUES (?, ?, 'debit', ?, ?, ?, ?)");
    $stmt->bind_param("isddds", $reseller['id'], $system_order_id, $price, $current_balance, $new_balance, $desc);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // 6. Send Immediate Telegram Notification
    $msg = "🆕 <b>SMILEONE ORDER RECEIVED</b>\n\n";
    $msg .= "🆔 ID: <code>$system_order_id</code>\n";
    $msg .= "👤 User: " . htmlspecialchars($reseller['email']) . "\n";
    $msg .= "🎮 Game: " . htmlspecialchars($product['game_name']) . "\n";
    $msg .= "💎 Item: " . htmlspecialchars($product['spu']) . "\n";
    $msg .= "🔑 UID: <code>$game_user_id ($game_zone_id)</code>\n";
    $msg .= "💰 Price: ₹$price\n";
    $msg .= "⏳ Status: <i>Processing...</i>";
    sendTelegramNotification($msg);

    // 7. Trigger Fulfillment
    $fulfillment_result = processFulfillment($system_order_id);

    send_api_response(true, "SmileOne order created and processing", [
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
