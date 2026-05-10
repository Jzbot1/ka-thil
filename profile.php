<?php
/**
 * profile.php - Modernized User Profile & Admin Dashboard
 */
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. AUTH CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// 2. FETCH USER DATA
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userData) {
    header("Location: auth/logout");
    exit();
}

$username = $userData['username'];
$role = $userData['role']; 
$balance = $userData['wallet_balance'] ?? 0;
$mobile = $userData['mobile'] ?? 'Not linked';
$email = $userData['email'] ?? 'Not set';

// 3. FETCH STORE SETTINGS
$setting = ['store_name' => 'JZ Store'];
$setting_result = $conn->query("SELECT * FROM fav_setting LIMIT 1");
if ($setting_result && $row = $setting_result->fetch_assoc()) {
    foreach ($row as $key => $val) { if (!empty($val)) $setting[$key] = $val; }
}

// 4. ADMIN STATISTICS (If Admin)
$stats = [
    'total_sales' => 0,
    'total_orders' => 0,
    'today_sales' => 0,
    'month_sales' => 0
];

if ($role === 'admin') {
    $res = $conn->query("SELECT 
        COUNT(id) as total_count,
        SUM(CASE WHEN status IN ('Success', 'Completed') THEN 1 ELSE 0 END) as success_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_count,
        SUM(CASE WHEN status IN ('Success', 'Completed') THEN price ELSE 0 END) as total_amt
        FROM orders");
    $row = $res->fetch_assoc();
    $stats['total_orders'] = $row['total_count'] ?? 0;
    $stats['success_orders'] = $row['success_count'] ?? 0;
    $stats['pending_orders'] = $row['pending_count'] ?? 0;
    $stats['today_orders'] = $row['today_count'] ?? 0;
    $stats['total_sales'] = $row['total_amt'] ?? 0;

    $res = $conn->query("SELECT SUM(price) as amt FROM orders WHERE status IN ('Success', 'Completed') AND DATE(created_at) = CURDATE()");
    $stats['today_sales'] = $res->fetch_assoc()['amt'] ?? 0;

    $res = $conn->query("SELECT SUM(price) as amt FROM orders WHERE status IN ('Success', 'Completed') AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stats['month_sales'] = $res->fetch_assoc()['amt'] ?? 0;
}

// 5. USER STATISTICS
$userStats = [
    'total' => 0,
    'success' => 0,
    'pending' => 0,
    'today' => 0
];

$stmt = $conn->prepare("SELECT 
    COUNT(id) as total,
    SUM(CASE WHEN status IN ('Success', 'Completed') THEN 1 ELSE 0 END) as success,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
    FROM orders WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resStats = $stmt->get_result()->fetch_assoc();
if ($resStats) {
    $userStats['total'] = (int)$resStats['total'];
    $userStats['success'] = (int)$resStats['success'];
    $userStats['pending'] = (int)$resStats['pending'];
    $userStats['today'] = (int)$resStats['today'];
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Profile - <?= htmlspecialchars($setting['store_name']); ?></title>
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
        .admin-card { background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05)); border: 1px solid rgba(255, 255, 255, 0.1); }
    </style>
</head>
<body class="pb-24">

    <!-- HEADER -->
    <header class="fixed top-0 w-full z-50 bg-[#1e293b]/80 backdrop-blur-xl h-16 border-b border-[#334155]">
        <div class="max-w-md mx-auto px-5 h-full flex items-center justify-between">
                <a href="index" class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center border border-white/10"><i class="fa-solid fa-arrow-left text-themeDark text-sm"></i></a>
            <div class="font-bold text-lg text-themeDark">Account</div>
            <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center border border-white/10 text-rose-400">
                <a href="auth/logout"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 mt-20">
        
        <!-- PROFILE INFO -->
        <div class="flex flex-col items-center mb-8">
            <div class="relative mb-4">
                <div class="w-24 h-24 rounded-full border-4 border-blue-600/30 p-1">
                    <img src="https://api.dicebear.com/7.x/bottts/svg?seed=<?= urlencode($username) ?>" class="w-full h-full rounded-full bg-navyBlue">
                </div>
                <?php if($role === 'admin'): ?>
                <div class="absolute -bottom-1 -right-1 bg-blue-600 text-white text-[10px] font-black px-2 py-0.5 rounded-lg border-2 border-navyDark uppercase tracking-tighter">Admin</div>
                <?php endif; ?>
            </div>
            <h2 class="text-xl font-black text-white"><?= htmlspecialchars($username) ?></h2>
            <p class="text-xs text-white/50 font-medium">Member since <?= date('M Y', strtotime($userData['created_at'] ?? 'now')) ?></p>
        </div>

        <!-- USER STATS GRID -->
        <div class="grid grid-cols-4 gap-2 mb-6">
            <div class="glass-panel rounded-2xl p-3 flex flex-col items-center justify-center text-center">
                <span class="text-[14px] font-black text-white"><?= $userStats['total'] ?></span>
                <span class="text-[7px] font-bold text-white/40 uppercase tracking-tighter">Total</span>
            </div>
            <div class="glass-panel rounded-2xl p-3 flex flex-col items-center justify-center text-center">
                <span class="text-[14px] font-black text-emerald-600"><?= $userStats['success'] ?></span>
                <span class="text-[7px] font-bold text-emerald-600/50 uppercase tracking-tighter">Success</span>
            </div>
            <div class="glass-panel rounded-2xl p-3 flex flex-col items-center justify-center text-center">
                <span class="text-[14px] font-black text-amber-600"><?= $userStats['pending'] ?></span>
                <span class="text-[7px] font-bold text-amber-600/50 uppercase tracking-tighter">Pending</span>
            </div>
            <div class="glass-panel rounded-2xl p-3 flex flex-col items-center justify-center text-center">
                <span class="text-[14px] font-black text-rose-600"><?= $userStats['today'] ?></span>
                <span class="text-[7px] font-bold text-rose-600/50 uppercase tracking-tighter">Today</span>
            </div>
        </div>

        <!-- WALLET CARD -->
        <div class="glass-panel rounded-[32px] p-6 mb-8 relative overflow-hidden group border border-white/10">
            <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
                <i class="fa-solid fa-wallet text-7xl -rotate-12"></i>
            </div>
            <div class="flex justify-between items-center mb-4">
                <div>
                    <p class="text-[10px] text-white/50 font-black uppercase tracking-widest">Wallet Balance</p>
                    <h3 class="text-3xl font-black text-white">₹<?= number_format($balance, 2) ?></h3>
                </div>
                <a href="wallet" class="w-12 h-12 rounded-2xl bg-white text-black flex items-center justify-center shadow-lg shadow-white/5 hover:scale-105 transition-transform">
                    <i class="fa-solid fa-plus"></i>
                </a>
            </div>
            <div class="flex gap-3">
                <a href="history" class="flex-1 py-2 rounded-xl bg-white/5 border border-white/10 text-center text-[10px] font-black text-white/60 uppercase tracking-tighter hover:bg-white/10 transition">Order History</a>
                <a href="wallet" class="flex-1 py-2 rounded-xl bg-white/5 border border-white/10 text-center text-[10px] font-black text-white/60 uppercase tracking-tighter hover:bg-white/10 transition">Wallet Logs</a>
            </div>
        </div>

        <!-- ADMIN PANEL (If Admin) -->
        <?php if($role === 'admin'): ?>
        <div class="mb-8">
            <div class="flex items-center justify-between px-2 mb-4">
                <h4 class="text-xs font-black text-white uppercase tracking-widest">Admin Dashboard</h4>
                <span class="text-[10px] text-white/60 font-bold">Real-time Stats</span>
            </div>
            
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="glass-panel admin-card rounded-2xl p-4">
                    <p class="text-[10px] text-white/60 font-bold uppercase mb-1">Today (<?= date('l') ?>)</p>
                    <p class="text-lg font-black text-rose-400"><?= number_format($stats['today_orders']) ?> Orders</p>
                    <p class="text-[9px] font-bold text-white/40">₹<?= number_format($stats['today_sales'], 0) ?> Revenue</p>
                </div>
                <div class="glass-panel admin-card rounded-2xl p-4">
                    <p class="text-[10px] text-white/60 font-bold uppercase mb-1">Pending Orders</p>
                    <p class="text-lg font-black text-amber-400"><?= number_format($stats['pending_orders']) ?></p>
                    <p class="text-[9px] font-bold text-white/40">Action Needed</p>
                </div>
                <div class="glass-panel admin-card rounded-2xl p-4">
                    <p class="text-[10px] text-white/60 font-bold uppercase mb-1">Successful Orders</p>
                    <p class="text-lg font-black text-emerald-400"><?= number_format($stats['success_orders']) ?></p>
                    <p class="text-[9px] font-bold text-white/40">Lifetime Success</p>
                </div>
                <div class="glass-panel admin-card rounded-2xl p-4">
                    <p class="text-[10px] text-white/60 font-bold uppercase mb-1">Total Sales</p>
                    <p class="text-lg font-black text-white">₹<?= number_format($stats['total_sales'], 0) ?></p>
                    <p class="text-[9px] font-bold text-white/40">Total Volume</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <a href="admin/admin_product" class="glass-panel rounded-2xl p-4 flex items-center gap-3 hover:bg-white/10 transition border border-white/10">
                    <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-white"><i class="fa-solid fa-box-open"></i></div>
                    <span class="text-[10px] font-black uppercase text-white">Products</span>
                </a>
                <a href="admin/admin_blog" class="glass-panel rounded-2xl p-4 flex items-center gap-3 hover:bg-white/10 transition border border-white/10">
                    <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-white"><i class="fa-solid fa-newspaper"></i></div>
                    <span class="text-[10px] font-black uppercase text-white">Blogs</span>
                </a>
                <a href="admin/admin_game" class="glass-panel rounded-2xl p-4 flex items-center gap-3 hover:bg-white/10 transition border border-white/10">
                    <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-white"><i class="fa-solid fa-gamepad"></i></div>
                    <span class="text-[10px] font-black uppercase text-white">Games</span>
                </a>
                <a href="admin/admin_order" class="glass-panel rounded-2xl p-4 flex items-center gap-3 hover:bg-white/10 transition border border-white/10">
                    <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-white"><i class="fa-solid fa-cart-shopping"></i></div>
                    <span class="text-[10px] font-black uppercase text-white">Orders</span>
                </a>
                <a href="admin/adminuser" class="glass-panel rounded-2xl p-4 flex items-center gap-3 hover:bg-white/10 transition border border-white/10">
                    <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-white"><i class="fa-solid fa-users-gear"></i></div>
                    <span class="text-[10px] font-black uppercase text-white">Users</span>
                </a>
                <a href="admin/admin_notification" class="glass-panel rounded-2xl p-4 flex items-center gap-3 hover:bg-white/10 transition border border-white/10">
                    <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-white"><i class="fa-solid fa-bullhorn"></i></div>
                    <span class="text-[10px] font-black uppercase text-white">Notify</span>
                </a>
                <a href="admin/settings" class="glass-panel rounded-2xl p-4 flex items-center gap-3 hover:bg-white/10 transition border border-white/10">
                    <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-white"><i class="fa-solid fa-sliders"></i></div>
                    <span class="text-[10px] font-black uppercase text-white">Settings</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- ACCOUNT SETTINGS -->
        <div class="mb-8">
            <h4 class="text-xs font-black text-white/60 uppercase tracking-widest px-2 mb-4">Account Settings</h4>
            <div class="glass-panel rounded-[24px] overflow-hidden border border-white/10">
                <div class="p-4 border-b border-white/10 flex items-center justify-between group">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center text-white/60"><i class="fa-solid fa-phone"></i></div>
                        <div>
                            <p class="text-[10px] text-white/50 font-bold uppercase tracking-tighter">Mobile Number</p>
                            <p class="text-sm font-bold text-white"><?= htmlspecialchars($mobile) ?></p>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-right text-[10px] text-themeDark/40 group-hover:translate-x-1 transition-transform"></i>
                </div>

                <a href="auth/change_password" class="p-4 border-b border-white/10 flex items-center justify-between group hover:bg-white/10 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center text-white"><i class="fa-solid fa-lock"></i></div>
                        <span class="text-[10px] font-black uppercase text-white tracking-widest">Change Password</span>
                    </div>
                    <i class="fa-solid fa-chevron-right text-[10px] text-white/40 group-hover:translate-x-1 transition-transform"></i>
                </a>

                <a href="auth/logout" class="p-4 flex items-center justify-between group hover:bg-white/10 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-rose-500/10 flex items-center justify-center text-rose-400"><i class="fa-solid fa-power-off"></i></div>
                        <span class="text-[10px] font-black uppercase text-rose-400 tracking-widest">Logout Account</span>
                    </div>
                    <i class="fa-solid fa-chevron-right text-[10px] text-rose-300 group-hover:translate-x-1 transition-transform"></i>
                </a>
            </div>
        </div>

        <p class="text-center text-[10px] text-white/50 font-medium px-8">Your account is secured with 256-bit encryption. Always logout from shared devices.</p>

    </main>

    <!-- FOOTER NAV -->
    <?php include 'footer.php'; ?>

</body>
</html>