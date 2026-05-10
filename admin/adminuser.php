<?php
require_once __DIR__ . '/strict_admin.php';
$user_role = 'admin'; // For compatibility with existing variables in this file
$admin_id = (int)($_SESSION['user_id'] ?? 0);

// 2. CSRF PROTECTION
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$view = $_GET['view'] ?? 'users'; // Default to users management
$update_message = '';
$message_type = 'success';

// 3. POST ACTIONS (Handling Updates)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    
    // ACTION A: BATCH ACCOUNT UPDATES
    if (isset($_POST['batch_update'])) {
        $conn->begin_transaction();
        try {
            if (isset($_POST['roles'])) {
                foreach ($_POST['roles'] as $uid => $role) {
                    if ($uid == $admin_id) continue;
                    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->bind_param("si", $role, $uid);
                    $stmt->execute();
                }
            }
            if (isset($_POST['status_updates'])) {
                foreach ($_POST['status_updates'] as $uid => $status) {
                    if ($uid == $admin_id) continue;
                    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $status, $uid);
                    $stmt->execute();
                }
            }
            if (isset($_POST['wallet_approvals'])) {
                foreach ($_POST['wallet_approvals'] as $uid => $appr) {
                    $val = (int)$appr;
                    $stmt = $conn->prepare("UPDATE users SET wallet_approved = ? WHERE id = ?");
                    $stmt->bind_param("ii", $val, $uid);
                    $stmt->execute();
                }
            }
            $conn->commit();
            $update_message = "Account settings updated successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $update_message = "Error: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    // ACTION B: INDIVIDUAL WALLET ADJUSTMENT
    if (isset($_POST['adj_wallet'])) {
        $target_uid = (int)$_POST['target_user_id'];
        $amount = (float)$_POST['amount'];
        $action = $_POST['action'];
        
        if ($amount > 0) {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
                $stmt->bind_param("i", $target_uid);
                $stmt->execute();
                $target = $stmt->get_result()->fetch_assoc();
                
                if ($target) {
                    $old_bal = (float)$target['wallet_balance'];
                    $new_bal = ($action === 'add') ? ($old_bal + $amount) : ($old_bal - $amount);
                    if ($new_bal < 0) throw new Exception("Insufficient balance to subtract.");

                    $stmt_u = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
                    $stmt_u->bind_param("di", $new_bal, $target_uid);
                    $stmt_u->execute();

                    $type = ($action === 'add') ? 'credit' : 'debit';
                    $desc = "Admin Adjustment ($action) by ID: $admin_id";
                    $stmt_l = $conn->prepare("INSERT INTO wallet_logs (user_id, order_id, type, amount, balance_before, balance_after, description) VALUES (?, 'ADMIN_ADJ', ?, ?, ?, ?, ?)");
                    $stmt_l->bind_param("isddds", $target_uid, $type, $amount, $old_bal, $new_bal, $desc);
                    $stmt_l->execute();

                    $conn->commit();
                    $update_message = "Wallet successfully " . ($action == 'add' ? 'credited' : 'debited') . ".";
                }
            } catch (Exception $e) {
                $conn->rollback();
                $update_message = "Wallet Error: " . $e->getMessage();
                $message_type = "danger";
            }
        }
    }

    // ACTION C: GENERATE API CREDENTIALS
    if (isset($_POST['generate_api'])) {
        $target_uid = (int)$_POST['target_user_id'];
        $partner_id = 'P' . strtoupper(substr(md5(uniqid()), 0, 10));
        $secret = bin2hex(random_bytes(16));
        $stmt = $conn->prepare("UPDATE users SET api_partner_id = ?, api_secret = ? WHERE id = ?");
        $stmt->bind_param("ssi", $partner_id, $secret, $target_uid);
        if ($stmt->execute()) {
            $update_message = "API Credentials generated successfully.";
        } else {
            $update_message = "Failed to generate API credentials.";
            $message_type = "danger";
        }
    }

    // ACTION D: UPDATE API WHITELIST
    if (isset($_POST['update_whitelist'])) {
        $target_uid = (int)$_POST['target_user_id'];
        $whitelist = trim($_POST['api_ip_whitelist']);
        $stmt = $conn->prepare("UPDATE users SET api_ip_whitelist = ? WHERE id = ?");
        $stmt->bind_param("si", $whitelist, $target_uid);
        if ($stmt->execute()) {
            $update_message = "API IP Whitelist updated.";
        } else {
            $update_message = "Failed to update whitelist.";
            $message_type = "danger";
        }
    }

    // ACTION E: REMOVE API CREDENTIALS
    if (isset($_POST['remove_api'])) {
        $target_uid = (int)$_POST['target_user_id'];
        $stmt = $conn->prepare("UPDATE users SET api_partner_id = NULL, api_secret = NULL, api_ip_whitelist = NULL WHERE id = ?");
        $stmt->bind_param("i", $target_uid);
        if ($stmt->execute()) {
            $update_message = "API Credentials removed successfully.";
        } else {
            $update_message = "Failed to remove API credentials.";
            $message_type = "danger";
        }
    }
}

