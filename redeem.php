<?php
// ==================== CONFIGURATION & SETUP ====================

// --- Database Credentials ---
define('DB_HOST', 'localhost');
define('DB_USER', 'jzstore1_game');
define('DB_PASSWORD', '28April2000*#');
define('DB_NAME', 'jzstore1_game');

// --- SmileOne API Credentials ---
define('SMILEONE_EMAIL', 'siamvela123@gmail.com');
define('SMILEONE_UID', '1208204');
define('SMILEONE_KEY', 'c47dec7188710249b341c5c60e9beb6e');
define('SMILEONE_PRODUCT_NAME', 'mobilelegends');
define('SMILEONE_PRODUCT_ID', '13'); 

// ==================== DEBUG MODE ====================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==================== PREREQUISITES CHECK ====================
if (!function_exists('mysqli_connect')) {
    die("❌ Fatal Error: The MySQLi extension is not installed or enabled.");
}
if (!function_exists('curl_init')) {
    die("❌ Fatal Error: The cURL extension is not installed or enabled.");
}

// ==================== SHARED API SIGNATURE FUNCTION ====================
function generateSign(array $params, string $key): string {
    ksort($params);
    $str = '';
    foreach ($params as $k => $v) {
        $str .= $k . '=' . $v . '&';
    }
    $stringToSign = $str . $key;
    return md5(md5($stringToSign));
}

// ==================== ROUTING: Handle Different Actions ====================
// AJAX: Get Username
if (isset($_POST['action']) && $_POST['action'] === 'get_username') {
    header('Content-Type: application/json');

    if (!isset($_POST['user_id'], $_POST['zone_id']) || empty(trim($_POST['user_id'])) || empty(trim($_POST['zone_id']))) {
        echo json_encode(["success" => false, "message" => "User ID and Zone ID are required."]);
        exit;
    }

    $userId = trim($_POST['user_id']);
    $zoneId = trim($_POST['zone_id']);

    $params = [
        'email'     => SMILEONE_EMAIL,
        'uid'       => SMILEONE_UID,
        'userid'    => $userId,
        'zoneid'    => $zoneId,
        'product'   => SMILEONE_PRODUCT_NAME,
        'productid' => SMILEONE_PRODUCT_ID,
        'time'      => time()
    ];

    $params['sign'] = generateSign($params, SMILEONE_KEY);

    $ch = curl_init('https://www.smile.one/smilecoin/api/getrole');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode(["success" => false, "message" => "API Connection Error: " . curl_error($ch)]);
        curl_close($ch);
        exit;
    }
    curl_close($ch);

    $data = json_decode($response, true);
    if ($data && isset($data['status']) && $data['status'] == 200 && isset($data['username'])) {
        echo json_encode([
            "success"  => true,
            "username" => $data['username']
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => $data['message'] ?? 'Player not found or invalid ID.'
        ]);
    }
    exit;
}

