<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) { header("Location: auth/login"); exit; }
$user_id = $_SESSION['user_id'];

// Fetch user cards
$stmt = $conn->prepare("SELECT * FROM virtual_cards WHERE user_id = ? AND status != 'blocked' ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$setting = ['store_name' => 'JZ Store'];
$sr = $conn->query("SELECT store_name FROM fav_setting LIMIT 1");
if ($sr && $row = $sr->fetch_assoc()) $setting['store_name'] = $row['store_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Wallet Cards - <?= htmlspecialchars($setting['store_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #020617; color: #fff; overflow-x: hidden; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.05); }
        
        /* Premium Card UI */
        .v-card {
            width: 100%; max-width: 340px; height: 210px;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 24px; position: relative; padding: 25px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5), inset 0 0 20px rgba(255,255,255,0.05);
            overflow: hidden; transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            margin: 0 auto;
        }
        .v-card::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
            pointer-events: none;
        }
        .v-card.frozen { filter: grayscale(1) opacity(0.6); }
        .v-card-chip { width: 45px; height: 35px; background: linear-gradient(135deg, #fbbf24, #d97706); border-radius: 8px; margin-bottom: 20px; }
        .v-card-number { font-size: 20px; font-weight: 800; letter-spacing: 3px; font-family: 'Courier New', Courier, monospace; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }
        
        .modal { display: none; position: fixed; inset: 0; z-index: 100; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); align-items: center; justify-content: center; padding: 20px; }
        .modal.show { display: flex; }
        
        .btn-premium { background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; font-weight: 800; padding: 14px; border-radius: 18px; width: 100%; transition: all 0.3s; }
        .btn-premium:active { transform: scale(0.96); }
    </style>
</head>
<body class="pb-24">

    <header class="fixed top-0 w-full z-50 bg-slate-950/50 backdrop-blur-xl h-16 border-b border-white/5">
        <div class="max-w-md mx-auto px-5 h-full flex items-center justify-between">
            <a href="wallet" class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center border border-white/5"><i class="fa-solid fa-arrow-left text-sm"></i></a>
            <div class="font-bold text-lg">Wallet Cards</div>
            <div class="w-10"></div>
        </div>
    </header>

    <main class="max-w-md mx-auto px-5 mt-24">
        
        <?php if (empty($cards)): ?>
            <div class="text-center py-20 glass rounded-[40px] border-dashed border-2 border-white/10">
                <div class="w-20 h-20 bg-indigo-500/10 text-indigo-500 rounded-3xl flex items-center justify-center text-3xl mx-auto mb-6">
                    <i class="fa-solid fa-credit-card"></i>
                </div>
                <h2 class="text-xl font-black mb-2">No Virtual Card</h2>
                <p class="text-sm text-slate-400 mb-8 px-10">Generate your internal wallet card to make instant payments on JZStore.</p>
                <button onclick="openModal('genModal')" class="px-8 py-4 bg-indigo-600 hover:bg-indigo-500 rounded-2xl font-black text-sm shadow-lg shadow-indigo-600/20">
                    Generate My Card
                </button>
            </div>
        <?php else: ?>
            <div class="space-y-8">
                <?php foreach($cards as $c): ?>
                    <div class="space-y-6">
                        <div class="v-card <?= $c['status'] === 'frozen' ? 'frozen' : '' ?>" id="card-<?= $c['id'] ?>">
                            <div class="flex justify-between items-start mb-6">
                                <div class="v-card-chip"></div>
                                <i class="fa-brands fa-cc-visa text-3xl text-white/20"></i>
                            </div>
                            <div class="v-card-number mb-6" id="num-<?= $c['id'] ?>" onclick="toggleNumber(this, '<?= $c['card_number'] ?>')">
                                **** **** **** <?= substr($c['card_number'], -4) ?>
                            </div>
                            <div class="flex justify-between items-end">
                                <div>
                                    <p class="text-[8px] uppercase tracking-widest text-white/30 font-black">Expiry</p>
                                    <p class="text-sm font-bold"><?= sprintf('%02d/%d', $c['expiry_month'], $c['expiry_year'] % 100) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-[8px] uppercase tracking-widest text-white/30 font-black">CVV</p>
                                    <p class="text-sm font-bold cursor-pointer" onclick="viewCvv(<?= $c['id'] ?>)">***</p>
                                </div>
                            </div>
                        </div>

                        <!-- Card Actions -->
                        <div class="grid grid-cols-4 gap-3">
                            <button onclick="toggleStatus(<?= $c['id'] ?>, '<?= $c['status'] ?>')" class="flex flex-col items-center gap-2">
                                <div class="w-12 h-12 glass rounded-2xl flex items-center justify-center text-lg">
                                    <i class="fa-solid <?= $c['status'] === 'frozen' ? 'fa-play text-emerald-500' : 'fa-snowflake text-sky-400' ?>"></i>
                                </div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase"><?= $c['status'] === 'frozen' ? 'Unfreeze' : 'Freeze' ?></span>
                            </button>
                            <button onclick="openPinChange(<?= $c['id'] ?>)" class="flex flex-col items-center gap-2">
                                <div class="w-12 h-12 glass rounded-2xl flex items-center justify-center text-lg text-amber-400">
                                    <i class="fa-solid fa-key"></i>
                                </div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase">PIN</span>
                            </button>
                            <button onclick="location.href='history?type=card&id=<?= $c['id'] ?>'" class="flex flex-col items-center gap-2">
                                <div class="w-12 h-12 glass rounded-2xl flex items-center justify-center text-lg text-indigo-400">
                                    <i class="fa-solid fa-clock-rotate-left"></i>
                                </div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase">History</span>
                            </button>
                            <button onclick="deleteCard(<?= $c['id'] ?>)" class="flex flex-col items-center gap-2 text-rose-500">
                                <div class="w-12 h-12 glass rounded-2xl flex items-center justify-center text-lg">
                                    <i class="fa-solid fa-trash-can"></i>
                                </div>
                                <span class="text-[10px] font-bold uppercase">Delete</span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if(count($cards) < 2): ?>
                <button onclick="openModal('genModal')" class="w-full py-4 border-2 border-dashed border-white/10 rounded-2xl text-slate-400 font-bold text-sm flex items-center justify-center gap-2">
                    <i class="fa-solid fa-plus"></i> Add Another Card
                </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Limits & Analytics -->
        <div class="mt-12 space-y-4">
            <h3 class="text-sm font-black uppercase tracking-widest text-slate-500 ml-1">Card Settings</h3>
            <div class="glass rounded-[32px] p-6 space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-black">Daily Spending Limit</p>
                        <p class="text-[10px] text-slate-500">Maximum ₹5,000 per day</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-black text-indigo-400">₹5,000.00</p>
                    </div>
                </div>
                <div class="w-full h-1.5 bg-white/5 rounded-full overflow-hidden">
                    <div class="h-full bg-indigo-500 w-[20%]"></div>
                </div>
            </div>
        </div>

    </main>

    <!-- GENERATE MODAL -->
    <div class="modal" id="genModal">
        <div class="glass w-full max-w-sm rounded-[32px] p-8 space-y-6">
            <div class="text-center">
                <h3 class="text-xl font-black mb-2">Secure Your Card</h3>
                <p class="text-xs text-slate-400">Create a 4-digit PIN to secure your new virtual debit card.</p>
            </div>
            <div class="space-y-4">
                <input type="password" id="pin" maxlength="4" placeholder="Enter 4-Digit PIN" class="w-full bg-white/5 border border-white/10 rounded-2xl py-4 text-center text-2xl tracking-[10px] focus:outline-none focus:border-indigo-500 transition-all font-black">
                <button onclick="generateCard()" id="genBtn" class="btn-premium">Create Card</button>
                <button onclick="closeModal('genModal')" class="w-full text-xs font-bold text-slate-500 uppercase tracking-widest">Cancel</button>
            </div>
        </div>
    </div>

    <!-- PIN CHANGE MODAL -->
    <div class="modal" id="pinChangeModal">
        <div class="glass w-full max-w-sm rounded-[32px] p-8 space-y-6">
            <div class="text-center">
                <h3 class="text-xl font-black mb-2">Change Card PIN</h3>
                <p class="text-xs text-slate-400">Verify your account password to set a new PIN.</p>
            </div>
            <div class="space-y-4">
                <input type="password" id="p_pass" placeholder="Account Password" class="w-full bg-white/5 border border-white/10 rounded-2xl py-4 px-4 text-sm focus:outline-none focus:border-indigo-500 transition-all font-bold">
                <input type="password" id="p_new" maxlength="4" placeholder="New 4-Digit PIN" class="w-full bg-white/5 border border-white/10 rounded-2xl py-4 text-center text-2xl tracking-[10px] focus:outline-none focus:border-indigo-500 transition-all font-black">
                <button onclick="changePin()" id="pBtn" class="btn-premium">Update PIN</button>
                <button onclick="closeModal('pinChangeModal')" class="w-full text-xs font-bold text-slate-500 uppercase tracking-widest">Cancel</button>
            </div>
        </div>
    </div>

    <!-- PIN VERIFY MODAL (For CVV) -->
    <div class="modal" id="pinModal">
        <div class="glass w-full max-w-sm rounded-[32px] p-8 space-y-6">
            <div class="text-center">
                <h3 class="text-xl font-black mb-2">Verify PIN</h3>
                <p class="text-xs text-slate-400">Enter your card PIN to view sensitive information.</p>
            </div>
            <div class="space-y-4">
                <input type="password" id="v_pin" maxlength="4" placeholder="****" class="w-full bg-white/5 border border-white/10 rounded-2xl py-4 text-center text-2xl tracking-[10px] focus:outline-none focus:border-indigo-500 transition-all font-black">
                <button onclick="verifyPinForCvv()" id="verifyBtn" class="btn-premium">Confirm</button>
                <button onclick="closeModal('pinModal')" class="w-full text-xs font-bold text-slate-500 uppercase tracking-widest">Cancel</button>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        let currentCardId = null;

        function openModal(id) { document.getElementById(id).classList.add('show'); }
        function closeModal(id) { document.getElementById(id).classList.remove('show'); }

        async function generateCard() {
            const pin = document.getElementById('pin').value;
            if(!pin || pin.length !== 4) return alert('Enter 4-digit PIN');
            
            const btn = document.getElementById('genBtn');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';
            btn.disabled = true;

            const fd = new FormData(); fd.append('act', 'generate'); fd.append('pin', pin);
            const r = await fetch('api/v1/card_manager.php', {method: 'POST', body: fd}).then(r => r.json());
            
            if(r.ok) { alert('Card generated successfully!'); location.reload(); }
            else { alert(r.err || 'Failed'); btn.innerHTML = 'Create Card'; btn.disabled = false; }
        }

        async function toggleStatus(id, current) {
            const fd = new FormData(); fd.append('act', 'toggle_status'); fd.append('card_id', id); fd.append('status', current);
            const r = await fetch('api/v1/card_manager.php', {method: 'POST', body: fd}).then(r => r.json());
            if(r.ok) location.reload();
        }

        function toggleNumber(el, num) {
            if(el.dataset.show === '1') {
                el.innerText = '**** **** **** ' + num.slice(-4);
                el.dataset.show = '0';
            } else {
                el.innerText = num.match(/.{1,4}/g).join(' ');
                el.dataset.show = '1';
                setTimeout(() => toggleNumber(el, num), 10000); // Auto hide after 10s
            }
        }

        function viewCvv(id) {
            currentCardId = id;
            openModal('pinModal');
        }

        async function verifyPinForCvv() {
            const pin = document.getElementById('v_pin').value;
            const fd = new FormData(); fd.append('act', 'get_cvv'); fd.append('card_id', currentCardId); fd.append('pin', pin);
            const r = await fetch('api/v1/card_manager.php', {method: 'POST', body: fd}).then(r => r.json());
            if(r.ok) {
                alert('Your CVV is: ' + r.cvv);
                closeModal('pinModal');
                document.getElementById('v_pin').value = '';
            } else alert(r.err);
        }

        async function deleteCard(id) {
            if(!confirm('Are you sure? This action cannot be undone.')) return;
            const fd = new FormData(); fd.append('act', 'delete'); fd.append('card_id', id);
            const r = await fetch('api/v1/card_manager.php', {method: 'POST', body: fd}).then(r => r.json());
            if(r.ok) location.reload();
        }

        function openPinChange(id) {
            currentCardId = id;
            openModal('pinChangeModal');
        }

        async function changePin() {
            const pass = document.getElementById('p_pass').value;
            const pin = document.getElementById('p_new').value;
            if(!pass) return alert('Enter account password');
            if(!pin || pin.length !== 4) return alert('Enter 4-digit PIN');

            const btn = document.getElementById('pBtn');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';
            btn.disabled = true;

            const fd = new FormData(); fd.append('act', 'change_pin'); fd.append('card_id', currentCardId); fd.append('password', pass); fd.append('new_pin', pin);
            const r = await fetch('api/v1/card_manager.php', {method: 'POST', body: fd}).then(r => r.json());
            
            if(r.ok) {
                alert('PIN updated successfully!');
                closeModal('pinChangeModal');
                document.getElementById('p_pass').value = '';
                document.getElementById('p_new').value = '';
            } else alert(r.err || 'Failed');
            
            btn.innerHTML = 'Update PIN'; btn.disabled = false;
        }

        function toast(m) { alert(m); }
    </script>
</body>
</html>
