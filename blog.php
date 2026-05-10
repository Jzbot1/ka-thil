<?php
require_once __DIR__ . '/config.php';

// Fetch Store Settings
$setting = [
    'store_name' => 'JZ Store',
    'whatsapp'   => '#',
    'facebook'   => '#',
    'instagram'  => '#'
];
$res = $conn->query("SELECT * FROM fav_setting LIMIT 1");
if($res && $row = $res->fetch_assoc()) {
    foreach($row as $k => $v) if(!empty($v)) $setting[$k] = $v;
}

// Fetch Blogs
$blogs = $conn->query("SELECT * FROM blogs ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Blog - <?= htmlspecialchars($setting['store_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; 
            background: hsla(213, 77%, 14%, 1);
            background: linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -moz-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -webkit-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            filter: progid: DXImageTransform.Microsoft.gradient( startColorstr="#08203E", endColorstr="#557C93", GradientType=1 );
            background-attachment: fixed; color: #ffffff; }
        .glass-panel { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
    </style>
</head>
<body class="pb-32">
    <header class="fixed top-0 w-full z-50 bg-black/20 backdrop-blur-xl h-16 border-b border-white/10">
        <div class="max-w-md mx-auto px-5 h-full flex items-center justify-between">
            <a href="<?= BASE_URL ?>" class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center border border-white/10"><i class="fa-solid fa-arrow-left text-white text-sm"></i></a>
            <div class="font-bold text-lg text-white">Information Hub</div>
            <div class="w-10"></div>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 mt-20 space-y-6">
        <div class="mb-6">
            <h2 class="text-2xl font-black text-white">Latest Articles</h2>
            <p class="text-xs font-bold text-white/60 uppercase tracking-widest mt-1">Tips, Tutorials & Updates</p>
        </div>

        <?php if($blogs && $blogs->num_rows > 0): ?>
            <?php while($b = $blogs->fetch_assoc()): ?>
                <a href="<?= BASE_URL ?>/blog_detail?slug=<?= $b['slug'] ?>" class="block glass-panel rounded-[2rem] overflow-hidden animate-slide">
                    <?php if($b['image_url']): ?>
                        <img src="<?= htmlspecialchars($b['image_url']) ?>" class="w-full h-44 object-cover">
                    <?php endif; ?>
                    <div class="p-6">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="px-2 py-0.5 bg-rose-500/10 text-rose-400 text-[8px] font-bold rounded-full uppercase">Update</span>
                            <span class="text-[9px] text-white/40 font-bold"><?= date('M d, Y', strtotime($b['created_at'])) ?></span>
                        </div>
                        <h3 class="text-lg font-black text-white leading-tight"><?= htmlspecialchars($b['title']) ?></h3>
                        <p class="text-[11px] text-white/60 mt-3 line-clamp-2">
                            <?= strip_tags($b['content']) ?>
                        </p>
                        <div class="mt-4 flex items-center gap-2 text-rose-400 font-bold text-xs">
                            Read More <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </div>
                    </div>
                </a>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="py-20 text-center glass-panel rounded-[2rem]">
                <i class="fa-solid fa-newspaper text-4xl text-white/20 mb-4"></i>
                <p class="text-sm font-bold text-white/40">No articles found yet.</p>
            </div>
        <?php endif; ?>

        <?php include 'footer.php'; ?>
    </main>
</body>
</html>
