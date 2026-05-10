<?php
require_once __DIR__ . '/includes/config.php';

// Fetch store settings
$setting = get_settings();
$store_name    = $setting['store_name']  ?? 'JZ Store';
$fav_icon      = $setting['fav_icon']    ?? '';
$whatsapp_link = $setting['whatsapp']    ?? 'https://wa.me/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>FAQ - <?= htmlspecialchars($store_name) ?></title>
    <meta name="description" content="Frequently asked questions about <?= htmlspecialchars($store_name) ?> — delivery, payments, refunds and more.">
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($fav_icon) ?>">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=DynaPuff:wght@400;600&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        themeDark: '#ffffff',
                        themeBlue: '#557C93',
                        midnight: '#08203E',
                    },
                    fontFamily: {
                        poppins:  ['Poppins', 'sans-serif'],
                        dynapuff: ['DynaPuff', 'cursive'],
                    }
                }
            }
        }
    </script>

    <style>
        /* ── Base ── */
        body {
            font-family: 'Poppins', sans-serif;
            background: hsla(213, 77%, 14%, 1);
            background: linear-gradient(90deg, hsla(213,77%,14%,1) 0%, hsla(202,27%,45%,1) 100%);
            background-attachment: fixed;
            color: #ffffff;
            overflow-x: hidden;
            -webkit-tap-highlight-color: transparent;
        }

        /* ── Floating blobs ── */
        .bg-blob {
            position: fixed;
            width: 420px; height: 420px;
            background: linear-gradient(135deg, rgba(8,32,62,.45) 0%, rgba(85,124,147,.45) 100%);
            filter: blur(80px);
            border-radius: 50%;
            z-index: -1;
            animation: blobMove 20s infinite alternate;
        }
        @keyframes blobMove {
            from { transform: translate(-10%,-10%) scale(1);   }
            to   { transform: translate(10%, 10%) scale(1.12); }
        }

        /* ── Glass panels ── */
        .glass {
            background: rgba(255,255,255,.1);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,.1);
        }

        /* ── FAQ accordion ── */
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height .35s cubic-bezier(.4,0,.2,1), padding .35s ease;
        }
        .faq-item.open .faq-answer {
            max-height: 600px;
        }
        .faq-item.open .chevron {
            transform: rotate(180deg);
        }
        .chevron {
            transition: transform .3s ease;
            flex-shrink: 0;
        }

        /* ── Slide-up animation ── */
        .slide-up {
            animation: slideUp .4s ease-out both;
        }
        @keyframes slideUp {
            from { opacity:0; transform:translateY(18px); }
            to   { opacity:1; transform:translateY(0);    }
        }
    </style>
