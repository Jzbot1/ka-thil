<?php
// Include your database connection file
include 'config.php';

// Fetch dynamic store settings from your database
$query = "SELECT store_name, whatsapp FROM fav_setting WHERE id = 1 LIMIT 1";
$result = mysqli_query($conn, $query);
$settings = mysqli_fetch_assoc($result);

// Defaults based on your provided data
$store_name = $settings['store_name'] ?? 'JZ Store';
$whatsapp_link = $settings['whatsapp'] ?? 'https://wa.me/918730063275';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refund Policy - <?php echo $store_name; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0f172a; 
            --bg-gradient: linear-gradient(177deg, #fbc2eb, #a6c1ee, hsl(86.7, 80.67784736040353%, 41.709338428627014%));
            --card-bg: rgba(255, 255, 255, 0.4);
            --text-main: #0f172a;
            --text-muted: rgba(15, 23, 42, 0.7);
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: var(--bg-gradient);
            background-attachment: fixed;
            margin: 0;
            padding: 15px;
            line-height: 1.6;
            color: var(--text-main);
        }

        .policy-container {
            max-width: 700px;
            margin: 20px auto;
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            padding: 30px;
            border-radius: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .header {
            border-bottom: 1px solid rgba(15, 23, 42, 0.1);
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0;
            color: var(--primary-color);
        }

        .last-updated {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        section {
            margin-bottom: 25px;
        }

        h2 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        p, li {
            font-size: 0.95rem;
            color: var(--text-main);
        }

        .highlight-box {
            background: rgba(15, 23, 42, 0.05);
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin: 15px 0;
            border-radius: 12px;
        }

        .fee-example {
            background: rgba(255, 255, 255, 0.3);
            padding: 10px 15px;
            border-radius: 12px;
            font-style: italic;
        }

        .support-btn {
            display: block;
            width: fit-content;
            background: #0f172a;
            color: white;
            padding: 12px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        @media (max-width: 600px) {
            .policy-container { padding: 20px; }
            .header h1 { font-size: 1.4rem; }
        }
    </style>
</head>
<body>

<div class="policy-container">
    <div class="header">
        <h1>Refund & Cancellation Policy</h1>
        <p class="last-updated">Last Updated: 17 Feb 2026</p>
    </div>

    <section>
        <h2><i class="fas fa-file-contract"></i> 1. Digital Goods Are Final</h2>
        <p>All products sold on <strong><?php echo $store_name; ?></strong> are digital goods. Once the in-game currency, items, or services are successfully delivered to the correct Player ID, the order is considered completed and cannot be cancelled, reversed, or refunded.</p>
    </section>

    <section>
        <h2><i class="fas fa-check-circle"></i> 2. When Refunds Apply</h2>
        <p>A refund may be issued only under the following conditions:</p>
        <ul>
            <li>Payment was successfully deducted but the top-up was not delivered</li>
            <li>The Player ID and Server ID were entered correctly, but the recharge failed due to system/technical errors</li>
            <li>The order could not be completed due to technical or provider issues</li>
        </ul>
        <p><em>All refund requests are subject to verification and approval.</em></p>
    </section>

    <section>
        <h2><i class="fas fa-times-circle"></i> 3. No Refund Will Be Issued If</h2>
        <div class="highlight-box">
            <ul>
                <li>Wrong Player ID or Server ID was entered by the customer</li>
                <li>The customer changed their mind after completing payment</li>
                <li>The order was successfully delivered to the provided Player ID</li>
                <li>Customer failed to follow payment instructions properly</li>
                <li>Customer refreshed, closed, or left the website during payment</li>
            </ul>
        </div>
        <p><strong>Please always double-check your Player ID and Server ID before making payment.</strong></p>
    </section>

    <section>
        <h2><i class="fas fa-clock"></i> 4. Refund Processing Time</h2>
        <ul>
            <li><strong>Original payment method:</strong> 1–7 business days</li>
            <li><strong>Wallet refund:</strong> Instant after approval</li>
        </ul>
    </section>

    <section>
        <h2><i class="fas fa-percentage"></i> 5. Processing Fee Deduction</h2>
        <p>A 2% processing fee will be deducted from the refund amount to cover payment gateway charges and administrative costs.</p>
        <div class="fee-example">
            Example: If you paid ₹100, you will receive ₹98 after the 2% deduction.
        </div>
    </section>

    <section>
        <h2><i class="fas fa-headset"></i> 6. Refund Request & Support</h2>
        <p>To request a refund, please provide:</p>
        <ul>
            <li>Website Username</li>
            <li>Payment screenshot & Transaction ID</li>
            <li>Payment amount, date, and time</li>
            <li>Description of the problem</li>
        </ul>
        <a href="<?php echo $whatsapp_link; ?>" class="support-btn">
            <i class="fab fa-whatsapp"></i> Contact WhatsApp Support
        </a>
    </section>
</div>

</body>
</html>