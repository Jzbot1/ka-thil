<?php
// ✅ 1. SECURITY & SESSION START
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

$order_id = $_GET['orderId'] ?? null;
if (!$order_id) {
    die("Invalid Access: Order ID required.");
}

// ✅ 2. FETCH ORDER DETAILS WITH IMAGES
$stmt = $conn->prepare("
    SELECT o.*, g.image as game_image, d.image_url as product_image 
    FROM orders o 
    LEFT JOIN diamonds d ON o.product_id = d.product_id 
    LEFT JOIN games g ON d.game_id = g.id 
    WHERE o.order_id = ? 
    LIMIT 1
");
$stmt->bind_param("s", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Error: Order #" . htmlspecialchars($order_id) . " not found.");
}

// Logic Variables
$orderStatus = strtolower($order['status'] ?? 'pending');
$game_name = !empty($order['game_name']) ? $order['game_name'] : "Game Top-up";
$game_image = $order['game_image'] ?? '';
$product_image = $order['product_image'] ?? '';
$is_success = in_array($orderStatus, ['completed', 'success']);
$is_processing = in_array($orderStatus, ['processing', 'pending']);

// FETCH STORE SETTINGS
$setting = ['store_name' => 'JZ Store'];
$setting_result = $conn->query("SELECT store_name FROM fav_setting LIMIT 1");
if ($setting_result && $row = $setting_result->fetch_assoc()) {
    if (!empty($row['store_name']))
        $setting['store_name'] = $row['store_name'];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Receipt - <?= htmlspecialchars($order['order_id']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navyDark: '#020617',
                        navyBlue: '#0f172a',
                        accentBlue: '#3b82f6',
                    }
                }
            }
        }
    </script>

    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: hsla(213, 77%, 14%, 1);
            background: linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -moz-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -webkit-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            filter: progid:DXImageTransform.Microsoft.gradient(startColorstr="#08203E",endColorstr="#557C93",GradientType=1);
            background-attachment: fixed;
            color: #ffffff;
            overflow-x: hidden;
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .status-badge {
            @apply px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade {
            animation: fadeIn 0.5s ease-out forwards;
        }
    </style>

    <?php if ($is_processing): ?>
        <meta http-equiv="refresh" content="5">
    <?php endif; ?>
</head>

<body class="min-h-screen flex flex-col items-center justify-center p-4">

    <div id="receipt-content" class="w-full max-w-md animate-fade">
        <div class="glass-panel rounded-[2.5rem] overflow-hidden shadow-2xl border border-white/5 relative">

            <!-- HEADER BANNER -->
            <div class="h-32 relative overflow-hidden">
                <img src="<?= (strpos($game_image, 'http') === 0) ? $game_image : BASE_URL . '/' . ltrim($game_image, '/') ?>"
                    class="w-full h-full object-cover opacity-30 blur-sm scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                <div class="absolute inset-0 flex flex-col items-center justify-center pt-4">
                    <?php if ($is_success): ?>
                        <div
                            class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center shadow-lg shadow-green-500/20 mb-2">
                            <i class="fa-solid fa-check text-white text-xl"></i>
                        </div>
                        <h1 class="text-sm font-black text-white uppercase tracking-widest">Order Success</h1>
                    <?php elseif ($is_processing): ?>
                        <div
                            class="w-12 h-12 bg-yellow-500 rounded-full flex items-center justify-center shadow-lg shadow-yellow-500/20 mb-2 animate-pulse">
                            <i class="fa-solid fa-spinner fa-spin text-white text-xl"></i>
                        </div>
                        <h1 class="text-sm font-black text-white uppercase tracking-widest">Processing</h1>
                    <?php else: ?>
                        <div
                            class="w-12 h-12 bg-red-500 rounded-full flex items-center justify-center shadow-lg shadow-red-500/20 mb-2">
                            <i class="fa-solid fa-xmark text-white text-xl"></i>
                        </div>
                        <h1 class="text-sm font-black text-white uppercase tracking-widest">Order Failed</h1>
                    <?php endif; ?>
                </div>
            </div>

            <div class="p-8 pt-4 space-y-6">
                <!-- ORDER INFO -->
                <div class="text-center">
                    <p class="text-[10px] text-white/60 uppercase font-bold tracking-[0.2em] mb-1">Transaction
                        Amount</p>
                    <h2 class="text-4xl font-black text-white">₹<?= number_format($order['price'], 0) ?></h2>
                </div>

                <div class="space-y-3 bg-white/10 rounded-3xl p-5 border border-white/10">
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] text-white/60 uppercase font-bold tracking-wider">Order ID</span>
                        <span
                            class="text-xs font-bold text-white">#<?= htmlspecialchars($order['order_id']) ?></span>
                    </div>
                    <div class="flex justify-between items-center border-t border-white/10 pt-3">
                        <span class="text-[10px] text-white/60 uppercase font-bold tracking-wider">Game</span>
                        <div class="flex items-center gap-2">
                            <img src="<?= (strpos($game_image, 'http') === 0) ? $game_image : BASE_URL . '/' . ltrim($game_image, '/') ?>"
                                class="w-5 h-5 rounded-md object-cover">
                            <span class="text-xs font-bold text-white"><?= htmlspecialchars($game_name) ?></span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center border-t border-white/10 pt-3">
                        <span class="text-[10px] text-white/60 uppercase font-bold tracking-wider">Product</span>
                        <div class="flex items-center gap-2">
                            <?php if (!empty($product_image)): ?>
                                <img src="<?= (strpos($product_image, 'http') === 0) ? $product_image : BASE_URL . '/' . ltrim($product_image, '/') ?>"
                                    class="w-5 h-5 object-contain">
                            <?php endif; ?>
                            <span
                                class="text-xs font-bold text-white"><?= htmlspecialchars($order['product_name']) ?></span>
                        </div>
                    </div>
                    <div class="flex justify-between items-center border-t border-white/10 pt-3">
                        <span class="text-[10px] text-white/60 uppercase font-bold tracking-wider">Player ID</span>
                        <span
                            class="text-xs font-bold text-white"><?= htmlspecialchars($order['game_user_id']) ?><?= ($order['game_zone_id'] !== 'none' && !empty($order['game_zone_id'])) ? " ({$order['game_zone_id']})" : "" ?></span>
                    </div>
                    <div class="flex justify-between items-center border-t border-white/10 pt-3">
                        <span class="text-[10px] text-white/60 uppercase font-bold tracking-wider">Date</span>
                        <span
                            class="text-xs font-bold text-white"><?= date("d M Y, h:i A", strtotime($order['created_at'])) ?></span>
                    </div>
                </div>

                <div id="status-note-box" class="bg-blue-600/10 border border-blue-500/20 rounded-2xl p-4 text-center">
                    <p class="text-[10px] text-blue-400 font-bold uppercase tracking-wider mb-1">Status Note</p>
                    <p id="status-note-text" class="text-xs text-white font-medium">
                        <?= $is_success ? "Your recharge has been completed successfully!" : ($is_processing ? "We are processing your order. Please wait..." : "Fulfillment failed. Contact support for help.") ?>
                    </p>

                    <?php if (!$is_success): ?>
                        <button onclick="verifyOrder()" id="verifyBtn"
                            class="mt-3 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-[10px] font-black uppercase rounded-xl transition-all">
                            Verify Status
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="bg-white/10 p-4 text-center">
                <p class="text-[9px] text-white/40 font-bold uppercase tracking-widest">Thank you for shopping at
                    <?= htmlspecialchars($setting['store_name']) ?></p>
            </div>
        </div>
    </div>

    <div class="w-full max-w-md mt-6 grid grid-cols-2 gap-3 px-2">
        <button onclick="downloadReceipt()"
            class="bg-white/10 hover:bg-white/20 text-white py-4 rounded-2xl font-bold text-xs border border-white/10 transition-all flex items-center justify-center gap-2">
            <i class="fa-solid fa-download text-white"></i>
            Save Image
        </button>
        <a href="<?= BASE_URL ?>/index"
            class="bg-white hover:bg-white/90 text-[#08203E] py-4 rounded-2xl font-bold text-xs text-center shadow-lg shadow-black/20 transition-all flex items-center justify-center gap-2">
            <i class="fa-solid fa-house"></i>
            Home
        </a>
    </div>

    <script>
        async function verifyOrder() {
            const btn = document.getElementById('verifyBtn');
            const noteText = document.getElementById('status-note-text');
            const originalHTML = btn.innerHTML;

            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';
            btn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('orderId', '<?= $order['order_id'] ?>');

                const response = await fetch('<?= BASE_URL ?>/api/verify_order', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success || result.status) {
                    noteText.innerText = result.message || "Status updated! Reloading page...";
                    setTimeout(() => location.reload(), 1500);
                } else {
                    alert(result.message || "Could not verify payment yet.");
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                }
            } catch (error) {
                console.error(error);
                alert("An error occurred while verifying.");
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        }
        async function downloadReceipt() {
            const element = document.getElementById('receipt-content');
            const btn = event.currentTarget;
            const originalHTML = btn.innerHTML;

            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

            const canvas = await html2canvas(element, {
                scale: 3,
                useCORS: true,
                backgroundColor: '#020617',
                borderRadius: 40
            });

            const link = document.createElement('a');
            link.download = 'Receipt-<?= $order['order_id'] ?>.png';
            link.href = canvas.toDataURL("image/png");
            link.click();

            btn.innerHTML = originalHTML;
        }
    </script>
</body>

</html>