</head>
<body class="pb-24 antialiased">

    <!-- Blobs -->
    <div class="bg-blob" style="top:-100px;left:-100px;"></div>
    <div class="bg-blob" style="bottom:-100px;right:-100px;animation-delay:-6s;"></div>

    <!-- ── Header ── -->
    <header class="fixed top-0 w-full z-40 glass h-16 border-b border-white/5 shadow-lg">
        <div class="max-w-md mx-auto px-4 h-full flex items-center justify-between">
            <a href="<?= BASE_URL ?>"
               class="w-10 h-10 rounded-2xl bg-white/10 border border-white/10 flex items-center justify-center transition active:scale-90">
                <i class="fa-solid fa-arrow-left text-white text-sm"></i>
            </a>
            <div class="font-bold text-base text-white font-dynapuff tracking-wider">
                FAQ
            </div>
            <div class="w-10"></div>
        </div>
    </header>

    <!-- ── Main ── -->
    <main class="max-w-md mx-auto pt-24 px-4">

        <!-- Page hero -->
        <div class="text-center mb-8 slide-up">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl mb-4"
                 style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);">
                <i class="fa-solid fa-circle-question text-2xl text-white/80"></i>
            </div>
            <h1 class="text-2xl font-black text-white font-dynapuff tracking-tight">
                Frequently Asked
            </h1>
            <p class="text-[11px] font-bold text-white/40 mt-1 uppercase tracking-[0.2em]">
                Everything you need to know
            </p>
        </div>

        <!-- ── FAQ Items ── -->
        <?php
        $faqs = [
            [
                'q' => "Is {$store_name} legit and safe?",
                'a' => "Yes, <strong>{$store_name}</strong> is 100% legit and safe. We provide fast and secure game recharges and digital products. Our website uses secure payment systems, and thousands of customers trust our service.",
                'icon' => 'fa-shield-halved', 'color' => 'text-emerald-400', 'bg' => 'rgba(16,185,129,.15)'
            ],
            [
                'q' => 'How long does delivery take?',
                'a' => "Most products are delivered <strong>instantly</strong> after successful payment.<ul class='mt-2 space-y-1 list-disc list-inside text-white/60'><li><strong class='text-white'>Instant products:</strong> Delivered within seconds to 1 minute</li><li><strong class='text-white'>Manual products:</strong> May take a few minutes to a few hours</li></ul>Non-instant products are always clearly marked before purchase.",
                'icon' => 'fa-bolt-lightning', 'color' => 'text-yellow-400', 'bg' => 'rgba(234,179,8,.15)'
            ],
            [
                'q' => 'Why is my order still pending?',
                'a' => "Your order may be pending due to:<ul class='mt-2 space-y-1 list-disc list-inside text-white/60'><li>Not returning to the website after payment</li><li>Refreshing or closing the site during processing</li><li>Payment gateway delays or network issues</li></ul><strong class='text-white'>What to do:</strong> Login → Profile → copy your Username → send it with payment screenshot & Transaction ID to our support team.",
                'icon' => 'fa-clock-rotate-left', 'color' => 'text-orange-400', 'bg' => 'rgba(249,115,22,.15)'
            ],
            [
                'q' => 'I paid but did not receive my Diamonds?',
                'a' => "Don't panic! Check your order status in your account. If it shows <strong>Pending</strong>, contact support with your <strong>Username</strong>, payment screenshot, and <strong>Transaction ID</strong>. We will resolve it quickly.",
                'icon' => 'fa-gem', 'color' => 'text-cyan-400', 'bg' => 'rgba(6,182,212,.15)'
            ],
            [
                'q' => 'Do I need to refresh the website after payment?',
                'a' => "<strong class='text-red-400'>No! Do NOT refresh, close, or leave</strong> while payment is processing. Wait until you are automatically redirected back and the status updates.",
                'icon' => 'fa-triangle-exclamation', 'color' => 'text-red-400', 'bg' => 'rgba(239,68,68,.15)'
            ],
            [
                'q' => 'Can I get a refund?',
                'a' => "Yes, if your request meets our Refund Policy.<ul class='mt-2 space-y-1 list-disc list-inside text-white/60'><li><strong class='text-white'>Wallet refund:</strong> Instant after approval</li><li><strong class='text-white'>Cash refund:</strong> Minimum 24 hours after approval</li></ul><a href='".BASE_URL."/refund' class='text-cyan-400 underline font-bold'>View full refund policy →</a>",
                'icon' => 'fa-rotate-left', 'color' => 'text-purple-400', 'bg' => 'rgba(139,92,246,.15)'
            ],
            [
                'q' => 'Double Diamonds — Why didn\'t I receive it?',
                'a' => "The Double Diamond bonus is a special Mobile Legends event for <strong>first purchase</strong> of selected packages.<ul class='mt-2 space-y-1 list-disc list-inside text-white/60'><li>You may have already used the bonus (it is once per account).</li><li>This is controlled entirely by the game server, not our store.</li></ul>",
                'icon' => 'fa-star', 'color' => 'text-yellow-300', 'bg' => 'rgba(253,224,71,.1)'
            ],
            [
                'q' => 'Is it safe to enter my User ID and Zone ID?',
                'a' => "Absolutely yes. These IDs are <strong>only used to deliver your purchase</strong> to the correct account. We <strong>never</strong> ask for your game password or login credentials.",
                'icon' => 'fa-lock', 'color' => 'text-green-400', 'bg' => 'rgba(34,197,94,.15)'
            ],
            [
                'q' => 'Additional information & policies',
                'a' => "<ul class='space-y-2 list-disc list-inside text-white/70'><li><strong class='text-white'>Payments:</strong> We accept all secure methods listed on our site.</li><li><strong class='text-white'>Cancellations:</strong> Completed orders cannot be cancelled.</li><li><strong class='text-white'>Accounts:</strong> Not required but recommended for order tracking.</li><li><strong class='text-red-400'>Wrong ID:</strong> Contact support immediately — double-check before payment!</li></ul>",
                'icon' => 'fa-circle-info', 'color' => 'text-blue-400', 'bg' => 'rgba(59,130,246,.15)'
            ],
        ];
        ?>

        <div class="space-y-3 mb-8">
        <?php foreach ($faqs as $i => $faq): ?>
            <div class="faq-item glass rounded-2xl overflow-hidden slide-up" style="animation-delay:<?= $i * 0.05 ?>s">
                <!-- Question Button -->
                <button onclick="toggleFaq(this)"
                        class="w-full flex items-center gap-3 p-4 text-left group">
                    <!-- Icon -->
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-sm <?= $faq['color'] ?>"
                         style="background:<?= $faq['bg'] ?>;border:1px solid rgba(255,255,255,.08)">
                        <i class="fa-solid <?= $faq['icon'] ?>"></i>
                    </div>
                    <!-- Text -->
                    <span class="flex-1 text-[13px] font-bold text-white leading-snug">
                        <?= htmlspecialchars($faq['q']) ?>
                    </span>
                    <!-- Chevron -->
                    <i class="fa-solid fa-chevron-down chevron text-white/30 text-xs"></i>
                </button>

                <!-- Answer -->
                <div class="faq-answer">
                    <div class="px-4 pb-4 pt-1 text-[12px] text-white/70 leading-relaxed border-t border-white/5 ml-12">
                        <?= $faq['a'] ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <!-- ── Support CTA ── -->
        <div class="glass rounded-3xl p-6 text-center mb-8 slide-up border border-white/10"
             style="animation-delay:.5s">
            <div class="w-12 h-12 bg-green-500/20 border border-green-400/20 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <i class="fa-brands fa-whatsapp text-green-400 text-2xl"></i>
            </div>
            <h2 class="text-base font-black text-white mb-1">Still need help?</h2>
            <p class="text-[11px] text-white/40 font-bold mb-4 uppercase tracking-wider">Our support team is always ready</p>
            <a href="<?= htmlspecialchars($whatsapp_link) ?>"
               class="inline-flex items-center gap-2 px-7 py-3.5 rounded-2xl font-black text-sm text-white transition active:scale-95 shadow-lg"
               style="background:linear-gradient(135deg,#25d366,#128c4a);box-shadow:0 6px 20px rgba(37,211,102,.3);">
                <i class="fa-brands fa-whatsapp text-base"></i>
                WhatsApp Support
            </a>
        </div>

    </main>

    <script>
        function toggleFaq(btn) {
            const item = btn.closest('.faq-item');
            const isOpen = item.classList.contains('open');

            // Close all
            document.querySelectorAll('.faq-item.open').forEach(el => el.classList.remove('open'));

            // Open clicked (unless it was already open)
            if (!isOpen) item.classList.add('open');
        }
    </script>
</body>
</html>