<?php
/**
 * mail_helper.php
 * Handles sending order invoices via Email (PHP mail() with SMTP fallback)
 * 
 * Requires these columns in fav_setting (run smtp_migration.sql first):
 *   smtp_enabled, smtp_from_email, smtp_from_name,
 *   smtp_host, smtp_port, smtp_username, smtp_password
 */

function sendOrderInvoice($order_id) {
    global $conn;

    // ✅ 1. Fetch Order + User + Store SMTP settings
    $stmt = $conn->prepare("
        SELECT 
            o.*,
            u.email  AS user_email,
            g.image AS game_image,
            d.image_url AS product_image,
            s.smtp_enabled,
            s.store_name,
            s.smtp_from_email,
            s.smtp_from_name,
            s.smtp_host,
            s.smtp_port,
            s.smtp_username,
            s.smtp_password
        FROM orders o
        LEFT JOIN users u  ON o.user_id = u.id
        LEFT JOIN diamonds d ON o.product_id = d.product_id
        LEFT JOIN games g ON d.game_id = g.id
        LEFT JOIN fav_setting s ON s.id = 1
        WHERE o.order_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        error_log("[MailHelper] Prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // ✅ 2. Guard: order must exist and SMTP must be enabled
    if (!$order) {
        error_log("[MailHelper] Order not found: $order_id");
        return false;
    }

    if ((int)($order['smtp_enabled'] ?? 0) !== 1) {
        // Email notifications are disabled in admin settings — silently skip
        return false;
    }

    // ✅ 3. Determine recipient email
    $to = trim($order['user_email'] ?? '');
    if (empty($to) && !empty($order['email'])) {
        $to = trim($order['email']);
    }

    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("[MailHelper] No valid recipient email for order $order_id");
        return false;
    }

    // ✅ 4. Sender info with fallbacks
    $from_name  = !empty($order['smtp_from_name'])  ? $order['smtp_from_name']  : ($order['store_name'] ?? 'Store');
    $from_email = !empty($order['smtp_from_email']) ? $order['smtp_from_email'] : 'no-reply@jzstore.in';
    $store_name = $order['store_name'] ?? 'JZ Store';
    $subject    = "✅ Order Confirmed #{$order_id} — {$store_name}";

    // ✅ 5. Build premium HTML invoice
    $status_color  = '#22c55e';  // green-500
    $product_name  = htmlspecialchars($order['product_name'] ?? 'N/A');
    $game_name     = htmlspecialchars($order['game_name']    ?? 'N/A');
    $game_uid      = htmlspecialchars($order['game_user_id'] ?? 'N/A');
    $game_zone     = ($order['game_zone_id'] !== 'none' && !empty($order['game_zone_id'])) ? " ({$order['game_zone_id']})" : "";
    $price         = number_format((float)($order['price'] ?? 0), 0);
    $created_at    = date("d M Y, h:i A", strtotime($order['created_at'] ?? 'now'));
    $pay_method    = htmlspecialchars($order['payment_method'] ?? 'N/A');
    $oid           = htmlspecialchars($order_id);
    
    // Images
    $base_url = defined('BASE_URL') ? BASE_URL : 'https://jzstore.in';
    $game_img = $order['game_image'] ?? '';
    if (!empty($game_img) && strpos($game_img, 'http') !== 0) {
        $game_img = rtrim($base_url, '/') . '/' . ltrim($game_img, '/');
    }
    $prod_img = $order['product_image'] ?? '';
    if (!empty($prod_img) && strpos($prod_img, 'http') !== 0) {
        $prod_img = rtrim($base_url, '/') . '/' . ltrim($prod_img, '/');
    }

    $message = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Receipt #{$oid}</title>
</head>
<body style="margin:0;padding:0;background-color:#020617;font-family:'Outfit','Segoe UI',Arial,sans-serif;color:#ffffff;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#020617;padding:40px 0;">
    <tr>
      <td align="center">
        <!-- OUTER CONTAINER -->
        <table width="500" cellpadding="0" cellspacing="0" style="background-color:#0f172a;border-radius:40px;overflow:hidden;border:1px solid rgba(255,255,255,0.1);box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
          
          <!-- BANNER HEADER -->
          <tr>
            <td style="position:relative;height:120px;background-color:#1e293b;text-align:center;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding:30px 20px;">
                            <div style="width:48px;height:48px;background-color:#22c55e;border-radius:50%;margin:0 auto 10px;line-height:48px;text-align:center;">
                                <span style="color:#ffffff;font-size:24px;">✓</span>
                            </div>
                            <h1 style="margin:0;color:#ffffff;font-size:14px;font-weight:900;text-transform:uppercase;letter-spacing:2px;">Order Success</h1>
                        </td>
                    </tr>
                </table>
            </td>
          </tr>

          <!-- TRANSACTION INFO -->
          <tr>
            <td style="padding:30px 40px 10px;text-align:center;">
              <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:2px;font-weight:700;">Transaction Amount</p>
              <h2 style="margin:8px 0 0;font-size:42px;font-weight:900;color:#ffffff;">₹{$price}</h2>
            </td>
          </tr>

          <!-- DETAILS BOX -->
          <tr>
            <td style="padding:20px 40px;">
              <table width="100%" cellpadding="0" cellspacing="0" style="background-color:rgba(255,255,255,0.05);border-radius:24px;border:1px solid rgba(255,255,255,0.05);">
                <!-- Order ID -->
                <tr>
                  <td style="padding:15px 20px;border-bottom:1px solid rgba(255,255,255,0.05);">
                    <span style="font-size:10px;color:rgba(255,255,255,0.5);font-weight:700;text-transform:uppercase;letter-spacing:1px;">Order ID</span>
                  </td>
                  <td style="padding:15px 20px;border-bottom:1px solid rgba(255,255,255,0.05);text-align:right;">
                    <span style="font-size:12px;font-weight:700;color:#ffffff;">#{$oid}</span>
                  </td>
                </tr>
                <!-- Game -->
                <tr>
                  <td style="padding:15px 20px;border-bottom:1px solid rgba(255,255,255,0.05);">
                    <span style="font-size:10px;color:rgba(255,255,255,0.5);font-weight:700;text-transform:uppercase;letter-spacing:1px;">Game</span>
                  </td>
                  <td style="padding:15px 20px;border-bottom:1px solid rgba(255,255,255,0.05);text-align:right;">
                    <span style="font-size:12px;font-weight:700;color:#ffffff;">{$game_name}</span>
                  </td>
                </tr>
                <!-- Product -->
                <tr>
                  <td style="padding:15px 20px;border-bottom:1px solid rgba(255,255,255,0.05);">
                    <span style="font-size:10px;color:rgba(255,255,255,0.5);font-weight:700;text-transform:uppercase;letter-spacing:1px;">Product</span>
                  </td>
                  <td style="padding:15px 20px;border-bottom:1px solid rgba(255,255,255,0.05);text-align:right;">
                    <span style="font-size:12px;font-weight:700;color:#ffffff;">{$product_name}</span>
                  </td>
                </tr>
                <!-- Player ID -->
                <tr>
                  <td style="padding:15px 20px;border-bottom:1px solid rgba(255,255,255,0.05);">
                    <span style="font-size:10px;color:rgba(255,255,255,0.5);font-weight:700;text-transform:uppercase;letter-spacing:1px;">Player ID</span>
                  </td>
                  <td style="padding:15px 20px;border-bottom:1px solid rgba(255,255,255,0.05);text-align:right;">
                    <span style="font-size:12px;font-weight:700;color:#ffffff;">{$game_uid}{$game_zone}</span>
                  </td>
                </tr>
                <!-- Date -->
                <tr>
                  <td style="padding:15px 20px;">
                    <span style="font-size:10px;color:rgba(255,255,255,0.5);font-weight:700;text-transform:uppercase;letter-spacing:1px;">Date</span>
                  </td>
                  <td style="padding:15px 20px;text-align:right;">
                    <span style="font-size:12px;font-weight:700;color:#ffffff;">{$created_at}</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- STATUS NOTE -->
          <tr>
            <td style="padding:10px 40px 30px;">
              <div style="background-color:rgba(59,130,246,0.1);border:1px solid rgba(59,130,246,0.2);border-radius:20px;padding:15px;text-align:center;">
                <p style="margin:0 0 5px;font-size:9px;color:#60a5fa;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Status Note</p>
                <p style="margin:0;font-size:11px;color:#ffffff;">Your recharge has been completed successfully!</p>
              </div>
            </td>
          </tr>

          <!-- FOOTER -->
          <tr>
            <td style="background-color:rgba(255,255,255,0.03);padding:20px;text-align:center;">
              <p style="margin:0;font-size:9px;color:rgba(255,255,255,0.3);font-weight:700;text-transform:uppercase;letter-spacing:2px;">Thank you for shopping at {$store_name}</p>
            </td>
          </tr>

        </table>

        <!-- SOCIAL LINKS / HELP -->
        <table width="500" cellpadding="0" cellspacing="0">
            <tr>
                <td style="padding:30px 20px;text-align:center;">
                    <p style="margin:0;font-size:10px;color:rgba(255,255,255,0.3);">If you have any issues, please contact our support team.</p>
                </td>
            </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    // ✅ 6. Set headers
    $from_encoded = mb_encode_mimeheader($from_name, 'UTF-8', 'B') . " <{$from_email}>";
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$from_encoded}\r\n";
    $headers .= "Reply-To: {$from_email}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    // ✅ 7. Send via php mail()
    $sent = @mail($to, $subject, $message, $headers);

    if (!$sent) {
        error_log("[MailHelper] mail() failed for order $order_id to $to");
    }

    return $sent;
}
