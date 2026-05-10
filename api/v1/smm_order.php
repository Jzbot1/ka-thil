<?php
/**
 * api/v1/smm_order.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Place an SMM order using reseller wallet balance.
 *
 * Auth: partner_id + secret
 *
 * Request (POST):
 *   partner_id        = your partner ID
 *   secret            = your secret key
 *   service_id        = smm_services.id (get from smm_services.php)
 *   link              = target URL (Instagram post, YouTube video, etc.)
 *   quantity          = number of units
 *   partner_order_id  = (optional) your own reference ID
 *   runs              = (optional) drip-feed runs
 *   interval          = (optional) drip-feed interval in minutes
 *
 * Response:
 *   { status: true, data: { order_ref, partner_order_id, status, price_deducted, remaining_balance } }
 */
require_once __DIR__ . '/api_core.php';

$reseller = authenticate_api();

// ── Input ───────────────────────────────────────────────────────────────────
$service_id       = (int)  ($_POST['service_id']       ?? $_GET['service_id']       ?? 0);
$link             = trim(   $_POST['link']              ?? $_GET['link']              ?? '');
$quantity         = (int)  ($_POST['quantity']          ?? $_GET['quantity']          ?? 0);
$partner_order_id = trim(   $_POST['partner_order_id']  ?? $_GET['partner_order_id']  ?? '');
$runs             = (int)  ($_POST['runs']              ?? $_GET['runs']              ?? 0);
$interval         = (int)  ($_POST['interval']          ?? $_GET['interval']          ?? 0);

if (!$service_id)                          send_api_response(false, "Missing: service_id");
if (!$link)                                send_api_response(false, "Missing: link");
if (!filter_var($link, FILTER_VALIDATE_URL)) send_api_response(false, "Invalid URL in 'link' field.");
if ($quantity < 1)                         send_api_response(false, "Missing or invalid: quantity");

// ── Check SMM tables exist ──────────────────────────────────────────────────
$tbl = $conn->query("SHOW TABLES LIKE 'smm_services'");
if (!$tbl || $tbl->num_rows === 0) {
    send_api_response(false, "SMM service unavailable. Contact admin.");
}

// ── Load service ────────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT id, provider_id,
                               COALESCE(custom_name, original_name) AS svc_name,
                               COALESCE(custom_price, ROUND(original_rate * 85 * 1.3, 2)) AS sell_price,
                               min_order, max_order, is_active
                        FROM smm_services WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$svc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$svc)              send_api_response(false, "Service not found.");
if (!$svc['is_active']) send_api_response(false, "This service is currently unavailable.");
if ($quantity < $svc['min_order'] || $quantity > $svc['max_order']) {
    send_api_response(false, "Quantity must be between {$svc['min_order']} and {$svc['max_order']}.");
}

// ── Price calculation ───────────────────────────────────────────────────────
$unit_price  = (float)$svc['sell_price'];   // per 1000
$total_price = round(($quantity / 1000) * $unit_price, 2);
if ($total_price <= 0) send_api_response(false, "Price calculation error.");

$order_ref = 'RSMM_' . strtoupper(bin2hex(random_bytes(5)));

// ── Transaction ─────────────────────────────────────────────────────────────
$conn->begin_transaction();

try {
    // Lock reseller row & check balance
    $stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $reseller['id']);
    $stmt->execute();
    $current_balance = (float)$stmt->get_result()->fetch_assoc()['wallet_balance'];
    $stmt->close();

    if ($current_balance < $total_price) {
        $conn->rollback();
        send_api_response(false, "Insufficient wallet balance. Required: ₹{$total_price}, Available: ₹{$current_balance}");
    }

    // Deduct balance
    $new_balance = round($current_balance - $total_price, 2);
    $stmt = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
    $stmt->bind_param("di", $new_balance, $reseller['id']);
    $stmt->execute();
    $stmt->close();

    // Wallet log
    $desc = "API SMM Order: {$svc['svc_name']} x{$quantity} [{$order_ref}]";
    $stmt = $conn->prepare("INSERT INTO wallet_logs (user_id, order_id, type, amount, balance_before, balance_after, description) VALUES (?, ?, 'debit', ?, ?, ?, ?)");
    $stmt->bind_param("isddds", $reseller['id'], $order_ref, $total_price, $current_balance, $new_balance, $desc);
    $stmt->execute();
    $stmt->close();

    // Create SMM order (queued for cron)
    $stmt = $conn->prepare("INSERT INTO smm_orders
        (order_ref, user_id, service_id, provider_id, target_link, quantity, runs, `interval`, price_paid, payment_method, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'API_WALLET', 'pending', NOW())");
    $stmt->bind_param("siiisiiid",
        $order_ref,
        $reseller['id'],
        $svc['id'],
        $svc['provider_id'],
        $link,
        $quantity,
        $runs,
        $interval,
        $total_price
    );
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    send_api_response(true, "SMM order queued successfully.", [
        'order_ref'         => $order_ref,
        'partner_order_id'  => $partner_order_id,
        'service'           => $svc['svc_name'],
        'quantity'          => $quantity,
        'status'            => 'pending',
        'price_deducted'    => $total_price,
        'remaining_balance' => $new_balance,
        'note'              => 'Order will be placed with provider on next cron cycle (every 10 min).',
    ]);

} catch (Exception $e) {
    $conn->rollback();
    send_api_response(false, "System error: " . $e->getMessage());
}
