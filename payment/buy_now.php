<?php
/**
 * buy_now.php - Updated to include game_name in database and gateway
 */
include __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!$conn) {
    die("Critical Error: Database connection failed.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. SANITIZE & EXTRACT DATA
    $email        = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $user_id      = (int)($_SESSION['user_id'] ?? 0); // Internal DB user ID
    $game_user_id = htmlspecialchars($_POST['user_id'] ?? '', ENT_QUOTES, 'UTF-8');
    $game_zone_id = htmlspecialchars($_POST['zone_id'] ?? '', ENT_QUOTES, 'UTF-8');
    $product_id   = $_POST['product_id'] ?? ''; 
    $order_id     = 'ORD_' . strtoupper(bin2hex(random_bytes(4)));
    $pay_method   = htmlspecialchars($_POST['payment_method'] ?? 'UPI', ENT_QUOTES, 'UTF-8');
    
    // Capture the game_name from the POST request
    $game_name    = htmlspecialchars($_POST['game_name'] ?? 'Mobile Legends', ENT_QUOTES, 'UTF-8');

    if (empty($product_id) || empty($game_user_id)) {
        die("Error: Missing required order details.");
    }

    // 2. FETCH VERIFIED PRICE & NAME FROM DIAMONDS TABLE
    // This prevents price manipulation from the frontend.
    $correct_price = null;
    $correct_name  = null;

    $stmt_check = $conn->prepare("SELECT price, spu FROM diamonds WHERE product_id = ? LIMIT 1");
    $stmt_check->bind_param("s", $product_id);
    $stmt_check->execute();
    $stmt_check->bind_result($price_found, $spu_found);
    
    if ($stmt_check->fetch()) {
        $correct_price = $price_found;
        $correct_name  = $spu_found; // Mapping SPU to product_name
    }
    $stmt_check->close();
    
    if ($correct_price === null) {
        die("Error: Product not found in database.");
    }

    // 3. INSERT INTO ORDERS TABLE
    // Matches schema: [order_id, user_id, email, game_user_id, game_zone_id, product_id, product_name, game_name, price, payment_method, status]
    $status = 'pending'; 
    $stmt = $conn->prepare("INSERT INTO orders 
        (order_id, user_id, email, game_user_id, game_zone_id, product_id, product_name, game_name, price, payment_method, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Binding parameters including the new $game_name
    $stmt->bind_param("sissssssdss", 
        $order_id, 
        $user_id, 
        $email,
        $game_user_id, 
        $game_zone_id, 
        $product_id, 
        $correct_name, 
        $game_name, // game_name column
        $correct_price, 
        $pay_method,
        $status
    );
    
    if ($stmt->execute()) {
        $stmt->close();

        // 4. INITIATE GATEWAY REQUEST
        $gateway_url = PAY_API_URL;
        $gateway_token = PAY0_USER_TOKEN;

        if ($pay_method === 'jzpay') {
            $gateway_url = JZPAY_CREATE_URL;
            $gateway_token = JZPAY_TOKEN;
        }

        $post_data = [
            'customer_mobile' => !empty($email) ? $email : '9999999999', 
            'user_token'      => $gateway_token,
            'amount'          => $correct_price, 
            'order_id'        => $order_id,
            'redirect_url'    => BASE_URL . "/payment/pay_callback.php?orderId=$order_id",
            'remark1'         => $game_name . ' Topup', // Uses dynamic game name for gateway records
            'remark2'         => 'ID: ' . $game_user_id . ' (' . $correct_name . ')'
        ];

        if ($pay_method === 'jzpay') {
            // For JZPay, redirect_url should be success page or callback
            $post_data['redirect_url'] = BASE_URL . "/payment/receipt/" . $order_id;
        }

        $ch = curl_init($gateway_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $apiResponse = json_decode($response, true);
        curl_close($ch);

        if ($apiResponse && isset($apiResponse['status']) && ($apiResponse['status'] === true || $apiResponse['status'] === 'true')) {
            $paymentUrl = $apiResponse['result']['payment_url'] ?? null;
            if ($paymentUrl) {
                header("Location: $paymentUrl");
                exit;
            }
        }

        echo "<h2>Payment Gateway Error</h2>";
        echo "<p>" . htmlspecialchars($apiResponse['message'] ?? "Could not initiate payment.") . "</p>";

    } else {
        echo "<h2>Database Error: " . $conn->error . "</h2>";
    }
}
?>