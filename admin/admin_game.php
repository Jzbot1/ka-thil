<?php
require_once __DIR__ . '/strict_admin.php';


// --- 1. SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); 
    exit("Access Denied.");
}

// --- HELPERS ---
function generateSlug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

/**
 * Updated for Admin Folder Context
 * Targets root/uploads/games/
 */
function uploadImage($file) {
    $targetDir = "../uploads/games/"; // Physical path for moving file
    $dbPathPrefix = "uploads/games/"; // Path saved in DB for frontend use
    
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    
    $fileExtension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $newFileName = time() . '_' . uniqid() . '.' . $fileExtension;
    $targetFile = $targetDir . $newFileName;
    
    if(isset($file["tmp_name"]) && !empty($file["tmp_name"]) && getimagesize($file["tmp_name"])) {
        if (move_uploaded_file($file["tmp_name"], $targetFile)) {
            return $dbPathPrefix . $newFileName;
        }
    }
    return null;
}

// --- 2. ACTIONS ---
if (isset($_GET['delete_game'])) {
    $id = (int)$_GET['delete_game'];
    $stmt = $conn->prepare("DELETE FROM games WHERE id = ?");
    $stmt->bind_param("i", $id);
    if($stmt->execute()) $_SESSION['flash_msg'] = "Game permanently removed.";
    header("Location: admin_game.php"); exit;
}

if (isset($_GET['delete_cat'])) {
    $id = (int)$_GET['delete_cat'];
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->bind_param("i", $id);
    if($stmt->execute()) $_SESSION['flash_msg'] = "Category unlinked.";
    header("Location: admin_game.php"); exit;
}

$edit_game = null;
if (isset($_GET['edit_game'])) {
    $id = (int)$_GET['edit_game'];
    $stmt = $conn->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_game = $stmt->get_result()->fetch_assoc();
}

// --- 3. POST HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_game'])) {
        $title = trim($_POST['title']);
        $slug = !empty($_POST['slug']) ? generateSlug($_POST['slug']) : generateSlug($title);
        $status = (int)$_POST['status'];
        $desc_title = trim($_POST['description_title']);
        $desc_body = trim($_POST['description_body']);
        $ext_url = trim($_POST['external_url']);
        $category = $_POST['category_group'];
        $id_system = $_POST['id_system'];
        $provider = $_POST['provider'];
        $sort_order = (int)$_POST['sort_order'];
        $is_flash_sale = isset($_POST['is_flash_sale']) ? 1 : 0;
        $badge_text = trim($_POST['badge_text']);
        
        $imagePath = !empty($_FILES['game_image']['name']) ? uploadImage($_FILES['game_image']) : ($_POST['current_image'] ?? '');

        if (!empty($_POST['game_id'])) {
            $stmt = $conn->prepare("UPDATE games SET title=?, slug=?, image=?, status=?, description_title=?, description_body=?, external_url=?, category=?, id_system=?, provider=?, sort_order=?, is_flash_sale=?, badge_text=? WHERE id=?");
            $stmt->bind_param("sssissssssiisi", $title, $slug, $imagePath, $status, $desc_title, $desc_body, $ext_url, $category, $id_system, $provider, $sort_order, $is_flash_sale, $badge_text, $_POST['game_id']);
        } else {
            $stmt = $conn->prepare("INSERT INTO games (title, slug, image, status, description_title, description_body, external_url, category, id_system, provider, sort_order, is_flash_sale, badge_text) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssissssssiis", $title, $slug, $imagePath, $status, $desc_title, $desc_body, $ext_url, $category, $id_system, $provider, $sort_order, $is_flash_sale, $badge_text);
        }
        $stmt->execute();
        $_SESSION['flash_msg'] = "Sync Successful!";
        header("Location: admin_game.php"); exit;
    }

    if (isset($_POST['save_category'])) {
        $game_id = (int)$_POST['game_id'];
        $name = trim($_POST['cat_name']);
        $slug = generateSlug($name);
        
        // Check for duplicates before inserting
        $check = $conn->prepare("SELECT id FROM categories WHERE game_id = ? AND name = ?");
        $check->bind_param("is", $game_id, $name);
        $check->execute();
        if($check->get_result()->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO categories (game_id, name, slug) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $game_id, $name, $slug);
            $stmt->execute();
            $_SESSION['flash_msg'] = "Category Added Successfully!";
        } else {
            $_SESSION['flash_msg'] = "Category already exists for this game.";
        }
        header("Location: admin_game.php"); exit;
    }
    if (isset($_POST['save_store_category'])) {
        $name = trim($_POST['store_cat_name']);
        $slug = generateSlug($name);
        $color = trim($_POST['store_cat_color']);
        
        $check = $conn->prepare("SELECT id FROM game_categories WHERE slug = ?");
        $check->bind_param("s", $slug);
        $check->execute();
        if($check->get_result()->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO game_categories (name, slug, color) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $slug, $color);
            $stmt->execute();
            $_SESSION['flash_msg'] = "Store Category Added Successfully!";
        } else {
            $_SESSION['flash_msg'] = "Store Category already exists.";
        }
        header("Location: admin_game.php"); exit;
    }
}

