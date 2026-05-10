<?php
require_once __DIR__ . '/config.php';

// Fetch store settings for the title
$setting = ['store_name' => 'JZ Store', 'whatsapp' => ''];
$check_setting = $conn->query("SELECT store_name, whatsapp FROM fav_setting LIMIT 1");
if ($check_setting && $check_setting->num_rows > 0) {
    $row = $check_setting->fetch_assoc();
    if (!empty($row['store_name'])) $setting['store_name'] = $row['store_name'];
    if (!empty($row['whatsapp'])) $setting['whatsapp'] = $row['whatsapp'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Tutorial - <?= htmlspecialchars($setting['store_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        jollyRed: '#80bf15',
                        darkBlue: '#ffffff',
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Roboto', sans-serif; 
            background: hsla(213, 77%, 14%, 1);
            background: linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -moz-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -webkit-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            filter: progid: DXImageTransform.Microsoft.gradient( startColorstr="#08203E", endColorstr="#557C93", GradientType=1 );
            background-attachment: fixed; color: #ffffff; }
        .tab-btn { padding: 10px; font-weight: bold; border-radius: 12px; transition: 0.3s; flex: 1; text-align: center; font-size: 12px; text-transform: uppercase; }
        .tab-btn.active { background: #ffffff; color: #0f172a; border: none; }
        .tab-btn.inactive { background: rgba(255,255,255,0.1); color: #ffffff; border: 1px solid rgba(255,255,255,0.1); }
        
        .lang-btn { padding: 6px 15px; border-radius: 20px; font-size: 11px; font-weight: 800; transition: 0.3s; border: none; cursor: pointer; }
        .lang-btn.active { background: #ffffff; color: #0f172a; }
        .lang-btn.inactive { background: transparent; border: 1px solid rgba(255, 255, 255, 0.2); color: #ffffff; }

        .content-card { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(12px); border-radius: 24px; color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.1); }
        .hidden { display: none; }
    </style>
</head>
<body class="p-4 pb-20">

    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="javascript:history.back()" class="w-10 h-10 flex items-center justify-center bg-white/10 rounded-full border border-white/10">
                <i class="fa-solid fa-chevron-left text-white"></i>
            </a>
            <h1 class="text-xl font-black italic uppercase text-white">Tutorial <span class="text-white/60">Center</span></h1>
        </div>
        <div class="flex bg-white/10 p-1 rounded-full border border-white/10">
            <button onclick="switchLang('mizo')" id="langMizo" class="lang-btn active">MIZO</button>
            <button onclick="switchLang('eng')" id="langEng" class="lang-btn inactive">ENG</button>
        </div>
    </div>

    <div class="flex gap-2 mb-6 overflow-x-auto pb-2">
        <button id="catRecharge" onclick="switchCat('recharge')" class="tab-btn active">Recharge</button>
        <button id="catWallet" onclick="switchCat('wallet')" class="tab-btn inactive">Wallet</button>
        <button id="catRegister" onclick="switchCat('register')" class="tab-btn inactive">Register</button>
    </div>

    <div id="tutorialContent">
        
        <div id="secRecharge" class="category-section space-y-4">
            <div class="aspect-video bg-black rounded-2xl overflow-hidden shadow-2xl border border-white/10">
                <iframe id="videoRecharge" class="w-full h-full" src="" frameborder="0" allowfullscreen></iframe>
            </div>
            <div class="content-card p-5 border border-white/30 shadow-sm">
                <h3 id="titleRecharge" class="font-bold text-lg mb-3 flex items-center gap-2 text-white">
                    <i class="fa-solid fa-bolt text-white"></i> <span>Recharge Dan</span>
                </h3>
                <ul id="listRecharge" class="space-y-3 text-sm text-white/60"></ul>
            </div>
        </div>

        <div id="secWallet" class="category-section hidden space-y-4">
            <div class="aspect-video bg-black rounded-2xl overflow-hidden shadow-2xl border border-white/10">
                <iframe id="videoWallet" class="w-full h-full" src="" frameborder="0" allowfullscreen></iframe>
            </div>
            <div class="content-card p-5 border border-white/30 shadow-sm">
                <h3 id="titleWallet" class="font-bold text-lg mb-3 flex items-center gap-2 text-white">
                    <i class="fa-solid fa-wallet text-white"></i> <span>Wallet Hman Dan</span>
                </h3>
                <ul id="listWallet" class="space-y-3 text-sm text-white/60"></ul>
            </div>
        </div>

        <div id="secRegister" class="category-section hidden space-y-4">
            <div class="aspect-video bg-black rounded-2xl overflow-hidden shadow-2xl border border-white/10">
                <iframe id="videoRegister" class="w-full h-full" src="" frameborder="0" allowfullscreen></iframe>
            </div>
            <div class="content-card p-5 border border-white/30 shadow-sm">
                <h3 id="titleRegister" class="font-bold text-lg mb-3 flex items-center gap-2 text-white">
                    <i class="fa-solid fa-user-plus text-white"></i> <span>Account Siam Dan</span>
                </h3>
                <ul id="listRegister" class="space-y-3 text-sm text-white/60"></ul>
            </div>
        </div>

    </div>

    <div class="mt-8 text-center">
        <p class="text-xs text-white/40 mb-4 italic">Still need help? Contact our support via WhatsApp.</p>
        <a href="https://wa.me/<?= htmlspecialchars($setting['whatsapp']); ?>" class="bg-white text-black px-8 py-3 rounded-xl font-bold text-sm inline-flex items-center gap-2 shadow-lg shadow-white/5">
            <i class="fa-brands fa-whatsapp text-lg"></i> WHATSAPP SUPPORT
        </a>
    </div>

    <script>
        let currentLang = 'mizo';
        let currentCat = 'recharge';

        const data = {
            recharge: {
                mizo: {
                    title: "Recharge Dan",
                    video: "h8exqb2ahIM", // Example YouTube ID
                    steps: [
                        "Game ID & Zone ID chhu lut la.",
                        "Check Username hmet la, i game name a rawn lan hun nghak rawh.",
                        "Diamond pack thlang la, payment method i duh kha thlang rawh.",
                        "Buy Now hmet la, Pay hmet leh rawh. Payment tih chhungin Website clear emaw refresh loh tur.",
                        "I pay zawh ah website ah i back leh ang a, order status update nghak rawh."
                    ]
                },
                eng: {
                    title: "How to Recharge",
                    video: "d93OR0ubTbw", // Example YouTube ID
                    steps: [
                        "Enter your User ID and Zone ID.",
                        "Click 'Check Username' and wait for your game name to appear.",
                        "Select your Diamond Pack and Payment Method.",
                        "Click 'Buy Now' and confirm the payment.",
                        "Do not refresh or close the website during payment processing.",
                        "After completing payment, return to the website and wait for the status to update."
                    ]
                }
            },
            wallet: {
                mizo: {
                    title: "Wallet Hman Dan",
                    video: "yL5G9LtRgk8",
                    steps: ["I account-ah log in hmasa rawh.", "Wallet-ah pawisa load hmasa rawh.", "Thil i lei hunah payment method-ah 'Wallet' thlang rawh.", "I balance atangin a in cut nghal ang."]
                },
                eng: {
                    title: "How to use Wallet",
                    video: "yL5G9LtRgk8",
                    steps: ["Log in to your account first.", "Top up your wallet balance.", "Choose 'Wallet' as payment method at checkout.", "Amount will be deducted from your balance."]
                }
            },
            register: {
                mizo: {
                    title: "Account Siam Dan",
                    video: "ctT6NQsIU7U",
                    steps: ["'Register' button hmet rawh.", "I hming leh Email .", "Password i duh ber siam rawh.", "Register hmet la, i hman thei nghal."]
                },
                eng: {
                    title: "How to Register",
                    video: "ctT6NQsIU7U",
                    steps: ["Click the 'Register' button.", "Enter your Name, Email, and Phone.", "Create a strong password.", "Click Register and your account is ready."]
                }
            }
        };

        function updateUI() {
            const categories = ['recharge', 'wallet', 'register'];
            
            categories.forEach(cat => {
                const content = data[cat][currentLang];
                const capCat = cat.charAt(0).toUpperCase() + cat.slice(1);
                
                // Update Title
                document.getElementById(`title${capCat}`).querySelector('span').innerText = content.title;
                
                // Update Video
                document.getElementById(`video${capCat}`).src = `https://www.youtube.com/embed/${content.video}`;
                
                // Update List
                let listHtml = '';
                content.steps.forEach((step, index) => {
                    listHtml += `
                        <li class="flex gap-3">
                            <span class="bg-jollyRed/10 text-jollyRed w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0">${index + 1}</span>
                            <span class="leading-tight">${step}</span>
                        </li>`;
                });
                document.getElementById(`list${capCat}`).innerHTML = listHtml;
            });
        }

        function switchCat(cat) {
            currentCat = cat;
            // Hide all sections
            document.querySelectorAll('.category-section').forEach(el => el.classList.add('hidden'));
            
            // Set all tabs to inactive
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('active');
                el.classList.add('inactive');
            });

            // Show selected section and active tab
            const capCat = cat.charAt(0).toUpperCase() + cat.slice(1);
            document.getElementById(`sec${capCat}`).classList.remove('hidden');
            document.getElementById(`cat${capCat}`).classList.replace('inactive', 'active');
        }

        function switchLang(lang) {
            currentLang = lang;
            document.getElementById('langMizo').className = lang === 'mizo' ? 'lang-btn active' : 'lang-btn inactive';
            document.getElementById('langEng').className = lang === 'eng' ? 'lang-btn active' : 'lang-btn inactive';
            updateUI();
        }

        // Initialize — respect hash or sessionStorage from homepage cards
        window.onload = function () {
            updateUI();

            // Priority: URL hash > sessionStorage > default (recharge)
            const hash = window.location.hash.replace('#', '').trim();
            const stored = sessionStorage.getItem('tutCat');
            const target = (hash && data[hash]) ? hash : (stored && data[stored]) ? stored : 'recharge';

            // Clear so it doesn't persist on refresh
            sessionStorage.removeItem('tutCat');

            if (target !== 'recharge') {
                switchCat(target);
            }
        };
    </script>
</body>
</html>