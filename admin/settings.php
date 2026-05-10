<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../includes/config.php';

// --- 1. SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); 
    exit("Access Denied: You must be an admin to view this page.");
}

// Global helpers are now loaded via includes/config.php

if (isset($_GET['delete_banner'])) {
    $id = (int)$_GET['delete_banner'];
    $stmt = $conn->prepare("DELETE FROM banners WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['flash_msg'] = "Banner removed successfully.";
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}

if (isset($_GET['delete_notif'])) {
    $id = (int)$_GET['delete_notif'];
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['flash_msg'] = "Notification deleted successfully.";
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}

// --- 3. HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // === A. SAVE STORE SETTINGS ===
    if (isset($_POST['save_settings'])) {
        $store_name  = trim($_POST['store_name']);
        $store_logo  = trim($_POST['store_logo']);
        $fav_icon    = trim($_POST['fav_icon']);
        $description = trim($_POST['description']);
        $keywords    = trim($_POST['keywords']);
        $facebook    = trim($_POST['facebook']);
        $instagram   = trim($_POST['instagram']);
        $whatsapp    = trim($_POST['whatsapp']);
        $whatsapp_group = trim($_POST['whatsapp_group'] ?? '');
        
        $is_banner_on = isset($_POST['is_banner_on']) ? 1 : 0;
        $is_maintenance = isset($_POST['is_maintenance']) ? 1 : 0;
        $flash_sale_end = !empty($_POST['flash_sale_end']) ? $_POST['flash_sale_end'] : null;

        $stmt = $conn->prepare("UPDATE fav_setting SET store_name=?, store_logo=?, fav_icon=?, description=?, keywords=?, facebook=?, instagram=?, whatsapp=?, whatsapp_group=?, is_banner_on=?, is_maintenance=?, flash_sale_end=? WHERE id=1");
        
        if ($stmt) {
            $stmt->bind_param("sssssssssiis", $store_name, $store_logo, $fav_icon, $description, $keywords, $facebook, $instagram, $whatsapp, $whatsapp_group, $is_banner_on, $is_maintenance, $flash_sale_end);
            if ($stmt->execute()) {
                $_SESSION['flash_msg'] = "Store settings & SEO updated successfully!";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_msg'] = "Error: " . $stmt->error;
                $_SESSION['flash_type'] = "error";
            }
            $stmt->close();
        }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    // === B. UPDATE PAYMENT METHOD ===
    if (isset($_POST['update_payment'])) {
        $p_id     = (int)$_POST['payment_id'];
        $p_name   = trim($_POST['method_name']);
        $p_image  = trim($_POST['image_url']);
        $p_order  = (int)$_POST['display_order'];
        $p_status = (int)$_POST['status'];

        $stmt = $conn->prepare("UPDATE payment_methods SET method_name=?, image_url=?, display_order=?, status=? WHERE id=?");
        $stmt->bind_param("ssiii", $p_name, $p_image, $p_order, $p_status, $p_id);
        $stmt->execute();
        $_SESSION['flash_msg'] = "Payment method updated.";
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    // === C. ADD NEW BANNER ===
    if (isset($_POST['add_banner'])) {
        $image_url = trim($_POST['banner_image_url']);
        $link_url  = trim($_POST['banner_link']);
        
        $uploaded = handle_upload('banner_file');
        if ($uploaded) $image_url = $uploaded;

        if (!empty($image_url)) {
            $stmt = $conn->prepare("INSERT INTO banners (image_url, link_url) VALUES (?, ?)");
            $stmt->bind_param("ss", $image_url, $link_url);
            $stmt->execute();
            $_SESSION['flash_msg'] = "Banner added successfully.";
        }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    // === E. SEND NOTIFICATION ===
    if (isset($_POST['send_notification'])) {
        $title = trim($_POST['notif_title']);
        $notif_msg = trim($_POST['notif_message']);
        $image_url = "";

        $uploaded = handle_upload('notif_image');
        if ($uploaded) $image_url = $uploaded;

        $stmt = $conn->prepare("INSERT INTO notifications (title, message, image_url) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $title, $notif_msg, $image_url);
        $stmt->execute();
        
        $_SESSION['flash_msg'] = "Notification sent.";
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }
    // === D. SAVE SMTP / EMAIL SETTINGS ===
    if (isset($_POST['save_smtp'])) {
        $smtp_enabled    = isset($_POST['smtp_enabled']) ? 1 : 0;
        $smtp_from_name  = trim($_POST['smtp_from_name']);
        $smtp_from_email = trim($_POST['smtp_from_email']);

        $stmt = $conn->prepare("UPDATE fav_setting SET smtp_enabled=?, smtp_from_name=?, smtp_from_email=? WHERE id=1");
        if ($stmt) {
            $stmt->bind_param("iss", $smtp_enabled, $smtp_from_name, $smtp_from_email);
            if ($stmt->execute()) {
                $_SESSION['flash_msg']  = "Email notification settings saved!";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_msg']  = "DB Error: " . $stmt->error . " — Did you run smtp_migration.sql?";
                $_SESSION['flash_type'] = "error";
            }
            $stmt->close();
        }
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }
}

// --- 4. DATA FETCHING ---
$current_settings = [];
$res_set = $conn->query("SELECT * FROM fav_setting LIMIT 1");
if ($res_set && $res_set->num_rows > 0) {
    $current_settings = $res_set->fetch_assoc();
} else {
    $current_settings = [
        'store_name' => 'My Store', 'store_logo' => '', 'fav_icon' => '', 'description' => '', 'keywords' => '',
        'facebook' => '', 'instagram' => '', 'whatsapp' => '', 'whatsapp_group' => '', 
        'is_banner_on' => 1, 'is_maintenance' => 0
    ];
}

$payment_methods = [];
$res_pm = $conn->query("SELECT * FROM payment_methods ORDER BY display_order ASC");
if ($res_pm) while($row = $res_pm->fetch_assoc()) $payment_methods[] = $row;

$banners = [];
$res_banners = $conn->query("SELECT * FROM banners ORDER BY id DESC");
if ($res_banners) while($row = $res_banners->fetch_assoc()) $banners[] = $row;

$notifications = [];
$res_notif = $conn->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 10");
if ($res_notif) while($row = $res_notif->fetch_assoc()) $notifications[] = $row;

$setting = $current_settings;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Dashboard – <?= htmlspecialchars($current_settings['store_name'] ?? 'JZ Store') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=DynaPuff:wght@400;600&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        themeDark: '#0f172a',
                        themeBlue: '#a6c1ee',
                        themeGreen: '#80bf15',
                        themePink: '#fbc2eb'
                    },
                    fontFamily: {
                        poppins: ['Poppins', 'sans-serif'],
                        dynapuff: ['DynaPuff', 'cursive']
                    }
                }
            }
        }
    </script>
    <style>
        :root { --theme-dark: #0f172a; }
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(177deg, #fbc2eb, #a6c1ee, #80bf15); background-attachment: fixed; color: var(--theme-dark); overflow-x: hidden; }
        .glass-panel { background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.2); }
        .font-dynapuff { font-family: 'DynaPuff', cursive; }
        input[type="text"], input[type="number"], input[type="datetime-local"], textarea, select {
            background: rgba(255, 255, 255, 0.4) !important;
            border: 1px solid rgba(255, 255, 255, 0.3) !important;
            backdrop-filter: blur(8px) !important;
            border-radius: 12px !important;
            outline: none !important;
            transition: all 0.3s ease;
        }
        input:focus, textarea:focus, select:focus {
            background: rgba(255, 255, 255, 0.6) !important;
            border-color: rgba(255, 255, 255, 0.5) !important;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="pb-32">

    <header class="fixed top-0 w-full z-50 glass-panel h-16">
        <div class="max-w-4xl mx-auto px-4 h-full flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="../index.php" class="w-9 h-9 rounded-xl bg-white/40 flex items-center justify-center border border-white/30 hover:bg-white/60 transition">
                    <i class="fa-solid fa-arrow-left text-themeDark text-sm"></i>
                </a>
                <div class="font-bold text-lg text-themeDark font-dynapuff tracking-wider">Admin Dashboard</div>
            </div>
            <div class="flex items-center gap-2">
                <div class="hidden sm:block text-right leading-tight">
                    <div class="text-[10px] text-themeDark/60 uppercase tracking-wider font-bold">Admin</div>
                    <div class="text-sm font-bold text-themeDark"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></div>
                </div>
                <div class="w-9 h-9 bg-white/40 border border-white/30 text-themeDark rounded-full flex items-center justify-center font-bold">
                    <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto pt-20 px-4">
        
        <?php if (isset($_SESSION['flash_msg'])): ?>
            <div class="mb-6 p-4 glass-panel rounded-2xl flex items-start gap-3 shadow-sm border <?= (isset($_SESSION['flash_type']) && $_SESSION['flash_type'] === 'error') ? 'bg-red-500/20 text-red-800 border-red-500/30' : 'bg-green-500/20 text-green-800 border-green-500/30' ?>">
                <i class="mt-1 fa-solid <?= (isset($_SESSION['flash_type']) && $_SESSION['flash_type'] === 'error') ? 'fa-circle-exclamation' : 'fa-circle-check' ?>"></i>
                <div class="text-sm font-bold"><?= htmlspecialchars($_SESSION['flash_msg']); ?></div>
            </div>
            <?php unset($_SESSION['flash_msg']); unset($_SESSION['flash_type']); ?>
        <?php endif; ?>

        <!-- Store Settings Section -->
        <div class="mb-10">
            <h2 class="text-sm font-bold text-themeDark flex items-center gap-2 mb-4 px-1">
                <span class="w-1 h-4 bg-blue-500 rounded-full shadow-[0_0_8px_rgba(59,130,246,0.5)]"></span>
                General Store Settings
            </h2>

            <form method="POST" class="glass-panel p-6 rounded-[2rem] shadow-sm">
                <input type="hidden" name="save_settings" value="1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-4">
                        <h3 class="text-[11px] font-black text-themeDark/40 uppercase tracking-widest flex items-center gap-2 border-b border-themeDark/5 pb-2">
                            <i class="fa-solid fa-fingerprint"></i> Identity & SEO
                        </h3>
                        <div>
                            <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">Store Name</label>
                            <input type="text" name="store_name" value="<?= htmlspecialchars($current_settings['store_name']); ?>" required class="w-full px-4 py-3 text-sm font-bold text-themeDark">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">SEO Keywords</label>
                            <input type="text" name="keywords" value="<?= htmlspecialchars($current_settings['keywords'] ?? ''); ?>" placeholder="pubg, mobile legends..." class="w-full px-4 py-3 text-sm font-bold text-themeDark">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">Logo URL</label>
                                <input type="text" name="store_logo" value="<?= htmlspecialchars($current_settings['store_logo']); ?>" class="w-full px-3 py-3 text-[11px] font-bold text-themeDark">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">Favicon URL</label>
                                <input type="text" name="fav_icon" value="<?= htmlspecialchars($current_settings['fav_icon']); ?>" class="w-full px-3 py-3 text-[11px] font-bold text-themeDark">
                            </div>
                        </div>
                        
                        <div class="space-y-3 pt-2">
                            <div class="flex items-center justify-between glass-panel p-4 rounded-2xl border-white/50">
                                <span class="text-xs font-bold text-themeDark">Show Slider Banners</span>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_banner_on" value="1" class="sr-only peer" <?= ($current_settings['is_banner_on'] == 1) ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-white/40 rounded-full peer peer-checked:bg-themeGreen after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                                </label>
                            </div>
                            <div class="flex items-center justify-between glass-panel p-4 rounded-2xl border-white/50 bg-red-500/5">
                                <span class="text-xs font-bold text-themeDark">Maintenance Mode</span>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_maintenance" value="1" class="sr-only peer" <?= ($current_settings['is_maintenance'] == 1) ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-white/40 rounded-full peer peer-checked:bg-red-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                                </label>
                            </div>
                            <div class="glass-panel p-4 rounded-2xl border-white/50 bg-orange-500/5">
                                <label class="block text-[10px] font-bold text-orange-600 mb-2 uppercase tracking-widest">Flash Sale End Time</label>
                                <input type="datetime-local" name="flash_sale_end" value="<?= !empty($current_settings['flash_sale_end']) ? date('Y-m-d\TH:i', strtotime($current_settings['flash_sale_end'])) : '' ?>" class="w-full px-3 py-2 text-xs font-bold text-themeDark">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-[11px] font-black text-themeDark/40 uppercase tracking-widest flex items-center gap-2 border-b border-themeDark/5 pb-2">
                            <i class="fa-solid fa-share-nodes"></i> Links & Social
                        </h3>
                        <div>
                            <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">WhatsApp Group URL</label>
                            <input type="text" name="whatsapp_group" value="<?= htmlspecialchars($current_settings['whatsapp_group'] ?? ''); ?>" class="w-full px-4 py-3 text-sm font-bold text-themeDark">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">Support WhatsApp</label>
                            <input type="text" name="whatsapp" value="<?= htmlspecialchars($current_settings['whatsapp']); ?>" class="w-full px-4 py-3 text-sm font-bold text-themeDark">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">Facebook</label>
                                <input type="text" name="facebook" value="<?= htmlspecialchars($current_settings['facebook']); ?>" class="w-full px-3 py-3 text-[11px] font-bold text-themeDark">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">Instagram</label>
                                <input type="text" name="instagram" value="<?= htmlspecialchars($current_settings['instagram']); ?>" class="w-full px-3 py-3 text-[11px] font-bold text-themeDark">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">Store Description</label>
                            <textarea name="description" rows="3" class="w-full px-4 py-3 text-xs font-bold text-themeDark leading-relaxed"><?= htmlspecialchars($current_settings['description']); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="mt-8 pt-4 border-t border-themeDark/5 text-right">
                    <button type="submit" class="bg-themeDark hover:bg-black text-white font-black py-4 px-10 rounded-2xl transition shadow-xl active:scale-95">Save Store Settings</button>
                </div>
            </form>
        </div>

        <!-- SMM Panel Integration Card -->
        <div class="mb-10">
            <h2 class="text-sm font-bold text-themeDark flex items-center gap-2 mb-4 px-1">
                <span class="w-1 h-4 bg-indigo-500 rounded-full shadow-[0_0_8px_rgba(99,102,241,0.5)]"></span>
                Automation & External APIs
            </h2>
            <a href="admin_smm.php" class="flex items-center gap-4 bg-themeDark text-white rounded-[2rem] p-6 shadow-xl hover:scale-[1.01] transition active:scale-[0.99] border border-white/10">
                <div class="w-14 h-14 bg-white/10 rounded-2xl flex items-center justify-center text-3xl">🚀</div>
                <div class="flex-1">
                    <div class="font-black text-lg font-dynapuff italic tracking-wide">SMM Panel Manager</div>
                    <div class="text-white/50 text-[10px] font-bold uppercase tracking-wider mt-1">Configure API, browse services & manage orders</div>
                </div>
                <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center">
                    <i class="fa-solid fa-arrow-right text-white/60"></i>
                </div>
            </a>
        </div>

        <!-- Email / Invoice Notification Settings -->
        <div class="mb-10">
            <h2 class="text-sm font-bold text-themeDark flex items-center gap-2 mb-4 px-1">
                <span class="w-1 h-4 bg-violet-500 rounded-full shadow-[0_0_8px_rgba(139,92,246,0.5)]"></span>
                Email Invoice Notifications
            </h2>
            <form method="POST" class="glass-panel p-6 rounded-[2rem] shadow-sm space-y-5">
                <input type="hidden" name="save_smtp" value="1">

                <!-- Enable Toggle -->
                <div class="flex items-center justify-between glass-panel p-4 rounded-2xl border-white/50">
                    <div>
                        <p class="text-xs font-black text-themeDark">Send Invoice Email on Order</p>
                        <p class="text-[10px] text-themeDark/50 mt-0.5">Sends a HTML invoice to user after every completed order</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="smtp_enabled" value="1" class="sr-only peer" <?= ($current_settings['smtp_enabled'] ?? 0) == 1 ? 'checked' : '' ?>>
                        <div class="w-11 h-6 bg-white/40 rounded-full peer peer-checked:bg-violet-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">Sender Name</label>
                        <input type="text" name="smtp_from_name"
                            value="<?= htmlspecialchars($current_settings['smtp_from_name'] ?? '') ?>"
                            placeholder="e.g. JZ Store" class="w-full px-4 py-3 text-sm font-bold text-themeDark">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">Sender Email (From)</label>
                        <input type="email" name="smtp_from_email"
                            value="<?= htmlspecialchars($current_settings['smtp_from_email'] ?? '') ?>"
                            placeholder="e.g. no-reply@jzstore.in" class="w-full px-4 py-3 text-sm font-bold text-themeDark">
                    </div>
                </div>

                <div class="bg-blue-500/10 border border-blue-400/20 rounded-2xl p-4 text-[11px] text-themeDark/60 leading-relaxed">
                    <p class="font-black text-blue-600 mb-1 uppercase tracking-wider text-[10px]">⚠️ Important — XAMPP / cPanel Note</p>
                    <p>On <strong>XAMPP localhost</strong>, PHP <code>mail()</code> requires Sendmail to be configured in <code>php.ini</code>.<br>
                    On <strong>cPanel hosting</strong>, mail() works automatically if the sender email matches your domain.<br>
                    Run <code>smtp_migration.sql</code> in phpMyAdmin before saving these settings.</p>
                </div>

                <div class="text-right">
                    <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white font-black py-4 px-10 rounded-2xl transition shadow-xl active:scale-95 text-sm">
                        <i class="fa-solid fa-paper-plane mr-2"></i>Save Email Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Payment Methods Section -->
        <div class="mb-10">
            <h2 class="text-sm font-bold text-themeDark flex items-center gap-2 mb-4 px-1">
                <span class="w-1 h-4 bg-themeGreen rounded-full shadow-[0_0_8px_rgba(128,191,21,0.5)]"></span>
                Payment Gateways
            </h2>
            <div class="glass-panel rounded-[2rem] overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-white/30 text-[10px] uppercase text-themeDark/50 font-black border-b border-white/20">
                            <tr>
                                <th class="p-5">Method</th>
                                <th class="p-5">Display Order</th>
                                <th class="p-5">Status</th>
                                <th class="p-5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/20 text-[11px] font-bold text-themeDark">
                            <?php foreach($payment_methods as $pm): ?>
                            <tr class="hover:bg-white/20 transition">
                                <form method="POST">
                                    <input type="hidden" name="update_payment" value="1">
                                    <input type="hidden" name="payment_id" value="<?= $pm['id'] ?>">
                                    <td class="p-5">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 bg-white/40 rounded-xl p-1.5 border border-white/30">
                                                <img src="<?= htmlspecialchars($pm['image_url']) ?>" class="w-full h-full object-contain">
                                            </div>
                                            <input type="text" name="method_name" value="<?= htmlspecialchars($pm['method_name']) ?>" class="bg-transparent font-black !border-none !rounded-none !p-0 !backdrop-filter-none">
                                        </div>
                                    </td>
                                    <td class="p-5"><input type="number" name="display_order" value="<?= $pm['display_order'] ?>" class="w-16 text-center py-2"></td>
                                    <td class="p-5">
                                        <select name="status" class="px-3 py-2 text-[10px] font-black uppercase">
                                            <option value="1" <?= $pm['status'] == 1 ? 'selected' : '' ?>>Show</option>
                                            <option value="0" <?= $pm['status'] == 0 ? 'selected' : '' ?>>Hide</option>
                                        </select>
                                    </td>
                                    <td class="p-5 text-right"><button type="submit" class="bg-themeDark text-white px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wider active:scale-95 transition shadow-lg">Update</button></td>
                                </form>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Banners & Notifications Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
             <div>
                <h2 class="text-sm font-bold text-themeDark flex items-center gap-2 mb-4 px-1">
                    <span class="w-1 h-4 bg-orange-500 rounded-full shadow-[0_0_8px_rgba(249,115,22,0.5)]"></span>
                    Hero Banners
                </h2>
                <div class="glass-panel p-6 rounded-[2rem] mb-4">
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="add_banner" value="1">
                        <div>
                            <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">Banner Image URL</label>
                            <input type="text" name="banner_image_url" placeholder="https://..." class="w-full px-4 py-3 text-[11px] font-bold text-themeDark">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">Target Link URL</label>
                            <input type="text" name="banner_link" placeholder="index.php?game=..." class="w-full px-4 py-3 text-[11px] font-bold text-themeDark">
                        </div>
                        <div class="flex items-center gap-4">
                            <input type="file" name="banner_file" class="text-[9px] font-bold text-themeDark/60 flex-1">
                            <button type="submit" class="bg-orange-500 text-white px-6 py-3 rounded-xl font-black text-xs shadow-lg active:scale-95 transition">Add Banner</button>
                        </div>
                    </form>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <?php foreach($banners as $b): ?>
                    <div class="relative rounded-[1.5rem] overflow-hidden border border-white/40 group shadow-sm h-28">
                        <img src="<?= (strpos($b['image_url'], 'http') === 0) ? $b['image_url'] : '../' . ltrim($b['image_url'], '/'); ?>" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                            <a href="?delete_banner=<?= $b['id'] ?>" class="bg-red-500 text-white w-8 h-8 rounded-full flex items-center justify-center shadow-lg"><i class="fa-solid fa-trash-can text-xs"></i></a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
             </div>

             <div>
                <h2 class="text-sm font-bold text-themeDark flex items-center gap-2 mb-4 px-1">
                    <span class="w-1 h-4 bg-pink-500 rounded-full shadow-[0_0_8px_rgba(236,72,153,0.5)]"></span>
                    Broadcast Notifications
                </h2>
                <div class="glass-panel p-6 rounded-[2rem] h-full flex flex-col">
                    <form method="POST" enctype="multipart/form-data" class="space-y-4 flex-1">
                        <input type="hidden" name="send_notification" value="1">
                        <div>
                            <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">Notification Title</label>
                            <input type="text" name="notif_title" placeholder="Special Offer!" required class="w-full px-4 py-3 text-sm font-bold text-themeDark">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">Message Content</label>
                            <textarea name="notif_message" rows="4" placeholder="Type your broadcast message here..." required class="w-full px-4 py-3 text-xs font-bold text-themeDark leading-relaxed"></textarea>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-themeDark/50 uppercase ml-1 mb-1.5">Attach Image (Optional)</label>
                            <input type="file" name="notif_image" class="text-[9px] font-bold text-themeDark/60 w-full">
                        </div>
                        <button type="submit" class="w-full bg-pink-500 text-white py-4 rounded-2xl font-black text-sm shadow-lg active:scale-95 transition mt-auto"><i class="fa-solid fa-paper-plane mr-2"></i> Broadcast to All Users</button>
                    </form>
                </div>
             </div>
        </div>

        <div class="mt-12 opacity-40 text-center">
             <div class="font-dynapuff text-xl mb-1"><?= htmlspecialchars($current_settings['store_name'] ?? 'JZ Store') ?></div>
             <p class="text-[10px] font-bold uppercase tracking-widest">Admin Control Center</p>
        </div>
    </main>

    <?php include '../footer.php'; ?>
</body>
</html>