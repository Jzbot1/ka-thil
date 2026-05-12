<?php
/**
 * payment/process_jcoin_payment.php
 * Handles payments using J-Coin (User Wallet Balance)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../fulfillment_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. AUTH CHECK
if (!isset($_SESSION['user_id'])) {
    die("Error: Authentication required for Wallet payments.");
}

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ CSRF Protection Check
    $client_token = $_POST['csrf_token'] ?? '';
    if (empty($client_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $client_token)) {
        die("Error: Security verification failed (CSRF).");
    }

    // 2. SANITIZE INPUT
    $product_id   = $_POST['product_id'] ?? '';
    $game_user_id = $_POST['user_id'] ?? '';
    $game_zone_id = $_POST['zone_id'] ?? 'none';
    $game_name    = $_POST['game_name'] ?? 'Game';
    $email        = $_POST['email'] ?? '';
    $order_id     = 'ORD_' . strtoupper(bin2hex(random_bytes(4)));

    if (empty($product_id) || empty($game_user_id)) {
        die("Error: Missing order details.");
    }

    // 3. FETCH PRODUCT PRICE
    $stmt_p = $conn->prepare("SELECT price, spu FROM diamonds WHERE product_id = ? LIMIT 1");
    $stmt_p->bind_param("s", $product_id);
    $stmt_p->execute();
    $prod = $stmt_p->get_result()->fetch_assoc();
    $stmt_p->close();

    if (!$prod) {
        die("Error: Product not found.");
    }

    $price = (float)$prod['price'];
    $product_name = $prod['spu'];

    // 4. START TRANSACTION
    $conn->begin_transaction();

    try {
        // 5. CHECK & DEBIT BALANCE (Row Locking)
        $stmt_bal = $conn->prepare("SELECT wallet_balance, wallet_approved FROM users WHERE id = ? FOR UPDATE");
        $stmt_bal->bind_param("i", $user_id);
        $stmt_bal->execute();
        $user_row = $stmt_bal->get_result()->fetch_assoc();
        $stmt_bal->close();

        if (!$user_row) throw new Exception("User not found.");

        // Check Admin Approval
        if (!(int)$user_row['wallet_approved']) {
            throw new Exception("Your account is not approved for Wallet payments yet. Please contact admin.");
        }

        $current_balance = (float)($user_row['wallet_balance'] ?? 0);

        if ($current_balance < $price) {
            throw new Exception("Insufficient J-Coin balance. Please topup your wallet.");
        }

        $new_balance = $current_balance - $price;
        $stmt_upd = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
        $stmt_upd->bind_param("di", $new_balance, $user_id);
        $stmt_upd->execute();
        $stmt_upd->close();

        // 6. LOG WALLET TRANSACTION
        $desc = "Payment for $game_name ($product_name) - Order: $order_id";
        $stmt_log = $conn->prepare("INSERT INTO wallet_logs (user_id, order_id, type, amount, balance_before, balance_after, description) VALUES (?, ?, 'debit', ?, ?, ?, ?)");
        $stmt_log->bind_param("isddd s", $user_id, $order_id, $price, $current_balance, $new_balance, $desc);
        $stmt_log->execute();
        $stmt_log->close();

        // 7. CREATE ORDER (Mark as paid immediately)
        $status = 'pending'; // Will be changed to completed by fulfillment helper
        $pay_method = 'J-Coin';
        
        $stmt_order = $conn->prepare("INSERT INTO orders 
            (order_id, user_id, email, game_user_id, game_zone_id, product_id, product_name, game_name, price, payment_method, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt_order->bind_param("sissssssdss", 
            $order_id, 
            $user_id, 
            $email,
            $game_user_id, 
            $game_zone_id, 
            $product_id, 
            $product_name, 
            $game_name,
            $price, 
            $pay_method,
            $status
        );
        $stmt_order->execute();
        $stmt_order->close();

        // 8. COMMIT TRANSACTION BEFORE FULFILLMENT
        $conn->commit();

        // 9. TRIGGER FULFILLMENT
        // This will call SmileOne/MooGold and update status to 'completed'
        $fulfillment = processFulfillment($order_id);

        // 10. REDIRECT TO RECEIPT
        header("Location: ../payment/receipt/" . $order_id);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }
}