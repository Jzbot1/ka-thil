<?php
require_once __DIR__ . '/../includes/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit("Access Denied");
}

$vcs = new VirtualCardSystem($conn);

// Actions
if(isset($_POST['act'])) {
    $id = (int)$_POST['id'];
    if($_POST['act'] === 'block') $conn->query("UPDATE virtual_cards SET status='blocked' WHERE id=$id");
    if($_POST['act'] === 'unblock') $conn->query("UPDATE virtual_cards SET status='active' WHERE id=$id");
    if($_POST['act'] === 'freeze') $conn->query("UPDATE virtual_cards SET status='frozen' WHERE id=$id");
    header("Location: admin_cards.php"); exit;
}

// Fetch Cards
$cards = $conn->query("SELECT c.*, u.username, u.email FROM virtual_cards c JOIN users u ON c.user_id = u.id ORDER BY c.id DESC")->fetch_all(MYSQLI_ASSOC);

// Fetch Logs
$logs = $conn->query("SELECT t.*, u.username, c.card_number FROM card_transactions t JOIN users u ON t.user_id = u.id JOIN virtual_cards c ON t.card_id = c.id ORDER BY t.id DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Virtual Cards - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>body{font-family:'Poppins',sans-serif;background:#f1f5f9;}</style>
</head>
<body class="p-4 md:p-8">
    <div class="max-w-6xl mx-auto space-y-8">
        
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-black text-slate-800">Virtual Card Management</h1>
            <a href="index.php" class="bg-white px-4 py-2 rounded-xl border border-slate-200 text-sm font-bold shadow-sm">Back to Dashboard</a>
        </div>

        <!-- Cards List -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                <h2 class="font-black text-slate-700">Active & Frozen Cards</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-400 font-bold uppercase text-[10px]">
                        <tr>
                            <th class="px-6 py-4">User</th>
                            <th class="px-6 py-4">Card Number</th>
                            <th class="px-6 py-4">Expiry</th>
                            <th class="px-6 py-4">Limit</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($cards as $c): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4">
                                <p class="font-bold text-slate-700"><?= htmlspecialchars($c['username']) ?></p>
                                <p class="text-[10px] text-slate-400"><?= htmlspecialchars($c['email']) ?></p>
                            </td>
                            <td class="px-6 py-4 font-mono font-bold text-indigo-600">
                                <?= substr($c['card_number'], 0, 4) ?> **** **** <?= substr($c['card_number'], -4) ?>
                            </td>
                            <td class="px-6 py-4 font-bold text-slate-600"><?= sprintf('%02d/%d', $c['expiry_month'], $c['expiry_year'] % 100) ?></td>
                            <td class="px-6 py-4 font-black text-slate-800">₹<?= number_format($c['daily_limit'], 0) ?></td>
                            <td class="px-6 py-4">
                                <?php $s = $c['status']; ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase <?= $s==='active'?'bg-emerald-100 text-emerald-600':($s==='frozen'?'bg-amber-100 text-amber-600':'bg-rose-100 text-rose-600') ?>">
                                    <?= $s ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <?php if($s === 'active'): ?>
                                        <button name="act" value="freeze" class="text-amber-500 hover:text-amber-600"><i class="fa-solid fa-snowflake"></i></button>
                                        <button name="act" value="block" class="text-rose-500 hover:text-rose-600"><i class="fa-solid fa-ban"></i></button>
                                    <?php elseif($s === 'frozen' || $s === 'blocked'): ?>
                                        <button name="act" value="unblock" class="text-emerald-500 hover:text-emerald-600"><i class="fa-solid fa-play"></i></button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Transaction Logs -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 border-b border-slate-100">
                <h2 class="font-black text-slate-700">Recent Card Transactions</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-400 font-bold uppercase text-[10px]">
                        <tr>
                            <th class="px-6 py-4">Date</th>
                            <th class="px-6 py-4">User</th>
                            <th class="px-6 py-4">Card</th>
                            <th class="px-6 py-4">Merchant</th>
                            <th class="px-6 py-4">Amount</th>
                            <th class="px-6 py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach($logs as $l): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4 text-slate-400 text-[10px]"><?= date('d M Y, H:i', strtotime($l['created_at'])) ?></td>
                            <td class="px-6 py-4 font-bold text-slate-700"><?= htmlspecialchars($l['username']) ?></td>
                            <td class="px-6 py-4 font-mono text-indigo-400">**** <?= substr($l['card_number'], -4) ?></td>
                            <td class="px-6 py-4 font-medium text-slate-600"><?= htmlspecialchars($l['merchant']) ?></td>
                            <td class="px-6 py-4 font-black text-slate-800">₹<?= number_format($l['amount'], 2) ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase <?= $l['status']==='success'?'bg-green-100 text-green-600':'bg-rose-100 text-rose-600' ?>">
                                    <?= $l['status'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>
</html>
