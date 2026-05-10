<?php
require_once __DIR__ . '/strict_admin.php';

if (!isset($conn) || !$conn) {
    die("Database connection error: Make sure \$conn is defined in config.php");
}

// Handle AJAX Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');
    $db_id = mysqli_real_escape_string($conn, $_POST['db_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $update_query = "UPDATE orders SET status = '$new_status' WHERE id = '$db_id'";
    if (mysqli_query($conn, $update_query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
    exit;
}

// Fetch Store Settings
$setting = ['store_name' => 'JZ Store'];
$res_s = $conn->query("SELECT * FROM fav_setting LIMIT 1");
if($res_s && $row_s = $res_s->fetch_assoc()) {
    foreach($row_s as $k => $v) if(!empty($v)) $setting[$k] = $v;
}

// --- 2. Search & Data Fetching ---
$search_query = "";
$where_clause = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = mysqli_real_escape_string($conn, $_GET['search']);
    $where_clause = "WHERE game_user_id LIKE '%$search_query%' OR order_id LIKE '%$search_query%' OR product_name LIKE '%$search_query%'";
}

$all_orders = [];
$query = "SELECT id, order_id, game_user_id, game_zone_id, product_name, price, status, created_at, email 
          FROM orders $where_clause 
          ORDER BY id DESC LIMIT 100";
$result = mysqli_query($conn, $query);

while ($row = mysqli_fetch_assoc($result)) {
    $all_orders[] = [
        'db_id'    => $row['id'],
        'order_id' => $row['order_id'],
        'user_id'  => $row['game_user_id'],
        'zone_id'  => $row['game_zone_id'],
        'spu'      => $row['product_name'],
        'price'    => $row['price'],
        'status'   => strtolower($row['status']),
        'email'    => $row['email'],
        'date'     => date('M j, g:i A', strtotime($row['created_at'])),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Order Management - <?= htmlspecialchars($setting['store_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: linear-gradient(177deg, #fbc2eb, #a6c1ee, #80bf15); background-attachment: fixed; color: #0f172a; }
        .glass-panel { background: rgba(255, 255, 255, 0.4); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.3); }
        .order-card { transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .order-card:active { transform: scale(0.97); }
        .status-badge { font-size: 8px; font-weight: 800; text-transform: uppercase; padding: 2px 8px; border-radius: 99px; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .status-processing { background: #dbeafe; color: #1e40af; }
    </style>
</head>
<body class="pb-32">
    <!-- HEADER -->
    <header class="fixed top-0 w-full z-50 bg-white/20 backdrop-blur-xl h-16 border-b border-white/20">
        <div class="max-w-md mx-auto px-5 h-full flex items-center justify-between">
            <a href="../profile" class="w-10 h-10 rounded-xl bg-white/40 flex items-center justify-center border border-white/30"><i class="fa-solid fa-arrow-left text-themeDark text-sm"></i></a>
            <div class="font-bold text-lg text-themeDark">Order Manager</div>
            <div class="w-10"></div>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 mt-20">
        
        <!-- SEARCH BAR -->
        <form method="GET" class="mb-6">
            <div class="relative">
                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search User ID or Order ID..." 
                       class="w-full glass-panel rounded-2xl py-4 pl-12 pr-4 text-sm font-semibold outline-none focus:ring-2 ring-blue-500/20 transition-all border border-white/50">
                <i class="fa-solid fa-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-themeDark/30"></i>
                <?php if(!empty($search_query)): ?>
                    <a href="admin_order" class="absolute right-4 top-1/2 -translate-y-1/2 text-rose-500"><i class="fa-solid fa-circle-xmark"></i></a>
                <?php endif; ?>
            </div>
        </form>

        <!-- ORDER LIST -->
        <div class="space-y-3">
            <?php if(empty($all_orders)): ?>
                <div class="py-20 text-center glass-panel rounded-[2rem]">
                    <i class="fa-solid fa-box-open text-4xl text-themeDark/10 mb-4"></i>
                    <p class="text-sm font-bold text-themeDark/40 uppercase tracking-widest">No orders found</p>
                </div>
            <?php endif; ?>

            <?php foreach($all_orders as $o): 
                $status_class = 'status-' . $o['status'];
            ?>
                <div onclick='openModal(<?= json_encode($o) ?>)' class="glass-panel p-4 rounded-[2rem] order-card cursor-pointer border border-white/40 hover:bg-white/50 transition-colors">
                    <div class="flex justify-between items-start">
                        <div class="flex gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-white/40 flex items-center justify-center text-themeDark shadow-sm">
                                <i class="fa-solid fa-receipt text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-themeDark truncate w-40"><?= htmlspecialchars($o['spu']) ?></h3>
                                <p class="text-[10px] font-bold text-themeDark/40 uppercase tracking-tighter">UID: <?= htmlspecialchars($o['user_id']) ?></p>
                                <p class="text-[9px] font-medium text-themeDark/60 mt-0.5"><?= $o['date'] ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-black text-themeDark mb-1">₹<?= number_format($o['price'], 0) ?></p>
                            <span class="status-badge <?= $status_class ?>"><?= $o['status'] ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- PROCESS MODAL -->
    <div id="orderModal" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-themeDark/40 backdrop-blur-sm" onclick="closeModal()"></div>
        <div class="absolute bottom-0 left-0 w-full glass-panel rounded-t-[3rem] p-8 pb-12 animate-slide-up border-t border-white/50 max-w-md mx-auto left-1/2 -translate-x-1/2">
            <div class="w-12 h-1.5 bg-themeDark/10 rounded-full mx-auto mb-6"></div>
            
            <h2 class="text-xl font-black text-themeDark mb-2">Order Details</h2>
            <p id="modalOrderId" class="text-[10px] font-bold text-themeDark/40 uppercase tracking-widest mb-6"></p>

            <div class="space-y-4 mb-8">
                <div class="flex justify-between items-center p-3 bg-white/40 rounded-2xl border border-white/30">
                    <span class="text-[10px] font-bold text-themeDark/50 uppercase">User ID</span>
                    <span id="modalUserId" class="text-xs font-black text-themeDark select-all"></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-white/40 rounded-2xl border border-white/30">
                    <span class="text-[10px] font-bold text-themeDark/50 uppercase">Zone ID</span>
                    <span id="modalZoneId" class="text-xs font-black text-themeDark select-all"></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-white/40 rounded-2xl border border-white/30">
                    <span class="text-[10px] font-bold text-themeDark/50 uppercase">Contact</span>
                    <span id="modalEmail" class="text-xs font-black text-themeDark"></span>
                </div>
            </div>

            <div class="mb-8">
                <label class="text-[10px] font-bold text-themeDark/40 uppercase tracking-widest block mb-2 px-1">Update Status</label>
                <div class="relative">
                    <select id="statusSelect" class="w-full glass-panel rounded-2xl py-4 px-4 text-sm font-black text-themeDark appearance-none outline-none border border-white/50">
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                    </select>
                    <i class="fa-solid fa-chevron-down absolute right-5 top-1/2 -translate-y-1/2 text-themeDark/30 pointer-events-none"></i>
                </div>
            </div>

            <button id="updateBtn" class="w-full py-4 rounded-2xl bg-themeDark text-white font-black text-sm shadow-xl shadow-themeDark/20 active:scale-95 transition-all">
                SAVE CHANGES
            </button>
        </div>
    </div>

    <script>
        let activeId = null;

        function openModal(data) {
            activeId = data.db_id;
            document.getElementById('modalOrderId').innerText = '#' + data.order_id;
            document.getElementById('modalUserId').innerText = data.user_id;
            document.getElementById('modalZoneId').innerText = data.zone_id || 'N/A';
            document.getElementById('modalEmail').innerText = data.email || 'N/A';
            document.getElementById('statusSelect').value = data.status;
            
            document.getElementById('orderModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            document.getElementById('orderModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        document.getElementById('updateBtn').onclick = async function() {
            const btn = this;
            const newStatus = document.getElementById('statusSelect').value;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> UPDATING...';

            const fd = new FormData();
            fd.append('action', 'update_status');
            fd.append('db_id', activeId);
            fd.append('status', newStatus);

            try {
                const res = await fetch(window.location.href, { method: 'POST', body: fd });
                const data = await res.json();
                if(data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (e) {
                alert('Connection error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'SAVE CHANGES';
            }
        };
    </script>

    <style>
        @keyframes slide-up {
            from { transform: translate(-50%, 100%); }
            to { transform: translate(-50%, 0); }
        }
        .animate-slide-up {
            animation: slide-up 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
    </style>
</body>
</html>