if (isset($_GET['delete_store_cat'])) {
    $id = (int)$_GET['delete_store_cat'];
    $stmt = $conn->prepare("DELETE FROM game_categories WHERE id = ?");
    $stmt->bind_param("i", $id);
    if($stmt->execute()) $_SESSION['flash_msg'] = "Store Category Deleted.";
    header("Location: admin_game.php"); exit;
}

$games = $conn->query("SELECT * FROM games ORDER BY sort_order ASC, id DESC")->fetch_all(MYSQLI_ASSOC);
$categories = $conn->query("SELECT c.*, g.title as game_title FROM categories c JOIN games g ON c.game_id = g.id")->fetch_all(MYSQLI_ASSOC);
$store_categories = $conn->query("SELECT * FROM game_categories ORDER BY sort_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Management Console | Moba Pay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        
        :root {
            --navy-dark: #070b14;
            --navy-card: #121826;
            --navy-border: #1e293b;
            --accent: #6366f1;
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--navy-dark); 
            color: #f1f5f9;
            -webkit-tap-highlight-color: transparent;
        }

        .glass-nav { 
            background: rgba(7, 11, 20, 0.85); 
            backdrop-filter: blur(20px); 
            border-top: 1px solid rgba(255,255,255,0.05); 
        }

        .card-bg { 
            background: var(--navy-card); 
            border: 1px solid var(--navy-border); 
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }

        .input-style {
            background-color: rgba(15, 23, 42, 0.6) !important;
            border: 1px solid var(--navy-border) !important;
            color: white !important;
            transition: all 0.2s ease;
        }

        .input-style:focus {
            border-color: var(--accent) !important;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            transition: transform 0.1s ease;
        }

        .btn-primary:active { transform: scale(0.96); }

        .nav-link {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .active-link {
            color: var(--accent);
            position: relative;
        }
        
        .active-link::after {
            content: '';
            position: absolute;
            bottom: -8px;
            width: 4px;
            height: 4px;
            background: var(--accent);
            border-radius: 50%;
        }

        @media (max-width: 1024px) {
            .main-container { padding-bottom: 120px; }
        }
    </style>
</head>
<body class="main-container">

    <aside class="hidden lg:flex fixed left-0 top-0 h-screen w-64 bg-[#070b14] border-r border-slate-800 flex-col p-6 z-40">
        <div class="flex items-center gap-3 mb-10 px-2">
            <div class="w-10 h-10 bg-indigo-500 rounded-xl flex items-center justify-center text-white shadow-lg">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <span class="font-extrabold text-white tracking-tight text-lg">MOBA PAY</span>
        </div>
        <nav class="space-y-2">
            <a href="admin_product.php" class="flex items-center gap-3 p-3 bg-indigo-500/10 text-indigo-400 rounded-xl font-bold">
                <i class="fa-solid fa-gamepad"></i> Products
            </a>
            <a href="../index.php" class="flex items-center gap-3 p-3 text-slate-400 hover:bg-slate-800/50 rounded-xl font-bold transition-all">
                <i class="fa-solid fa-shop"></i> View Store
            </a>
        </nav>
        <div class="mt-auto">
            <a href="logout.php" class="flex items-center gap-3 p-3 text-rose-400 hover:bg-rose-950/20 rounded-xl font-bold transition-all">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
            </a>
        </div>
    </aside>

    <header class="lg:ml-64 bg-[#070b14]/80 backdrop-blur-md sticky top-0 z-30 px-5 py-4 border-b border-slate-800/50 flex justify-between items-center">
        <div>
            <h1 class="text-lg lg:text-xl font-black text-white tracking-tight">Game Catalog</h1>
            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-[0.2em] mt-0.5">Control Center</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-2xl bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-300">
                <i class="fa-solid fa-shield-halved text-xs"></i>
            </div>
        </div>
    </header>

    <main class="lg:ml-64 p-4 lg:p-10 max-w-5xl mx-auto">
        
        <?php if (isset($_SESSION['flash_msg'])): ?>
            <div class="mb-6 p-4 bg-indigo-500 rounded-2xl flex items-center gap-3 shadow-lg shadow-indigo-500/20">
                <i class="fa-solid fa-circle-check text-white text-lg"></i>
                <p class="text-white text-sm font-bold"><?= htmlspecialchars($_SESSION['flash_msg']); unset($_SESSION['flash_msg']); ?></p>
            </div>
        <?php endif; ?>

        <section class="mb-10">
            <div class="card-bg rounded-[28px] p-6 lg:p-8 relative overflow-hidden">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h2 class="text-lg font-black text-white"><?= $edit_game ? 'Edit Game' : 'New Integration' ?></h2>
                        <p class="text-xs text-slate-400 font-medium">Configure product & provider logic</p>
                    </div>
                    <?php if($edit_game): ?>
                        <a href="admin_game.php" class="bg-slate-800 text-slate-400 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-wider">Cancel</a>
                    <?php endif; ?>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-5">
                    <input type="hidden" name="save_game" value="1">
                    <input type="hidden" name="game_id" value="<?= $edit_game['id'] ?? '' ?>">
                    <input type="hidden" name="current_image" value="<?= $edit_game['image'] ?? '' ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Display Name</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($edit_game['title'] ?? '') ?>" required placeholder="e.g. Mobile Legends" class="input-style w-full rounded-2xl p-4 text-sm font-bold outline-none">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Store Category</label>
                            <select name="category_group" class="input-style w-full rounded-2xl p-4 text-sm font-bold outline-none appearance-none">
                                <?php foreach($store_categories as $sc): ?>
                                    <option value="<?= htmlspecialchars($sc['slug']) ?>" <?= ($edit_game['category'] ?? '') == $sc['slug'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sc['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                         <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Input System</label>
                            <select name="id_system" class="input-style w-full rounded-2xl p-4 text-sm font-bold text-indigo-400 outline-none">
                                <option value="user_only" <?= ($edit_game['id_system'] ?? '') == 'user_only' ? 'selected' : '' ?>>User ID Only</option>
                                <option value="user_zone_input" <?= ($edit_game['id_system'] ?? 'user_zone_input') == 'user_zone_input' ? 'selected' : '' ?>>ID + Zone</option>
                                <option value="user_zone_select" <?= ($edit_game['id_system'] ?? '') == 'user_zone_select' ? 'selected' : '' ?>>ID + Dropdown</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">API Provider</label>
                            <select name="provider" class="input-style w-full rounded-2xl p-4 text-sm font-bold text-orange-400 outline-none">
                                <option value="smileone" <?= ($edit_game['provider'] ?? 'smileone') == 'smileone' ? 'selected' : '' ?>>SmileOne</option>
                                <option value="moogold" <?= ($edit_game['provider'] ?? '') == 'moogold' ? 'selected' : '' ?>>MooGold</option>
                                <option value="manual" <?= ($edit_game['provider'] ?? '') == 'manual' ? 'selected' : '' ?>>Manual Fulfillment</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Visibility</label>
                            <select name="status" class="input-style w-full rounded-2xl p-4 text-sm font-bold outline-none">
                                <option value="1" <?= ($edit_game['status'] ?? 1) == 1 ? 'selected' : '' ?>>Online</option>
                                <option value="0" <?= ($edit_game['status'] ?? 1) == 0 ? 'selected' : '' ?>>Offline/Maintenance</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Promo Header</label>
                        <input type="text" name="description_title" value="<?= htmlspecialchars($edit_game['description_title'] ?? '') ?>" placeholder="e.g. Instant Delivery within 5 mins" class="input-style w-full rounded-2xl p-4 text-sm font-medium outline-none">
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">How to Top Up (Instructions)</label>
                        <textarea name="description_body" rows="3" class="input-style w-full rounded-2xl p-4 text-sm font-medium outline-none" placeholder="Enter steps for the user..."><?= htmlspecialchars($edit_game['description_body'] ?? '') ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Custom Link (Redirect)</label>
                            <input type="url" name="external_url" value="<?= htmlspecialchars($edit_game['external_url'] ?? '') ?>" placeholder="https://" class="input-style w-full rounded-2xl p-4 text-sm outline-none">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Sort & Media</label>
                            <div class="flex gap-2">
                                <input type="number" name="sort_order" value="<?= $edit_game['sort_order'] ?? 0 ?>" class="input-style w-20 rounded-2xl p-4 text-sm text-center outline-none">
                                <div class="flex-1 input-style rounded-2xl p-2 flex items-center gap-3">
                                    <?php if(!empty($edit_game['image'])): ?>
                                        <img src="../<?= $edit_game['image'] ?>" class="w-10 h-10 rounded-xl object-cover shadow-lg border border-slate-700">
                                    <?php endif; ?>
                                    <input type="file" name="game_image" class="text-[10px] text-slate-400 file:mr-2 file:py-1 file:px-3 file:rounded-full file:border-0 file:bg-indigo-500 file:text-white file:text-[9px] file:font-black">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FLASH SALE TOGGLE -->
                    <div class="bg-orange-500/5 border border-orange-500/20 rounded-2xl p-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-orange-500/10 rounded-xl flex items-center justify-center text-orange-500">
                                <i class="fa-solid fa-bolt-lightning"></i>
                            </div>
                            <div>
                                <p class="text-xs font-black text-white">Flash Sale Badge</p>
                                <p class="text-[10px] text-orange-500/60 font-bold">Display ribbon on home page</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <input type="text" name="badge_text" value="<?= htmlspecialchars($edit_game['badge_text'] ?? '') ?>" placeholder="e.g. HOT, NEW, 10% OFF" class="input-style w-32 rounded-xl p-2 text-[10px] font-black uppercase text-center outline-none">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_flash_sale" value="1" <?= ($edit_game['is_flash_sale'] ?? 0) == 1 ? 'checked' : '' ?> class="sr-only peer">
                                <div class="w-11 h-6 bg-slate-800 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                            </label>
                        </div>
                    </div>

                    <button class="btn-primary w-full text-white py-5 rounded-[20px] font-black text-sm shadow-xl shadow-indigo-500/20 mt-4">
                        <i class="fa-solid fa-rocket mr-2"></i> <?= $edit_game ? 'Update Catalog' : 'Launch Game' ?>
                    </button>
                </form>
            </div>
        </section>

        <!-- STORE CATEGORIES MANAGEMENT -->
        <section class="mt-8 space-y-4">
            <div class="flex justify-between items-center px-2">
                <h2 class="text-lg font-black text-white tracking-tight">Store Categories</h2>
            </div>
            <div class="card-bg p-6 rounded-[32px] border border-slate-800 shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-orange-500/5 blur-[50px] rounded-full"></div>
                
                <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end mb-6 relative z-10">
                    <input type="hidden" name="save_store_category" value="1">
                    <div class="md:col-span-2 space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Category Name</label>
                        <input type="text" name="store_cat_name" required placeholder="e.g. Action Games" class="input-style w-full rounded-2xl p-4 text-sm font-bold outline-none">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Tailwind Color</label>
                        <input type="text" name="store_cat_color" placeholder="e.g. bg-blue-500" value="bg-blue-500" class="input-style w-full rounded-2xl p-4 text-sm font-bold outline-none">
                    </div>
                    <button class="bg-orange-500 hover:bg-orange-600 text-white p-4 rounded-2xl font-black shadow-lg transition-all">
                        Add Category
                    </button>
                </form>

                <div class="flex flex-wrap gap-3">
                    <?php foreach($store_categories as $sc): ?>
                        <div class="bg-slate-800/50 border border-slate-700/50 px-4 py-2 rounded-xl flex items-center gap-3">
                            <span class="w-3 h-3 rounded-full <?= htmlspecialchars($sc['color']) ?>"></span>
                            <span class="text-xs font-bold text-slate-300"><?= htmlspecialchars($sc['name']) ?></span>
                            <a href="?delete_store_cat=<?= $sc['id'] ?>" onclick="return confirm('Delete this store category?')" class="text-rose-400 hover:text-rose-300 ml-2">
                                <i class="fa-solid fa-trash-can text-[10px]"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="mt-12 space-y-6">
            <div class="flex justify-between items-center px-2">
                <div>
                    <h2 class="text-xl font-black text-white tracking-tight">Active Inventory</h2>
                    <p class="text-xs text-slate-500 font-bold">Manage current store items</p>
                </div>
                <span class="text-[10px] font-black text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 px-3 py-1.5 rounded-full uppercase tracking-tighter">
                    <?= count($games) ?> total units
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach($games as $g): ?>
                    <div class="card-bg p-5 rounded-3xl border border-slate-800 transition-all hover:border-slate-700 group">
                        <div class="flex gap-4">
                            <div class="relative">
                                <img src="../<?= $g['image'] ?: 'https://via.placeholder.com/100' ?>" class="w-16 h-16 rounded-[20px] object-cover border border-slate-700">
                                <?php if($g['status']): ?>
                                    <div class="absolute -top-1 -right-1 w-5 h-5 bg-emerald-500 rounded-full border-[3px] border-[#121826] flex items-center justify-center">
                                        <i class="fa-solid fa-check text-[8px] text-white"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="absolute -top-1 -right-1 w-5 h-5 bg-rose-500 rounded-full border-[3px] border-[#121826] flex items-center justify-center">
                                        <i class="fa-solid fa-xmark text-[8px] text-white"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start">
                                    <h3 class="font-black text-white text-base truncate pr-2"><?= htmlspecialchars($g['title']) ?></h3>
                                    <div class="flex gap-3">
                                        <a href="?edit_game=<?= $g['id'] ?>" class="w-8 h-8 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-xs active:scale-90 transition-transform"><i class="fa-solid fa-pen"></i></a>
                                        <a href="?delete_game=<?= $g['id'] ?>" onclick="return confirm('Delete?')" class="w-8 h-8 rounded-lg bg-rose-500/10 text-rose-400 flex items-center justify-center text-xs active:scale-90 transition-transform"><i class="fa-solid fa-trash"></i></a>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2 mt-2">
                                    <span class="text-[9px] font-black px-2 py-1 bg-indigo-500/10 text-indigo-400 rounded-md uppercase tracking-tighter"><?= $g['provider'] ?></span>
                                    <span class="text-[9px] font-black px-2 py-1 bg-slate-800 text-slate-400 rounded-md uppercase tracking-tighter">Pos: <?= $g['sort_order'] ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 pt-4 border-t border-slate-800/60 flex flex-wrap gap-2">
                            <?php 
                            $thisGameCats = array_filter($categories, fn($c) => $c['game_id'] == $g['id']);
                            foreach($thisGameCats as $tc): ?>
                                <div class="bg-orange-500/10 text-orange-400 text-[10px] font-black px-3 py-1.5 rounded-xl border border-orange-500/10 flex items-center gap-2">
                                    <?= htmlspecialchars($tc['name']) ?>
                                    <a href="?delete_cat=<?= $tc['id'] ?>" class="text-orange-900/50 hover:text-rose-500 transition-colors"><i class="fa-solid fa-circle-xmark"></i></a>
                                </div>
                            <?php endforeach; ?>
                            
                            <button onclick="openCategoryModal('<?= $g['id'] ?>', '<?= htmlspecialchars($g['title']) ?>')" class="bg-slate-800/40 text-slate-500 text-[10px] font-black px-3 py-1.5 rounded-xl border border-dashed border-slate-700 hover:text-indigo-400 hover:border-indigo-400 transition-all">
                                <i class="fa-solid fa-plus mr-1"></i> Add Tab
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <div id="catModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[60] hidden flex items-center justify-center p-4">
        <div class="card-bg w-full max-w-md rounded-[32px] p-8 relative">
            <button onclick="closeCategoryModal()" class="absolute top-6 right-6 text-slate-500 hover:text-white"><i class="fa-solid fa-xmark text-xl"></i></button>
            
            <div class="mb-6">
                <h3 class="text-xl font-black text-white">Add New Category</h3>
                <p id="modalGameTitle" class="text-indigo-400 text-xs font-bold uppercase tracking-widest mt-1"></p>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="save_category" value="1">
                <input type="hidden" name="game_id" id="modalGameId">
                
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">Tab Label</label>
                    <input type="text" name="cat_name" placeholder="e.g. UC, Diamonds, Magic Chess" required class="input-style w-full rounded-2xl p-4 text-sm font-bold outline-none">
                </div>

                <button class="btn-primary w-full text-white py-4 rounded-2xl font-black text-sm shadow-xl shadow-indigo-500/20 mt-2">
                    Confirm & Link Tab
                </button>
            </form>
        </div>
    </div>

    <script>
        function openCategoryModal(id, title) {
            document.getElementById('modalGameId').value = id;
            document.getElementById('modalGameTitle').innerText = "Linking to: " + title;
            document.getElementById('catModal').classList.remove('hidden');
        }

        function closeCategoryModal() {
            document.getElementById('catModal').classList.add('hidden');
        }

        // Close modal on outside click
        window.onclick = function(event) {
            let modal = document.getElementById('catModal');
            if (event.target == modal) closeCategoryModal();
        }
    </script>

    <nav class="lg:hidden fixed bottom-0 left-0 w-full glass-nav z-50 px-6 pb-8 pt-4">
        <div class="flex items-center justify-between max-w-md mx-auto">
            <a href="admin_game.php" class="nav-link active-link flex flex-col items-center gap-1.5">
                <i class="fa-solid fa-gamepad text-xl"></i>
                <span class="text-[9px] font-black uppercase tracking-tighter">Games</span>
            </a>
            <a href="admin_product.php" class="nav-link flex flex-col items-center gap-1.5 text-slate-500">
                <i class="fa-solid fa-layer-group text-xl"></i>
                <span class="text-[9px] font-black uppercase tracking-tighter">Items</span>
            </a>
            
            <div class="relative -top-10">
                <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})" class="w-14 h-14 btn-primary text-white rounded-2xl shadow-2xl shadow-indigo-500/40 flex items-center justify-center border-4 border-[#070b14] active:rotate-90 transition-transform duration-300">
                    <i class="fa-solid fa-plus text-xl"></i>
                </button>
            </div>

            <a href="admin_faq.php" class="nav-link flex flex-col items-center gap-1.5 text-slate-500">
                <i class="fa-solid fa-comment-nodes text-xl"></i>
                <span class="text-[9px] font-black uppercase tracking-tighter">FAQ</span>
            </a>
            <a href="logout.php" class="nav-link flex flex-col items-center gap-1.5 text-rose-500/70">
                <i class="fa-solid fa-power-off text-xl"></i>
                <span class="text-[9px] font-black uppercase tracking-tighter">Exit</span>
            </a>
        </div>
    </nav>

</body>
</html>