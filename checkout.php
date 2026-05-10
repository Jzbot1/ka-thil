<?php
// ✅ 1. SECURITY & SESSION START
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// ✅ 2. DATA VALIDATION (Incoming from product.php)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && empty($_SESSION['checkout_data'])) {
    header("Location: index.php");
    exit;
}

// Store POST data in session or use directly
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['checkout_data'] = $_POST;
}

$data = $_SESSION['checkout_data'];

$game_name         = $data['game_name'] ?? 'Game';
$game_slug         = $data['game_slug'] ?? '';
$game_image        = $data['game_image'] ?? '';
$provider          = $data['provider'] ?? 'smileone';

// FETCH USER BALANCE FOR J-COIN
$user_balance = 0;
if (isset($_SESSION['user_id'])) {
    $stmt_b = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt_b->bind_param("i", $_SESSION['user_id']);
    $stmt_b->execute();
    $res_b = $stmt_b->get_result()->fetch_assoc();
    $user_balance = (float)($res_b['wallet_balance'] ?? 0);
    $stmt_b->close();
}
$product_id        = $data['product_id'] ?? '';

// FETCH VERIFIED PRICE FROM DATABASE (DO NOT TRUST FRONTEND/SESSION)
$stmt_price = $conn->prepare("SELECT price, spu, game_id FROM diamonds WHERE product_id = ? LIMIT 1");
$stmt_price->bind_param("s", $product_id);
$stmt_price->execute();
$prod_verified = $stmt_price->get_result()->fetch_assoc();
$stmt_price->close();

if (!$prod_verified) {
    die("Error: Product not found in our database.");
}

$product_price     = (float)$prod_verified['price'];
$product_name      = $prod_verified['spu'];
$product_image     = $data['product_image'] ?? '';
$user_id           = $data['user_id'] ?? '';
$zone_id           = $data['zone_id'] ?? '';
$verified_username = $data['verified_username'] ?? 'Order';
$email             = $data['email'] ?? '';

if (empty($product_id) || empty($user_id)) {
    die("Invalid checkout request. Missing product or user information.");
}

// ✅ 3. FETCH PAYMENT METHODS
$paymentMethods = $conn->query("SELECT * FROM payment_methods WHERE status = 1 ORDER BY display_order ASC");

