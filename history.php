<?php
// ✅ 1. SECURITY & SESSION START
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Check Login
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login");
    exit;
}

$user_id = $_SESSION['user_id'];

// ✅ 2. FETCH USER ORDERS
$stmt = $conn->prepare("
    SELECT o.*, g.image as game_image 
    FROM orders o 
    LEFT JOIN diamonds d ON o.product_id = d.product_id 
    LEFT JOIN games g ON d.game_id = g.id 
    WHERE o.user_id = ? 
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = $stmt->get_result();

// FETCH STORE SETTINGS
$setting = [
    'store_name' => 'JZ Store',
    'facebook'   => '#',
    'instagram'  => '#',
    'whatsapp'   => '#'
];
$setting_result = $conn->query("SELECT * FROM fav_setting LIMIT 1");
if ($setting_result && $row = $setting_result->fetch_assoc()) {
    foreach ($row as $key => $val) {
        if (!empty($val)) $setting[$key] = $val;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Order History - <?= htmlspecialchars($setting['store_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        themePink: '#08203E',
                        themeBlue: '#557C93',
                        themeGreen: '#80bf15',
                        themeDark: '#ffffff',
                    }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Outfit', sans-serif; 
            background: hsla(213, 77%, 14%, 1);
            background: linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -moz-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -webkit-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            filter: progid: DXImageTransform.Microsoft.gradient( startColorstr="#08203E", endColorstr="#557C93", GradientType=1 );
            background-attachment: fixed; color: #ffffff; overflow-x: hidden; }
        .glass-panel { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .order-card { transition: all 0.3s ease; border: 1px solid rgba(255,255,255,0.1); background: rgba(255, 255, 255, 0.1); }
        .order-card:hover { background: rgba(255, 255, 255, 0.15); border-color: #ffffff; }
        .status-badge { @apply px-2.5 py-1 rounded-full text-[8px] font-black uppercase tracking-widest; }
        @keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .animate-slide { animation: slideIn 0.5s ease forwards; }
    </style>
</head>
<body class="pb-24">

    <!-- HEADER -->
    <header class="fixed top-0 w-full z-50 bg-black/20 backdrop-blur-xl h-16 border-b border-white/10">
        <div class="max-w-md mx-auto px-5 h-full flex items-center justify-between">
            <a href="index" class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center border border-white/10"><i class="fa-solid fa-arrow-left text-themeDark text-sm"></i></a>
            <div class="font-bold text-lg text-themeDark">Order History</div>
            <div class="w-10"></div>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 mt-20 space-y-4">
        
        <?php if ($orders && $orders->num_rows > 0): ?>
            <?php $delay = 0; while ($order = $orders->fetch_assoc()): ?>
                <div class="order-card glass-panel rounded-3xl p-5 animate-slide" style="animation-delay: <?= $delay ?>s;">
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-white/5 rounded-2xl overflow-hidden border border-white/10">
                                <img src="<?= (strpos($order['game_image'] ?? '', 'http') === 0) ? $order['game_image'] : BASE_URL . '/' . ltrim($order['game_image'] ?? '', '/'); ?>" class="w-full h-full object-cover">
                            </div>
                            <div>
                                <h3 class="text-xs font-black text-white"><?= htmlspecialchars($order['game_name'] ?? 'Game') ?></h3>
                                <p class="text-[10px] text-white/60 font-bold"><?= htmlspecialchars($order['product_name']) ?></p>
                            </div>
                        </div>
                        <?php 
                            $status = strtolower($order['status']);
                            if ($status === 'completed' || $status === 'success') {
                                echo '<span class="status-badge bg-green-500/20 text-green-600 border border-green-500/30">Success</span>';
                            } elseif ($status === 'pending' || $status === 'processing') {
                                echo '<span class="status-badge bg-yellow-500/20 text-yellow-600 border border-yellow-500/30">Pending</span>';
                            } else {
                                echo '<span class="status-badge bg-red-500/20 text-red-600 border border-red-500/30">Failed</span>';
                            }
                        ?>
                    </div>

                    <div class="grid grid-cols-2 gap-4 border-t border-white/30 pt-4">
                        <div>
                            <p class="text-[9px] text-white/40 font-bold uppercase tracking-widest mb-1">Account ID</p>
                            <p class="text-[11px] font-black text-white truncate"><?= htmlspecialchars($order['game_user_id']) ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[9px] text-white/40 font-bold uppercase tracking-widest mb-1">Amount</p>
                            <p class="text-sm font-black text-white">₹<?= number_format($order['price'], 0) ?></p>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between gap-3">
                        <p class="text-[10px] text-white/60 font-medium"><?= date("d M Y, h:i A", strtotime($order['created_at'])) ?></p>
                        
                        <div class="flex gap-2">
                            <a href="<?= BASE_URL ?>/payment/receipt/<?= $order['order_id'] ?>" class="px-4 py-1.5 bg-white/10 hover:bg-white/20 text-white text-[9px] font-black uppercase rounded-lg border border-white/10 transition-all">Receipt</a>
                            
                            <?php if ($status !== 'completed' && $status !== 'success'): ?>
                                <button onclick="verifyOrder('<?= $order['order_id'] ?>', this)" class="px-4 py-1.5 bg-white text-black text-[9px] font-black uppercase rounded-lg shadow-lg shadow-white/5 transition-all">Verify</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php $delay += 0.05; endwhile; ?>
        <?php else: ?>
            <div class="text-center py-20 animate-fade">
                <div class="w-20 h-20 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4 border border-white/10">
                    <i class="fa-solid fa-receipt text-white/40 text-2xl"></i>
                </div>
                <h3 class="text-base font-black text-white">No Orders Found</h3>
                <p class="text-xs text-white/60 mt-1">You haven't made any purchases yet.</p>
                <a href="index" class="mt-6 inline-block px-8 py-3 bg-white text-black rounded-2xl font-black text-xs">Start Shopping</a>
            </div>
        <?php endif; ?>

    </main>

    <!-- FOOTER NAV -->
    <?php include 'footer.php'; ?>

    <script>
        async function verifyOrder(orderId, btn) {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            btn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('orderId', orderId);

                const response = await fetch('<?= BASE_URL ?>/api/verify_order', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    btn.innerHTML = '<i class="fa-solid fa-check"></i>';
                    btn.classList.replace('bg-blue-600', 'bg-green-600');
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
    </script>
</body>
</html>