// ==================== SCRIPT LOGIC FOR FORM SUBMISSION ====================
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_code'])) {

    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if (!$conn) {
        die("❌ Database connection failed: " . htmlspecialchars(mysqli_connect_error()));
    }

    function placeSmileOrder($product_id, $user_id, $zone_id) {
        $params = [
            'email'     => SMILEONE_EMAIL,
            'uid'       => SMILEONE_UID,
            'userid'    => $user_id,
            'zoneid'    => $zone_id,
            'product'   => SMILEONE_PRODUCT_NAME,
            'productid' => $product_id,
            'time'      => time()
        ];
        $params['sign'] = generateSign($params, SMILEONE_KEY);
        $post_fields = http_build_query($params);

        $ch = curl_init("https://www.smile.one/smilecoin/api/createorder");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        $response = curl_exec($ch);

        if ($response === false) {
            return ['status' => 500, 'message' => "cURL Error: " . curl_error($ch)];
        }
        curl_close($ch);

        $data = json_decode($response, true);
        if (!$data) {
            return ['status' => 500, 'message' => "Invalid JSON from SmileOne: " . htmlspecialchars($response)];
        }
        return $data;
    }

    $redeem_code = strtoupper(trim($_POST['redeem_code'] ?? ''));
    $user_id = trim($_POST['user_id'] ?? '');
    $zone_id = trim($_POST['zone_id'] ?? '');

    if (empty($redeem_code) || empty($user_id) || empty($zone_id)) {
        $error = "Please fill in all required fields.";
    } else {
        $conn->begin_transaction();
        try {
            // Step 1: Find the code and lock the row
            $stmt = $conn->prepare("SELECT * FROM smileredeem WHERE code = ? AND status = 'unused' FOR UPDATE");
            $stmt->bind_param('s', $redeem_code);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $code_data = $result->fetch_assoc();
                $product_id = $code_data['product_id'];
                $product_name = $code_data['product_name'];

                // Step 2: Call the SmileOne API to place the order
                $orderResponse = placeSmileOrder($product_id, $user_id, $zone_id);

                // Step 3: Check API response and update DB
                if (isset($orderResponse['status']) && $orderResponse['status'] == 200) {
                    $smile_order_id = $orderResponse['order_id'] ?? 'N/A';

                    // Mark the code as used
                    $update_stmt = $conn->prepare("UPDATE smileredeem SET status = 'used', used_by = ?, used_at = NOW() WHERE id = ?");
                    $used_by_info = "$user_id ($zone_id)";
                    $update_stmt->bind_param('si', $used_by_info, $code_data['id']);
                    $update_stmt->execute();

                    // Log successful redemption
                    $history_stmt = $conn->prepare("INSERT INTO smileredeemhistory (redeem_code, userid, zoneid, order_id, product_id, product_name) VALUES (?, ?, ?, ?, ?, ?)");
                    $history_stmt->bind_param('ssssss', $redeem_code, $user_id, $zone_id, $smile_order_id, $product_id, $product_name);
                    $history_stmt->execute();

                    $conn->commit();
                    $msg = "Success! Top-up for " . $product_name . " is complete.";
                } else {
                    throw new Exception("API Error: " . ($orderResponse['message'] ?? 'An unknown error occurred.'));
                }
            } else {
                throw new Exception("Invalid or already used redeem code.");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        } finally {
            if (isset($conn)) {
                $conn->close();
            }
        }
    }
}