// ✅ 4. FETCH STORE SETTINGS
$setting = [
    'store_name' => 'JZ Store',
    'fav_icon'   => 'https://jzstore.in/logo/jzstorelogo.jpg',
];
$setting_result = $conn->query("SELECT store_name, fav_icon FROM fav_setting LIMIT 1");
if ($setting_result && $row = $setting_result->fetch_assoc()) {
    if (!empty($row['store_name'])) $setting['store_name'] = $row['store_name'];
    if (!empty($row['fav_icon']))   $setting['fav_icon']   = $row['fav_icon'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Checkout - <?= htmlspecialchars($setting['store_name']); ?></title>
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
            filter: progid:DXImageTransform.Microsoft.gradient(startColorstr="#08203E",endColorstr="#557C93",GradientType=1);
            background-attachment: fixed; color: #ffffff; overflow-x: hidden; }
        .glass-panel { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .payment-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255,255,255,0.1); }
        .payment-card.selected { border: 2px solid #ffffff; background: rgba(255, 255, 255, 0.15); box-shadow: 0 0 20px rgba(255, 255, 255, 0.1); }
        .bottom-bar { position: fixed; bottom: 0; left: 0; right: 0; z-index: 60; background: rgba(8, 32, 62, 0.85); backdrop-filter: blur(20px); border-top: 1px solid rgba(255,255,255,0.1); padding: 16px 20px env(safe-area-inset-bottom); }
        @keyframes slideIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .animate-slide { animation: slideIn 0.5s ease forwards; }
    </style>
</head>
<body class="pb-32">

    <header class="fixed top-0 w-full z-50 bg-black/20 backdrop-blur-xl h-16 border-b border-white/10">
        <div class="max-w-md mx-auto px-5 h-full flex items-center justify-between">
            <a href="<?= BASE_URL ?>/product/<?= $game_slug ?>" class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center border border-white/10"><i class="fa-solid fa-arrow-left text-white text-sm"></i></a>
            <div class="font-bold text-lg text-white">Checkout</div>
            <div class="w-10"></div>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 mt-20 space-y-6">
        
        <!-- SECTION 1: ORDER SUMMARY -->
        <section class="animate-slide">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-6 h-6 bg-themeBlue rounded-md flex items-center justify-center text-[10px] font-bold shadow-lg shadow-themeBlue/30 text-white">1</div>
                <h2 class="text-xs font-bold text-white uppercase tracking-widest">Order Summary</h2>
            </div>
            
            <div class="glass-panel rounded-[2rem] overflow-hidden">
                <div class="p-6 flex items-center gap-4 border-b border-white/5">
                    <img src="<?= (strpos($game_image, 'http') === 0) ? $game_image : BASE_URL . '/' . ltrim($game_image, '/') ?>" class="w-16 h-16 rounded-2xl object-cover border border-white/10">
                    <div>
                    <h3 class="text-base font-black text-white"><?= htmlspecialchars($game_name) ?></h3>
                        <div class="flex items-center gap-2 mt-1">
                            <?php 
                                $isManual = (strtolower($provider) === 'manual');
                                $display_text = $isManual ? 'Manual Delivery' : 'Instant Delivery';
                                $badge_style = $isManual ? 'bg-orange-500/10 text-orange-400' : 'bg-rose-500/10 text-rose-400';
                            ?>
                            <span class="px-2 py-0.5 <?= $badge_style ?> text-[8px] font-bold rounded-full uppercase"><?= $display_text ?></span>
                            <span class="text-[10px] text-white/50">Official Recharge</span>
                        </div>
                    </div>
                </div>
                
                <div class="p-6 space-y-4">
                    <div class="flex justify-between items-center bg-white/5 p-4 rounded-2xl border border-white/5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center">
                                <img src="<?= (strpos($product_image, 'http') === 0) ? $product_image : BASE_URL . '/' . ltrim($product_image, '/') ?>" class="w-7 h-7 object-contain">
                            </div>
                                <div>
                                    <p class="text-[10px] text-white/60 font-bold uppercase tracking-wider">Product</p>
                                    <p class="text-xs font-bold text-white"><?= htmlspecialchars($product_name) ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] text-white/60 font-bold uppercase tracking-wider">Price</p>
                                <p class="text-base font-black text-rose-400">₹<?= number_format($product_price, 0) ?></p>
                            </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-white/5 p-4 rounded-2xl border border-white/5">
                            <p class="text-[9px] text-white/60 font-bold uppercase tracking-widest mb-1">Username</p>
                            <p class="text-xs font-black text-white truncate"><?= htmlspecialchars($verified_username) ?></p>
                        </div>
                        <div class="bg-white/5 p-4 rounded-2xl border border-white/5">
                            <p class="text-[9px] text-white/60 font-bold uppercase tracking-widest mb-1">User ID</p>
                            <p class="text-xs font-black text-white"><?= htmlspecialchars($user_id) ?><?= ($zone_id !== 'none') ? " ($zone_id)" : "" ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 2: PAYMENT METHODS -->
        <section class="animate-slide" style="animation-delay: 0.1s;">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-6 h-6 bg-rose-600 rounded-md flex items-center justify-center text-[10px] font-bold shadow-lg shadow-rose-600/30 text-white">2</div>
                <h2 class="text-xs font-bold text-white uppercase tracking-widest">Select Payment Method</h2>
            </div>
            
            <div class="space-y-2">
                <?php if ($paymentMethods && $paymentMethods->num_rows > 0): ?>
                    <?php while ($pm = $paymentMethods->fetch_assoc()): ?>
                        <div class="payment-card rounded-[1.5rem] p-4 flex items-center justify-between cursor-pointer group" onclick="selectPayment('<?= $pm['method_code'] ?>', '<?= htmlspecialchars($pm['method_name']) ?>', this)">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center p-2 shadow-inner">
                                    <img src="<?= (strpos($pm['image_url'] ?? '', 'http') === 0) ? ($pm['image_url'] ?? '') : BASE_URL . '/' . ltrim($pm['image_url'] ?? '', '/'); ?>" class="w-full h-full object-contain">
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white group-hover:text-rose-400 transition-colors"><?= htmlspecialchars($pm['method_name']) ?></p>
                                    <p class="text-[10px] text-white/60 uppercase font-bold tracking-tighter">
                                        <?php if ($pm['method_code'] == 'jcoin'): ?>
                                            Balance: ₹<?= number_format($user_balance, 2) ?>
                                            <?php if ($user_balance < $product_price): ?>
                                                <span class="text-rose-400 ml-1">(Insufficient)</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Instant UPI
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="w-6 h-6 rounded-full border-2 border-white/20 flex items-center justify-center transition-all duration-300">
                                <div class="dot w-3 h-3 bg-rose-500 rounded-full scale-0 transition-transform duration-300"></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-center text-xs text-white/50 py-4">No payment methods available.</p>
                <?php endif; ?>
            </div>
        </section>

        <footer class="pb-10 pt-4 text-center">
            <p class="text-[10px] text-white/40 font-medium">By proceeding, you agree to our Terms of Service</p>
        </footer>
    </main>

    <!-- STICKY PAY BUTTON -->
    <div class="bottom-bar">
        <div class="max-w-md mx-auto flex items-center justify-between gap-6">
            <div class="flex flex-col">
                <p class="text-[10px] text-white/60 font-bold uppercase tracking-wider leading-none mb-1">Total Pay</p>
                <p class="text-2xl font-black text-white leading-none">₹<?= number_format($product_price, 0) ?></p>
            </div>
            <button onclick="handlePay()" id="payBtn" class="flex-1 h-14 bg-rose-600 hover:bg-rose-500 text-white rounded-2xl font-black text-sm shadow-xl shadow-rose-600/30 transition-all active:scale-95 disabled:opacity-50 disabled:pointer-events-none">
                Pay Now
            </button>
        </div>
    </div>

    <script>
        let selection = {
            method: '',
            methodName: '',
            processing: false
        };

        function selectPayment(code, name, el) {
            document.querySelectorAll('.payment-card').forEach(card => {
                card.classList.remove('selected');
                card.querySelector('.dot').classList.add('scale-0');
            });
            el.classList.add('selected');
            el.querySelector('.dot').classList.remove('scale-0');
            selection.method = code;
            selection.methodName = name;
        }

        function handlePay() {
            if (!selection.method) {
                alert("Please select a payment method");
                return;
            }
            if (selection.processing) return;

            selection.processing = true;
            const btn = document.getElementById('payBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
            btn.disabled = true;

            const form = document.createElement('form');
            form.method = 'POST';
            
            // Redirect to J-Coin processor if selected, otherwise standard buy_now
            if (selection.method === 'jcoin') {
                form.action = '<?= BASE_URL ?>/payment/process_jcoin_payment';
            } else {
                form.action = '<?= BASE_URL ?>/payment/buy_now';
            }

            const params = {
                user_id: "<?= $user_id ?>",
                zone_id: "<?= $zone_id ?>",
                product_id: "<?= $product_id ?>",
                payment_method: selection.method,
                username: "<?= addslashes($verified_username) ?>",
                provider: "<?= $provider ?>",
                game_name: "<?= addslashes($game_name) ?>",
                email: "<?= $email ?>",
                final_purchase: 1
            };

            for (const key in params) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = params[key];
                form.appendChild(input);
            }

            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
