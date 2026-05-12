<?php
/**
 * JZ AI Assistant - Support System
 * Overhauled to support Free Gemini AI Integration and dynamic Store Knowledge
 */

require_once 'config.php';

// 1. FETCH DYNAMIC KNOWLEDGE BASE
$setting = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM fav_setting LIMIT 1"));
$store_name = $setting['store_name'] ?? 'JZ Store';
$ai_key = $setting['gemini_api_key'] ?? '';

// Fetch all active games and their cheapest diamond prices
$games_data = [];
$games_query = mysqli_query($conn, "SELECT g.title, MIN(d.price) as min_price FROM games g JOIN diamonds d ON g.id = d.game_id WHERE g.status = 1 GROUP BY g.id");
while($g = mysqli_fetch_assoc($games_query)) {
    $games_data[] = $g['title'] . " (Prices start from ₹" . number_format($g['min_price'], 0) . ")";
}
$knowledge_base = implode(", ", $games_data);

// 2. AJAX REQUEST HANDLER
$inputJSON = file_get_contents("php://input");
$data = json_decode($inputJSON, true);

if (!empty($data['msg'])) {
    header('Content-Type: application/json');
    $msg_raw = trim($data['msg']);
    $msg_lower = strtolower($msg_raw);

    function sendReply($html) {
        echo json_encode(["reply" => $html]);
        exit;
    }

    // --- A. COMMAND TRIGGERS (HIGH PRIORITY) ---
    
    if ($msg_lower == "show_recover") {
        $admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT recover_instruction FROM admin LIMIT 1"));
        sendReply("
            <div class='space-y-3'>
                <div class='font-bold text-white flex items-center gap-2'><i class='fa-solid fa-circle-exclamation text-rose-500'></i> Payment Recovery</div>
                <p class='text-xs text-white/70 leading-relaxed'>{$admin['recover_instruction']}</p>
                <iframe class='w-full aspect-video rounded-xl border border-white/10' src='https://www.youtube.com/embed/ctT6NQsIU7U' frameborder='0' allowfullscreen></iframe>
            </div>
        ");
    }

    if ($msg_lower == "show_jcoin") {
        $admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT jcoin_instruction FROM admin LIMIT 1"));
        sendReply("
            <div class='space-y-3'>
                <div class='font-bold text-white flex items-center gap-2'><i class='fa-solid fa-wallet text-indigo-400'></i> Wallet Guide</div>
                <p class='text-xs text-white/70 leading-relaxed'>{$admin['jcoin_instruction']}</p>
                <iframe class='w-full aspect-video rounded-xl border border-white/10' src='https://www.youtube.com/embed/CbLI92BqNIU' frameborder='0' allowfullscreen></iframe>
            </div>
        ");
    }

    if ($msg_lower == "check_wallet_req") {
        sendReply("<div class='p-1'>Please enter your <b>Username</b> or <b>Email</b> to check balance.</div>");
    }

    if ($msg_lower == "check_order_req") {
        sendReply("<div class='p-1'>Please enter your <b>Order ID</b> (e.g. 1054) to track status.</div>");
    }

    // --- B. DATABASE SEARCH (MEDIUM PRIORITY) ---

    // 1. Check for USER (Wallet)
    $stmt_u = $conn->prepare("SELECT username, email, wallet_balance, status FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt_u->bind_param("ss", $msg_raw, $msg_raw);
    $stmt_u->execute();
    $user_res = $stmt_u->get_result();
    
    if ($user_res->num_rows > 0) {
        $u = $user_res->fetch_assoc();
        $balance = number_format($u['wallet_balance'], 2);
        sendReply("
            <div class='bg-[#08203E] rounded-2xl p-4 border border-white/10 shadow-xl overflow-hidden'>
                <div class='flex justify-between items-start mb-4'>
                    <div>
                        <p class='text-[10px] text-white/40 uppercase font-black'>Account Balance</p>
                        <h4 class='text-2xl font-black text-white'>₹$balance</h4>
                    </div>
                    <span class='px-2 py-1 bg-emerald-500/20 text-emerald-400 text-[9px] font-black rounded-lg border border-emerald-500/20 uppercase'>{$u['status']}</span>
                </div>
                <div class='text-[11px] text-white/60 space-y-1'>
                    <p><b>User:</b> {$u['username']}</p>
                    <p><b>ID:</b> " . substr($u['email'], 0, 3) . "***</p>
                </div>
            </div>
        ");
    }

    // 2. Check for ORDER
    preg_match('/(ORD)?\d+/i', $msg_raw, $m);
    $input_id = $m[0] ?? $msg_raw;
    $stmt_o = $conn->prepare("SELECT o.*, g.title as game_name, g.image as game_image FROM orders o LEFT JOIN diamonds d ON o.product_id = d.product_id LEFT JOIN games g ON d.game_id = g.id WHERE o.order_id LIKE ? OR o.user_id = ? ORDER BY o.id DESC LIMIT 1");
    $like_id = "%$input_id%";
    $stmt_o->bind_param("ss", $like_id, $input_id);
    $stmt_o->execute();
    $o = $stmt_o->get_result()->fetch_assoc();

    if ($o) {
        $status = strtoupper($o['status']);
        $color = ($status == 'SUCCESS' || $status == 'COMPLETED') ? 'text-emerald-400' : 'text-rose-400';
        sendReply("
            <div class='bg-white/5 rounded-2xl p-4 border border-white/10'>
                <div class='flex gap-3 items-center mb-3'>
                    <img src='" . BASE_URL . "/" . ltrim($o['game_image'], '/') . "' class='w-10 h-10 rounded-lg object-cover'>
                    <div>
                        <h4 class='font-black text-white'>{$o['game_name']}</h4>
                        <p class='text-[9px] text-white/40 uppercase'>#{$o['order_id']}</p>
                    </div>
                </div>
                <div class='grid grid-cols-2 gap-2 text-[11px]'>
                    <div class='opacity-60'>Status:</div>
                    <div class='font-black $color'>$status</div>
                    <div class='opacity-60'>Product:</div>
                    <div class='text-white'>{$o['product_name']}</div>
                </div>
                <button onclick='verifyOrder(\"{$o['order_id']}\", this)' class='w-full mt-3 py-2 bg-indigo-600 rounded-lg text-[10px] font-black uppercase tracking-wider'>Check Real-time Update</button>
            </div>
        ");
    }

    // --- C. AI ASSISTANT (LOW PRIORITY FALLBACK) ---

    if (!empty($ai_key)) {
        // CALL GEMINI AI
        $prompt = "You are the Customer Support AI for '$store_name', an Indian gaming top-up store. 
        Available Games & Starting Prices: $knowledge_base. 
        Store Info: $store_name provides instant top-up for games like PUBG, MLBB, Free Fire, etc. 
        Instructions: 
        1. Be friendly, professional, and concise. 
        2. If asked about prices, use the provided knowledge base. 
        3. If asked about order status, tell them to type their Order ID. 
        4. If asked about wallet, tell them to type their Username. 
        5. Keep answers under 50 words.
        
        User Question: $msg_raw";

        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$ai_key");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "contents" => [["parts" => [["text" => $prompt]]]]
        ]));
        $response = curl_exec($ch);
        $res_data = json_decode($response, true);
        
        $reply = $res_data['candidates'][0]['content']['parts'][0]['text'] ?? "I'm here to help! Could you please specify your Order ID or Username so I can assist you better?";
        sendReply("<div class='leading-relaxed'>$reply</div>");
    }

    // --- D. SMART FALLBACK (IF NO AI KEY) ---
    $reply = "I'm sorry, I couldn't find a direct match. You can type your <b>Order ID</b> to track a purchase, or your <b>Username</b> to check your balance. How else can I help you today?";
    
    if (strpos($msg_lower, 'price') !== false || strpos($msg_lower, 'game') !== false || strpos($msg_lower, 'list') !== false) {
        $reply = "We offer top-ups for many games! <br><br><b>Our Catalog:</b><br>" . str_replace(", ", "<br>", $knowledge_base);
    } elseif (strpos($msg_lower, 'hi') !== false || strpos($msg_lower, 'hello') !== false) {
        $reply = "Hello! 👋 Welcome to $store_name Support. I'm your virtual assistant. How can I assist you with your orders or account today?";
    }

    sendReply($reply);
}

// 3. FRONTEND UI
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?= $store_name ?> Support</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=DynaPuff:wght@400;600&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Outfit', sans-serif; overflow: hidden; background: #08203E; }
        .h-dvh { height: 100vh; height: 100dvh; }
        .glass-panel { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .msg-enter { animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .custom-scroll::-webkit-scrollbar { width: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4" 
    style="background: linear-gradient(135deg, #08203E 0%, #1a2a6c 100%);">

    <div class="relative w-full h-dvh sm:h-[85vh] sm:max-w-[400px] glass-panel sm:rounded-[40px] shadow-2xl flex flex-col overflow-hidden">
        
        <!-- HEADER -->
        <header class="bg-black/40 backdrop-blur-xl border-b border-white/10 px-6 py-5 flex items-center justify-between z-20">
            <div class="flex items-center gap-3">
                <div class="relative">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-xl shadow-lg">🤖</div>
                    <div class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-emerald-500 border-2 border-[#121826] rounded-full"></div>
                </div>
                <div>
                    <h1 class="font-black text-white text-sm tracking-tight"><?= $store_name ?> Assistant</h1>
                    <p class="text-[10px] font-bold text-emerald-400 uppercase tracking-widest">Always Online</p>
                </div>
            </div>
            <a href="<?= BASE_URL ?>" class="w-9 h-9 rounded-xl bg-white/5 flex items-center justify-center border border-white/10 text-white/40 hover:text-white transition-all"><i class="fa-solid fa-house text-sm"></i></a>
        </header>

        <!-- CHAT AREA -->
        <div id="chat-box" class="flex-1 overflow-y-auto px-4 pt-6 pb-32 custom-scroll space-y-6">
            <div class="flex w-full space-x-3 max-w-[90%] msg-enter">
                <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center flex-shrink-0 text-white shadow-lg"><i class="fa-solid fa-robot text-xs"></i></div>
                <div class="space-y-1">
                    <span class="text-[10px] text-white/30 ml-1 font-bold uppercase tracking-widest">AI Support</span>
                    <div class="bg-white/10 backdrop-blur-xl border border-white/10 p-4 rounded-2xl rounded-tl-none text-white text-[13px] shadow-xl leading-relaxed">
                        <p class="mb-4">Hello! 👋 I'm your <b><?= $store_name ?> AI Assistant</b>. I can check your orders, wallet balance, and answer questions about games or prices.</p>
                        
                        <div class="grid grid-cols-1 gap-2">
                            <button onclick="sendMessage('check_order_req')" class="text-left w-full p-3 bg-white/5 hover:bg-white/10 rounded-xl text-[11px] font-black text-white transition-all flex items-center gap-3 border border-white/5">
                                <span class="bg-indigo-500/20 p-2 rounded-lg text-indigo-400"><i class="fa-solid fa-box-open"></i></span> Track My Order
                            </button>
                            <button onclick="sendMessage('check_wallet_req')" class="text-left w-full p-3 bg-white/5 hover:bg-white/10 rounded-xl text-[11px] font-black text-white transition-all flex items-center gap-3 border border-white/5">
                                <span class="bg-emerald-500/20 p-2 rounded-lg text-emerald-400"><i class="fa-solid fa-wallet"></i></span> Wallet Balance
                            </button>
                            <button onclick="sendMessage('price_list')" class="text-left w-full p-3 bg-white/5 hover:bg-white/10 rounded-xl text-[11px] font-black text-white transition-all flex items-center gap-3 border border-white/5">
                                <span class="bg-amber-500/20 p-2 rounded-lg text-amber-400"><i class="fa-solid fa-tags"></i></span> View Price List
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TYPING INDICATOR -->
        <div id="typing" class="hidden absolute bottom-24 left-6 z-10 items-center gap-1.5 bg-white/5 backdrop-blur-md px-4 py-2 rounded-full border border-white/10 animate-pulse">
             <div class="w-1.5 h-1.5 bg-white/40 rounded-full"></div>
             <div class="w-1.5 h-1.5 bg-white/60 rounded-full"></div>
             <div class="w-1.5 h-1.5 bg-white/40 rounded-full"></div>
        </div>

        <!-- INPUT BAR -->
        <div class="absolute bottom-0 w-full p-6 bg-gradient-to-t from-black/60 to-transparent z-20">
            <div class="bg-white/10 backdrop-blur-2xl p-2 rounded-[24px] border border-white/10 flex items-center gap-2 focus-within:ring-2 focus-within:ring-indigo-500/50 transition-all shadow-2xl">
                <input id="msg-input" type="text" class="flex-1 bg-transparent border-none focus:ring-0 text-sm px-4 py-3 text-white placeholder-white/20 font-bold" placeholder="Type a message or order ID..." autocomplete="off">
                <button onclick="handleUserSend()" class="w-12 h-12 bg-indigo-600 text-white rounded-2xl shadow-xl shadow-indigo-600/30 flex items-center justify-center hover:scale-105 active:scale-95 transition-all">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
        </div>

    </div>

    <script>
        const chatBox = document.getElementById('chat-box');
        const input = document.getElementById('msg-input');
        const typing = document.getElementById('typing');

        input.addEventListener("keypress", (e) => { if(e.key === "Enter") handleUserSend(); });

        function scrollToBottom() { 
            setTimeout(() => chatBox.scrollTo({ top: chatBox.scrollHeight, behavior: 'smooth' }), 50); 
        }

        function appendMsg(role, html) {
            const wrapper = document.createElement('div');
            wrapper.className = `flex w-full space-x-3 max-w-[90%] msg-enter ${role === 'user' ? 'ml-auto justify-end' : ''}`;
            
            if (role === 'bot') {
                wrapper.innerHTML = `
                    <div class="w-8 h-8 rounded-full bg-white/5 border border-white/10 flex items-center justify-center flex-shrink-0 text-white"><i class="fa-solid fa-robot text-[10px]"></i></div>
                    <div class="space-y-1 w-full">
                        <span class="text-[9px] text-white/20 ml-1 font-black uppercase tracking-widest">Assistant</span>
                        <div class="bg-white/10 backdrop-blur-xl border border-white/10 p-4 rounded-2xl rounded-tl-none text-white text-[13px] shadow-xl leading-relaxed w-full">
                            ${html}
                        </div>
                    </div>
                `;
            } else {
                wrapper.innerHTML = `
                    <div class="bg-indigo-600 p-4 px-6 rounded-2xl rounded-br-none text-white text-[13px] font-bold shadow-xl shadow-indigo-600/10 border border-indigo-400/20">
                        ${html}
                    </div>
                `;
            }
            chatBox.appendChild(wrapper);
            scrollToBottom();
        }

        function handleUserSend() {
            const val = input.value.trim();
            if (!val) return;
            appendMsg('user', val);
            input.value = '';
            sendMessage(val);
        }

        function sendMessage(msg) {
            typing.style.display = 'flex';
            scrollToBottom();
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ msg: msg })
            })
            .then(res => res.json())
            .then(data => {
                setTimeout(() => {
                    typing.style.display = 'none';
                    appendMsg('bot', data.reply);
                }, 800);
            })
            .catch(() => {
                typing.style.display = 'none';
                appendMsg('bot', '<span class="text-rose-500">Connection error. Please try again.</span>');
            });
        }

        async function verifyOrder(orderId, btn) {
            const original = btn.innerText;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('orderId', orderId);

            fetch('<?= BASE_URL ?>/api/verify_order', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if(res.success) {
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> Verified!';
                    btn.className = "w-full mt-3 py-2 bg-emerald-600 rounded-lg text-[10px] font-black uppercase tracking-wider";
                    setTimeout(() => sendMessage(orderId), 1000);
                } else {
                    alert(res.message);
                    btn.innerText = original;
                    btn.disabled = false;
                }
            });
        }
    </script>
</body>
</html>