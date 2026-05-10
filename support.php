<?php
// 1. DATABASE CONNECTION
require_once 'config.php';

// 2. BACKEND LOGIC (API HANDLE)
$inputJSON = file_get_contents("php://input");
$data = json_decode($inputJSON, true);

if (!empty($data['msg'])) {
    header('Content-Type: application/json');
    // Sanitize input
    $msg_raw = trim($data['msg']);
    $msg = mysqli_real_escape_string($conn, $msg_raw); 
    $msg_lower = strtolower($msg_raw);

    // Fetch Admin Settings (Still fetching text instructions, but ignoring video columns now)
    $admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM admin LIMIT 1"));

    function sendReply($html) {
        echo json_encode(["reply" => $html]);
        exit;
    }

    // --- BUTTON COMMAND HANDLERS ---
    
    // 1. Recover Logic
    if ($msg_lower == "show_recover") {
        // [UPDATED] Video Link Hardcoded below
        $recover_video_link = "https://www.youtube.com/embed/ctT6NQsIU7U"; // <--- CHANGE THIS LINK

        sendReply("
            <div class='space-y-3 animation-fade-in'>
                <div class='font-bold text-themeDark flex items-center gap-2'>
                    <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'></path></svg>
                    Payment Recovery
                </div>
                <p class='text-sm text-themeDark/60 whitespace-pre-line leading-relaxed'>{$admin['recover_instruction']}</p>
                <div class='rounded-2xl overflow-hidden shadow-lg border border-white/40 mt-2'>
                    <iframe class='w-full aspect-video' src='$recover_video_link' frameborder='0' allowfullscreen></iframe>
                </div>
            </div>
        ");
    }

    // 2. J-Coin/Wallet Instructions
    if ($msg_lower == "show_jcoin") {
        // [UPDATED] Video Link Hardcoded below
        $jcoin_video_link = $jcoin_video_link = "https://www.youtube.com/embed/CbLI92BqNIU"; // <--- CHANGE THIS LINK

        sendReply("
            <div class='space-y-3 animation-fade-in'>
                <div class='font-bold text-themeDark flex items-center gap-2'>
                    <svg class='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'></path></svg>
                    J-Coin Wallet Guide
                </div>
                <p class='text-sm text-themeDark/60 whitespace-pre-line leading-relaxed'>{$admin['jcoin_instruction']}</p>
                <div class='rounded-2xl overflow-hidden shadow-lg border border-white/40 mt-2'>
                    <iframe class='w-full aspect-video' src='$jcoin_video_link' frameborder='0' allowfullscreen></iframe>
                </div>
            </div>
        ");
    }

    // 3. Wallet Check Prompt
    if ($msg_lower == "check_wallet_req") {
        sendReply("
            <div class='bg-white/40 backdrop-blur-md p-4 rounded-xl border border-white/30 text-sm'>
                <p class='text-themeDark font-semibold'>Please enter your <span class='text-themeDark underline'>Username</span> or <span class='text-themeDark underline'>Email</span> below.</p>
                <p class='text-[10px] text-themeDark/40 mt-1'>I will securely search the database for your balance.</p>
            </div>
        ");
    }

    // 4. [UPDATED] Check Order Prompt
    if ($msg_lower == "check_order_req") {
        sendReply("
            <div class='bg-orange-50/80 p-4 rounded-xl border border-orange-100 text-sm'>
                <p class='text-gray-800 font-semibold'>Please enter your <span class='text-orange-600'>Order ID</span> or <span class='text-orange-600'>User ID</span> below.</p>
                <p class='text-xs text-gray-500 mt-1'>I will search for your latest order details immediately.</p>
            </div>
        ");
    }

    // --- SEARCH LOGIC (Order OR User) ---

    // A. Check for USER (Wallet Balance)
    $user_query = "SELECT * FROM users WHERE username = '$msg' OR email = '$msg' LIMIT 1";
    $user_res = mysqli_query($conn, $user_query);

    if (mysqli_num_rows($user_res) > 0) {
        $u = mysqli_fetch_assoc($user_res);
        $balance = number_format($u['wallet_balance'], 2);
        $status_badge = ($u['status'] == 'active') 
            ? "<span class='bg-green-100 text-green-700 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider'>Active</span>" 
            : "<span class='bg-red-100 text-red-700 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider'>{$u['status']}</span>";
        
        $email_parts = explode("@", $u['email']);
        $masked_email = substr($email_parts[0], 0, 3) . "***@" . $email_parts[1];

        sendReply("
            <div class='bg-white/40 backdrop-blur-md rounded-2xl p-0 border border-white/30 shadow-lg mt-2 relative overflow-hidden group'>
                 <div class='absolute top-0 right-0 p-2 opacity-5 text-themeDark'>
                    <svg class='w-24 h-24' fill='currentColor' viewBox='0 0 20 20'><path d='M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z'></path><path fill-rule='evenodd' d='M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z' clip-rule='evenodd'></path></svg>
                 </div>
                 <div class='bg-[#08203E] p-4 text-white relative z-10'>
                     <p class='text-xs text-white/60 uppercase font-bold tracking-wider mb-1'>Available Balance</p>
                     <p class='text-3xl font-black'>{$balance} <span class='text-sm font-normal text-white/40'>Coins</span></p>
                 </div>
                 <div class='p-4 space-y-3 text-[11px] relative z-10'>
                     <div class='flex justify-between items-center border-b border-white/20 pb-2'>
                         <span class='text-themeDark/40 font-medium'>Username</span>
                         <span class='font-bold text-themeDark'>{$u['username']}</span>
                     </div>
                     <div class='flex justify-between items-center border-b border-white/20 pb-2'>
                         <span class='text-themeDark/40 font-medium'>Email</span>
                         <span class='font-medium text-themeDark/80'>{$masked_email}</span>
                     </div>
                     <div class='flex justify-between items-center'>
                         <span class='text-themeDark/40 font-medium'>Status</span>
                         {$status_badge}
                     </div>
                 </div>
            </div>
        ");
    }

    // B. Check for ORDER
    preg_match('/(ORD)?\d+/i', $msg, $m);
    $input_id = $m[0] ?? $msg; 

    $fallback_msg = "
    <div class='text-center space-y-2'>
        <div class='inline-block p-2 bg-red-50 text-red-500 rounded-full mb-1'>
            <svg class='w-6 h-6' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'></path></svg>
        </div>
        <p class='text-gray-800 font-medium'>No match found for <b class='text-gray-900'>$msg</b></p>
        <p class='text-xs text-gray-500'>Try entering an <b>Order ID</b> (e.g. 1054) or your <b>Username</b>.</p>
    </div>";

    $order_query = "
        SELECT o.*, g.image as game_image, g.title as game_name
        FROM orders o 
        LEFT JOIN diamonds d ON o.product_id = d.product_id 
        LEFT JOIN games g ON d.game_id = g.id 
        WHERE o.order_id LIKE '%$input_id%' 
        LIMIT 1
    ";
    $o = mysqli_fetch_assoc(mysqli_query($conn, $order_query));
    
    if (!$o) {
        $order_query_user = "
            SELECT o.*, g.image as game_image, g.title as game_name
            FROM orders o 
            LEFT JOIN diamonds d ON o.product_id = d.product_id 
            LEFT JOIN games g ON d.game_id = g.id 
            WHERE o.user_id='$input_id' 
            ORDER BY o.id DESC LIMIT 1
        ";
        $o = mysqli_fetch_assoc(mysqli_query($conn, $order_query_user));
    }

    if (!$o) {
        sendReply($fallback_msg);
    }

    // --- ORDER FOUND LOGIC ---
    $status = strtoupper(trim($o['status'])); 
    
    // Status Logic for Styling
    $statusColor = ($status == 'SUCCESS' || $status == 'COMPLETED') ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700';
    $statusIcon = ($status == 'SUCCESS' || $status == 'COMPLETED') 
        ? '<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>'
        : '<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';

    $game_img_url = (strpos($o['game_image'] ?? '', 'http') === 0) ? $o['game_image'] : BASE_URL . '/' . ltrim($o['game_image'] ?? '', '/');

    $details_html = "
        <div class='bg-white rounded-xl p-4 border border-gray-100 text-xs mt-3 shadow-sm'>
            <div class='flex items-center gap-3 mb-4 pb-4 border-b border-gray-50'>
                <div class='w-10 h-10 rounded-lg bg-gray-50 overflow-hidden border border-gray-100'>
                    <img src='{$game_img_url}' class='w-full h-full object-cover'>
                </div>
                <div>
                    <h4 class='font-bold text-gray-900'>{$o['game_name']}</h4>
                    <p class='text-[10px] text-gray-400 font-medium uppercase'>{$o['product_name']}</p>
                </div>
            </div>
            <div class='grid grid-cols-2 gap-y-3'>
                <div class='flex flex-col'>
                    <span class='text-gray-400 text-[10px] uppercase font-semibold'>Order ID</span>
                    <span class='font-mono font-bold text-gray-700 text-sm'>#{$o['order_id']}</span>
                </div>
                <div class='flex flex-col text-right'>
                    <span class='text-gray-400 text-[10px] uppercase font-semibold'>Price</span>
                    <span class='font-bold text-indigo-600 text-sm'>₹{$o['price']}</span>
                </div>
                <div class='flex flex-col'>
                    <span class='text-gray-400 text-[10px] uppercase font-semibold'>User ID</span>
                    <span class='font-medium text-gray-700'>{$o['game_user_id']}</span>
                </div>
                 <div class='flex flex-col items-end'>
                    <span class='text-gray-400 text-[10px] uppercase font-semibold mb-1'>Status</span>
                    <span class='flex items-center font-bold px-2 py-1 rounded-md text-[10px] {$statusColor}'>
                        {$statusIcon} {$status}
                    </span>
                </div>
            </div>
        </div>
    ";

    // 🟥 PENDING ORDER
    if ($status == "PENDING") {
        // [UPDATED] Video Link Hardcoded below
        $payment_video_link = $payment_video_link = "https://www.youtube.com/embed/ctT6NQsIU7U"; // <--- CHANGE THIS LINK

        sendReply("
            <div class='space-y-3'>
                <div class='flex items-center gap-2 text-rose-600 font-bold text-sm'>
                    <span class='relative flex h-2.5 w-2.5'>
                      <span class='animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75'></span>
                      <span class='relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500'></span>
                    </span>
                    Payment Action Required
                </div>
                
                $details_html

                <div class='bg-gray-50 p-3 rounded-xl border border-gray-100 mt-2'>
                    <p class='text-xs font-bold text-gray-500 uppercase mb-2'>Action Required</p>
                    <button onclick='verifyOrder(\"{$o['order_id']}\", this)' class='w-full py-3 bg-indigo-600 text-white rounded-xl font-bold text-xs shadow-lg shadow-indigo-200 mb-3 flex items-center justify-center gap-2'>
                        <i class='fa-solid fa-shield-check'></i> Verify Payment Status
                    </button>
                    <p class='text-[10px] text-gray-500 mb-2 italic text-center'>If you've already paid, click above to verify.</p>
                    <div class='rounded-lg overflow-hidden shadow-sm'>
                        <iframe class='w-full aspect-video' src='$payment_video_link' allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        ");
    }

    // 🟢 SUCCESS ORDER
    if ($status == "SUCCESS" || $status == "COMPLETED") {
        sendReply("
            <div class='space-y-3'>
                <div class='flex items-center gap-2 text-emerald-600 font-bold text-sm'>
                    <div class='w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center'>
                        <svg class='w-4 h-4' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'></path></svg>
                    </div>
                    Order Completed
                </div>

                $details_html
                
                <p class='text-sm text-gray-500 mt-4 text-center'>Need further assistance?</p>

                <div class='grid grid-cols-1 gap-2'>
                    <button onclick='sendMessage(\"show_recover\")' class='w-full text-left p-3 bg-gradient-to-r from-orange-50 to-white hover:to-orange-50 text-orange-800 border border-orange-100 rounded-xl transition-all shadow-sm hover:shadow-md flex items-center justify-between group'>
                        <span class='font-medium text-xs flex items-center gap-2'>
                           <span class='text-lg'>⚠️</span> Product Not Received
                        </span>
                        <svg class='w-4 h-4 text-orange-400 group-hover:translate-x-1 transition' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 5l7 7-7 7'></path></svg>
                    </button>

                    <button onclick='sendMessage(\"show_jcoin\")' class='w-full text-left p-3 bg-gradient-to-r from-indigo-50 to-white hover:to-indigo-50 text-indigo-800 border border-indigo-100 rounded-xl transition-all shadow-sm hover:shadow-md flex items-center justify-between group'>
                        <span class='font-medium text-xs flex items-center gap-2'>
                           <span class='text-lg'>💰</span> J-Coin Wallet Help
                        </span>
                        <svg class='w-4 h-4 text-indigo-400 group-hover:translate-x-1 transition' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 5l7 7-7 7'></path></svg>
                    </button>

                    <a href='https://wa.me/918730063275?text=Hello%20Admin,%20I%20need%20help%20with%20my%20order' target='_blank' class='w-full text-left p-3 bg-gradient-to-r from-green-50 to-white hover:to-green-50 text-green-800 border border-green-100 rounded-xl transition-all shadow-sm hover:shadow-md flex items-center justify-between group'>
                        <span class='font-medium text-xs flex items-center gap-2'>
                           <span class='text-lg'>💬</span> Contact Admin
                        </span>
                        <svg class='w-4 h-4 text-green-500 group-hover:translate-x-1 transition' fill='currentColor' viewBox='0 0 24 24'><path d='M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z'/></svg>
                    </a>
                </div>
            </div>
        ");
    }

    // General Order Reply
    sendReply($details_html);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Customer Support</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: '#6366f1',
                        secondary: '#a855f7',
                    },
                    boxShadow: {
                        'glass': '0 8px 32px 0 rgba(31, 38, 135, 0.07)',
                    }
                }
            }
        }
    </script>

    <style>
        /* Modern Reset */
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        
        /* Mobile Viewport Fix */
        .h-dvh { height: 100vh; height: 100dvh; }

        /* Custom Scrollbar */
        .custom-scroll::-webkit-scrollbar { width: 5px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: #d1d5db; }

        /* Animations */
        @keyframes slideUp { 
            from { opacity: 0; transform: translateY(15px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        .msg-enter { animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        
        @keyframes pulse-dot { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(0.8); opacity: 0.5; } }
        .typing-dot { animation: pulse-dot 1s infinite; }
        .typing-dot:nth-child(2) { animation-delay: 0.1s; }
        .typing-dot:nth-child(3) { animation-delay: 0.2s; }

        /* --- CSS ANIMATED AVATAR --- */
        .bot-avatar {
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-3px); }
        }
        .bot-eyes { animation: blink 4s infinite; transform-origin: center; }
        @keyframes blink {
            0%, 96%, 100% { transform: scaleY(1); }
            98% { transform: scaleY(0.1); }
        }
        .bot-antenna { animation: wiggle 2.5s ease-in-out infinite; transform-origin: bottom center; }
        @keyframes wiggle {
            0%, 100% { transform: rotate(-5deg); }
            50% { transform: rotate(5deg); }
        }
        .bot-glow { animation: pulse-glow 2s infinite; }
        @keyframes pulse-glow {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.1); }
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen text-white p-4" 
    style="background: hsla(213, 77%, 14%, 1);
           background: linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
           background: -moz-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
           background: -webkit-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);">

    <div class="relative w-full h-dvh sm:h-[85vh] sm:max-w-[400px] bg-white/10 backdrop-blur-2xl sm:rounded-[40px] sm:shadow-2xl shadow-black/20 flex flex-col overflow-hidden sm:border border-white/10">
        
        <div class="absolute top-0 left-0 w-full h-64 bg-gradient-to-b from-white/20 to-transparent pointer-events-none"></div>

        <header class="absolute top-0 w-full z-20 bg-black/20 backdrop-blur-xl border-b border-white/10 px-4 py-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <div class="relative w-10 h-10 flex items-center justify-center">
                    <div class="absolute inset-0 bg-indigo-400 rounded-full blur opacity-20 bot-glow"></div>
                    <svg class="w-10 h-10 bot-avatar drop-shadow-lg" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="20" y="30" width="60" height="50" rx="12" fill="url(#grad1)" />
                        <rect x="28" y="42" width="44" height="28" rx="6" fill="#1e1b4b" opacity="0.9"/>
                        <g class="bot-eyes">
                            <circle cx="40" cy="56" r="3.5" fill="#4ade80" />
                            <circle cx="60" cy="56" r="3.5" fill="#4ade80" />
                        </g>
                        <g class="bot-antenna">
                            <line x1="50" y1="30" x2="50" y2="15" stroke="#6366f1" stroke-width="3" stroke-linecap="round"/>
                            <circle cx="50" cy="12" r="4" fill="#ef4444"/>
                        </g>
                        <defs>
                            <linearGradient id="grad1" x1="20" y1="30" x2="80" y2="80" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#6366f1" />
                                <stop offset="1" stop-color="#8b5cf6" />
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 border-2 border-white rounded-full"></div>
                </div>
                
                <div class="flex flex-col">
                    <h1 class="font-bold text-white text-sm leading-tight">JZ Assistant</h1>
                    <span class="text-[10px] font-medium text-white bg-white/10 px-1.5 py-0.5 rounded-full w-fit border border-white/10">Active Now</span>
                </div>
            </div>
            
            <button onclick="location.reload()" class="p-2 rounded-full hover:bg-gray-100 transition text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            </button>
        </header>

        <div id="chat-box" class="flex-1 overflow-y-auto px-4 pt-20 pb-32 custom-scroll space-y-5">
            
            <div class="flex w-full space-x-3 max-w-[90%] msg-enter">
                <div class="flex-shrink-0 h-8 w-8 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center mt-auto shadow-md">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
                <div class="space-y-1">
                    <span class="text-[10px] text-white/40 ml-1">JZ Assistant</span>
                    <div class="bg-white/10 backdrop-blur-md border border-white/10 p-4 rounded-2xl rounded-tl-none text-white text-sm shadow-glass">
                        <p class="mb-3">Hello! 👋 I can help you check your <b>Order Status</b> or <b>Wallet Balance</b>.</p>
                        
                        <div class="grid grid-cols-1 gap-2">
                            <button onclick="sendMessage('check_order_req')" class="text-left w-full p-2.5 bg-white/10 hover:bg-white/20 rounded-xl text-xs font-bold text-white transition flex items-center gap-2 border border-white/10">
                                <span class="bg-white/10 p-1 rounded-md shadow-sm border border-white/10">📦</span> Check Order Status
                            </button>
                            
                            <button onclick="sendMessage('check_wallet_req')" class="text-left w-full p-2.5 bg-white/5 hover:bg-white/10 rounded-xl text-xs font-bold text-white transition flex items-center gap-2 border border-white/5">
                                <span class="bg-white/5 p-1 rounded-md shadow-sm border border-white/5">💳</span> Check Wallet Balance
                            </button>

                            <a href="https://wa.me/918730063275?text=Hello%20Admin,%20I%20need%20help" target="_blank" class="text-left w-full p-2.5 bg-white/5 hover:bg-white/10 rounded-xl text-xs font-bold text-white transition flex items-center gap-2 border border-white/5">
                                <span class="bg-white/5 p-1 rounded-md shadow-sm border border-white/5 text-green-400">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                                    </span> Contact Admin
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div id="typing" class="hidden absolute bottom-20 left-4 z-10 bg-white/10 backdrop-blur-md px-4 py-3 rounded-2xl rounded-bl-none shadow-lg border border-white/10 items-center gap-1.5 msg-enter">
             <div class="w-1.5 h-1.5 bg-white/40 rounded-full typing-dot"></div>
             <div class="w-1.5 h-1.5 bg-white/40 rounded-full typing-dot"></div>
             <div class="w-1.5 h-1.5 bg-white/40 rounded-full typing-dot"></div>
        </div>

        <div class="absolute bottom-0 w-full p-4 pb-2 bg-gradient-to-t from-black/40 via-transparent to-transparent z-20 flex flex-col gap-2 backdrop-blur-sm">
            <div class="bg-white/5 backdrop-blur-xl p-1.5 rounded-[20px] shadow-[0_0_20px_rgba(0,0,0,0.1)] border border-white/10 flex items-center gap-2 focus-within:ring-2 focus-within:ring-white/10 focus-within:border-white/20 transition-all">
                <input id="msg-input" type="text" 
                    class="flex-1 bg-transparent border-none focus:ring-0 text-sm px-4 py-3 text-white placeholder-white/30" 
                    placeholder="Enter Order ID or Username..." 
                    autocomplete="off">
                
                <button onclick="handleUserSend()" class="p-3 bg-white text-indigo-900 rounded-2xl shadow-lg shadow-white/5 hover:scale-105 active:scale-95 transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transform rotate-90" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                    </svg>
                </button>
            </div>
            
            <div class="text-center">
                <span class="text-[10px] text-white/30 font-bold tracking-widest uppercase cursor-default">
                    Powered by JZSTORE
                </span>
            </div>
        </div>

    </div>

    <script>
        const chatBox = document.getElementById('chat-box');
        const input = document.getElementById('msg-input');
        const typing = document.getElementById('typing');

        input.addEventListener("keypress", (e) => { if(e.key === "Enter") handleUserSend(); });

        function scrollToBottom() { 
            setTimeout(() => {
                chatBox.scrollTo({ top: chatBox.scrollHeight, behavior: 'smooth' });
            }, 100);
        }

        function appendMsg(role, html) {
            const wrapper = document.createElement('div');
            wrapper.className = `flex w-full space-x-3 max-w-[90%] msg-enter ${role === 'user' ? 'ml-auto justify-end' : ''}`;
            
            if (role === 'bot') {
                wrapper.innerHTML = `
                    <div class="flex-shrink-0 h-8 w-8 rounded-full bg-white/10 border border-white/10 flex items-center justify-center mt-auto shadow-md">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'></path></svg>
                    </div>
                    <div class="space-y-1 w-full">
                        <span class="text-[10px] text-white/40 ml-1">JZ Assistant</span>
                        <div class="bg-white/10 backdrop-blur-md border border-white/10 p-3.5 rounded-2xl rounded-tl-none text-white text-sm shadow-glass leading-relaxed w-full overflow-hidden">
                            ${html}
                        </div>
                    </div>
                `;
            } else {
                wrapper.innerHTML = `
                    <div class="bg-white p-3 px-5 rounded-2xl rounded-br-none text-black shadow-lg shadow-white/5">
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
            input.focus();
            
            sendMessage(val);
        }

        window.sendMessage = function(msg) {
            typing.style.display = 'flex';
            scrollToBottom();
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ msg: msg })
            })
            .then(res => res.json())
            .then(data => {
                setTimeout(() => { // Artifical delay for realism
                    typing.style.display = 'none';
                    appendMsg('bot', data.reply);
                }, 600);
            })
            .catch(err => {
                typing.style.display = 'none';
                appendMsg('bot', '<span class="text-red-500 font-bold">Error:</span> Could not connect to server.');
            });
        }

        async function verifyOrder(orderId, btn) {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Checking...';
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
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> Verified!';
                    btn.classList.replace('bg-indigo-600', 'bg-emerald-600');
                    setTimeout(() => sendMessage(orderId), 1500); 
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