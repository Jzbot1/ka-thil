<?php
/**
 * mail_helper.php
 * Handles sending order invoices via Email
 */

function sendOrderInvoice($order_id) {
    global $conn;

    // 1. Fetch Order & User details
    $stmt = $conn->prepare("SELECT o.*, u.email as user_email, s.smtp_enabled, s.store_name, s.smtp_from_email, s.smtp_from_name 
                            FROM orders o 
                            LEFT JOIN users u ON o.user_id = u.id 
                            CROSS JOIN fav_setting s
                            WHERE o.order_id = ? LIMIT 1");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order || $order['smtp_enabled'] != 1) {
        return false;
    }

    $to = !empty($order['user_email']) ? $order['user_email'] : $order['email'];
    if (empty($to)) return false;

    $subject = "Invoice for Order #" . $order_id . " - " . $order['store_name'];
    
    // 2. Build Invoice HTML
    $message = "
    <html>
    <head>
        <style>
            body { font-family: sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; border: 1px solid #eee; padding: 20px; border-radius: 10px; }
            .header { text-align: center; border-bottom: 2px solid #f4f4f4; padding-bottom: 10px; }
            .details { margin: 20px 0; }
            .footer { font-size: 12px; color: #888; text-align: center; margin-top: 20px; }
            .price { font-size: 20px; font-weight: bold; color: #e11d48; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>" . htmlspecialchars($order['store_name']) . "</h1>
                <p>Order Confirmation & Invoice</p>
            </div>
            <div class='details'>
                <p><strong>Order ID:</strong> " . $order_id . "</p>
                <p><strong>Product:</strong> " . htmlspecialchars($order['product_name']) . "</p>
                <p><strong>Game:</strong> " . htmlspecialchars($order['game_name']) . "</p>
                <p><strong>Player ID:</strong> " . htmlspecialchars($order['game_user_id']) . "</p>
                <p><strong>Status:</strong> Completed</p>
                <p class='price'>Total Paid: ₹" . number_format($order['price'], 2) . "</p>
            </div>
            <div class='footer'>
                <p>Thank you for choosing " . htmlspecialchars($order['store_name']) . "!</p>
                <p>This is an automated invoice. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // 3. Headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . $order['smtp_from_name'] . " <" . $order['smtp_from_email'] . ">" . "\r\n";

    // 4. Send
    return mail($to, $subject, $message, $headers);
}
?>
