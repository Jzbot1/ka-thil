<?php
/**
 * payment/process_card_payment.php
 * Handles payments using Internal Virtual Debit Card
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../fulfillment_helper.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// 1. AUTH CHECK
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['ok' => false, 'err' => 'Authentication required']));
}

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // 2. SANITIZE & PARSE INPUT
    $card_num    = trim($_POST['card_num'] ?? '');
    $card_expiry = trim($_POST['card_expiry'] ?? '');
    $card_cvv    = trim($_POST['card_cvv'] ?? '');
    $card_pin    = trim($_POST['card_pin'] ?? '');
    
    $product_id   = $_POST['product_id'] ?? '';
    $game_user_id = $_POST['user_id'] ?? '';
    $game_zone_id = $_POST['zone_id'] ?? 'none';
    $game_name    = $_POST['game_name'] ?? 'Game';
    $email        = $_POST['email'] ?? '';
    $order_id     = 'ORD_' . strtoupper(bin2hex(random_bytes(4)));

    if (empty($card_num) || empty($card_expiry) || empty($card_cvv) || empty($card_pin)) {
        echo json_encode(['ok' => false, 'err' => 'Missing card details']); exit;
    }

    // Parse Expiry (MM/YY)
    if (!preg_match('/^(\d{2})\/(\d{2})$/', $card_expiry, $m)) {
        echo json_encode(['ok' => false, 'err' => 'Invalid expiry format (MM/YY)']); exit;
    }
    $expiry_month = (int)$m[1];
    $expiry_year  = 2000 + (int)$m[2];

    // 3. FETCH PRODUCT PRICE
    $stmt_p = $conn->prepare("SELECT price, spu FROM diamonds WHERE product_id = ? LIMIT 1");
    $stmt_p->bind_param("s", $product_id);
    $stmt_p->execute();
    $prod = $stmt_p->get_result()->fetch_assoc();
    if (!$prod) { echo json_encode(['ok' => false, 'err' => 'Product not found']); exit; }
    $price = (float)$prod['price'];
    $product_name = $prod['spu'];

    // 4. VALIDATE CARD
    $vcs = new VirtualCardSystem($conn);
    $validation = $vcs->validateCardPayment($card_num, $expiry_month, $expiry_year, $card_cvv, $card_pin, $price);
    
    if (!$validation['ok']) {
        echo json_encode(['ok' => false, 'err' => $validation['err']]); exit;
    }
    $card = $validation['card'];

    // 5. PROCESS PAYMENT
    if ($vcs->processTransaction($card['id'], $user_id, $price, "JZStore - $product_name")) {
        // 6. CREATE ORDER
        $status = 'pending';
        $pay_method = 'Wallet Card';
        
        $stmt_order = $conn->prepare("INSERT INTO orders 
            (order_id, user_id, email, game_user_id, game_zone_id, product_id, product_name, game_name, price, payment_method, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt_order->bind_param("sissssssdss", 
            $order_id, $user_id, $email, $game_user_id, $game_zone_id, $product_id, $product_name, $game_name, $price, $pay_method, $status
        );
        $stmt_order->execute();
        $stmt_order->close();

        // 7. TRIGGER FULFILLMENT
        processFulfillment($order_id);

        echo json_encode(['ok' => true, 'redirect' => BASE_URL . "/payment/receipt/" . $order_id]);
    } else {
        echo json_encode(['ok' => false, 'err' => 'Transaction failed. Please try again.']);
    }
}
