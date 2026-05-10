<?php
if (!isset($setting)) {
    $setting = $current_settings ?? [];
    if (empty($setting) && isset($conn)) {
        $res_s = $conn->query("SELECT * FROM fav_setting LIMIT 1");
        if ($res_s && $row_s = $res_s->fetch_assoc()) $setting = $row_s;
    }
    if (empty($setting)) $setting = ['store_name' => 'JZ Store'];
}
?>
<div class="mb-8 px-1">
            <h3 class="text-sm font-bold text-themeDark flex items-center gap-2 mb-4">
                <span class="w-1 h-4 bg-themeGreen rounded-full shadow-[0_0_8px_rgba(128,191,21,0.5)]"></span>
                Information & Help
            </h3>
            <div class="grid grid-cols-2 gap-3">
                <a href="<?= BASE_URL ?>/faq" class="flex items-center gap-3 p-3 glass-panel rounded-2xl hover:bg-white/50 transition">
                    <div class="w-10 h-10 rounded-xl bg-themeDark/10 flex items-center justify-center text-themeDark">
                        <i class="fa-solid fa-circle-question"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-themeDark">FAQ</p>
                        <p class="text-[9px] text-themeDark/60">Common questions</p>
                    </div>
                </a>
                <a href="<?= BASE_URL ?>/blog" class="flex items-center gap-3 p-3 glass-panel rounded-2xl hover:bg-white/50 transition">
                    <div class="w-10 h-10 rounded-xl bg-themeDark/10 flex items-center justify-center text-themeDark">
                        <i class="fa-solid fa-newspaper"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-themeDark">Blog</p>
                        <p class="text-[9px] text-themeDark/60">News & Tutorials</p>
                    </div>
                </a>
                <a href="<?= BASE_URL ?>/leaderboard" class="flex items-center gap-3 p-3 glass-panel rounded-2xl hover:bg-white/50 transition">
                    <div class="w-10 h-10 rounded-xl bg-themeDark/10 flex items-center justify-center text-themeDark">
                        <i class="fa-solid fa-trophy"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-themeDark">Ranking</p>
                        <p class="text-[9px] text-themeDark/60">Top Spenders</p>
                    </div>
                </a>
                <a href="<?= BASE_URL ?>/terms" class="flex items-center gap-3 p-3 glass-panel rounded-2xl hover:bg-white/50 transition">
                    <div class="w-10 h-10 rounded-xl bg-themeDark/10 flex items-center justify-center text-themeDark">
                        <i class="fa-solid fa-file-contract"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-themeDark">Terms</p>
                        <p class="text-[9px] text-themeDark/60">Our policies</p>
                    </div>
                </a>
                <a href="<?= htmlspecialchars($setting['whatsapp']); ?>" class="flex items-center gap-3 p-3 glass-panel rounded-2xl hover:bg-white/50 transition">
                    <div class="w-10 h-10 rounded-xl bg-themeDark/10 flex items-center justify-center text-themeDark">
                        <i class="fa-solid fa-headset"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-themeDark">Support</p>
                        <p class="text-[9px] text-themeDark/60">Get instant help</p>
                    </div>
                </a>
                <a href="<?= BASE_URL ?>/apidocs" class="flex items-center gap-3 p-3 glass-panel rounded-2xl hover:bg-white/50 transition">
                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-600">
                        <i class="fa-solid fa-code"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-themeDark">API Docs</p>
                        <p class="text-[9px] text-themeDark/60">Developer portal</p>
                    </div>
                </a>
                <a href="<?= BASE_URL ?>/smm" class="flex items-center gap-3 p-3 glass-panel rounded-2xl hover:bg-white/50 transition col-span-2" style="background:linear-gradient(135deg,rgba(139,92,246,.12),rgba(99,102,241,.08));border:1px solid rgba(139,92,246,.25)">
                    <div class="w-10 h-10 rounded-xl bg-purple-500/15 flex items-center justify-center text-purple-600 flex-shrink-0">
                        <i class="fa-solid fa-rocket"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-[11px] font-bold text-themeDark">Social Boosting 🚀</p>
                        <p class="text-[9px] text-themeDark/60">Followers, likes, views & more</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-purple-400 text-xs"></i>
                </a>
            </div>
        </div>

        <div class="mt-10 mb-8 px-1 text-center">
            <h3 class="text-sm font-bold text-themeDark flex items-center justify-center gap-2 mb-6">
                <span class="w-1 h-4 bg-yellow-500 rounded-full"></span>
                Why Choose <?= htmlspecialchars($setting['store_name']); ?>?
            </h3>
            <div class="grid grid-cols-2 gap-3">
                <div class="glass-panel p-4 rounded-2xl flex flex-col items-center gap-2">
                    <div class="w-11 h-11 rounded-full bg-themeDark/10 flex items-center justify-center">
                        <i class="fa-solid fa-bolt text-themeDark text-lg"></i>
                    </div>
                    <h4 class="text-[11px] font-semibold text-themeDark">Fast Recharge</h4>
                    <p class="text-[9px] text-themeDark/60">Instant delivery!</p>
                </div>
                <div class="glass-panel p-4 rounded-2xl flex flex-col items-center gap-2">
                    <div class="w-11 h-11 rounded-full bg-themeDark/10 flex items-center justify-center">
                        <i class="fa-solid fa-clock text-themeDark text-lg"></i>
                    </div>
                    <h4 class="text-[11px] font-semibold text-themeDark">24x7 Support</h4>
                    <p class="text-[9px] text-themeDark/60">Anytime, anywhere!</p>
                </div>
            </div>
        </div>

        <div class="mt-8 mb-12 text-center">
            <p class="text-[10px] uppercase tracking-widest text-themeDark/50 font-bold mb-4">Secure UPI Payments</p>
            <div class="flex flex-wrap justify-center gap-3">
                <div class="payment-chip px-3 py-1.5 rounded-xl flex items-center gap-2">
                    <img src="https://img.icons8.com/color/48/google-pay-india.png" class="w-4 h-4" alt="Gpay">
                    <span class="text-[9px] font-bold">G-Pay</span>
                </div>
                <div class="payment-chip px-3 py-1.5 rounded-xl flex items-center gap-2">
                    <img src="https://img.icons8.com/color/48/phone-pe.png" class="w-4 h-4" alt="PhonePe">
                    <span class="text-[9px] font-bold">PhonePe</span>
                </div>
                <div class="payment-chip px-3 py-1.5 rounded-xl flex items-center gap-2">
                    <img src="https://img.icons8.com/color/48/paytm.png" class="w-4 h-4" alt="Paytm">
                    <span class="text-[9px] font-bold">Paytm</span>
                </div>
            </div>
        </div>

        <footer class="mt-12 mb-8 text-center px-4">
            <div class="flex justify-center gap-5 mb-6 opacity-60">
                <a href="<?= htmlspecialchars($setting['facebook'] ?? '#'); ?>" class="hover:text-themeDark transition"><i class="fab fa-facebook text-xl"></i></a>
                <a href="<?= htmlspecialchars($setting['whatsapp'] ?? '#'); ?>" class="hover:text-themeDark transition"><i class="fab fa-whatsapp text-xl"></i></a>
                <a href="<?= htmlspecialchars($setting['instagram'] ?? '#'); ?>" class="hover:text-themeDark transition"><i class="fab fa-instagram text-xl"></i></a>
            </div>
            <p class="text-[10px] text-themeDark/60">© <?= date('Y'); ?> <?= htmlspecialchars($setting['store_name']); ?>. All Rights Reserved.</p>
            <p class="text-[10px] text-themeDark/60 mt-1">Designed & Developed by <a href="https://wa.me/918730063275" target="_blank" class="text-themeDark font-bold hover:underline">Zomunaa Sailo</a></p>
        </footer>
    </main>

    <nav class="fixed bottom-0 w-full bg-white/60 backdrop-blur-xl z-50 h-16 flex justify-around items-center max-w-md left-1/2 -translate-x-1/2 border-t border-white/30">
        <a href="<?= htmlspecialchars($setting['whatsapp']); ?>" class="flex flex-col items-center text-themeDark/40 hover:text-themeDark transition">
            <i class="fa-brands fa-whatsapp text-lg"></i>
            <span class="text-[9px] font-medium">Support</span>
        </a>
        <a href="<?= BASE_URL ?>/history" class="flex flex-col items-center text-themeDark/40 hover:text-themeDark transition">
            <i class="fa-solid fa-clock-rotate-left text-lg"></i>
            <span class="text-[9px] font-medium">History</span>
        </a>
        <div class="relative -top-4">
            <a href="<?= BASE_URL ?>/index" class="w-12 h-12 bg-themeDark rounded-full flex items-center justify-center shadow-lg shadow-themeDark/20 text-white border-4 border-white transition hover:scale-105 active:scale-95">
                <i class="fa-solid fa-house"></i>
            </a>
        </div>
        <a href="<?= BASE_URL ?>/wallet" class="flex flex-col items-center text-themeDark/40 hover:text-themeDark transition">
            <i class="fa-solid fa-wallet text-lg"></i>
            <span class="text-[9px] font-medium">Wallet</span>
        </a>
        <a href="<?= BASE_URL ?>/profile" class="flex flex-col items-center text-themeDark/40 hover:text-themeDark transition">
            <i class="fa-solid fa-user-gear text-lg"></i>
            <span class="text-[9px] font-medium">Account</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        // Initialize Swiper only if the slider exists on page
        if (document.querySelector('.swiper')) {
            const swiper = new Swiper('.swiper', {
                loop: true,
                autoplay: { delay: 3000 },
                pagination: { el: '.swiper-pagination', clickable: true },
            });
        }

        // Flash Sale Countdown Timer
        function startFlashTimer() {
            const timerH = document.getElementById('timer-h');
            const timerM = document.getElementById('timer-m');
            const timerS = document.getElementById('timer-s');

            if (!timerH) return;

            // Target time from DB or End of current day
            let target;
            if (window.flashSaleEnd && window.flashSaleEnd !== "") {
                target = new Date(window.flashSaleEnd);
            } else {
                target = new Date();
                target.setHours(23, 59, 59, 999);
            }

            function updateTimer() {
                const currentTime = new Date();
                const diff = target - currentTime;

                if (diff <= 0) {
                    timerH.innerText = "00";
                    timerM.innerText = "00";
                    timerS.innerText = "00";
                    // Optionally hide flash sale section if expired
                    const flashSec = document.querySelector('.mb-8.relative.overflow-hidden');
                    // if(flashSec) flashSec.style.display = 'none';
                    return;
                }

                const h = Math.floor(diff / (1000 * 60 * 60));
                const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const s = Math.floor((diff % (1000 * 60)) / 1000);

                timerH.innerText = h.toString().padStart(2, '0');
                timerM.innerText = m.toString().padStart(2, '0');
                timerS.innerText = s.toString().padStart(2, '0');
            }

            updateTimer();
            setInterval(updateTimer, 1000);
        }

        document.addEventListener('DOMContentLoaded', startFlashTimer);
    </script>
    <script>
        // PWA Install Logic
        let deferredPrompt;
        const installBtn = document.getElementById('pwa-install-btn');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            if (installBtn) installBtn.classList.remove('hidden');
        });

        if (installBtn) {
            installBtn.addEventListener('click', async () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    const { outcome } = await deferredPrompt.userChoice;
                    if (outcome === 'accepted') {
                        installBtn.classList.add('hidden');
                    }
                    deferredPrompt = null;
                }
            });
        }

        window.addEventListener('appinstalled', () => {
            if (installBtn) installBtn.classList.add('hidden');
            deferredPrompt = null;
        });

        // Register Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?= BASE_URL ?>/sw.js')
                    .then(reg => console.log('SW Registered'))
                    .catch(err => console.log('SW Failed', err));
            });
        }
    </script>
</body>
</html>