// 4. DATA FETCHING
$search_term = $_GET['search'] ?? '';
$search_param = "%$search_term%";

if ($view === 'users') {
    $sql = "SELECT * FROM users";
    if ($search_term) $sql .= " WHERE username LIKE ? OR email LIKE ? OR mobile LIKE ?";
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if ($search_term) $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt->execute();
    $all_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $sql = "SELECT l.*, u.username FROM wallet_logs l JOIN users u ON l.user_id = u.id";
    if ($search_term) $sql .= " WHERE u.username LIKE ? OR l.order_id LIKE ?";
    $sql .= " ORDER BY l.created_at DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    if ($search_term) $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: linear-gradient(177deg, #fbc2eb, #a6c1ee, #80bf15); background-attachment: fixed; color: #0f172a; }
        .glass-panel { background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.3); }
        .glass-btn { background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(4px); transition: all 0.2s; }
        .glass-btn:hover { background: rgba(255, 255, 255, 0.8); }
        .glass-btn.active { background: #0f172a; color: white; border-color: #0f172a; }
    </style>
</head>
<body class="pb-32">

    <!-- HEADER -->
    <header class="fixed top-0 w-full z-50 bg-white/20 backdrop-blur-xl h-16 border-b border-white/20">
        <div class="max-w-4xl mx-auto px-5 h-full flex items-center justify-between">
            <a href="../profile" class="w-10 h-10 rounded-xl bg-white/40 flex items-center justify-center border border-white/30 hover:bg-white/60 transition"><i class="fa-solid fa-arrow-left text-themeDark text-sm"></i></a>
            <div class="font-black text-lg text-themeDark">User Manager</div>
            <div class="w-10"></div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 mt-24">

        <!-- FEEDBACK MESSAGE -->
        <?php if ($update_message): ?>
        <div class="mb-6 p-4 rounded-2xl bg-white/60 backdrop-blur border <?= $message_type === 'success' ? 'border-emerald-400' : 'border-rose-400' ?> flex items-center gap-3 animate-pulse">
            <i class="fa-solid fa-circle-<?= $message_type === 'success' ? 'check text-emerald-600' : 'xmark text-rose-600' ?>"></i>
            <p class="text-sm font-bold <?= $message_type === 'success' ? 'text-emerald-800' : 'text-rose-800' ?>"><?= $update_message ?></p>
        </div>
        <?php endif; ?>

        <!-- TABS & SEARCH -->
        <div class="flex flex-col md:flex-row gap-4 justify-between items-center mb-8">
            <div class="flex glass-panel p-1 rounded-2xl w-full md:w-auto">
                <a href="?view=users" class="glass-btn px-6 py-2 rounded-xl text-xs font-black uppercase tracking-widest <?= $view === 'users' ? 'active' : 'text-themeDark' ?>">Users</a>
                <a href="?view=transactions" class="glass-btn px-6 py-2 rounded-xl text-xs font-black uppercase tracking-widest <?= $view === 'transactions' ? 'active' : 'text-themeDark' ?>">History</a>
            </div>
            <form method="GET" class="w-full md:w-64">
                <input type="hidden" name="view" value="<?= $view ?>">
                <div class="relative">
                    <input type="text" name="search" value="<?= htmlspecialchars($search_term) ?>" placeholder="Search users..." class="w-full glass-panel border border-white/50 rounded-2xl px-4 py-3 text-sm font-bold outline-none focus:ring-2 ring-white/50 transition">
                    <button type="submit" class="absolute right-4 top-1/2 -translate-y-1/2 text-themeDark/40 hover:text-themeDark"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
            </form>
        </div>

        <?php if ($view === 'users'): ?>
            <!-- USERS VIEW -->
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="batch_update" value="1">
                
                <div class="space-y-4">
                    <?php foreach ($all_users as $u): ?>
                    <div class="glass-panel p-6 rounded-[2rem] border border-white/40 shadow-sm relative overflow-hidden">
                        
                        <div class="flex flex-col md:flex-row gap-6 items-start md:items-center">
                            <!-- User Identity -->
                            <div class="flex items-center gap-4 flex-1">
                                <div class="w-12 h-12 rounded-2xl bg-white/50 flex items-center justify-center text-themeDark border border-white/30 shadow-inner">
                                    <i class="fa-solid fa-user-astronaut text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="font-black text-themeDark text-base leading-none mb-1"><?= htmlspecialchars($u['username']) ?></h3>
                                    <p class="text-[9px] text-themeDark/60 font-black uppercase tracking-widest"><?= htmlspecialchars($u['email']) ?> | <?= htmlspecialchars($u['mobile']) ?></p>
                                </div>
                            </div>

                            <!-- Wallet Stats & Quick Action -->
                            <div class="flex items-center gap-6 w-full md:w-auto bg-white/30 p-3 rounded-2xl border border-white/40">
                                <div class="text-center px-2">
                                    <p class="text-[8px] text-themeDark/50 font-black uppercase tracking-widest mb-0.5">Wallet</p>
                                    <p class="text-sm font-black text-themeDark">₹<?= number_format($u['wallet_balance'], 2) ?></p>
                                </div>
                                <div class="w-px h-8 bg-white/50"></div>
                                <div class="flex items-center gap-2">
                                    <input type="number" id="amt_<?= $u['id'] ?>" placeholder="Amount" class="w-20 bg-white/50 rounded-xl px-2 py-1 text-xs font-bold outline-none text-center border border-white/50">
                                    <button type="button" onclick="adjWallet(<?= $u['id'] ?>, 'add')" class="w-8 h-8 rounded-xl bg-emerald-500 text-white flex items-center justify-center text-xs shadow-lg shadow-emerald-500/20 active:scale-95 transition"><i class="fa-solid fa-plus"></i></button>
                                    <button type="button" onclick="adjWallet(<?= $u['id'] ?>, 'remove')" class="w-8 h-8 rounded-xl bg-rose-500 text-white flex items-center justify-center text-xs shadow-lg shadow-rose-500/20 active:scale-95 transition"><i class="fa-solid fa-minus"></i></button>
                                </div>
                            </div>

                            <!-- Controls -->
                            <div class="grid grid-cols-3 gap-2 w-full md:w-auto">
                                <div>
                                    <label class="text-[8px] font-black text-themeDark/50 uppercase block mb-1">Role</label>
                                    <select name="roles[<?= $u['id'] ?>]" class="bg-white/50 border border-white/50 rounded-xl px-2 py-1.5 text-[10px] font-bold outline-none w-full">
                                        <option value="user" <?= $u['role'] == 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="reseller" <?= $u['role'] == 'reseller' ? 'selected' : '' ?>>Reseller</option>
                                        <option value="admin" <?= $u['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[8px] font-black text-themeDark/50 uppercase block mb-1">Status</label>
                                    <select name="status_updates[<?= $u['id'] ?>]" class="bg-white/50 border border-white/50 rounded-xl px-2 py-1.5 text-[10px] font-bold outline-none w-full">
                                        <option value="active" <?= $u['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="blocked" <?= $u['status'] == 'blocked' ? 'selected' : '' ?>>Blocked</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[8px] font-black text-themeDark/50 uppercase block mb-1">Topup</label>
                                    <select name="wallet_approvals[<?= $u['id'] ?>]" class="bg-white/50 border border-white/50 rounded-xl px-2 py-1.5 text-[10px] font-bold outline-none w-full">
                                        <option value="0" <?= !$u['wallet_approved'] ? 'selected' : '' ?>>Pending</option>
                                        <option value="1" <?= $u['wallet_approved'] ? 'selected' : '' ?>>Approved</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- API MANAGEMENT -->
                        <?php if ($u['role'] === 'reseller'): ?>
                        <div class="mt-4 pt-4 border-t border-white/30">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-6 h-6 rounded-lg bg-blue-500/20 flex items-center justify-center"><i class="fa-solid fa-code text-[10px] text-blue-700"></i></div>
                                <h4 class="text-[10px] font-black text-themeDark uppercase tracking-widest">Reseller API Hub</h4>
                            </div>

                            <div class="flex flex-col md:flex-row gap-4">
                                <?php if ($u['api_partner_id']): ?>
                                    <div class="flex-1 bg-white/40 border border-white/50 p-3 rounded-2xl space-y-2">
                                        <div class="flex justify-between items-center text-[10px]">
                                            <span class="font-bold text-themeDark/50 uppercase tracking-widest">Partner ID</span>
                                            <span class="font-mono font-black text-themeDark bg-white/50 px-2 py-0.5 rounded"><?= $u['api_partner_id'] ?></span>
                                        </div>
                                        <div class="flex justify-between items-center text-[10px]">
                                            <span class="font-bold text-themeDark/50 uppercase tracking-widest">Secret Key</span>
                                            <span class="font-mono font-black text-themeDark bg-white/50 px-2 py-0.5 rounded select-all"><?= $u['api_secret'] ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="flex-1 bg-white/40 border border-white/50 p-3 rounded-2xl flex items-center justify-center text-[10px] font-bold text-themeDark/50 uppercase tracking-widest">
                                        No API Keys Generated
                                    </div>
                                <?php endif; ?>

                                <div class="flex-1 flex flex-col gap-2 justify-center">
                                    <div class="flex gap-2">
                                        <button type="button" onclick="generateApi(<?= $u['id'] ?>)" class="flex-1 bg-themeDark text-white text-[10px] font-black py-2 rounded-xl hover:bg-themeDark/80 transition uppercase">
                                            <?= $u['api_partner_id'] ? '<i class="fa-solid fa-rotate mr-1"></i> Regen' : '<i class="fa-solid fa-key mr-1"></i> Generate' ?>
                                        </button>
                                        <?php if ($u['api_partner_id']): ?>
                                        <button type="button" onclick="removeApi(<?= $u['id'] ?>)" class="bg-rose-500 text-white px-3 rounded-xl hover:bg-rose-600 transition flex items-center justify-center shadow-lg shadow-rose-500/20">
                                            <i class="fa-solid fa-trash text-[10px]"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex gap-2">
                                        <input type="text" id="ip_<?= $u['id'] ?>" value="<?= htmlspecialchars($u['api_ip_whitelist']) ?>" placeholder="IP Whitelist (comma separated)" class="flex-1 bg-white/50 border border-white/50 rounded-xl px-3 py-2 text-[10px] font-bold outline-none">
                                        <button type="button" onclick="saveWhitelist(<?= $u['id'] ?>)" class="bg-blue-600 text-white text-[10px] font-black px-4 rounded-xl shadow-lg shadow-blue-500/20 active:scale-95 transition">SAVE</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="fixed bottom-0 left-0 w-full p-4 bg-white/20 backdrop-blur-xl border-t border-white/30 z-40">
                    <div class="max-w-4xl mx-auto flex justify-end">
                        <button type="submit" class="px-8 py-4 bg-themeDark text-white rounded-2xl font-black text-sm uppercase tracking-widest shadow-xl shadow-themeDark/20 flex items-center gap-2 hover:scale-105 active:scale-95 transition-all">
                            <i class="fa-solid fa-floppy-disk"></i> Save All Changes
                        </button>
                    </div>
                </div>
            </form>

        <?php else: ?>
            <!-- TRANSACTIONS VIEW -->
            <div class="glass-panel rounded-[2rem] overflow-hidden border border-white/40">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-white/40 border-b border-white/40">
                                <th class="p-4 text-[9px] font-black text-themeDark/60 uppercase tracking-widest">Date</th>
                                <th class="p-4 text-[9px] font-black text-themeDark/60 uppercase tracking-widest">User</th>
                                <th class="p-4 text-[9px] font-black text-themeDark/60 uppercase tracking-widest">Ref / Desc</th>
                                <th class="p-4 text-[9px] font-black text-themeDark/60 uppercase tracking-widest text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $l): ?>
                            <tr class="border-b border-white/20 hover:bg-white/30 transition">
                                <td class="p-4 text-[10px] font-bold text-themeDark/70"><?= date('d M, H:i', strtotime($l['created_at'])) ?></td>
                                <td class="p-4 text-[11px] font-black text-themeDark"><?= htmlspecialchars($l['username']) ?></td>
                                <td class="p-4">
                                    <span class="font-mono text-[10px] bg-white/50 border border-white/50 px-2 py-0.5 rounded-lg text-themeDark font-bold"><?= htmlspecialchars($l['order_id']) ?></span>
                                    <br><span class="text-[9px] font-bold text-themeDark/50 tracking-wide block mt-1"><?= htmlspecialchars($l['description']) ?></span>
                                </td>
                                <td class="p-4 text-sm font-black text-right <?= $l['type'] == 'credit' ? 'text-emerald-600' : 'text-rose-600' ?>">
                                    <?= $l['type'] == 'credit' ? '+' : '-' ?>₹<?= number_format($l['amount'], 2) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($logs)): ?>
                            <tr><td colspan="4" class="p-8 text-center text-[10px] font-bold text-themeDark/40 uppercase tracking-widest">No transactions found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- Hidden form generator for JS actions -->
    <script>
        function createAndSubmitForm(data) {
            const form = document.createElement('form');
            form.method = 'POST';
            for (const key in data) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = data[key];
                form.appendChild(input);
            }
            document.body.appendChild(form);
            form.submit();
        }

        function adjWallet(uid, action) {
            const amt = document.getElementById('amt_'+uid).value;
            if(!amt || amt <= 0) { alert('Enter valid amount'); return; }
            if(!confirm(`Are you sure you want to ${action} ₹${amt} for this user?`)) return;
            createAndSubmitForm({
                csrf_token: '<?= $_SESSION['csrf_token'] ?>',
                adj_wallet: '1', action: action, target_user_id: uid, amount: amt
            });
        }

        function generateApi(uid) {
            if(!confirm('Generate new API keys? Old keys will stop working.')) return;
            createAndSubmitForm({
                csrf_token: '<?= $_SESSION['csrf_token'] ?>',
                generate_api: '1', target_user_id: uid
            });
        }

        function removeApi(uid) {
            if(!confirm('WARNING: Are you sure you want to completely remove this user\'s API keys? Their API integrations will immediately break.')) return;
            createAndSubmitForm({
                csrf_token: '<?= $_SESSION['csrf_token'] ?>',
                remove_api: '1', target_user_id: uid
            });
        }

        function saveWhitelist(uid) {
            const ips = document.getElementById('ip_'+uid).value;
            createAndSubmitForm({
                csrf_token: '<?= $_SESSION['csrf_token'] ?>',
                update_whitelist: '1', target_user_id: uid, api_ip_whitelist: ips
            });
        }
    </script>
</body>
</html>