// ==================== FETCH REDEMPTION HISTORY ====================
$history_items = [];
try {
    $history_conn = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if ($history_conn) {
        $query = "SELECT userid, product_name, created_at FROM smileredeemhistory ORDER BY created_at DESC LIMIT 10";
        $result = mysqli_query($history_conn, $query);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $history_items[] = $row;
            }
        }
        mysqli_close($history_conn);
    }
} catch (Exception $e) {}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Redeem Code - JZ Store</title>
    <link rel="icon" type="image/png" href="https://jzstore.in/logo/jzstorelogo.jpg">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { 
                        themePink: '#08203E',
                        themeBlue: '#557C93',
                        themeGreen: '#80bf15',
                        themeDark: '#ffffff',
                        brand: { 500: '#ffffff', 600: '#f8fafc' } 
                    },
                    fontFamily: { poppins: ['Poppins', 'sans-serif'] },
                    animation: { 'slide-up': 'slideUp 0.3s ease-out forwards' },
                    keyframes: { slideUp: { '0%': { transform: 'translateY(100%)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } } }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Poppins', sans-serif; 
            background: hsla(213, 77%, 14%, 1);
            background: linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -moz-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -webkit-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            filter: progid: DXImageTransform.Microsoft.gradient( startColorstr="#08203E", endColorstr="#557C93", GradientType=1 );
            background-attachment: fixed; color: #ffffff; -webkit-tap-highlight-color: transparent; }
        .glass-panel { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .glass-nav { background: rgba(8, 32, 62, 0.8); backdrop-filter: blur(20px); border-top: 1px solid rgba(255,255,255,0.1); }
        .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #ffffff; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        body.modal-open { overflow: hidden; }
        .pb-safe { padding-bottom: env(safe-area-inset-bottom); }
    </style>
</head>
<body class="text-slate-800 pb-32 antialiased">

    <header class="fixed top-0 w-full z-40 bg-black/20 backdrop-blur-xl h-16 border-b border-white/10 shadow-sm">
        <div class="max-w-md mx-auto px-4 h-full flex items-center justify-between">
            <a href="index.php" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center border border-white/10 hover:bg-white/20 transition">
                <i class="fa-solid fa-arrow-left text-themeDark"></i>
            </a>
            <div class="font-bold text-lg text-white">Redeem Code</div>
            <div class="w-10"></div> </div>
    </header>

    <main class="max-w-md mx-auto pt-20 px-4 min-h-screen">

        <div class="flex flex-col items-center justify-center mb-6 mt-2">
            <div class="w-16 h-16 bg-white/10 rounded-full flex items-center justify-center text-white mb-2 shadow-sm border border-white/10">
                <i class="fa-solid fa-gift text-2xl"></i>
            </div>
            <h2 class="font-bold text-white">Voucher Redemption</h2>
            <p class="text-xs text-white/60">Enter your code to claim rewards</p>
        </div>

        <div class="bg-white/40 backdrop-blur-md rounded-2xl p-5 shadow-sm border border-white/50 mb-6">
            <form id="redeemForm" method="POST" action="">
                
                <div class="mb-4 relative">
                    <label class="text-[10px] uppercase font-bold text-white/60 ml-1 mb-1 block">Voucher Code</label>
                    <div class="relative">
                        <input type="password" id="redeem_code" name="redeem_code" 
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm font-semibold text-white focus:outline-none focus:border-white transition pr-10 placeholder:text-white/20" 
                            placeholder="XXXX-XXXX-XXXX" required>
                        <button type="button" id="togglePassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40 hover:text-white transition p-1">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 mb-6">
                    <div class="col-span-2">
                        <label class="text-[10px] uppercase font-bold text-white/60 ml-1 mb-1 block">User ID</label>
                        <input type="number" id="user_id" name="user_id" 
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm font-semibold text-white focus:outline-none focus:border-white transition placeholder:text-white/20" 
                            placeholder="12345678" required>
                    </div>
                    <div>
                        <label class="text-[10px] uppercase font-bold text-white/60 ml-1 mb-1 block">Zone ID</label>
                        <input type="number" id="zone_id" name="zone_id" 
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm font-semibold text-white focus:outline-none focus:border-white transition placeholder:text-white/20" 
                            placeholder="1234" required>
                    </div>
                </div>

                <button type="submit" id="redeemButton" class="w-full bg-white text-black py-3.5 rounded-xl font-bold shadow-lg shadow-white/5 active:scale-[0.98] transition flex items-center justify-center gap-2">
                    Redeem Now
                </button>
            </form>
        </div>

        <div class="bg-white/10 backdrop-blur-md rounded-2xl p-5 shadow-sm border border-white/10 mb-6">
            <h3 class="font-bold text-white mb-3 text-sm">How to Redeem</h3>
            <div class="text-xs text-white/60 leading-6 space-y-2">
                <div class="flex gap-2">
                    <span class="w-5 h-5 rounded-full bg-white/10 text-white flex items-center justify-center text-[10px] font-bold shrink-0 border border-white/10">1</span>
                    <p>Enter your purchased <b>Redeem Code</b> above.</p>
                </div>
                <div class="flex gap-2">
                    <span class="w-5 h-5 rounded-full bg-white/10 text-white flex items-center justify-center text-[10px] font-bold shrink-0 border border-white/10">2</span>
                    <p>Input your Mobile Legends <b>User ID</b> and <b>Zone ID</b>.</p>
                </div>
                <div class="flex gap-2">
                    <span class="w-5 h-5 rounded-full bg-white/10 text-white flex items-center justify-center text-[10px] font-bold shrink-0 border border-white/10">3</span>
                    <p>Click "Redeem Now", verify your username, and confirm.</p>
                </div>
                <div class="flex gap-2">
                    <span class="w-5 h-5 rounded-full bg-green-500/20 text-green-600 flex items-center justify-center text-[10px] font-bold shrink-0 border border-green-500/30"><i class="fa-solid fa-check"></i></span>
                    <p>Items are sent to your in-game mailbox instantly.</p>
                </div>
            </div>
        </div>

            <h3 class="font-bold text-white mb-3 px-1 flex items-center gap-2 text-sm">
                <span class="w-1 h-4 bg-white rounded-full"></span> Recent Redemptions
            </h3>
            
            <div class="flex flex-col gap-3">
                <?php if (empty($history_items)): ?>
                    <div class="bg-white rounded-xl p-6 text-center shadow-sm border border-slate-100">
                        <i class="fa-solid fa-clock-rotate-left text-slate-300 text-2xl mb-2"></i>
                        <p class="text-xs text-slate-400">No history found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($history_items as $item): ?>
                        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3 shadow-sm border border-white/10 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-green-500/10 flex items-center justify-center text-green-400">
                                    <i class="fa-solid fa-check-double"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-white"><?php echo htmlspecialchars($item['product_name']); ?></p>
                                    <p class="text-[10px] text-white/40">ID: <?php echo htmlspecialchars(substr($item['userid'], 0, 4) . '****'); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] font-medium text-white/40"><?php echo date('d M, h:i A', strtotime($item['created_at'])); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <nav id="bottom-nav" class="fixed bottom-0 w-full bg-[#08203E]/80 backdrop-blur-xl z-40 pb-safe transition-transform duration-300 border-t border-white/10">
        <div class="max-w-md mx-auto h-16 flex justify-around items-center px-2">
            <a href="https://wa.me/918730063275" target="_blank" class="nav-item flex flex-col items-center justify-center w-14 text-white/40 hover:text-white transition">
                <i class="fa-brands fa-whatsapp text-xl mb-1"></i>
                <span class="text-[9px] font-medium">Help</span>
            </a>
            <a href="orders.php" class="nav-item flex flex-col items-center justify-center w-14 text-white/40 hover:text-white transition">
                <i class="fa-solid fa-clock-rotate-left text-xl mb-1"></i>
                <span class="text-[9px] font-medium">Orders</span>
            </a>
            <a href="index.php" class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-black shadow-lg shadow-white/5 relative -top-5 border-4 border-[#08203E] transition hover:scale-105 active:scale-95">
                <i class="fa-solid fa-house text-lg"></i>
            </a>
            <a href="wallet_topup.php" class="nav-item flex flex-col items-center justify-center w-14 text-white/40 hover:text-white transition">
                <i class="fa-solid fa-wallet text-xl mb-1"></i>
                <span class="text-[9px] font-medium">Wallet</span>
            </a>
            <a href="profile.php" class="nav-item flex flex-col items-center justify-center w-14 text-white/40 hover:text-white transition">
                <i class="fa-solid fa-user text-xl mb-1"></i>
                <span class="text-[9px] font-medium">Account</span>
            </a>
        </div>
    </nav>

    <div id="confirmationModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[60] hidden flex items-end sm:items-center justify-center sm:p-4">
        <div class="bg-[#08203E] w-full max-w-sm rounded-t-2xl sm:rounded-2xl overflow-hidden shadow-2xl animate-slide-up border border-white/10">
            <div class="p-5 border-b border-white/10 flex justify-between items-center bg-white/5">
                <h3 class="font-bold text-white">Confirm Account</h3>
                <button id="cancelModalBtn" class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-white/60 hover:bg-white/20 transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="text-center mb-4">
                    <div class="w-16 h-16 bg-white/10 text-white rounded-full flex items-center justify-center mx-auto mb-2 text-2xl font-bold uppercase" id="modalInitials">?</div>
                    <p class="text-lg font-bold text-white" id="modalPlayerName">Loading...</p>
                    <p class="text-xs text-white/40">Is this you?</p>
                </div>

                <div class="space-y-3 text-sm text-white/70 bg-white/5 p-4 rounded-xl border border-white/10">
                    <div class="flex justify-between border-b border-white/10 pb-2">
                        <span>User ID</span>
                        <strong class="text-white" id="modalUserId">...</strong>
                    </div>
                    <div class="flex justify-between">
                        <span>Zone ID</span>
                        <strong class="text-white" id="modalZoneId">...</strong>
                    </div>
                </div>

                <button id="confirmRedeemBtn" class="w-full bg-white text-black py-3.5 rounded-xl font-bold shadow-lg shadow-white/5 active:scale-[0.98] transition">
                    Yes, Redeem Now
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('redeemForm');
            const redeemBtn = document.getElementById('redeemButton');
            const modal = document.getElementById('confirmationModal');
            const confirmBtn = document.getElementById('confirmRedeemBtn');
            const cancelBtn = document.getElementById('cancelModalBtn');
            const togglePw = document.getElementById('togglePassword');
            const redeemInput = document.getElementById('redeem_code');

            // --- PHP Messages Handling ---
            const phpSuccess = "<?php echo addslashes($msg); ?>";
            const phpError = "<?php echo addslashes($error); ?>";
            
            if(phpSuccess) showToast(phpSuccess, 'success');
            if(phpError) showToast(phpError, 'error');

            // --- Toggle Password Visibility ---
            togglePw.addEventListener('click', () => {
                const type = redeemInput.getAttribute('type') === 'password' ? 'text' : 'password';
                redeemInput.setAttribute('type', type);
                togglePw.innerHTML = type === 'password' ? '<i class="fa-regular fa-eye"></i>' : '<i class="fa-regular fa-eye-slash"></i>';
            });

            // --- Form Submission & Verification ---
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const userId = document.getElementById('user_id').value.trim();
                const zoneId = document.getElementById('zone_id').value.trim();
                const code = redeemInput.value.trim();

                if (!userId || !zoneId || !code) {
                    showToast('Please fill all fields', 'error');
                    return;
                }

                // UI Loading
                const originalText = redeemBtn.innerHTML;
                redeemBtn.disabled = true;
                redeemBtn.innerHTML = '<span class="spinner"></span> Verifying...';

                try {
                    const formData = new FormData();
                    formData.append('action', 'get_username');
                    formData.append('user_id', userId);
                    formData.append('zone_id', zoneId);

                    const res = await fetch('', { method: 'POST', body: formData });
                    const data = await res.json();

                    redeemBtn.disabled = false;
                    redeemBtn.innerHTML = originalText;

                    if (data.success) {
                        // Open Modal
                        document.getElementById('modalPlayerName').innerText = data.username;
                        document.getElementById('modalInitials').innerText = data.username.charAt(0);
                        document.getElementById('modalUserId').innerText = userId;
                        document.getElementById('modalZoneId').innerText = zoneId;
                        modal.classList.remove('hidden');
                    } else {
                        showToast(data.message || 'Player not found', 'error');
                    }
                } catch (err) {
                    console.error(err);
                    redeemBtn.disabled = false;
                    redeemBtn.innerHTML = originalText;
                    showToast('Connection error', 'error');
                }
            });

            // --- Modal Actions ---
            cancelBtn.addEventListener('click', () => modal.classList.add('hidden'));

            confirmBtn.addEventListener('click', () => {
                confirmBtn.innerHTML = '<span class="spinner"></span> Processing...';
                confirmBtn.disabled = true;
                
                // Submit form natively to trigger PHP processing
                form.submit();
            });
        });

        // --- Toast Notification System ---
        function showToast(message, type = 'info') {
            const colors = type === 'error' ? 'bg-red-500' : (type === 'success' ? 'bg-green-500' : 'bg-slate-800');
            const icon = type === 'error' ? '<i class="fa-solid fa-circle-exclamation mr-2"></i>' : '<i class="fa-solid fa-circle-check mr-2"></i>';
            
            const toast = document.createElement('div');
            toast.className = `fixed top-20 left-1/2 transform -translate-x-1/2 px-4 py-3 rounded-xl shadow-lg z-[70] text-xs font-bold text-white animate-slide-up ${colors} flex items-center min-w-[200px] justify-center`;
            toast.innerHTML = icon + message;
            
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>