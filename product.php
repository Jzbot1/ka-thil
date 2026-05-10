<?php
// ✅ 1. SECURITY & SESSION START
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self' https:;");

require_once __DIR__ . '/config.php';
// generate_sign include removed from here as it's now in the API file

// --- FETCH STORE SETTINGS ---
$setting = [
    'store_name'    => 'JZ Store',
    'fav_icon'      => 'https://jzstore.in/logo/jzstorelogo.jpg', 
    'whatsapp'      => 'https://wa.me/918730063275', 
];

$check_setting = $conn->query("SHOW TABLES LIKE 'fav_setting'");
if ($check_setting && $check_setting->num_rows > 0) {
    $setting_result = $conn->query("SELECT * FROM fav_setting LIMIT 1");
    if ($setting_result && $setting_result->num_rows > 0) {
        $row = $setting_result->fetch_assoc();
        if (!empty($row['store_name'])) $setting['store_name'] = $row['store_name'];
        if (!empty($row['fav_icon']))   $setting['fav_icon']   = $row['fav_icon'];
        if (!empty($row['whatsapp']))   $setting['whatsapp']   = $row['whatsapp'];
    }
}

// ✅ 2. DYNAMIC ROUTING LOGIC
$game_slug = $_GET['game'] ?? 'mobile-legends';
$cat_slug = $_GET['category'] ?? '';

$stmt_game = $conn->prepare("SELECT id, title, image, id_system, provider, description_title, description_body, external_url FROM games WHERE slug = ? AND status = 1 LIMIT 1");
$stmt_game->bind_param("s", $game_slug);
$stmt_game->execute();
$game = $stmt_game->get_result()->fetch_assoc();

if (!$game) { die("Game not found or inactive."); }

$game_id = $game['id'];
$id_system = $game['id_system'] ?? 'user_zone_input'; 
$game_provider = $game['provider'] ?? 'smileone'; 
$game_image_url = (!empty($game['image'])) ? ltrim($game['image'], '/') : 'https://via.placeholder.com/800x400';

// ✅ 3. USER AUTHENTICATION
$is_logged_in = isset($_SESSION['user_id']);
$user_wallet_balance = 0.00;
$user_mobile = '';

