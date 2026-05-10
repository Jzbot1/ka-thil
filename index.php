<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
          (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Strict'
    ]);
    session_start();
}

require_once __DIR__ . '/includes/config.php';

$protocol = $https ? "https://" : "http://";
$current_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$base_url = $protocol . $_SERVER['HTTP_HOST'];
$query = $_GET['query'] ?? '';

// FETCH STORE SETTINGS
$setting = [
    'store_name'       => 'JZ Store',
    'store_logo'       => '', 
    'fav_icon'         => '', 
    'facebook'         => '',
    'instagram'        => '',
    'whatsapp'         => 'https://wa.me/',
    'whatsapp_group'   => 'https://chat.whatsapp.com/FtqISWOcbAf0fiDsroWIok?mode=ems_copy_t',
    'description'      => 'Purchase game topups for Mobile Legends, PUBG, Genshin Impact and more at best prices',
    'keywords'         => 'mobile legends topup, pubg uc, game topup, cheap diamonds, jz store, fast topup, mlbb diamonds',
    'is_banner_on'     => 1,
    'is_maintenance'   => 0 
];

$setting_sql = "SELECT * FROM fav_setting LIMIT 1";
$setting_result = $conn->query($setting_sql);

if ($setting_result && $setting_result->num_rows > 0) {
    $row = $setting_result->fetch_assoc();
    if (!empty($row['store_name'])) $setting['store_name'] = $row['store_name'];
    if (!empty($row['store_logo'])) $setting['store_logo'] = $row['store_logo'];
    if (!empty($row['fav_icon']))   $setting['fav_icon']   = $row['fav_icon'];
    if (!empty($row['facebook']))   $setting['facebook']   = $row['facebook'];
    if (!empty($row['instagram']))  $setting['instagram']  = $row['instagram'];
    if (!empty($row['whatsapp']))   $setting['whatsapp']   = $row['whatsapp'];
    if (!empty($row['whatsapp_group'])) $setting['whatsapp_group'] = $row['whatsapp_group'];
    if (!empty($row['description'])) $setting['description'] = $row['description'];
    if (!empty($row['keywords'])) $setting['keywords'] = $row['keywords']; 
    if (isset($row['is_banner_on'])) $setting['is_banner_on'] = $row['is_banner_on'];
    if (isset($row['is_maintenance'])) $setting['is_maintenance'] = $row['is_maintenance'];
    if (!empty($row['flash_sale_end'])) $setting['flash_sale_end'] = $row['flash_sale_end'];
}

// AUTO-LOGIN & BLOCK CHECK
if (!isset($_SESSION['user_id']) && !empty($_COOKIE['user_id'])) {
    $user_id = (int) $_COOKIE['user_id'];
    $stmt = $conn->prepare("SELECT id, username, role, subscription_status, status FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        if ($row['status'] === 'blocked') {
            setcookie('user_id', '', time() - 3600, '/', '', $https, true);
        } else {
            $_SESSION['user_id']    = $row['id'];
            $_SESSION['username']   = $row['username'];
            $_SESSION['role']       = $row['role'];
            $_SESSION['is_premium'] = ($row['subscription_status'] === 'active') ? 1 : 0;
        }
    } else {
        setcookie('user_id', '', time() - 3600, '/', '', $https, true);
    }
    $stmt->close();
}

if (isset($_SESSION['user_id'])) {
    $current_uid = $_SESSION['user_id'];
    $chk_stmt = $conn->prepare("SELECT status FROM users WHERE id = ?");
    $chk_stmt->bind_param("i", $current_uid);
    $chk_stmt->execute();
    $chk_res = $chk_stmt->get_result();
    if ($chk_res && $chk_row = $chk_res->fetch_assoc()) {
        if ($chk_row['status'] === 'blocked') {
            session_unset();
            session_destroy();
            setcookie('user_id', '', time() - 3600, '/', '', $https, true);
            header("Location: login?error=account_blocked");
            exit();
        }
    }
    $chk_stmt->close();
}

