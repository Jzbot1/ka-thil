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
    <title>FAQ - <?php echo $store_name; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0f172a;
            --bg-gradient: linear-gradient(177deg, #fbc2eb, #a6c1ee, hsl(86.7, 80.67784736040353%, 41.709338428627014%));
            --card-bg: rgba(255, 255, 255, 0.4);
            --text-main: #0f172a;
            --text-muted: rgba(15, 23, 42, 0.6);
            --accent-green: #0f172a;
        }

        body {
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-gradient);
            background-attachment: fixed;
            margin: 0;
            padding: 15px;
            color: var(--text-main);
            line-height: 1.6;
        }

        .faq-container {
            max-width: 600px;
            margin: 0 auto;
        }

        .faq-header {
            text-align: center;
            padding: 20px 0;
        }

        .faq-header h1 {
            font-size: 1.6rem;
            font-weight: 800;
            margin: 0;
            color: var(--primary-color);
        }

        /* Modern Accordion Layout */
        .faq-item {
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            border-radius: 1.5rem;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }

        .faq-question {
            width: 100%;
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            background: none;
            border: none;
            font-weight: 700;
            font-size: 0.95rem;
            text-align: left;
            color: var(--text-main);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            background-color: rgba(255, 255, 255, 0.2);
        }

        .faq-content {
            padding: 0 20px 20px 20px;
            font-size: 0.9rem;
            color: var(--text-main);
            border-top: 1px solid rgba(15, 23, 42, 0.05);
            padding-top: 15px;
        }

        .faq-item.active .faq-answer {
            max-height: 1000px; /* High enough for long text */
        }

        .faq-item.active .fa-chevron-down {
            transform: rotate(180deg);
            color: var(--primary-color);
        }

        .faq-item.active {
            border-color: var(--primary-color);
        }

        /* Support Section */
        .support-box {
            margin-top: 30px;
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(12px);
            padding: 25px;
            border-radius: 2rem;
            text-align: center;
            border: 1px dashed rgba(15, 23, 42, 0.2);
        }

        .btn-support {
            display: inline-block;
            margin-top: 15px;
            background: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        ul { padding-left: 20px; margin: 10px 0; }
        li { margin-bottom: 5px; }
    </style>
</head>
<body>

<div class="faq-container">
    <div class="faq-header">
        <h1>Frequently Asked Questions</h1>
        <p>Everything you need to know about <?php echo $store_name; ?></p>
    </div>

    <div class="faq-item">
        <button class="faq-question">
            1. Is <?php echo $store_name; ?> legit and safe?
            <i class="fas fa-chevron-down"></i>
        </button>
        <div class="faq-answer">
            <div class="faq-content">
                Yes, <?php echo $store_name; ?> is 100% legit and safe. We provide fast and secure game recharges and digital products. Our website uses secure payment systems, and thousands of customers trust our service.
            </div>
        </div>
    </div>

    <div class="faq-item">
        <button class="faq-question">
            2. How long does delivery take?
            <i class="fas fa-chevron-down"></i>
        </button>
        <div class="faq-answer">
            <div class="faq-content">
                Most products are delivered instantly after successful payment.
                <ul>
                    <li><strong>Instant products:</strong> Delivered within a few seconds to 1 minute</li>
                    <li><strong>Manual products:</strong> May take a few minutes to a few hours</li>
                </ul>
                Non-instant products are always clearly marked before purchase.
            </div>
        </div>
    </div>

    <div class="faq-item">
        <button class="faq-question">
            3. Why is my order or recharge still pending?
            <i class="fas fa-chevron-down"></i>
        </button>
        <div class="faq-answer">
            <div class="faq-content">
                Your order may be pending due to:
                <ul>
                    <li>Not returning to the website after payment</li>
                    <li>Refreshing or closing the site during processing</li>
                    <li>Payment gateway delays or network issues</li>
                </ul>
                <strong>What to do:</strong> Login, go to your Profile, copy your Username, and send it with Payment Details (screenshot, transaction ID) to our Support Team.
            </div>
        </div>
    </div>

    <div class="faq-item">
        <button class="faq-question">
            4. I paid but did not receive Diamonds?
            <i class="fas fa-chevron-down"></i>
        </button>
        <div class="faq-answer">
            <div class="faq-content">
                Do not panic. Check your order status in your account. If it is pending, contact support with your Username, Payment screenshot, and Transaction ID.
            </div>
        </div>
    </div>

    <div class="faq-item">
        <button class="faq-question">
            5. Do I need to refresh the website after payment?
            <i class="fas fa-chevron-down"></i>
        </button>
        <div class="faq-answer">
            <div class="faq-content">
                <strong>No.</strong> Do NOT refresh, close, or leave while payment is processing. Wait until you are redirected back and the status updates.
            </div>
        </div>
    </div>

    <div class="faq-item">
        <button class="faq-question">
            6. Can I get a refund?
            <i class="fas fa-chevron-down"></i>
        </button>
        <div class="faq-answer">
            <div class="faq-content">
                Yes, if your request meets our Refund Policy guidelines.
                <ul>
                    <li><strong>Wallet refund:</strong> Instant after approval</li>
                    <li><strong>Cash refund:</strong> Minimum 24 hours after approval</li>
                </ul>
                Read the full policy at: <a href="https://mobapay.in/refund">jzstore.in/refund</a>
            </div>
        </div>
    </div>

    <div class="faq-item">
        <button class="faq-question">
            7 & 8. Double Diamonds Bonus
            <i class="fas fa-chevron-down"></i>
        </button>
        <div class="faq-answer">
            <div class="faq-content">
                This is a special Mobile Legends bonus for your first purchase of selected packages. If you did not receive it:
                <ul>
                    <li>You may have used the bonus before (it is once per account).</li>
                    <li>This is controlled by the game server, not our store.</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="faq-item">
        <button class="faq-question">
            9. Is it safe to enter my User ID and Zone ID?
            <i class="fas fa-chevron-down"></i>
        </button>
        <div class="faq-answer">
            <div class="faq-content">
                Yes. These are only required to deliver items to your account. We <strong>never</strong> ask for your password.
            </div>
        </div>
    </div>

    <div class="faq-item">
        <button class="faq-question">
            Additional Information
            <i class="fas fa-chevron-down"></i>
        </button>
        <div class="faq-answer">
            <div class="faq-content">
                <ul>
                    <li><strong>Payments:</strong> We accept all secure methods listed on our site.</li>
                    <li><strong>Cancellations:</strong> Completed orders cannot be cancelled.</li>
                    <li><strong>Accounts:</strong> Not required, but recommended for tracking and faster support.</li>
                    <li><strong>Wrong ID:</strong> Contact support immediately. Double-check your ID before payment!</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="support-box">
        <h3>Still need help?</h3>
        <p>Our support team is always ready to assist you.</p>
        <a href="<?php echo $whatsapp_link; ?>" class="btn-support">
            <i class="fab fa-whatsapp"></i> Contact Support
        </a>
    </div>
</div>

<script>
    // Handle Accordion toggles
    document.querySelectorAll('.faq-question').forEach(button => {
        button.addEventListener('click', () => {
            const faqItem = button.parentElement;
            
            // Close other open items (Optional)
            document.querySelectorAll('.faq-item').forEach(item => {
                if (item !== faqItem) item.classList.remove('active');
            });

            faqItem.classList.toggle('active');
        });
    });
</script>

</body>
</html>