if ($is_logged_in) {
    $stmt = $conn->prepare("SELECT wallet_balance, mobile, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user_role = 'user';
    $stmt->bind_result($user_wallet_balance, $user_mobile, $user_role);
    $stmt->fetch();
    $stmt->close();
}

// ✅ 4. AJAX HANDLER REMOVED (Moved to /api/check_username.php)

// ✅ 5. CATEGORY & PRODUCT FETCHING
$cat_stmt = $conn->prepare("SELECT id, name, slug FROM categories WHERE game_id = ?");
$cat_stmt->bind_param("i", $game_id);
$cat_stmt->execute();
$categories_result = $cat_stmt->get_result();
$categories = [];
$active_cat_id = null;
while($catRow = $categories_result->fetch_assoc()) {
    $categories[] = $catRow;
    if ($cat_slug == $catRow['slug']) { $active_cat_id = $catRow['id']; }
}
if (!$active_cat_id && !empty($categories)) { $active_cat_id = $categories[0]['id']; }

$diamonds = [];
if ($active_cat_id) {
    $stmt_d = $conn->prepare("SELECT product_id, spu, price, reseller_price, image_url FROM diamonds WHERE game_id = ? AND category_id = ? ORDER BY price ASC");
    $stmt_d->bind_param("ii", $game_id, $active_cat_id);
    $stmt_d->execute();
    $diamondsResult = $stmt_d->get_result();
    while ($dRow = $diamondsResult->fetch_assoc()) { $diamonds[] = $dRow; }
}

$faqs = [];
$faq_stmt = $conn->prepare("SELECT question, answer FROM faqs WHERE (game_id = ? OR game_id IS NULL) AND status = 1 ORDER BY sort_order ASC");
$faq_stmt->bind_param("i", $game_id);
$faq_stmt->execute();
$faq_res = $faq_stmt->get_result();
while($fRow = $faq_res->fetch_assoc()) { $faqs[] = $fRow; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title><?= htmlspecialchars($setting['store_name']); ?> - <?= htmlspecialchars($game['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=DynaPuff:wght@400;600&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        themePink: '#fbc2eb',
                        themeBlue: '#a6c1ee',
                        themeGreen: '#80bf15',
                        themeDark: '#0f172a',
                    },
                    animation: {
                        'shimmer': 'shimmer 1.5s infinite linear',
                    }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Outfit', sans-serif; background: linear-gradient(177deg, #fbc2eb, #a6c1ee, hsl(86.7, 80.67784736040353%, 41.709338428627014%)); background-attachment: fixed; color: #0f172a; overflow-x: hidden; }
        @keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
        .skeleton { background: linear-gradient(90deg, rgba(15, 23, 42, 0.1) 25%, rgba(15, 23, 42, 0.2) 50%, rgba(15, 23, 42, 0.1) 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite linear; }
        .glass-panel { background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.3); }
        .header-banner { height: 140px; position: relative; overflow: hidden; border-radius: 0 0 2rem 2rem; }
        .header-img { position: absolute; width: 100%; height: 100%; object-fit: cover; filter: brightness(0.8) blur(1px); transform: scale(1.1); }
        .item-card { transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); background: rgba(255, 255, 255, 0.3); border: 1px solid rgba(255,255,255,0.2); }
        .item-card.selected { border: 2px solid #3b82f6; background: rgba(59, 130, 246, 0.2); box-shadow: 0 0 25px rgba(59, 130, 246, 0.2); }
        .bottom-bar { position: fixed; bottom: 0; left: 0; right: 0; z-index: 60; background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(20px); border-top: 1px solid rgba(255,255,255,0.2); padding: 12px 20px env(safe-area-inset-bottom); transform: translateY(100%); transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .bottom-bar.show { transform: translateY(0); }
        .faq-answer { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out, padding 0.3s ease; }
        .faq-item.active .faq-answer { max-height: 500px; padding-top: 1rem; }
        
        /* Floating & Glow Animations */
        @keyframes float { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-5px); } }
        .animate-float { animation: float 3s ease-in-out infinite; }
        .feature-icon-glow { filter: drop-shadow(0 0 12px currentColor); }
        .modern-glass { background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(8px); border: 1px solid rgba(255, 255, 255, 0.3); }
    </style>
    <script>
        const BASE_URL = '<?= BASE_URL ?>';
    </script>
</head>
<body class="pb-32">

    <div id="skeleton-loader" class="fixed inset-0 z-[100] bg-white/20 backdrop-blur-xl transition-opacity duration-500">
        <div class="header-banner skeleton"></div>
    </div>

    <div id="main-content" class="opacity-0 transition-opacity duration-500">
        <header class="fixed top-0 w-full z-50 bg-white/40 backdrop-blur-xl h-16 border-b border-white/30">
            <div class="max-w-md mx-auto px-5 h-full flex items-center justify-between">
                <a href="<?= BASE_URL ?>" class="w-10 h-10 rounded-xl bg-white/40 flex items-center justify-center border border-white/30"><i class="fa-solid fa-arrow-left text-themeDark text-sm"></i></a>
                <div class="font-bold text-lg text-themeDark font-dynapuff"><?= htmlspecialchars($setting['store_name']); ?></div>
                <div class="w-10"></div>
            </div>
        </header>

        <?php 
            $final_game_img = $game_image_url;
            if (strpos($final_game_img, 'http') !== 0) {
                $final_game_img = ltrim($final_game_img, '/');
                if (!file_exists(__DIR__ . '/' . $final_game_img) && file_exists(__DIR__ . '/admin/' . $final_game_img)) {
                    $final_game_img = 'admin/' . $final_game_img;
                }
                $final_game_img = BASE_URL . '/' . $final_game_img;
            }
        ?>

        <div class="header-banner">
            <img src="<?= $final_game_img ?>" class="header-img">
            <div class="absolute inset-0 bg-gradient-to-t from-white/40 via-transparent to-transparent"></div>
        </div>

        <div class="max-w-md mx-auto px-4 -mt-8 relative z-10">
            <div class="glass-panel rounded-2xl p-3 flex items-center gap-3 shadow-2xl">
                <img src="<?= $final_game_img ?>" class="w-16 h-16 rounded-xl object-cover border border-white/10">
                <div>
                    <h1 class="text-base font-black text-themeDark leading-tight"><?= htmlspecialchars($game['title']); ?></h1>
                    <div class="flex items-center gap-1.5 mt-1">
                        <span class="w-2 h-2 bg-green-600 rounded-full animate-pulse"></span>
                        <p class="text-[10px] font-bold text-themeDark/60 uppercase tracking-wider">Top-up Service</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-md mx-auto px-2 mt-4">
            <div class="grid grid-cols-4 gap-2">
                <div class="modern-glass rounded-2xl py-3 flex flex-col items-center justify-center gap-2 group hover:bg-white/40 transition-all duration-500 animate-float">
                    <div class="w-10 h-10 rounded-full bg-rose-500/20 flex items-center justify-center text-rose-600 feature-icon-glow">
                        <i class="fa-solid fa-bolt text-xs"></i>
                    </div>
                    <span class="text-[8px] font-black text-themeDark/70 uppercase tracking-tighter">Instant</span>
                </div>
                <div class="modern-glass rounded-2xl py-3 flex flex-col items-center justify-center gap-2 group hover:bg-white/40 transition-all duration-500 animate-float" style="animation-delay: 0.2s;">
                    <div class="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-600 feature-icon-glow">
                        <i class="fa-solid fa-shield-halved text-xs"></i>
                    </div>
                    <span class="text-[8px] font-black text-themeDark/70 uppercase tracking-tighter">Official</span>
                </div>
                <div class="modern-glass rounded-2xl py-3 flex flex-col items-center justify-center gap-2 group hover:bg-white/40 transition-all duration-500 animate-float" style="animation-delay: 0.4s;">
                    <div class="w-10 h-10 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-600 feature-icon-glow">
                        <i class="fa-solid fa-headset text-xs"></i>
                    </div>
                    <span class="text-[8px] font-black text-themeDark/70 uppercase tracking-tighter">Support</span>
                </div>
                <div class="modern-glass rounded-2xl py-3 flex flex-col items-center justify-center gap-2 group hover:bg-white/40 transition-all duration-500 animate-float" style="animation-delay: 0.6s;">
                    <div class="w-10 h-10 rounded-full bg-amber-500/20 flex items-center justify-center text-amber-600 feature-icon-glow">
                        <i class="fa-solid fa-lock text-xs"></i>
                    </div>
                    <span class="text-[8px] font-black text-themeDark/70 uppercase tracking-tighter">Secure</span>
                </div>
            </div>
        </div>

        <main class="max-w-md mx-auto px-4 mt-6 space-y-4">
            <section class="glass-panel rounded-[2rem] p-5">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-8 h-8 bg-rose-600 rounded-lg flex items-center justify-center text-white text-sm font-bold shadow-lg shadow-rose-600/20">1</div>
                    <h2 class="text-sm font-bold text-themeDark uppercase tracking-wider">Account Details</h2>
                </div>
                <form id="checkUserForm" onsubmit="handleVerify(event)" class="space-y-4">
                    <div class="grid grid-cols-3 gap-3">
                        <div class="relative <?= ($id_system === 'user_only') ? 'col-span-3' : 'col-span-2' ?>">
                            <i class="fa-solid fa-user absolute left-4 top-1/2 -translate-y-1/2 text-themeDark/60 text-xs"></i>
                            <input type="text" id="user_id" name="user_id" placeholder="User ID" class="w-full bg-white/40 border border-white/50 focus:border-themeDark focus:ring-4 focus:ring-themeDark/10 transition-all rounded-xl p-3.5 pl-10 text-sm text-themeDark outline-none placeholder:text-themeDark/30" required>
                        </div>
                        <?php if ($id_system === 'user_zone_input'): ?>
                            <input type="number" id="zone_id" name="zone_id" placeholder="Zone" class="col-span-1 bg-white/40 border border-white/50 focus:border-themeDark transition-all rounded-xl p-3.5 text-sm text-themeDark text-center outline-none placeholder:text-themeDark/30" required>
                        <?php endif; ?>
                    </div>
                    <?php if ($game_provider === 'smileone'): ?>
                        <button type="submit" id="verifyBtn" class="w-full bg-rose-600 text-white py-3 rounded-xl font-bold text-xs shadow-lg shadow-rose-600/20 active:scale-[0.97] transition-all">Verify Username</button>
                    <?php endif; ?>
                    <div id="userResult" class="hidden p-3 rounded-xl text-center text-[10px] font-bold"></div>
                </form>
            </section>

            <section class="glass-panel rounded-[2rem] p-5">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-8 h-8 bg-rose-600 rounded-lg flex items-center justify-center text-white text-sm font-bold shadow-lg shadow-rose-600/20">2</div>
                    <h2 class="text-sm font-bold text-themeDark uppercase tracking-wider">Select Amount</h2>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <?php foreach ($diamonds as $row): 
                        $isReseller = (isset($user_role) && $user_role === 'reseller');
                        $finalPrice = ($isReseller && !empty($row['reseller_price'])) ? $row['reseller_price'] : $row['price'];
                    ?>
                        <div class="item-card rounded-2xl cursor-pointer flex flex-col items-center p-3 text-center" data-product-id="<?= htmlspecialchars($row['product_id']) ?>" onclick="selectItem(this, '<?= $row['product_id'] ?>', '<?= addslashes($row['spu']) ?>', <?= $finalPrice ?>, '<?= $row['image_url'] ?>')">
                            <?php if(!empty($row['image_url'])): 
                                $d_img = $row['image_url'];
                                if (strpos($d_img, 'http') !== 0) {
                                    $d_img = ltrim($d_img, '/');
                                    if (!file_exists(__DIR__ . '/' . $d_img) && file_exists(__DIR__ . '/admin/' . $d_img)) {
                                        $d_img = 'admin/' . $d_img;
                                    }
                                    $d_img = BASE_URL . '/' . $d_img;
                                }
                            ?>
                                <img src="<?= $d_img ?>" class="w-10 h-10 object-contain mb-2">
                            <?php endif; ?>
                            <div class="text-[9px] text-themeDark/60 font-bold uppercase mb-1"><?= htmlspecialchars($row['spu']) ?></div>
                            <div class="text-[12px] font-extrabold text-themeDark">
                                <?php if ($isReseller && !empty($row['reseller_price'])): ?>
                                    <span class="text-[8px] text-gray-400 line-through mr-1">₹<?= number_format($row['price'], 0) ?></span>
                                    <span class="text-rose-600">₹<?= number_format($row['reseller_price'], 0) ?></span>
                                <?php else: ?>
                                    ₹<?= number_format($row['price'], 0) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>


            <?php if (!empty($game['description_title']) || !empty($game['description_body']) || !empty($game['external_url'])): ?>
            <section class="glass-panel rounded-[2rem] p-6 space-y-3">
                <?php if (!empty($game['description_title'])): ?>
                    <h2 class="text-sm font-black text-rose-600 uppercase tracking-widest"><?= htmlspecialchars($game['description_title']) ?></h2>
                <?php endif; ?>
                
                <?php if (!empty($game['description_body'])): ?>
                    <div class="text-[11px] text-themeDark/60 leading-relaxed">
                        <?= nl2br(htmlspecialchars($game['description_body'])) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($game['external_url'])): ?>
                    <div class="pt-2">
                        <a href="<?= htmlspecialchars($game['external_url']) ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-white/40 border border-white/50 rounded-xl text-[10px] font-bold text-themeDark hover:bg-white/50 transition-all">
                            <i class="fa-solid fa-arrow-up-right-from-square text-themeDark"></i>
                            Official Website
                        </a>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <section class="glass-panel rounded-[2rem] p-5 space-y-4 mb-10">
                <div class="flex items-center gap-3 mb-2">
                    <i class="fa-solid fa-circle-question text-themeDark"></i>
                    <h2 class="text-xs font-black text-themeDark uppercase tracking-widest">Questions & Help</h2>
                </div>
                
                <div class="space-y-3">
                    <?php if(empty($faqs)): ?>
                        <p class="text-[10px] text-themeDark/40 text-center py-4 italic">No FAQs available.</p>
                    <?php else: ?>
                        <?php foreach($faqs as $faq): ?>
                            <div class="faq-item bg-white/30 border border-white/50 rounded-2xl overflow-hidden">
                                <button onclick="toggleFaq(this)" class="w-full flex items-center justify-between p-4 text-left">
                                    <span class="text-[11px] font-bold text-themeDark/80"><?= htmlspecialchars($faq['question']) ?></span>
                                    <i class="faq-icon fa-solid fa-chevron-down text-[10px] text-themeDark/40 transition-transform"></i>
                                </button>
                                <div class="faq-answer px-4 pb-0 text-[10px] text-themeDark/60 leading-relaxed border-t border-white/30">
                                    <?= nl2br(htmlspecialchars($faq['answer'])) ?>
                                    <div class="h-4"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <footer class="pb-10"><?php include 'footer.php'; ?></footer>
        </main>

        <div id="stickyBar" class="bottom-bar">
            <div class="max-w-md mx-auto flex items-center justify-between gap-4">
                <div><p class="text-[9px] text-themeDark/60 uppercase font-bold">Price</p><p id="bar_price" class="text-xl font-black text-rose-600">₹0</p></div>
                <button onclick="handleCheckout()" class="flex-1 bg-rose-600 text-white h-12 rounded-xl font-bold text-sm shadow-xl shadow-rose-600/20">Checkout</button>
            </div>
        </div>
    </div>


   <script>
        // ✅ SKELETON HANDLER
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                document.getElementById('skeleton-loader').style.opacity = '0';
                setTimeout(() => {
                    document.getElementById('skeleton-loader').classList.add('hidden');
                    document.getElementById('main-content').classList.remove('opacity-0');
                }, 500);
            }, 800);
        });

        // ✅ FAQ TOGGLE
        function toggleFaq(btn) {
            const item = btn.parentElement;
            const isActive = item.classList.contains('active');
            document.querySelectorAll('.faq-item').forEach(el => el.classList.remove('active'));
            if (!isActive) item.classList.add('active');
        }

        let selection = { productId: '', spu: '', price: 0, method: '', methodName: '', verified: false, username: '', provider: '<?= $game_provider ?>', isLoggedIn: <?= $is_logged_in ? 'true' : 'false' ?> };

        function selectItem(el, id, name, price, img) {
            document.querySelectorAll('.item-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            selection.productId = id; selection.spu = name; selection.price = price;
            selection.productImage = img;
            document.getElementById('bar_price').innerText = "₹" + price;
            document.getElementById('stickyBar').classList.add('show');
            if (selection.provider === 'smileone') selection.verified = false;
        }

        function handleVerify(e) {
            if(e) e.preventDefault();
            const btn = document.getElementById('verifyBtn');
            const resDiv = document.getElementById('userResult');
            const userId = document.getElementById('user_id').value;
            if(!userId) return Promise.resolve(false);
            if(!selection.productId) { alert("Select a product first!"); return Promise.resolve(false); }
            
            if(btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('zone_id', document.getElementById('zone_id') ? document.getElementById('zone_id').value : 'none');
            formData.append('product_id', selection.productId);

            return fetch(`${BASE_URL}/api/check_username`, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if(btn) btn.innerHTML = 'Verify Username';
                resDiv.classList.remove('hidden');
                if(data.success) {
                    console.log("GETROLE RESPONSE:", data.raw_response);
                    selection.verified = true; 
                    selection.username = data.username;
                    resDiv.innerText = "Verified: " + data.username;
                    resDiv.className = "p-3 rounded-xl text-center text-[10px] font-bold bg-green-500/10 text-green-400 border border-green-500/20";
                    return true;
                } else {
                    console.log("ERROR RESPONSE:", data.raw_response);
                    resDiv.innerText = data.message;
                    resDiv.className = "p-3 rounded-xl text-center text-[10px] font-bold bg-red-500/10 text-red-400 border border-red-500/20";
                    return false;
                }
            })
            .catch(err => {
                if(btn) btn.innerHTML = 'Verify Username';
                console.error("Fetch Error:", err);
                return false;
            });
        }

        function handleCheckout() {
            if(!selection.isLoggedIn) { alert("Please login first"); return; }
            const uId = document.getElementById('user_id').value;
            const zId = document.getElementById('zone_id')?.value || 'none';
            if(!uId) { alert("Please enter User ID"); return; }
            if(!selection.productId) { alert("Please select an item"); return; }
            
            const processCheckout = () => {
                const form = document.createElement('form');
                form.method = 'POST'; 
                form.action = `${BASE_URL}/checkout`;
                
                const params = { 
                    game_name: "<?= addslashes($game['title']) ?>",
                    game_slug: "<?= $game_slug ?>",
                    game_image: "<?= $game['image'] ?>",
                    provider: selection.provider,
                    product_id: selection.productId, 
                    product_name: selection.spu,
                    product_price: selection.price,
                    product_image: selection.productImage || '',
                    user_id: uId, 
                    zone_id: zId,
                    verified_username: selection.username || 'Order',
                    email: '<?= $user_mobile ?>'
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
            };

            if(!selection.verified && selection.provider === 'smileone') {
                handleVerify().then(v => {
                    if(v) processCheckout();
                });
            } else {
                processCheckout();
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const params = new URLSearchParams(window.location.search);
            const autoSelectId = params.get('auto_select');
            if (autoSelectId) {
                const card = document.querySelector(`.item-card[data-product-id="${autoSelectId}"]`);
                if (card) {
                    card.click();
                    setTimeout(() => {
                        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 500);
                }
            }
        });
    </script>
</body>
</html>