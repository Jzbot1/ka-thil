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

// Generate CSRF Token for security
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];

// ✅ 2. FETCH USER BALANCE
$stmt = $conn->prepare("SELECT wallet_balance, wallet_approved, username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$balance = $user_data['wallet_balance'] ?? 0;
$is_approved = (int)($user_data['wallet_approved'] ?? 0);
$username = $user_data['username'] ?? 'User';

// FETCH STORE SETTINGS
$setting = ['store_name' => 'JZ Store'];
$setting_result = $conn->query("SELECT * FROM fav_setting LIMIT 1");
if ($setting_result && $row = $setting_result->fetch_assoc()) {
    foreach ($row as $key => $val) { if (!empty($val)) $setting[$key] = $val; }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Wallet - <?= htmlspecialchars($setting['store_name']); ?></title>
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
        .amount-card { transition: all 0.2s ease; border: 1px solid rgba(255,255,255,0.1); cursor: pointer; background: rgba(255, 255, 255, 0.1); }
        .amount-card.selected { background: rgba(255, 255, 255, 0.2); border-color: #ffffff; box-shadow: 0 0 15px rgba(255, 255, 255, 0.1); }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-10px); } 100% { transform: translateY(0px); } }
        .animate-float { animation: float 4s ease-in-out infinite; }
    </style>
</head>
<body class="pb-24">

    <!-- HEADER -->
    <header class="fixed top-0 w-full z-50 bg-black/20 backdrop-blur-xl h-16 border-b border-white/10">
        <div class="max-w-md mx-auto px-5 h-full flex items-center justify-between">
            <a href="index" class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center border border-white/10"><i class="fa-solid fa-arrow-left text-themeDark text-sm"></i></a>
            <div class="font-bold text-lg text-themeDark">My Wallet</div>
            <div class="w-10"></div>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 mt-20">
        
        <!-- BALANCE CARD -->
        <div class="glass-panel rounded-[32px] p-8 text-center relative overflow-hidden mb-8 border border-white/10">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                <i class="fa-solid fa-wallet text-8xl -rotate-12"></i>
            </div>
            
            <p class="text-[10px] text-white/50 font-black uppercase tracking-[3px] mb-2">Available Balance</p>
            <h1 class="text-5xl font-black text-white mb-2">₹<?= number_format($balance, 2) ?></h1>
            <p class="text-xs text-white/60 font-bold">Hello, <?= htmlspecialchars($username) ?>!</p>
            
            <div class="mt-6 flex justify-center gap-2">
                <?php if ($is_approved): ?>
                    <div class="px-3 py-1 bg-green-500/10 text-green-400 text-[9px] font-black uppercase rounded-full border border-green-500/20">Verified Account</div>
                <?php else: ?>
                    <div class="px-3 py-1 bg-rose-500/10 text-rose-400 text-[9px] font-black uppercase rounded-full border border-rose-500/20">Pending Admin Approval</div>
                <?php endif; ?>
                <div class="px-3 py-1 bg-blue-500/10 text-blue-400 text-[9px] font-black uppercase rounded-full border border-blue-500/20">Instant Add</div>
            </div>
        </div>

        <!-- VIRTUAL CARD PROMO -->
        <a href="wallet_cards" class="block glass-panel rounded-3xl p-5 mb-8 border border-indigo-500/30 bg-indigo-500/5 relative overflow-hidden group">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-indigo-500/10 rounded-full blur-2xl group-hover:bg-indigo-500/20 transition-all"></div>
            <div class="flex items-center gap-4 relative z-10">
                <div class="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center text-xl shadow-lg shadow-indigo-600/20">
                    <i class="fa-solid fa-credit-card text-white"></i>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-black text-white">Virtual Debit Card</h3>
                    <p class="text-[10px] text-indigo-300 font-bold uppercase tracking-widest">Internal Wallet Payments</p>
                </div>
                <i class="fa-solid fa-chevron-right text-indigo-500 text-xs mr-2 group-hover:translate-x-1 transition-transform"></i>
            </div>
        </a>

        <!-- ADD FUNDS SECTION -->
        <div class="space-y-6">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-sm font-black text-white">Add Funds</h3>
                <span class="text-[10px] text-white/50 font-bold uppercase">Select Amount</span>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <?php 
                $amounts = [100, 200, 500, 1000, 2000, 5000];
                foreach($amounts as $amt): 
                ?>
                <div onclick="selectAmount(<?= $amt ?>, this)" class="amount-card glass-panel rounded-2xl p-4 text-center">
                    <p class="text-[10px] text-white/40 font-bold mb-1">Add</p>
                    <p class="text-lg font-black text-white">₹<?= $amt ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="glass-panel rounded-3xl p-4 border border-white/10">
                <p class="text-[10px] text-white/40 font-bold uppercase mb-3 ml-1">Custom Amount</p>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-lg font-black text-white/30">₹</span>
                    <input type="number" id="custom-amount" placeholder="Enter amount" class="w-full bg-white/5 border border-white/10 rounded-2xl py-4 pl-10 pr-4 text-white font-black text-lg focus:outline-none focus:border-white transition-all placeholder:text-white/20">
                </div>
            </div>

            <button onclick="handleAddFunds()" class="w-full py-5 bg-white hover:bg-white/90 text-black rounded-[24px] font-black text-sm shadow-xl shadow-white/5 transition-all flex items-center justify-center gap-3">
                <i class="fa-solid fa-plus-circle"></i>
                Add Funds Now
            </button>
        </div>

        <!-- TRANSACTION HISTORY -->
        <div class="mt-12 space-y-4">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-sm font-black text-white">Recent Activity</h3>
                <a href="history" class="text-[9px] text-blue-600 font-bold uppercase tracking-widest">View All</a>
            </div>

            <div class="space-y-3">
                <?php
                $activity_stmt = $conn->prepare("SELECT product_name, price, status, created_at, product_id FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                $activity_stmt->bind_param("i", $user_id);
                $activity_stmt->execute();
                $activity_res = $activity_stmt->get_result();
                
                if ($activity_res->num_rows > 0):
                    while($act = $activity_res->fetch_assoc()):
                        $is_topup = ($act['product_id'] === 'WALLET_TOPUP');
                        $status_color = 'text-amber-500';
                        if ($act['status'] === 'completed' || $act['status'] === 'success') $status_color = 'text-green-500';
                        if ($act['status'] === 'failed' || $act['status'] === 'cancelled') $status_color = 'text-rose-500';
                ?>
                <div class="glass-panel p-4 rounded-3xl flex items-center gap-4 border border-white/40">
                    <div class="w-12 h-12 rounded-2xl <?= $is_topup ? 'bg-green-500/10 text-green-600' : 'bg-blue-500/10 text-blue-600' ?> flex items-center justify-center text-lg">
                        <i class="fa-solid <?= $is_topup ? 'fa-arrow-down-long' : 'fa-cart-shopping' ?>"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-xs font-black text-white line-clamp-1"><?= htmlspecialchars($act['product_name']) ?></h4>
                        <p class="text-[9px] font-bold text-white/40 uppercase tracking-tighter"><?= date('d M, h:i A', strtotime($act['created_at'])) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-black <?= $is_topup ? 'text-green-400' : 'text-white' ?>">
                            <?= $is_topup ? '+' : '-' ?>₹<?= number_format($act['price'], 2) ?>
                        </p>
                        <p class="text-[8px] font-black uppercase <?= $status_color ?>"><?= $act['status'] ?></p>
                    </div>
                </div>
                <?php 
                    endwhile;
                else:
                ?>
                <div class="py-10 text-center glass-panel rounded-3xl border border-white/10">
                    <i class="fa-solid fa-clock-rotate-left text-3xl text-white/10 mb-2"></i>
                    <p class="text-[10px] font-bold text-white/30 uppercase tracking-widest">No recent transactions</p>
                </div>
                <?php endif; $activity_stmt->close(); ?>
            </div>
        </div>

        <p class="text-[10px] text-center text-white/60 font-medium px-4 mt-8">By adding funds, you agree to our terms. Funds added to the wallet are non-refundable and can only be used for game topups.</p>

    </main>

    <!-- FOOTER NAV -->
    <?php include 'footer.php'; ?>

    <script>
        let selectedAmt = 0;

        function selectAmount(amt, el) {
            selectedAmt = amt;
            document.querySelectorAll('.amount-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('custom-amount').value = '';
        }

        document.getElementById('custom-amount').addEventListener('input', function() {
            if(this.value > 0) {
                selectedAmt = this.value;
                document.querySelectorAll('.amount-card').forEach(c => c.classList.remove('selected'));
            }
        });

        async function handleAddFunds() {
            if (selectedAmt < 10) {
                alert("Minimum amount is ₹10");
                return;
            }

            const btn = event.currentTarget;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
            btn.disabled = true;

            try {
                // We'll create a dynamic form and submit to a special wallet handler
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?= BASE_URL ?>/payment/initiate_wallet';

                const fields = {
                    amount: selectedAmt,
                    user_id: '<?= $user_id ?>',
                    email: '<?= $_SESSION['username'] ?>',
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                };

                for (const key in fields) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = fields[key];
                    form.appendChild(input);
                }

                document.body.appendChild(form);
                form.submit();

            } catch (error) {
                console.error(error);
                alert("An error occurred.");
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
