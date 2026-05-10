<?php
require_once __DIR__ . '/config.php';

// Fetch Store Settings
$setting = [
    'store_name' => 'JZ Store',
    'whatsapp'   => '#',
    'facebook'   => '#',
    'instagram'  => '#'
];
$res_s = $conn->query("SELECT * FROM fav_setting LIMIT 1");
if($res_s && $row_s = $res_s->fetch_assoc()) {
    foreach($row_s as $k => $v) if(!empty($v)) $setting[$k] = $v;
}

// 1. Fetch Top 10 Spenders for CURRENT MONTH
$sql = "SELECT o.user_id, SUM(o.price) as total_spent, u.username, u.picture 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.status = 'completed' 
          AND MONTH(o.created_at) = MONTH(CURRENT_DATE()) 
          AND YEAR(o.created_at) = YEAR(CURRENT_DATE()) 
        GROUP BY o.user_id 
        ORDER BY total_spent DESC 
        LIMIT 10";

$res = $conn->query($sql);
$leaderboard = [];
while($row = $res->fetch_assoc()) {
    $leaderboard[] = $row;
}

// Calculate time remaining until end of month
$endOfMonth = new DateTime('last day of this month');
$endOfMonth->setTime(23, 59, 59);
$remainingSeconds = $endOfMonth->getTimestamp() - time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Leaderboard - <?= htmlspecialchars($setting['store_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: linear-gradient(177deg, #fbc2eb, #a6c1ee, #80bf15); background-attachment: fixed; color: #0f172a; }
        .glass-panel { background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.3); }
        .podium-item { transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .rank-badge { position: absolute; bottom: -5px; right: -5px; width: 24px; height: 24px; border-radius: 50%; display: flex; items-center; justify-content: center; font-size: 10px; font-weight: 800; border: 2px solid white; }
    </style>
</head>
<body class="pb-32">
    <header class="fixed top-0 w-full z-50 bg-white/20 backdrop-blur-xl h-16 border-b border-white/20">
        <div class="max-w-md mx-auto px-5 h-full flex items-center justify-between">
            <a href="<?= BASE_URL ?>" class="w-10 h-10 rounded-xl bg-white/40 flex items-center justify-center border border-white/30"><i class="fa-solid fa-arrow-left text-themeDark text-sm"></i></a>
            <div class="font-bold text-lg text-themeDark">Top Spenders</div>
            <div class="w-10"></div>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 mt-20">
        
        <!-- RESET TIMER -->
        <div class="glass-panel rounded-3xl p-4 mb-8 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-rose-600/10 rounded-xl flex items-center justify-center text-rose-600">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-themeDark/40 uppercase tracking-widest leading-none">Resets In</p>
                    <p id="countdown" class="text-sm font-black text-themeDark mt-1">Calculating...</p>
                </div>
            </div>
            <div class="text-right">
                <span class="px-3 py-1 bg-rose-600 text-white text-[9px] font-black rounded-full uppercase shadow-lg shadow-rose-600/20">Monthly</span>
            </div>
        </div>

        <!-- PODIUM (Top 3) -->
        <div class="flex items-end justify-center gap-4 mb-10 mt-14">
            <?php if(count($leaderboard) >= 2): ?>
                <!-- 2nd Place -->
                <div class="podium-item flex flex-col items-center">
                    <div class="relative mb-3">
                        <img src="<?= $leaderboard[1]['picture'] ?: 'https://api.dicebear.com/7.x/avataaars/svg?seed='.$leaderboard[1]['username'] ?>" class="w-16 h-16 rounded-full border-4 border-slate-300 shadow-xl object-cover">
                        <div class="rank-badge bg-slate-300 text-slate-700">2</div>
                    </div>
                    <p class="text-[10px] font-black text-themeDark truncate w-20 text-center"><?= htmlspecialchars($leaderboard[1]['username']) ?></p>
                    <p class="text-[9px] font-bold text-slate-600 mt-1">₹<?= number_format($leaderboard[1]['total_spent'], 0) ?></p>
                </div>
            <?php endif; ?>

            <?php if(count($leaderboard) >= 1): ?>
                <!-- 1st Place -->
                <div class="podium-item flex flex-col items-center -mt-6">
                    <div class="relative mb-3">
                        <i class="fa-solid fa-crown absolute -top-8 left-1/2 -translate-x-1/2 text-yellow-500 text-2xl drop-shadow-lg"></i>
                        <img src="<?= $leaderboard[0]['picture'] ?: 'https://api.dicebear.com/7.x/avataaars/svg?seed='.$leaderboard[0]['username'] ?>" class="w-24 h-24 rounded-full border-4 border-yellow-400 shadow-2xl object-cover">
                        <div class="rank-badge bg-yellow-400 text-white text-xs">1</div>
                    </div>
                    <p class="text-xs font-black text-themeDark truncate w-24 text-center"><?= htmlspecialchars($leaderboard[0]['username']) ?></p>
                    <p class="text-[10px] font-black text-rose-600 mt-1">₹<?= number_format($leaderboard[0]['total_spent'], 0) ?></p>
                </div>
            <?php endif; ?>

            <?php if(count($leaderboard) >= 3): ?>
                <!-- 3rd Place -->
                <div class="podium-item flex flex-col items-center">
                    <div class="relative mb-3">
                        <img src="<?= $leaderboard[2]['picture'] ?: 'https://api.dicebear.com/7.x/avataaars/svg?seed='.$leaderboard[2]['username'] ?>" class="w-16 h-16 rounded-full border-4 border-orange-400 shadow-xl object-cover">
                        <div class="rank-badge bg-orange-400 text-white">3</div>
                    </div>
                    <p class="text-[10px] font-black text-themeDark truncate w-20 text-center"><?= htmlspecialchars($leaderboard[2]['username']) ?></p>
                    <p class="text-[9px] font-bold text-slate-600 mt-1">₹<?= number_format($leaderboard[2]['total_spent'], 0) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- LIST (4-10) -->
        <div class="space-y-3 mb-10">
            <?php for($i=3; $i < count($leaderboard); $i++): ?>
                <div class="glass-panel p-4 rounded-3xl flex items-center justify-between animate-slide" style="animation-delay: <?= $i*0.1 ?>s">
                    <div class="flex items-center gap-4">
                        <div class="w-8 h-8 rounded-xl bg-themeDark/10 flex items-center justify-center text-xs font-black text-themeDark"><?= $i + 1 ?></div>
                        <div class="relative">
                            <img src="<?= $leaderboard[$i]['picture'] ?: 'https://api.dicebear.com/7.x/avataaars/svg?seed='.$leaderboard[$i]['username'] ?>" class="w-10 h-10 rounded-full object-cover">
                        </div>
                        <div>
                            <p class="text-xs font-black text-themeDark"><?= htmlspecialchars($leaderboard[$i]['username']) ?></p>
                            <p class="text-[9px] font-bold text-themeDark/40 uppercase">Elite Member</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-black text-themeDark">₹<?= number_format($leaderboard[$i]['total_spent'], 0) ?></p>
                    </div>
                </div>
            <?php endfor; ?>
            
            <?php if(count($leaderboard) == 0): ?>
                <div class="py-20 text-center glass-panel rounded-[2rem]">
                    <i class="fa-solid fa-trophy text-4xl text-themeDark/10 mb-4"></i>
                    <p class="text-sm font-bold text-themeDark/40 uppercase tracking-widest">No rankings yet</p>
                </div>
            <?php endif; ?>
        </div>

        <?php include 'footer.php'; ?>
    </main>

    <script>
        let remaining = <?= $remainingSeconds ?>;
        const countdown = document.getElementById('countdown');

        function updateCountdown() {
            if (remaining <= 0) {
                countdown.innerText = "00:00:00:00";
                return;
            }
            const days = Math.floor(remaining / (3600 * 24));
            const hours = Math.floor((remaining % (3600 * 24)) / 3600);
            const minutes = Math.floor((remaining % 3600) / 60);
            const seconds = remaining % 60;

            countdown.innerText = `${days}d ${hours}h ${minutes}m ${seconds}s`;
            remaining--;
        }

        setInterval(updateCountdown, 1000);
        updateCountdown();
    </script>
</body>
</html>
