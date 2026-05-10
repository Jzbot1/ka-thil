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
    $status_color  = '#16a34a';  // green
    $product_name  = htmlspecialchars($order['product_name'] ?? 'N/A');
    $game_name     = htmlspecialchars($order['game_name']    ?? 'N/A');
    $game_uid      = htmlspecialchars($order['game_user_id'] ?? 'N/A');
    $price         = number_format((float)($order['price'] ?? 0), 2);
    $created_at    = date("d M Y, h:i A", strtotime($order['created_at'] ?? 'now'));
    $pay_method    = htmlspecialchars($order['payment_method'] ?? 'N/A');
    $oid           = htmlspecialchars($order_id);

    $message = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order Receipt #{$oid}</title>
</head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:30px 0;">
    <tr>
      <td align="center">
        <table width="580" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

          <!-- HEADER -->
          <tr>
            <td style="background:linear-gradient(135deg,#0f4c8f 0%,#2d9cdb 100%);padding:36px 40px;text-align:center;">
              <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:800;letter-spacing:-0.5px;">{$store_name}</h1>
              <p style="margin:8px 0 0;color:rgba(255,255,255,0.75);font-size:12px;letter-spacing:2px;text-transform:uppercase;">Order Confirmation &amp; Invoice</p>
            </td>
          </tr>

          <!-- STATUS BADGE -->
          <tr>
            <td style="padding:28px 40px 0;text-align:center;">
              <span style="display:inline-block;background:#dcfce7;color:{$status_color};font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;padding:6px 18px;border-radius:50px;">✅ Completed</span>
            </td>
          </tr>

          <!-- AMOUNT -->
          <tr>
            <td style="padding:16px 40px 8px;text-align:center;">
              <p style="margin:0;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Amount Paid</p>
              <p style="margin:4px 0 0;font-size:40px;font-weight:800;color:#0f172a;">&#8377;{$price}</p>
            </td>
          </tr>

          <!-- DIVIDER -->
          <tr><td style="padding:20px 40px;"><hr style="border:none;border-top:1px solid #e5e7eb;"></td></tr>

          <!-- ORDER DETAILS TABLE -->
          <tr>
            <td style="padding:0 40px 28px;">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;">
                    <span style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:1px;">Order ID</span>
                  </td>
                  <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;text-align:right;">
                    <span style="font-size:12px;font-weight:700;color:#0f172a;font-family:monospace;">#{$oid}</span>
                  </td>
                </tr>
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;">
                    <span style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:1px;">Game</span>
                  </td>
                  <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;text-align:right;">
                    <span style="font-size:12px;font-weight:700;color:#0f172a;">{$game_name}</span>
                  </td>
                </tr>
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;">
                    <span style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:1px;">Product</span>
                  </td>
                  <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;text-align:right;">
                    <span style="font-size:12px;font-weight:700;color:#0f172a;">{$product_name}</span>
                  </td>
                </tr>
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;">
                    <span style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:1px;">Player ID</span>
                  </td>
                  <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;text-align:right;">
                    <span style="font-size:12px;font-weight:700;color:#0f172a;">{$game_uid}</span>
                  </td>
                </tr>
                <tr>
                  <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;">
                    <span style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:1px;">Payment Method</span>
                  </td>
                  <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;text-align:right;">
                    <span style="font-size:12px;font-weight:700;color:#0f172a;">{$pay_method}</span>
                  </td>
                </tr>
                <tr>
                  <td style="padding:10px 0;">
                    <span style="font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:1px;">Date &amp; Time</span>
                  </td>
                  <td style="padding:10px 0;text-align:right;">
                    <span style="font-size:12px;font-weight:700;color:#0f172a;">{$created_at}</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- FOOTER -->
          <tr>
            <td style="background:#f8fafc;padding:24px 40px;text-align:center;border-top:1px solid #e5e7eb;">
              <p style="margin:0;font-size:11px;color:#9ca3af;">Thank you for your purchase at <strong style="color:#0f172a;">{$store_name}</strong>!</p>
              <p style="margin:6px 0 0;font-size:10px;color:#d1d5db;">This is an automated invoice — please do not reply to this email.</p>
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