// MAINTENANCE MODE
$isAdmin = isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin';
if ($setting['is_maintenance'] == 1 && !$isAdmin) {
    ob_end_clean(); 
    include 'maintenance_template.php'; 
    exit(); 
}

// Fetch Banners
$banners = [];
if ($setting['is_banner_on'] == 1) {
    $banner_result = $conn->query("SELECT * FROM banners");
    if ($banner_result && $banner_result->num_rows > 0) {
        while ($row = $banner_result->fetch_assoc()) { $banners[] = $row; }
    }
}

// FETCH GAMES & CATEGORIES
$sections = [];
$game_data = [];

$cat_sql = "SELECT * FROM game_categories ORDER BY sort_order ASC, id ASC";
$cat_res = $conn->query($cat_sql);
if ($cat_res && $cat_res->num_rows > 0) {
    while($r = $cat_res->fetch_assoc()) {
        $sections[$r['slug']] = ['title' => $r['name'], 'color' => $r['color']];
        $game_data[$r['slug']] = [];
    }
} else {
    $sections = [
        'ml' => ['title' => 'Mobile Legends', 'color' => 'bg-blue-500'],
        'popular' => ['title' => 'Popular Games', 'color' => 'bg-blue-500'],
        'subs' => ['title' => 'Codes & Subs', 'color' => 'bg-yellow-500']
    ];
    $game_data = ['ml' => [], 'popular' => [], 'subs' => []];
}

