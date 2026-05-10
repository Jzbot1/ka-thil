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

$slug = $_GET['slug'] ?? '';
$stmt = $conn->prepare("SELECT * FROM blogs WHERE slug = ? LIMIT 1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$blog = $stmt->get_result()->fetch_assoc();

if (!$blog) { header("Location: blog"); exit; }

// Helper function to embed videos
function getEmbedUrl($url) {
    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
        preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match);
        return isset($match[1]) ? "https://www.youtube.com/embed/" . $match[1] : $url;
    }
    return $url; // Return as is for FB/Insta (might need specialized embedding)
}

$embed_url = !empty($blog['video_url']) ? getEmbedUrl($blog['video_url']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title><?= htmlspecialchars($blog['title']) ?></title>
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
        .content-area img { max-width: 100%; height: auto; border-radius: 1rem; margin: 1rem 0; }
        .content-area p { margin-bottom: 1rem; line-height: 1.6; }
    </style>
</head>
<body class="pb-32">
    <header class="fixed top-0 w-full z-50 bg-black/20 backdrop-blur-xl h-16 border-b border-white/10">
        <div class="max-w-md mx-auto px-5 h-full flex items-center justify-between">
            <a href="<?= BASE_URL ?>/blog" class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center border border-white/10"><i class="fa-solid fa-arrow-left text-white text-sm"></i></a>
            <div class="font-bold text-xs text-white uppercase tracking-widest truncate max-w-[200px]"><?= htmlspecialchars($blog['title']) ?></div>
            <div class="w-10"></div>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 mt-20 space-y-6">
        <div class="glass-panel rounded-[2rem] overflow-hidden">
            <?php if($blog['image_url']): ?>
                <img src="<?= htmlspecialchars($blog['image_url']) ?>" class="w-full h-56 object-cover">
            <?php endif; ?>
            
            <div class="p-6">
                <div class="flex items-center gap-2 mb-4">
                    <span class="px-2 py-0.5 bg-rose-500/10 text-rose-400 text-[8px] font-bold rounded-full uppercase">Article</span>
                    <span class="text-[10px] text-white/40 font-bold"><?= date('F d, Y', strtotime($blog['created_at'])) ?></span>
                </div>
                
                <h1 class="text-2xl font-black text-white leading-tight mb-6"><?= htmlspecialchars($blog['title']) ?></h1>

                <?php if($embed_url): ?>
                    <div class="mb-6 rounded-2xl overflow-hidden aspect-video bg-black shadow-xl">
                        <?php if(strpos($embed_url, 'youtube') !== false): ?>
                            <iframe class="w-full h-full" src="<?= $embed_url ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                        <?php else: ?>
                            <!-- Fallback or Specialized Embed for FB/Insta -->
                            <div class="w-full h-full flex items-center justify-center text-white p-4 text-center">
                                <a href="<?= $blog['video_url'] ?>" target="_blank" class="bg-rose-600 px-6 py-2 rounded-xl text-xs font-bold">Watch Video on Social Media</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="content-area text-sm text-white/80">
                    <?= nl2br($blog['content']) ?>
                </div>
                
                <div class="mt-10 pt-6 border-t border-white/10">
                    <button onclick="shareContent()" class="flex items-center gap-2 text-rose-400 font-bold text-xs bg-rose-500/10 px-4 py-2 rounded-xl">
                        <i class="fa-solid fa-share-nodes"></i> Share Article
                    </button>
                </div>
            </div>
        </div>

        <?php include 'footer.php'; ?>
    </main>

    <script>
        function shareContent() {
            if (navigator.share) {
                navigator.share({
                    title: '<?= addslashes($blog['title']) ?>',
                    url: window.location.href
                });
            } else {
                alert("Link copied to clipboard!");
                navigator.clipboard.writeText(window.location.href);
            }
        }
    </script>
</body>
</html>
