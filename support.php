<?php
/**
 * JZ AI Support Assistant - Full Edition
 * Features: Games Catalog, Products, Order History, Order Status, Wallet, AI Chat
 */
require_once 'config.php';

// ─── KNOWLEDGE BASE ───────────────────────────────────────────────
$setting    = $conn->query("SELECT * FROM fav_setting LIMIT 1")->fetch_assoc();
$store_name = $setting['store_name'] ?? 'JZ Store';
$ai_key     = $setting['gemini_api_key'] ?? '';
$whatsapp   = $setting['whatsapp'] ?? '';

// Games list for AI context
$games_kb = [];
$gq = $conn->query("SELECT g.id, g.title, g.slug, g.image, MIN(d.price) as min_price, COUNT(d.id) as pkg_count FROM games g JOIN diamonds d ON g.id = d.game_id WHERE g.status = 1 GROUP BY g.id ORDER BY g.title");
while ($g = $gq->fetch_assoc()) $games_kb[] = $g;
$kb_text = implode(", ", array_map(fn($g) => "{$g['title']} (from ₹{$g['min_price']})", $games_kb));

// ─── AJAX HANDLER ─────────────────────────────────────────────────
$input = json_decode(file_get_contents("php://input"), true);
if (!empty($input['msg']) || !empty($input['action'])) {
    header('Content-Type: application/json');

    $action    = $input['action'] ?? '';
    $msg_raw   = trim($input['msg'] ?? '');
    $msg_lower = strtolower($msg_raw);

    function R(string $html): never {
        echo json_encode(['reply' => $html]);
        exit;
    }

    // ── COMMAND: Show All Games Catalog ──
    if ($action === 'show_games' || str_contains($msg_lower, 'game list') || str_contains($msg_lower, 'all game')) {
        global $games_kb, $store_name;
        $cards = '';
        foreach ($games_kb as $g) {
            $img = (strpos($g['image'], 'http') === 0) ? $g['image'] : BASE_URL . '/' . ltrim($g['image'], '/');
            $cards .= "<button onclick=\"sendAction('show_products','{$g['id']}','{$g['title']}')\" class='flex items-center gap-3 w-full p-2 bg-white/5 hover:bg-white/10 rounded-xl border border-white/5 transition-all text-left'>
                <img src='$img' class='w-10 h-10 rounded-lg object-cover flex-shrink-0'>
                <div class='flex-1 min-w-0'><p class='text-[11px] font-black text-white truncate'>{$g['title']}</p><p class='text-[9px] text-white/40'>{$g['pkg_count']} packages • from ₹{$g['min_price']}</p></div>
                <i class='fa-solid fa-chevron-right text-white/20 text-[10px]'></i>
            </button>";
        }
        R("<div class='space-y-2'><p class='text-[10px] font-black text-white/40 uppercase tracking-widest mb-3'>📋 Available Games</p>$cards</div>");
    }

    // ── COMMAND: Show Products for a Game ──
    if ($action === 'show_products' && !empty($input['game_id'])) {
        $gid  = (int)$input['game_id'];
        $gtitle = htmlspecialchars($input['game_title'] ?? 'Game');
        $stmt = $conn->prepare("SELECT spu, price FROM diamonds WHERE game_id = ? AND status = 1 ORDER BY price ASC LIMIT 20");
        $stmt->bind_param("i", $gid);
        $stmt->execute();
        $pkgs = $stmt->get_result();
        $rows = '';
        while ($p = $pkgs->fetch_assoc()) {
            $rows .= "<div class='flex justify-between items-center py-1.5 border-b border-white/5 last:border-0'>
                <span class='text-[11px] text-white/80'>{$p['spu']}</span>
                <span class='text-[11px] font-black text-emerald-400'>₹{$p['price']}</span>
            </div>";
        }
        R("<div><p class='text-[10px] font-black text-white/40 uppercase mb-2'>💎 $gtitle – Packages</p><div class='bg-white/5 rounded-xl p-3 border border-white/10'>$rows</div><a href='" . BASE_URL . "/product/" . htmlspecialchars($input['game_slug'] ?? '') . "' class='block mt-3 text-center py-2 bg-indigo-600 rounded-xl text-[11px] font-black uppercase tracking-wider'>Buy Now →</a></div>");
    }

    // ── COMMAND: Order History (session user) ──
    if ($action === 'show_history') {
        session_start();
        $uid = $_SESSION['user_id'] ?? 0;
        if (!$uid) {
            R("<div class='text-[13px]'>🔐 Please <a href='" . BASE_URL . "/auth/login' class='text-indigo-400 underline'>log in</a> to view your order history.</div>");
        }
        $stmt = $conn->prepare("SELECT o.order_id, o.product_name, o.price, o.status, o.created_at, g.title as game FROM orders o LEFT JOIN diamonds d ON o.product_id = d.product_id LEFT JOIN games g ON d.game_id = g.id WHERE o.user_id = ? ORDER BY o.id DESC LIMIT 10");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $orders = $stmt->get_result();
        if ($orders->num_rows === 0) R("<div class='text-[13px] text-white/60'>No orders found for your account yet.</div>");
        $rows = '';
        while ($o = $orders->fetch_assoc()) {
            $sc = in_array(strtoupper($o['status']), ['SUCCESS','COMPLETED']) ? 'text-emerald-400' : (strtoupper($o['status']) === 'PENDING' ? 'text-amber-400' : 'text-rose-400');
            $rows .= "<div class='flex items-center justify-between py-2 border-b border-white/5 last:border-0 gap-2'>
                <div class='flex-1 min-w-0'><p class='text-[11px] font-bold text-white truncate'>#{$o['order_id']} – {$o['product_name']}</p><p class='text-[9px] text-white/30'>{$o['game']} • {$o['created_at']}</p></div>
                <div class='text-right flex-shrink-0'><p class='text-[11px] font-black text-white'>₹{$o['price']}</p><p class='text-[9px] font-black $sc uppercase'>{$o['status']}</p></div>
            </div>";
        }
        R("<div><p class='text-[10px] font-black text-white/40 uppercase mb-2'>📦 Your Recent Orders</p><div class='bg-white/5 rounded-xl p-3 border border-white/10'>$rows</div></div>");
    }

    // ── COMMAND: Quick Links ──
    if ($action === 'show_recover') {
        $admin = $conn->query("SELECT recover_instruction FROM admin LIMIT 1")->fetch_assoc();
        R("<div class='space-y-2'><p class='font-bold text-rose-400'><i class='fa-solid fa-circle-exclamation mr-1'></i>Payment Recovery</p><p class='text-xs text-white/70 leading-relaxed'>{$admin['recover_instruction']}</p><iframe class='w-full aspect-video rounded-xl mt-2 border border-white/10' src='https://www.youtube.com/embed/ctT6NQsIU7U' frameborder='0' allowfullscreen></iframe></div>");
    }

    if ($action === 'show_jcoin') {
        $admin = $conn->query("SELECT jcoin_instruction FROM admin LIMIT 1")->fetch_assoc();
        R("<div class='space-y-2'><p class='font-bold text-indigo-400'><i class='fa-solid fa-wallet mr-1'></i>Wallet Guide</p><p class='text-xs text-white/70 leading-relaxed'>{$admin['jcoin_instruction']}</p><iframe class='w-full aspect-video rounded-xl mt-2 border border-white/10' src='https://www.youtube.com/embed/CbLI92BqNIU' frameborder='0' allowfullscreen></iframe></div>");
    }

    // ── SEARCH: Username / Email → Wallet ──
    if ($msg_raw) {
        $stmt_u = $conn->prepare("SELECT username, email, wallet_balance, status FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt_u->bind_param("ss", $msg_raw, $msg_raw);
        $stmt_u->execute();
        if ($u = $stmt_u->get_result()->fetch_assoc()) {
            $bal   = number_format($u['wallet_balance'], 2);
            $sc    = $u['status'] === 'active' ? 'text-emerald-400' : 'text-rose-400';
            $em    = substr($u['email'], 0, 3) . "***@" . explode('@', $u['email'])[1];
            R("<div class='bg-[#08203E] rounded-2xl p-4 border border-white/10'>
                <p class='text-[9px] text-white/30 uppercase font-black'>Wallet Balance</p>
                <p class='text-2xl font-black text-white'>₹$bal</p>
                <div class='mt-3 space-y-1 text-[11px]'>
                    <div class='flex justify-between'><span class='text-white/40'>User</span><span class='font-bold'>{$u['username']}</span></div>
                    <div class='flex justify-between'><span class='text-white/40'>Email</span><span>$em</span></div>
                    <div class='flex justify-between'><span class='text-white/40'>Status</span><span class='font-black $sc uppercase'>{$u['status']}</span></div>
                </div>
            </div>");
        }

        // ── SEARCH: Order ID / User ID ──
        preg_match('/\d+/', $msg_raw, $m);
        $oid = $m[0] ?? $msg_raw;
        $stmt_o = $conn->prepare("SELECT o.*, g.title as game, g.image FROM orders o LEFT JOIN diamonds d ON o.product_id = d.product_id LEFT JOIN games g ON d.game_id = g.id WHERE o.order_id LIKE ? OR o.user_id = ? ORDER BY o.id DESC LIMIT 1");
        $like = "%$oid%";
        $stmt_o->bind_param("ss", $like, $oid);
        $stmt_o->execute();
        if ($o = $stmt_o->get_result()->fetch_assoc()) {
            $st  = strtoupper($o['status']);
            $sc  = in_array($st, ['SUCCESS','COMPLETED']) ? 'text-emerald-400' : ($st === 'PENDING' ? 'text-amber-400' : 'text-rose-400');
            $img = (strpos($o['image'] ?? '', 'http') === 0) ? $o['image'] : BASE_URL . '/' . ltrim($o['image'] ?? '', '/');
            R("<div class='space-y-3'>
                <div class='flex gap-3 items-center bg-white/5 rounded-xl p-3 border border-white/10'>
                    <img src='$img' class='w-12 h-12 rounded-xl object-cover'>
                    <div>
                        <p class='font-black text-white text-sm'>{$o['game']}</p>
                        <p class='text-[10px] text-white/40 uppercase'>#{$o['order_id']} • {$o['product_name']}</p>
                    </div>
                </div>
                <div class='grid grid-cols-2 gap-2 text-[11px]'>
                    <div class='bg-white/5 rounded-lg p-2'><p class='text-white/30 mb-0.5'>Amount</p><p class='font-black text-white'>₹{$o['price']}</p></div>
                    <div class='bg-white/5 rounded-lg p-2'><p class='text-white/30 mb-0.5'>Status</p><p class='font-black $sc'>$st</p></div>
                    <div class='bg-white/5 rounded-lg p-2 col-span-2'><p class='text-white/30 mb-0.5'>Game User ID</p><p class='font-bold text-white'>{$o['game_user_id']}</p></div>
                </div>
                <button onclick=\"verifyOrder('{$o['order_id']}', this)\" class='w-full py-2.5 bg-indigo-600 rounded-xl text-[11px] font-black uppercase tracking-wider hover:bg-indigo-700 transition-all'>
                    <i class='fa-solid fa-rotate mr-1'></i>Check Real-Time Status
                </button>
            </div>");
        }

        // ── AI FALLBACK (Gemini) ──
        if (!empty($ai_key)) {
            $prompt = "You are the friendly support AI for '{$store_name}', an Indian gaming top-up store.
Available games: {$kb_text}.
Rules:
- Answer ONLY about this store: games, pricing, orders, wallet, account.
- For order status: ask for Order ID. For wallet: ask for Username.
- Keep answers under 60 words, use emojis sparingly.
- Do not answer off-topic questions.
User: {$msg_raw}";
            $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$ai_key");
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode(["contents" => [["parts" => [["text" => $prompt]]]]])]);
            $res = json_decode(curl_exec($ch), true);
            $reply = $res['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($reply) R("<div class='leading-relaxed text-[13px]'>$reply</div>");
        }

        // ── Smart keyword fallback ──
        if (str_contains($msg_lower, 'price') || str_contains($msg_lower, 'game') || str_contains($msg_lower, 'buy')) {
            R("<div class='text-[13px]'>We have " . count($games_kb) . " games available! Tap <b>🎮 Browse Games</b> below to see all packages and pricing.</div>");
        }
        if (str_contains($msg_lower, 'hello') || str_contains($msg_lower, 'hi') || str_contains($msg_lower, 'help')) {
            R("<div class='text-[13px]'>Hello! 👋 I can help you with <b>order tracking</b>, <b>wallet balance</b>, <b>game prices</b>, and more. What do you need?</div>");
        }

        R("<div class='text-[13px] text-white/70'>I couldn't find a match. Try entering an <b>Order ID</b>, your <b>Username</b>, or ask about a game. You can also tap a quick-action button below. 👇</div>");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= htmlspecialchars($store_name) ?> Support Chat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Outfit', sans-serif; }
        body { overflow: hidden; }
        .h-dvh { height: 100dvh; }
        .glass { background: rgba(255,255,255,0.07); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); }
        .msg-in { animation: slideUp .4s cubic-bezier(.16,1,.3,1) both; }
        @keyframes slideUp { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
        .scroll::-webkit-scrollbar { width: 3px; }
        .scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 99px; }
        .quick-btn { transition: all .2s; }
        .quick-btn:hover { transform: translateY(-1px); }
    </style>
</head>
<body class="flex items-center justify-center h-dvh" style="background: linear-gradient(135deg, #08203E 0%, #1a2a6c 50%, #08203E 100%);">

<div class="relative w-full h-dvh sm:h-[92vh] sm:max-w-[420px] glass sm:rounded-[40px] flex flex-col overflow-hidden">

    <!-- HEADER -->
    <header class="flex-shrink-0 bg-black/40 backdrop-blur-2xl border-b border-white/10 px-5 py-4 flex items-center justify-between z-20">
        <div class="flex items-center gap-3">
            <div class="relative">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-600 flex items-center justify-center text-xl shadow-lg shadow-indigo-600/30">🤖</div>
                <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-400 border-2 border-[#09182f] rounded-full"></div>
            </div>
            <div>
                <h1 class="font-black text-white text-sm"><?= htmlspecialchars($store_name) ?> Assistant</h1>
                <p class="text-[9px] font-black text-emerald-400 uppercase tracking-widest">Online • Instant Support</p>
            </div>
        </div>
        <a href="<?= BASE_URL ?>" class="w-9 h-9 rounded-xl glass flex items-center justify-center text-white/40 hover:text-white transition-all">
            <i class="fa-solid fa-house text-sm"></i>
        </a>
    </header>

    <!-- MESSAGES -->
    <div id="chat" class="flex-1 overflow-y-auto scroll px-4 pt-5 pb-4 space-y-5">
        <!-- Welcome message -->
        <div class="flex gap-3 max-w-[92%] msg-in">
            <div class="w-8 h-8 rounded-full bg-indigo-600/40 flex items-center justify-center flex-shrink-0 border border-indigo-500/30">
                <i class="fa-solid fa-robot text-indigo-400 text-[10px]"></i>
            </div>
            <div>
                <p class="text-[9px] text-white/20 font-black uppercase mb-1 ml-1">AI Assistant</p>
                <div class="glass p-4 rounded-2xl rounded-tl-none text-white text-[13px] leading-relaxed">
                    <p class="mb-4">Hi! 👋 I'm your <b><?= htmlspecialchars($store_name) ?></b> support assistant. I can help you with:</p>
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="sendAction('show_games')" class="quick-btn flex flex-col items-center gap-1.5 p-3 glass rounded-xl text-[10px] font-black text-white border-0">
                            <span class="text-xl">🎮</span> Browse Games
                        </button>
                        <button onclick="sendAction('show_history')" class="quick-btn flex flex-col items-center gap-1.5 p-3 glass rounded-xl text-[10px] font-black text-white">
                            <span class="text-xl">📦</span> My Orders
                        </button>
                        <button onclick="typeMsg('check order')" class="quick-btn flex flex-col items-center gap-1.5 p-3 glass rounded-xl text-[10px] font-black text-white">
                            <span class="text-xl">🔍</span> Track Order
                        </button>
                        <button onclick="typeMsg('check wallet')" class="quick-btn flex flex-col items-center gap-1.5 p-3 glass rounded-xl text-[10px] font-black text-white">
                            <span class="text-xl">💳</span> Wallet Balance
                        </button>
                        <button onclick="sendAction('show_recover')" class="quick-btn flex flex-col items-center gap-1.5 p-3 glass rounded-xl text-[10px] font-black text-white">
                            <span class="text-xl">⚠️</span> Payment Help
                        </button>
                        <button onclick="sendAction('show_jcoin')" class="quick-btn flex flex-col items-center gap-1.5 p-3 glass rounded-xl text-[10px] font-black text-white">
                            <span class="text-xl">💰</span> Wallet Guide
                        </button>
                    </div>
                    <?php if ($whatsapp): ?>
                    <a href="https://wa.me/<?= preg_replace('/\D/', '', $whatsapp) ?>?text=Hi+<?= urlencode($store_name) ?>+Support" target="_blank"
                        class="flex items-center justify-center gap-2 mt-3 py-2.5 bg-green-600/20 border border-green-500/20 rounded-xl text-[10px] font-black text-green-400 hover:bg-green-600/30 transition-all">
                        <i class="fa-brands fa-whatsapp text-sm"></i> Chat with Human Support
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- TYPING INDICATOR -->
    <div id="typing" class="hidden absolute bottom-[88px] left-8 z-10">
        <div class="glass px-4 py-2.5 rounded-full flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 bg-white/50 rounded-full animate-bounce" style="animation-delay:0s"></span>
            <span class="w-1.5 h-1.5 bg-white/50 rounded-full animate-bounce" style="animation-delay:.1s"></span>
            <span class="w-1.5 h-1.5 bg-white/50 rounded-full animate-bounce" style="animation-delay:.2s"></span>
        </div>
    </div>

    <!-- INPUT -->
    <div class="flex-shrink-0 p-4 bg-black/30 backdrop-blur-xl border-t border-white/5">
        <div class="glass flex items-center gap-2 p-2 rounded-[20px] focus-within:border-indigo-500/50 transition-all">
            <input id="inp" type="text"
                class="flex-1 bg-transparent border-none focus:ring-0 text-[13px] text-white placeholder-white/20 px-3 py-2.5 font-bold"
                placeholder="Ask anything or enter Order ID…" autocomplete="off">
            <button onclick="handleSend()" class="w-11 h-11 bg-indigo-600 hover:bg-indigo-500 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-600/30 transition-all hover:scale-105 active:scale-95">
                <i class="fa-solid fa-paper-plane text-white text-sm"></i>
            </button>
        </div>
    </div>
</div>

<script>
const chat    = document.getElementById('chat');
const inp     = document.getElementById('inp');
const typing  = document.getElementById('typing');

inp.addEventListener('keypress', e => { if (e.key === 'Enter') handleSend(); });

function scroll() { setTimeout(() => chat.scrollTo({ top: chat.scrollHeight, behavior: 'smooth' }), 50); }

function addMsg(role, html) {
    const w = document.createElement('div');
    if (role === 'user') {
        w.className = 'flex justify-end msg-in';
        w.innerHTML = `<div class="bg-indigo-600 text-white text-[13px] font-bold px-5 py-3 rounded-2xl rounded-br-none shadow-lg max-w-[85%]">${html}</div>`;
    } else {
        w.className = 'flex gap-3 max-w-[92%] msg-in';
        w.innerHTML = `
            <div class="w-8 h-8 rounded-full bg-indigo-600/30 flex items-center justify-center flex-shrink-0 border border-indigo-500/30 mt-5">
                <i class="fa-solid fa-robot text-indigo-400 text-[10px]"></i>
            </div>
            <div>
                <p class="text-[9px] text-white/20 font-black uppercase mb-1 ml-1">Assistant</p>
                <div class="glass p-4 rounded-2xl rounded-tl-none text-white text-[13px] leading-relaxed w-full">${html}</div>
            </div>`;
    }
    chat.appendChild(w);
    scroll();
}

function handleSend() {
    const val = inp.value.trim();
    if (!val) return;
    addMsg('user', val);
    inp.value = '';
    post({ msg: val });
}

function sendAction(action, game_id = '', game_title = '', game_slug = '') {
    const labels = { show_games:'Browse Games 🎮', show_history:'My Orders 📦', show_recover:'Payment Help ⚠️', show_jcoin:'Wallet Guide 💰' };
    if (labels[action]) addMsg('user', labels[action]);
    post({ msg: '', action, game_id, game_title, game_slug });
}

function typeMsg(msg) {
    inp.value = msg;
    inp.focus();
}

function post(body) {
    typing.style.display = 'block';
    scroll();
    fetch('', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
        .then(r => r.json())
        .then(d => { setTimeout(() => { typing.style.display = 'none'; addMsg('bot', d.reply); }, 700); })
        .catch(() => { typing.style.display = 'none'; addMsg('bot', '<span class="text-rose-400">Connection error. Please try again.</span>'); });
}

async function verifyOrder(orderId, btn) {
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Checking…';
    btn.disabled = true;
    const fd = new FormData();
    fd.append('orderId', orderId);
    fetch('<?= BASE_URL ?>/api/verify_order', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                btn.innerHTML = '<i class="fa-solid fa-check mr-1"></i>Verified!';
                btn.className = btn.className.replace('bg-indigo-600','bg-emerald-600');
                setTimeout(() => post({ msg: orderId }), 1200);
            } else { alert(res.message ?? 'Could not verify yet.'); btn.innerHTML = orig; btn.disabled = false; }
        });
}
</script>
</body>
</html>