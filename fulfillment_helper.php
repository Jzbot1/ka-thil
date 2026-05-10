<?php
/**
 * fulfillment_helper.php
 * Reusable logic for automated topup fulfillment via SmileOne, MooGold, etc.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/payment/generate_sign.php';
require_once __DIR__ . '/includes/notifications/telegram_notify.php';
require_once __DIR__ . '/includes/notifications/mail_helper.php';

/**
 * Trigger fulfillment based on the order data and provider
 */
function processFulfillment($order_id) {
    global $conn;

    // ✅ 1. START TRANSACTION & LOCK ROW
    // This prevents double fulfillment if pay_callback and verify_order run at once.
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("SELECT o.*, g.provider, d.region, d.smileone_game, d.moogold_category, u.username
            FROM orders o 
            LEFT JOIN diamonds d ON o.product_id = d.product_id 
            LEFT JOIN games g ON d.game_id = g.id 
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.order_id = ? LIMIT 1 FOR UPDATE");
        
        $stmt->bind_param("s", $order_id);
        $stmt->execute();
        $order_full = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order_full) {
            $conn->rollback();
            return ['success' => false, 'message' => 'Order not found'];
        }

        // ✅ 2. DOUBLE-CHECK STATUS
        // If it's already completed, do nothing and return.
        if ($order_full['status'] === 'completed') {
            $conn->rollback();
            return ['success' => true, 'status' => 'completed', 'message' => 'Order already processed'];
        }

    $provider    = $order_full['provider'] ?? 'manual';
    $region      = strtolower($order_full['region'] ?? 'br');
    $smile_game  = $order_full['smileone_game'] ?? 'mobilelegends';
    $moo_category = $order_full['moogold_category'] ?? '';
    
    $fulfillment_success = false;
    $provider_order_id   = null;

    // 2. Provider-Specific Fulfillment
    if ($provider === 'smileone') {
        $smile_params = [
            'email'     => SMILE_API_EMAIL,
            'uid'       => SMILE_API_UID,
            'userid'    => $order_full['game_user_id'],
            'zoneid'    => ($order_full['game_zone_id'] === 'none') ? '' : $order_full['game_zone_id'],
            'product'   => $smile_game,
            'productid' => $order_full['product_id'],
            'time'      => time()
        ];

        if (function_exists('generateSign')) {
            $smile_params['sign'] = generateSign($smile_params, SMILE_API_KEY);
        }

        $api_url = ($region === 'ph') ? 'https://www.smile.one/ph/smilecoin/api/createorder' : 'https://www.smile.one/br/smilecoin/api/createorder';

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($smile_params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res_raw = curl_exec($ch);
        $res = json_decode($res_raw, true);
        curl_close($ch);

        if (isset($res['status']) && $res['status'] == 200) {
            $fulfillment_success = true;
            $provider_order_id   = $res['orderid'] ?? null; // SmileOne uses 'orderid'
        } else {
            file_put_contents(__DIR__ . '/fulfillment_error_log.txt', "Order $order_id | Smile API Fail: $res_raw\n", FILE_APPEND);
        }

    } elseif ($provider === 'moogold') {
        // ... (MooGold logic)
        $timestamp = time();
        $path = 'order/create_order';
        $moo_payload = [
            'path'       => $path,
            'category'   => $moo_category,
            'product-id' => $order_full['product_id'],
            'quantity'   => 1,
            'User ID'    => $order_full['game_user_id'],
            'Zone ID'    => ($order_full['game_zone_id'] === 'none' || $order_full['game_zone_id'] === '') ? '' : $order_full['game_zone_id']
        ];
        $json_payload = json_encode($moo_payload);
        $string_to_sign = $json_payload . $timestamp . $path;
        $auth_signature = hash_hmac('sha256', $string_to_sign, MOOGOLD_SECRET_KEY);
        $basic_auth = base64_encode(MOOGOLD_PARTNER_ID . ':' . MOOGOLD_SECRET_KEY);

        $ch = curl_init(MOOGOLD_BASE_URL . '/' . $path);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . $basic_auth,
            'auth: ' . $auth_signature,
            'timestamp: ' . $timestamp
        ]);
        $res_raw = curl_exec($ch);
        $res = json_decode($res_raw, true);
        curl_close($ch);

        if (isset($res['order_id'])) {
            $fulfillment_success = true;
            $provider_order_id   = $res['order_id']; // MooGold uses 'order_id'
        } else {
            file_put_contents(__DIR__ . '/fulfillment_error_log.txt', "Order $order_id | MooGold Fail: $res_raw\n", FILE_APPEND);
        }

    } elseif ($order_full['product_id'] === 'WALLET_TOPUP') {
        // ✅ WALLET TOPUP FULFILLMENT (SECURED)
        $topup_amount = (float)$order_full['price'];
        $target_user  = (int)$order_full['user_id'];
        
        if ($topup_amount <= 0) throw new Exception("Invalid wallet amount.");

        // 1. Get current balance for audit log
        $stmt_bal = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
        $stmt_bal->bind_param("i", $target_user);
        $stmt_bal->execute();
        $user_row = $stmt_bal->get_result()->fetch_assoc();
        $stmt_bal->close();

        $balance_before = (float)($user_row['wallet_balance'] ?? 0);
        $balance_after  = $balance_before + $topup_amount;

        // 2. Update user balance
        $stmt_wallet = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
        $stmt_wallet->bind_param("di", $balance_after, $target_user);
        
        if ($stmt_wallet->execute()) {
            // 3. Create Audit Log Entry
            $desc = "Wallet topup via " . $order_full['payment_method'] . " (Order: $order_id)";
            $stmt_log = $conn->prepare("INSERT INTO wallet_logs (user_id, order_id, type, amount, balance_before, balance_after, description) VALUES (?, ?, 'credit', ?, ?, ?, ?)");
            $stmt_log->bind_param("isddds", $target_user, $order_id, $topup_amount, $balance_before, $balance_after, $desc);
            $stmt_log->execute();
            $stmt_log->close();

            $fulfillment_success = true;
        }
        $stmt_wallet->close();

    } else {
        // Manual or unknown provider
        $fulfillment_success = true; 
    }

    // 3. Update Database Status
    $finalStatus = $fulfillment_success ? 'completed' : 'failed';
    $stmt_upd = $conn->prepare("UPDATE orders SET status = ?, provider_id = ? WHERE order_id = ?");
    $stmt_upd->bind_param("sss", $finalStatus, $provider_order_id, $order_id);
    $stmt_upd->execute();
    $stmt_upd->close();

    // 5. COMMIT TRANSACTION
    $conn->commit();

    // 6. TELEGRAM NOTIFICATION (On Success)
    if ($fulfillment_success) {
        $msg = "🔔 *NEW ORDER COMPLETED*\n\n";
        $msg .= "🆔 Order ID: `" . $order_id . "`\n";
        $msg .= "👤 User: " . ($order_full['username'] ?? 'User ID: ' . $order_full['user_id']) . "\n";
        $msg .= "🎮 Game: " . ($order_full['game_name'] ?? 'N/A') . "\n";
        $msg .= "💎 Item: " . $order_full['product_name'] . "\n";
        $msg .= "💰 Price: ₹" . $order_full['price'] . "\n";
        $msg .= "🔑 Game ID: `" . $order_full['game_user_id'] . "`\n";
        if (!empty($order_full['game_zone_id'])) $msg .= "🌐 Zone: `" . $order_full['game_zone_id'] . "`\n";
        $msg .= "🏦 Method: " . $order_full['payment_method'] . "\n";
        $msg .= "🚀 Status: *INSTANT*";
        
        sendTelegramNotification($msg);
    }

    // 7. EMAIL INVOICE (If enabled)
    if ($fulfillment_success) {
        sendOrderInvoice($order_id);
    }

    return [
        'success' => $fulfillment_success,
        'status'  => $finalStatus,
        'message' => $fulfillment_success ? 'Fulfillment completed' : 'Fulfillment failed (Logged)'
    ];

    } catch (Throwable $e) {
        if (isset($conn) && $conn->in_transaction) $conn->rollback();
        file_put_contents(__DIR__ . '/fulfillment_error_log.txt', "Order $order_id | CRITICAL ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        return ['success' => false, 'message' => 'Critical error during fulfillment'];
    }
}
