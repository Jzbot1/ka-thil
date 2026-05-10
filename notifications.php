<?php
require_once __DIR__ . '/includes/config.php';

// Fetch Store Settings via Central Helper
$setting = get_settings();

// Fetch Notifications
$notifications = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC");

function getEmbedUrl($url) {
    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
        preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
        return isset($match[1]) ? "https://www.youtube.com/embed/" . $match[1] : $url;
    }
    if (strpos($url, 'facebook.com') !== false) {
        return "https://www.facebook.com/plugins/video.php?href=" . urlencode($url) . "&show_text=0";
    }
    return $url;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Notifications - <?= htmlspecialchars($setting['store_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: linear-gradient(177deg, #fbc2eb, #a6c1ee, #80bf15); background-attachment: fixed; color: #0f172a; }
        .glass-panel { background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.3); }
        .noti-card { transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    </style>
</head>
<body class="pb-32">
    <header class="fixed top-0 w-full z-50 bg-white/20 backdrop-blur-xl h-16 border-b border-white/20">
        <div class="max-w-md mx-auto px-5 h-full flex items-center justify-between">
            <a href="<?= BASE_URL ?>" class="w-10 h-10 rounded-xl bg-white/40 flex items-center justify-center border border-white/30"><i class="fa-solid fa-arrow-left text-themeDark text-sm"></i></a>
            <div class="font-bold text-lg text-themeDark font-dynapuff">News & Updates</div>
            <div class="w-10"></div>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 mt-20 space-y-6">
        
        <?php if($notifications->num_rows == 0): ?>
            <div class="py-20 text-center glass-panel rounded-[2rem]">
                <i class="fa-solid fa-bell-slash text-4xl text-themeDark/10 mb-4"></i>
                <p class="text-sm font-bold text-themeDark/40 uppercase tracking-widest">No notifications yet</p>
            </div>
        <?php endif; ?>

        <?php while($n = $notifications->fetch_assoc()): ?>
            <div class="glass-panel p-6 rounded-[2.5rem] noti-card border border-white/40 relative overflow-hidden">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-2xl bg-blue-600/10 flex items-center justify-center text-blue-600">
                        <i class="fa-solid fa-bullhorn"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-themeDark leading-tight"><?= htmlspecialchars($n['title']) ?></h3>
                        <p class="text-[9px] font-bold text-themeDark/40 uppercase tracking-widest"><?= date('d M, Y', strtotime($n['created_at'])) ?></p>
                    </div>
                </div>

                <p class="text-sm text-themeDark/70 leading-relaxed mb-4">
                    <?= nl2br(htmlspecialchars($n['message'])) ?>
                </p>

                <?php if(!empty($n['video_url'])): 
                    $embed = getEmbedUrl($n['video_url']);
                ?>
                    <div class="rounded-3xl overflow-hidden mb-4 shadow-xl shadow-themeDark/10 aspect-video">
                        <iframe src="<?= $embed ?>" class="w-full h-full" frameborder="0" allowfullscreen></iframe>
                    </div>
                <?php elseif(!empty($n['image_url'])): ?>
                    <div class="rounded-3xl overflow-hidden mb-4 shadow-xl shadow-themeDark/10">
                        <img src="<?= htmlspecialchars($n['image_url']) ?>" class="w-full h-auto object-cover">
                    </div>
                <?php endif; ?>

                <div class="flex items-center justify-between mt-6 pt-4 border-t border-white/20">
                    <span class="px-3 py-1 bg-white/40 rounded-full text-[8px] font-black uppercase text-themeDark/40 tracking-widest">System Update</span>
                    <button class="text-[10px] font-black text-blue-600 uppercase tracking-widest">Read More <i class="fa-solid fa-chevron-right ml-1"></i></button>
                </div>
            </div>
        <?php endwhile; ?>

    </main>

    <?php include 'footer.php'; ?>
</body>
</html>