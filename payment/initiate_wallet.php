<?php
/**
 * payment/initiate_wallet.php
 * Handles initiation of wallet topup payments via Pay0
 */
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die("Error: Authentication required.");
}

// ✅ CSRF Protection Check
$client_token = $_POST['csrf_token'] ?? '';
if (empty($client_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $client_token)) {
    die("Error: Security verification failed (CSRF).");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $user_id = (int)$_SESSION['user_id'];
    $email = $_SESSION['username'] ?? 'User'; // Or use actual email if available

    if (!$amount || $amount < 10) {
        die("Error: Invalid amount. Minimum ₹10.");
    }

    $order_id = 'WAL_' . strtoupper(bin2hex(random_bytes(4)));
    $status = 'pending';
    $product_id = 'WALLET_TOPUP';
    $product_name = 'Wallet Credit (₹' . $amount . ')';
    $game_name = 'System Wallet';
    $pay_method = 'UPI';

    // 1. INSERT INTO ORDERS TABLE
    $stmt = $conn->prepare("INSERT INTO orders 
        (order_id, user_id, email, game_user_id, game_zone_id, product_id, product_name, game_name, price, payment_method, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $game_user_id = (string)$user_id; // Store local user_id in game_user_id for wallet
    $game_zone_id = 'INTERNAL';

    $stmt->bind_param("sissssssdss", 
        $order_id, 
        $user_id, 
        $email,
        $game_user_id, 
        $game_zone_id, 
        $product_id, 
        $product_name, 
        $game_name,
        $amount, 
        $pay_method,
        $status
    );
    
    if ($stmt->execute()) {
        $stmt->close();

        // 2. INITIATE GATEWAY REQUEST
        $post_data = [
            'customer_mobile' => '9999999999', 
            'user_token'      => PAY0_USER_TOKEN,
            'amount'          => $amount, 
            'order_id'        => $order_id,
            'redirect_url'    => BASE_URL . "/payment/pay_callback.php?orderId=$order_id",
            'remark1'         => 'Wallet Topup',
            'remark2'         => 'User: ' . $email
        ];

        $ch = curl_init(PAY_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $apiResponse = json_decode($response, true);
        curl_close($ch);

        if ($apiResponse && isset($apiResponse['status']) && $apiResponse['status'] === true) {
            $paymentUrl = $apiResponse['result']['payment_url'] ?? null;
            if ($paymentUrl) {
                header("Location: $paymentUrl");
                exit;
            }
        }

        die("Gateway Error: " . ($apiResponse['message'] ?? "Could not initiate payment."));

    } else {
        die("Database Error: " . $conn->error);
    }
}