$check_table = $conn->query("SHOW TABLES LIKE 'games'");
if ($check_table && $check_table->num_rows > 0) {
    $games_sql = "SELECT * FROM games ORDER BY sort_order ASC, id DESC";
    $games_result = $conn->query($games_sql);
    if ($games_result && $games_result->num_rows > 0) {
        while ($row = $games_result->fetch_assoc()) {
            if (isset($game_data[$row['category']])) { 
                $game_data[$row['category']][] = $row; 
            }
        }
    }
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($setting['store_name']); ?> - Best Game Topup</title>
    <link rel="icon" href="<?= htmlspecialchars($setting['fav_icon']); ?>">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=DynaPuff:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root { --bg-deep: #08203E; --theme-pink: #08203E; --theme-blue: #557C93; --theme-green: #80bf15; --theme-dark: #ffffff; --accent-blue: #3b82f6; --accent-purple: #8b5cf6; }
        body { font-family: 'Poppins', sans-serif; 
            background: hsla(213, 77%, 14%, 1);
            background: linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -moz-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -webkit-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            filter: progid: DXImageTransform.Microsoft.gradient( startColorstr="#08203E", endColorstr="#557C93", GradientType=1 );
            background-attachment: fixed; color: #ffffff; overflow-x: hidden; }
        .bg-blob { position: absolute; width: 500px; height: 500px; background: linear-gradient(135deg, rgba(8, 32, 62, 0.2) 0%, rgba(85, 124, 147, 0.2) 100%); filter: blur(80px); border-radius: 50%; animation: move 20s infinite alternate; }
        @keyframes move { from { transform: translate(-10%, -10%) scale(1); } to { transform: translate(20%, 20%) scale(1.2); } }
        .glass-panel { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .game-card:active { transform: scale(0.95); }
        .feature-card { background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.1); transition: all 0.3s ease; backdrop-filter: blur(8px); }
        .payment-chip { background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(8px); }
        .font-dynapuff { font-family: 'DynaPuff', cursive; }
        @keyframes scrollText { 0% { transform: translateX(100%); } 100% { transform: translateX(-100%); } }
        .animate-scroll-text { animation: scrollText 12s linear infinite; }
    </style>
    <script>
        window.flashSaleEnd = "<?= $setting['flash_sale_end'] ?? '' ?>";
    </script>
</head>
<body class="pb-32">
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
        <div class="bg-blob" style="top: -100px; left: -100px;"></div>
        <div class="bg-blob" style="bottom: -100px; right: -100px; animation-delay: -5s;"></div>
    </div>

    <header class="fixed top-0 w-full z-40 glass-panel h-16">
        <div class="max-w-md mx-auto px-4 h-full flex items-center justify-between">
            <div class="flex items-center gap-2">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile" class="w-9 h-9 rounded-full bg-white/10 flex items-center justify-center border border-white/10">
                        <i class="fa-solid fa-user text-themeDark text-xs"></i>
                    </a>
                <?php else: ?>
                    <a href="auth/login" class="px-4 py-1.5 rounded-full bg-themeDark text-white text-xs font-bold shadow-lg">LOG IN</a>
                <?php endif; ?>
            </div>
            <div class="font-bold text-lg text-themeDark font-dynapuff tracking-wider"><?= htmlspecialchars($setting['store_name']); ?></div>
            <div class="flex items-center gap-2">
                <button id="pwa-install-btn" class="hidden w-9 h-9 rounded-full bg-themeDark text-white flex items-center justify-center border border-white/30 shadow-lg animate-bounce">
                    <i class="fa-solid fa-download text-xs"></i>
                </button>
                <a href="notifications" class="w-9 h-9 rounded-full bg-white/10 flex items-center justify-center border border-white/10">
                        <i class="fa-solid fa-bell text-themeDark text-sm"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-md mx-auto pt-20 px-3">
        <?php if($setting['is_banner_on'] == 1 && !empty($banners)): ?>
        <div class="mb-6 rounded-2xl overflow-hidden shadow-2xl border border-white/5">
            <div class="swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($banners as $banner): 
                        $img_path = $banner['image_url'];
                        if (strpos($img_path, 'http') !== 0) {
                            $img_path = ltrim($img_path, '/');
                            if (!file_exists(__DIR__ . '/' . $img_path) && file_exists(__DIR__ . '/admin/' . $img_path)) {
                                $img_path = 'admin/' . $img_path;
                            }
                            $img_path = BASE_URL . '/' . $img_path;
                        }
                    ?>
                        <div class="swiper-slide">
                            <a href="<?= htmlspecialchars($banner['link_url'] ?? '#'); ?>">
                                <img src="<?= $img_path ?>" class="w-full aspect-[16/7] object-cover" loading="lazy">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </div>
        <!-- Premium Search Bar -->
        <div class="mb-6 relative group">
            <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none transition-colors group-focus-within:text-themeDark text-themeDark/40">
                <i class="fa-solid fa-magnifying-glass text-sm"></i>
            </div>
            <input type="text" id="gameSearch" 
                class="w-full bg-white/10 backdrop-blur-xl border border-white/10 rounded-2xl py-4 pl-12 pr-4 text-sm font-bold text-themeDark placeholder:text-themeDark/40 focus:bg-white/20 focus:border-themeDark/20 focus:ring-4 focus:ring-themeDark/5 transition-all outline-none shadow-xl shadow-themeDark/5" 
                placeholder="Search games or services...">
            <div id="searchBadge" class="absolute right-4 top-1/2 -translate-y-1/2 px-2 py-1 bg-themeDark/10 rounded-lg text-[8px] font-black uppercase text-themeDark/40 tracking-tighter opacity-0 transition-opacity">
                Press Enter
            </div>
        </div>
        <?php endif; ?>

        <div class="mb-6 bg-white/10 backdrop-blur-md border border-white/10 rounded-xl py-2.5 px-3 flex items-center gap-3 overflow-hidden shadow-sm">
            <i class="fa-solid fa-bullhorn text-themeDark text-xs"></i>
            <div class="overflow-hidden w-full relative h-5">
                <p class="animate-scroll-text whitespace-nowrap absolute text-[11px] text-themeDark/80 font-bold top-0">
                    Welcome to <?= htmlspecialchars($setting['store_name']); ?> - Instant Topup, 100% Secure! ⚡ Best Prices Guaranteed!
                </p>
            </div>
        </div>

        <div class="grid grid-cols-4 gap-2 mb-8">
            <?php 
                $links = [
                    ['icon' => 'fa-receipt', 'label' => 'Orders', 'color' => 'text-themeDark', 'url' => 'support'],
                    ['icon' => 'fa-wallet', 'label' => 'Wallet', 'color' => 'text-themeDark', 'url' => 'wallet'],
                    ['icon' => 'fa-ranking-star', 'label' => 'Top', 'color' => 'text-themeDark', 'url' => 'leaderboard'],
                    ['icon' => 'fa-gift', 'label' => 'Redeem', 'color' => 'text-themeDark', 'url' => 'redeem']
                ];
                foreach($links as $link): 
            ?>
            <a href="<?= $link['url'] ?>" class="flex flex-col items-center gap-2">
                <div class="w-12 h-12 rounded-2xl bg-white/10 border border-white/10 flex items-center justify-center shadow-lg transition hover:bg-white/20">
                    <i class="fa-solid <?= $link['icon'] ?> <?= $link['color'] ?> text-lg"></i>
                </div>
                <span class="text-[10px] text-themeDark/80 font-bold"><?= $link['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <?php
        // Fetch Real Flash Sale Data from Diamonds table
        $flash_sales = [];
        $fs_sql = "SELECT d.*, g.slug as game_slug 
                   FROM diamonds d 
                   LEFT JOIN games g ON d.game_id = g.id 
                   WHERE d.is_flash_sale = 1 OR d.is_flash_sale = '1' 
                   ORDER BY d.product_id DESC LIMIT 10";
        $fs_res = $conn->query($fs_sql);
        if($fs_res) {
            while($fs_row = $fs_res->fetch_assoc()) {
                // Map fields manually to ensure consistency
                $display_price = $fs_row['flash_price'];
                $original_price = $fs_row['price'];
                
                $fs_row['title'] = $fs_row['spu'];
                $fs_row['price'] = $display_price;
                $fs_row['old_price'] = $original_price;
                $fs_row['sold'] = $fs_row['flash_sold_percent'];
                $fs_row['image'] = $fs_row['image_url'];
                $flash_sales[] = $fs_row;
            }
        }

        $is_flash_active = !empty($flash_sales);
        if ($is_flash_active && !empty($setting['flash_sale_end'])) {
            if (strtotime($setting['flash_sale_end']) <= time()) {
                $is_flash_active = false;
            }
        }

        if($is_flash_active):
        ?>
        <!-- PREMIUM FLASH SALE SECTION -->
        <div class="mb-8">
            <h3 class="text-sm font-bold text-themeDark flex items-center justify-between gap-2 mb-4 px-1">
                <div class="flex items-center gap-2">
                    <span class="w-1 h-4 bg-orange-500 rounded-full shadow-[0_0_8px_rgba(249,115,22,0.5)] animate-pulse"></span>
                    <span class="flex items-center gap-1.5">
                        Flash Sale
                        <i class="fa-solid fa-bolt-lightning text-orange-500 text-sm animate-bounce"></i>
                    </span>
                </div>
                <div id="flash-sale-timer" class="flex items-center gap-1 bg-white/10 border border-white/10 px-2 py-0.5 rounded-lg shadow-sm">
                    <i class="fa-solid fa-clock text-orange-500 text-[9px] animate-pulse"></i>
                    <span class="text-[9px] font-black text-themeDark font-mono tracking-wider">00:00:00</span>
                </div>
            </h3>

            <div class="grid grid-cols-4 gap-x-2 gap-y-4">
                    <?php foreach($flash_sales as $flash): 
                        $f_img = $flash['image'];
                        if (strpos($f_img, 'http') !== 0) {
                            $f_img = ltrim($f_img, '/');
                            if (!file_exists(__DIR__ . '/' . $f_img) && file_exists(__DIR__ . '/admin/' . $f_img)) {
                                $f_img = 'admin/' . $f_img;
                            }
                            $f_img = BASE_URL . '/' . $f_img;
                        }
                    ?>
                        <a href="product/<?= htmlspecialchars($flash['game_slug'] ?? '') ?>?auto_select=<?= urlencode($flash['product_id']) ?>" class="game-card flex flex-col items-center gap-2 transition duration-300 group">
                            <?php 
                                $discount = 0;
                                if (!empty($flash['old_price']) && $flash['old_price'] > 0) {
                                    $discount = round((($flash['old_price'] - $flash['price']) / $flash['old_price']) * 100);
                                }
                            ?>
                            
                            <div class="w-14 h-14 rounded-xl bg-white/10 overflow-hidden shadow-lg border border-white/10 relative">
                                <?php if($discount > 0): ?>
                                <div class="absolute top-0 right-0 z-20 bg-themeDark text-white text-[6px] font-black px-1 py-0.5 rounded-bl-md uppercase tracking-tighter">
                                    -<?= $discount ?>%
                                </div>
                                <?php endif; ?>
                                <img src="<?= $f_img ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                            </div>
                            
                            <div class="flex flex-col items-center w-full px-0.5">
                                <span class="text-[10px] text-themeDark font-bold text-center leading-tight truncate w-full">
                                    <?= htmlspecialchars($flash['title']) ?>
                                </span>
                                
                                <div class="flex flex-col items-center justify-center w-full mt-0.5">
                                    <span class="text-[9px] font-black text-themeDark leading-none">₹<?= number_format($flash['price'], 0) ?></span>
                                    <span class="text-[6px] text-themeDark/40 line-through leading-none mt-0.5">₹<?= number_format($flash['old_price'], 0) ?></span>
                                </div>

                                <div class="w-full mt-1.5">
                                    <div class="w-full h-[3px] bg-themeDark/10 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-orange-400 to-red-500 rounded-full" style="width: <?= $flash['sold'] ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php 
        foreach($sections as $key => $sec): 
            if(!empty($game_data[$key])):
        ?>
        <div class="mb-8">
            <h3 class="text-sm font-bold text-themeDark flex items-center gap-2 mb-4 px-1">
                <span class="w-1 h-4 <?= htmlspecialchars($sec['color']) ?> rounded-full shadow-[0_0_8px_rgba(59,130,246,0.5)]"></span>
                <?= htmlspecialchars($sec['title']) ?>
            </h3>
            <div class="grid grid-cols-4 gap-x-2 gap-y-4">
                <?php foreach($game_data[$key] as $item): ?>
                <?php 
                    $game_slug = !empty($item['slug']) ? $item['slug'] : 'game-' . $item['id'];
                    $clean_url = "product/" . htmlspecialchars($game_slug); 
                    $is_out_of_stock = ($item['status'] == 0); 
                ?>
                <a href="<?= $is_out_of_stock ? 'javascript:void(0)' : $clean_url ?>" 
                   class="game-card flex flex-col items-center gap-2 transition duration-300 <?= $is_out_of_stock ? 'opacity-50 grayscale' : '' ?>"
                   data-title="<?= strtolower(htmlspecialchars($item['title'])) ?>">
                    <div class="w-14 h-14 rounded-xl bg-white/10 overflow-hidden shadow-lg border border-white/10 relative">
                        <?php 
                            $g_img = $item['image'];
                            if (strpos($g_img, 'http') !== 0) {
                                $g_img = ltrim($g_img, '/');
                                if (!file_exists(__DIR__ . '/' . $g_img) && file_exists(__DIR__ . '/admin/' . $g_img)) {
                                    $g_img = 'admin/' . $g_img;
                                }
                                $g_img = BASE_URL . '/' . $g_img;
                            }
                        ?>
                        <img src="<?= $g_img ?>" class="w-full h-full object-cover" loading="lazy">
                        <?php if($is_out_of_stock): ?>
                        <div class="absolute inset-0 bg-themeDark/80 flex items-center justify-center">
                            <span class="text-[7px] text-white font-black bg-red-600 px-1.5 py-0.5 rounded uppercase">Sold Out</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] text-themeDark font-bold text-center leading-tight">
                        <?= htmlspecialchars($item['title']) ?>
                    </span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php 
            endif;
        endforeach; 
        ?>

        <!-- ── Information & Help Section ── -->
        <div class="mb-8 mt-2">
            <h3 class="text-sm font-bold text-themeDark flex items-center justify-between gap-2 mb-4 px-1">
                <div class="flex items-center gap-2">
                    <span class="w-1 h-4 bg-cyan-400 rounded-full shadow-[0_0_8px_rgba(34,211,238,0.6)]"></span>
                    Information &amp; Help
                </div>
                <a href="<?= BASE_URL ?>/tutorial" class="text-[10px] font-black text-white/40 hover:text-white transition flex items-center gap-1">
                    View All <i class="fa-solid fa-arrow-right text-[8px]"></i>
                </a>
            </h3>

            <!-- 3 Tutorial Category Cards -->
            <div class="grid grid-cols-3 gap-2 mb-3">

                <!-- Recharge Card -->
                <a href="<?= BASE_URL ?>/tutorial#recharge"
                   onclick="sessionStorage.setItem('tutCat','recharge')"
                   class="group relative overflow-hidden rounded-2xl border border-white/10 p-3.5 flex flex-col gap-2 transition active:scale-95"
                   style="background:rgba(255,255,255,0.08);backdrop-filter:blur(12px);">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center text-lg mb-0.5"
                         style="background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.2);">
                        ⚡
                    </div>
                    <div>
                        <p class="text-[11px] font-black text-white leading-tight">Recharge</p>
                        <p class="text-[9px] text-white/40 font-bold mt-0.5">Recharge Dan</p>
                    </div>
                    <div class="flex items-center gap-1 mt-auto">
                        <span class="text-[8px] font-black text-cyan-400 bg-cyan-400/10 px-1.5 py-0.5 rounded-full">6 steps</span>
                    </div>
                    <!-- Hover glow -->
                    <div class="absolute inset-0 rounded-2xl opacity-0 group-hover:opacity-100 transition pointer-events-none"
                         style="background:radial-gradient(circle at 50% 0%,rgba(59,130,246,0.12),transparent 70%);"></div>
                </a>

                <!-- Wallet Card -->
                <a href="<?= BASE_URL ?>/tutorial#wallet"
                   onclick="sessionStorage.setItem('tutCat','wallet')"
                   class="group relative overflow-hidden rounded-2xl border border-white/10 p-3.5 flex flex-col gap-2 transition active:scale-95"
                   style="background:rgba(255,255,255,0.08);backdrop-filter:blur(12px);">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center text-lg mb-0.5"
                         style="background:rgba(139,92,246,0.15);border:1px solid rgba(139,92,246,0.2);">
                        💳
                    </div>
                    <div>
                        <p class="text-[11px] font-black text-white leading-tight">Wallet</p>
                        <p class="text-[9px] text-white/40 font-bold mt-0.5">Wallet Hman Dan</p>
                    </div>
                    <div class="flex items-center gap-1 mt-auto">
                        <span class="text-[8px] font-black text-purple-400 bg-purple-400/10 px-1.5 py-0.5 rounded-full">4 steps</span>
                    </div>
                    <div class="absolute inset-0 rounded-2xl opacity-0 group-hover:opacity-100 transition pointer-events-none"
                         style="background:radial-gradient(circle at 50% 0%,rgba(139,92,246,0.12),transparent 70%);"></div>
                </a>

                <!-- Register Card -->
                <a href="<?= BASE_URL ?>/tutorial#register"
                   onclick="sessionStorage.setItem('tutCat','register')"
                   class="group relative overflow-hidden rounded-2xl border border-white/10 p-3.5 flex flex-col gap-2 transition active:scale-95"
                   style="background:rgba(255,255,255,0.08);backdrop-filter:blur(12px);">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center text-lg mb-0.5"
                         style="background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.2);">
                        🙋
                    </div>
                    <div>
                        <p class="text-[11px] font-black text-white leading-tight">Register</p>
                        <p class="text-[9px] text-white/40 font-bold mt-0.5">Account Siam Dan</p>
                    </div>
                    <div class="flex items-center gap-1 mt-auto">
                        <span class="text-[8px] font-black text-emerald-400 bg-emerald-400/10 px-1.5 py-0.5 rounded-full">4 steps</span>
                    </div>
                    <div class="absolute inset-0 rounded-2xl opacity-0 group-hover:opacity-100 transition pointer-events-none"
                         style="background:radial-gradient(circle at 50% 0%,rgba(16,185,129,0.12),transparent 70%);"></div>
                </a>
            </div>

            <!-- WhatsApp Support + Tutorial CTA Row -->
            <div class="grid grid-cols-2 gap-2">
                <a href="<?= htmlspecialchars($setting['whatsapp'] ?? 'https://wa.me/') ?>"
                   class="flex items-center justify-center gap-2 rounded-2xl py-3.5 font-black text-xs transition active:scale-95 border border-white/10"
                   style="background:rgba(37,211,102,0.12);color:#25d366;">
                    <i class="fa-brands fa-whatsapp text-base"></i>
                    <span>WhatsApp Support</span>
                </a>
                <a href="<?= BASE_URL ?>/tutorial"
                   class="flex items-center justify-center gap-2 rounded-2xl py-3.5 font-black text-xs text-white transition active:scale-95 border border-white/10"
                   style="background:rgba(255,255,255,0.08);">
                    <i class="fa-solid fa-play-circle text-cyan-400 text-base"></i>
                    <span>Full Tutorial</span>
                </a>
            </div>
        </div>
        <!-- ── / Information & Help ── -->

        <?php include 'footer.php'; ?>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Swiper Init
            const swiper = new Swiper('.swiper', {
                loop: true,
                autoplay: { delay: 4000, disableOnInteraction: false },
                pagination: { el: '.swiper-pagination', clickable: true },
                effect: 'fade',
                fadeEffect: { crossFade: true },
            });

            // Search Logic
            const gameSearch = document.getElementById('gameSearch');
            const gameCards = document.querySelectorAll('.game-card');
            const sections = document.querySelectorAll('.game-section');

            gameSearch.addEventListener('input', function(e) {
                const term = e.target.value.toLowerCase().trim();
                
                gameCards.forEach(card => {
                    const title = card.getAttribute('data-title');
                    if (title.includes(term)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Hide empty sections
                sections.forEach(section => {
                    const visibleCards = section.querySelectorAll('.game-card[style="display: flex;"]');
                    const hasVisible = visibleCards.length > 0 || term === '';
                    section.style.display = hasVisible ? 'block' : 'none';
                });
            });

            // Flash Sale Timer
            const timerEl = document.querySelector('#flash-sale-timer span');
            if (timerEl && window.flashSaleEnd) {
                const endTime = new Date(window.flashSaleEnd).getTime();
                
                const updateTimer = () => {
                    const now = new Date().getTime();
                    const diff = endTime - now;

                    if (diff <= 0) {
                        timerEl.innerText = "EXPIRED";
                        return;
                    }

                    const h = Math.floor(diff / (1000 * 60 * 60));
                    const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const s = Math.floor((diff % (1000 * 60)) / 1000);

                    timerEl.innerText = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
                };

                setInterval(updateTimer, 1000);
                updateTimer();
            }
        });
    </script>
</body>
</html>
