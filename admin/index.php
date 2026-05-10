<?php
require_once __DIR__ . '/../includes/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); 
    exit("Access Denied");
}

// Fetch settings
$res_set = $conn->query("SELECT * FROM fav_setting LIMIT 1");
$setting = ($res_set && $res_set->num_rows > 0) ? $res_set->fetch_assoc() : [];

// Stats
$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0] ?? 0;
$total_orders = $conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0] ?? 0;
$pending_orders = $conn->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetch_row()[0] ?? 0;
$wallet_balance = $conn->query("SELECT SUM(wallet_balance) FROM users")->fetch_row()[0] ?? 0;

$flash_end = $setting['flash_sale_end'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Dashboard – <?= htmlspecialchars($setting['store_name'] ?? 'JZ Store') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=DynaPuff:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f8fafc; color: #0f172a; }
        .glass { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.3); }
        .font-dynapuff { font-family: 'DynaPuff', cursive; }
    </style>
</head>
<body class="pb-20">

    <header class="sticky top-0 z-50 glass border-b border-slate-200 px-4 py-4">
        <div class="max-w-md mx-auto flex justify-between items-center">
            <h1 class="text-xl font-black font-dynapuff italic tracking-wide text-indigo-600">Admin <span class="text-slate-400">Panel</span></h1>
            <a href="settings.php" class="w-10 h-10 rounded-2xl bg-white flex items-center justify-center border border-slate-200 shadow-sm">
                <i class="fa-solid fa-gear text-slate-400"></i>
            </a>
        </div>
    </header>

    <main class="max-w-md mx-auto p-4 space-y-6">

        <!-- Flash Sale Status Card -->
        <div class="bg-slate-900 rounded-[2.5rem] p-6 text-white shadow-2xl relative overflow-hidden">
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-indigo-500/20 rounded-full blur-3xl"></div>
            
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-orange-500 rounded-2xl flex items-center justify-center text-xl animate-pulse shadow-[0_0_15px_rgba(249,115,22,0.4)]">
                    ⚡
                </div>
                <div>
                    <h2 class="text-sm font-black uppercase tracking-widest">Flash Sale Monitor</h2>
                    <p class="text-[10px] text-white/40 font-bold">Live Countdown & Status</p>
                </div>
            </div>

            <div id="admin-flash-timer" class="bg-white/5 border border-white/10 rounded-3xl p-6 text-center">
                <div class="text-[10px] font-black text-orange-500 uppercase tracking-[0.3em] mb-2">Time Remaining</div>
                <div id="flash-countdown" class="text-4xl font-black font-mono tracking-tighter">00:00:00</div>
                
                <div class="mt-4 pt-4 border-t border-white/5 flex items-center justify-between">
                    <div class="text-left">
                        <p class="text-[9px] font-bold text-white/30 uppercase">Ends At</p>
                        <p class="text-xs font-bold text-white/80"><?= !empty($flash_end) ? date('d M, h:i A', strtotime($flash_end)) : 'Not Set' ?></p>
                    </div>
                    <a href="settings.php" class="bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase transition">Edit</a>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-white p-5 rounded-[2rem] border border-slate-100 shadow-sm">
                <i class="fa-solid fa-users text-blue-500 mb-2"></i>
                <div class="text-2xl font-black"><?= $total_users ?></div>
                <div class="text-[10px] font-bold text-slate-400 uppercase">Total Users</div>
            </div>
            <div class="bg-white p-5 rounded-[2rem] border border-slate-100 shadow-sm">
                <i class="fa-solid fa-receipt text-emerald-500 mb-2"></i>
                <div class="text-2xl font-black"><?= $total_orders ?></div>
                <div class="text-[10px] font-bold text-slate-400 uppercase">Total Orders</div>
            </div>
            <div class="bg-white p-5 rounded-[2rem] border border-slate-100 shadow-sm">
                <i class="fa-solid fa-clock text-orange-500 mb-2"></i>
                <div class="text-2xl font-black text-orange-600"><?= $pending_orders ?></div>
                <div class="text-[10px] font-bold text-slate-400 uppercase">Pending</div>
            </div>
            <div class="bg-white p-5 rounded-[2rem] border border-slate-100 shadow-sm">
                <i class="fa-solid fa-wallet text-indigo-500 mb-2"></i>
                <div class="text-2xl font-black">₹<?= number_format($wallet_balance, 0) ?></div>
                <div class="text-[10px] font-bold text-slate-400 uppercase">User Balance</div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="space-y-3">
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest px-2">Quick Management</h3>
            <div class="grid grid-cols-1 gap-2">
                <a href="admin_order.php" class="bg-white p-4 rounded-2xl border border-slate-100 flex items-center gap-4 hover:bg-slate-50 transition">
                    <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600"><i class="fa-solid fa-shopping-cart"></i></div>
                    <div class="flex-1 font-bold text-sm text-slate-700">Manage Orders</div>
                    <i class="fa-solid fa-chevron-right text-slate-300 text-xs"></i>
                </a>
                <a href="admin_product.php" class="bg-white p-4 rounded-2xl border border-slate-100 flex items-center gap-4 hover:bg-slate-50 transition">
                    <div class="w-10 h-10 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600"><i class="fa-solid fa-box"></i></div>
                    <div class="flex-1 font-bold text-sm text-slate-700">Inventory & Products</div>
                    <i class="fa-solid fa-chevron-right text-slate-300 text-xs"></i>
                </a>
                <a href="admin_game.php" class="bg-white p-4 rounded-2xl border border-slate-100 flex items-center gap-4 hover:bg-slate-50 transition">
                    <div class="w-10 h-10 bg-pink-50 rounded-xl flex items-center justify-center text-pink-600"><i class="fa-solid fa-gamepad"></i></div>
                    <div class="flex-1 font-bold text-sm text-slate-700">Games & Categories</div>
                    <i class="fa-solid fa-chevron-right text-slate-300 text-xs"></i>
                </a>
            </div>
        </div>

    </main>

    <script>
        window.flashSaleEnd = "<?= !empty($flash_end) ? date('c', strtotime($flash_end)) : '' ?>";
        
        function updateTimer() {
            const timerEl = document.getElementById('flash-countdown');
            if (!timerEl || !window.flashSaleEnd) return;

            const endTime = new Date(window.flashSaleEnd.replace(/\s/, 'T')).getTime();
            const now = new Date().getTime();
            const diff = endTime - now;

            if (diff <= 0) {
                timerEl.innerText = "EXPIRED";
                timerEl.classList.add('text-red-500');
                return;
            }

            const h = Math.floor(diff / (1000 * 60 * 60));
            const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const s = Math.floor((diff % (1000 * 60)) / 1000);

            timerEl.innerText = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
        }

        if (window.flashSaleEnd) {
            setInterval(updateTimer, 1000);
            updateTimer();
        } else {
            document.getElementById('flash-countdown').innerText = "NOT ACTIVE";
            document.getElementById('flash-countdown').classList.add('text-white/20');
        }
    </script>
</body>
</html>
