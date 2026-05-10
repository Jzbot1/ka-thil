<?php
// ==================== CONFIGURATION & SETUP ====================
include __DIR__ . '../../config.php'; 

// ==================== DEBUG MODE ====================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==================== PREREQUISITES CHECK ====================
if (!function_exists('mysqli_connect')) { die("❌ Fatal Error: The MySQLi extension is not installed or enabled."); }
if (!function_exists('curl_init')) { die("❌ Fatal Error: The cURL extension is not installed or enabled."); }
if (!function_exists('random_bytes')) { die("❌ Fatal Error: The OpenSSL extension is not enabled."); }

if (!isset($conn) || !$conn) {
    die("❌ Database connection variable not found. Check config.php.");
}

// ==================== SECURITY & SESSION CHECK (ADMIN ONLY) ====================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'secure' => true, 'samesite' => 'Strict']);
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$stmt_user_role = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt_user_role->bind_param("s", $_SESSION['user_id']);
$stmt_user_role->execute();
$result = $stmt_user_role->get_result();
$user = $result->fetch_assoc();
$stmt_user_role->close();

if (!$user || $user['role'] !== 'admin') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// ==================== API SIGNATURE FUNCTION ====================
function generateSign(array $params, string $key): string {
    ksort($params);
    $str = '';
    foreach ($params as $k => $v) { $str .= $k . '=' . $v . '&'; }
    return md5(md5($str . $key));
}

// ==================== SmileOne API FUNCTIONS ====================
function getSmileApiResponse(string $url, array $params, string $key) {
    $params['time'] = time();
    $sign = generateSign($params, $key);
    $post_fields = http_build_query(array_merge($params, ['sign' => $sign]));

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);

    if ($response === false) { return ['status' => 500, 'error' => "cURL Error: " . curl_error($ch)]; }
    curl_close($ch);

    $data = json_decode($response, true);
    if (!$data) { return ['status' => 500, 'error' => "Invalid JSON Response"]; }
    return $data;
}

function getSmileBalance(string $region_url, $email, $uid, $key) {
    $params = ['email' => $email, 'uid' => $uid];
    $data = getSmileApiResponse($region_url, $params, $key);
    return $data['smile_points'] ?? ($data['data']['smile_points'] ?? ($data['data']['points'] ?? 0));
}

function getSmileProducts(string $url, $email, $uid, $key) {
    $params = ['email' => $email, 'uid' => $uid, 'product' => 'mobilelegends'];
    return getSmileApiResponse($url, $params, $key);
}

// ==================== SCRIPT LOGIC ====================
$msg = '';
$error = '';

// Fetch Balances
$balanceBRL = getSmileBalance("https://www.smile.one/smilecoin/api/querypoints", SMILE_API_EMAIL, SMILE_API_UID, SMILE_API_KEY);
$balancePH = getSmileBalance("https://www.smile.one/ph/smilecoin/api/querypoints", SMILE_API_EMAIL, SMILE_API_UID, SMILE_API_KEY);

// Fetch Products
$apiDataBRL = getSmileProducts("https://www.smile.one/br/smilecoin/api/productlist", SMILE_API_EMAIL, SMILE_API_UID, SMILE_API_KEY);
$apiDataPH = getSmileProducts("https://www.smile.one/ph/smilecoin/api/productlist", SMILE_API_EMAIL, SMILE_API_UID, SMILE_API_KEY);

// --- UPDATE CUSTOM PRODUCT NAME ---
if (isset($_POST['update_mapping'])) {
    $p_id = $_POST['product_id'];
    $c_name = $_POST['custom_name'];
    $stmt = $conn->prepare("INSERT INTO managed_products (product_id, custom_name) VALUES (?, ?) ON DUPLICATE KEY UPDATE custom_name = ?");
    $stmt->bind_param("sss", $p_id, $c_name, $c_name);
    if($stmt->execute()) { $msg = "✅ Product mapping updated."; }
    $stmt->close();
}

// --- UPDATE EXISTING CODE NAME ---
if (isset($_POST['edit_existing_name'])) {
    $code_id = $_POST['code_id'];
    $new_name = $_POST['new_product_name'];
    $stmt = $conn->prepare("UPDATE smileredeem SET product_name = ? WHERE id = ?");
    $stmt->bind_param("si", $new_name, $code_id);
    if($stmt->execute()) { $msg = "✅ Code name updated."; }
    $stmt->close();
}

// Handle Create Code Request
if (isset($_POST['create_code'])) {
    $product_info = explode('|', $_POST['product_info'] ?? '');
    $product_id = $product_info[0] ?? '';
    $product_name = $product_info[1] ?? '';

    if (!empty($product_id)) {
        try {
            $code = strtoupper(bin2hex(random_bytes(4)));
            $stmt = $conn->prepare("INSERT INTO smileredeem (code, product_id, product_name) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $code, $product_id, $product_name);
            if (!$stmt->execute()) throw new Exception("SQL Error: " . $stmt->error);
            $msg = "✅ Redeem Code Created: <b>" . htmlspecialchars($code) . "</b>";
        } catch (Exception $e) { $error = "❌ " . htmlspecialchars($e->getMessage()); }
    }
}

// Handle Delete Code Request
if (isset($_POST['delete_code'])) {
    $code_to_delete = $_POST['code'] ?? '';
    $stmt = $conn->prepare("DELETE FROM smileredeem WHERE code = ?");
    $stmt->bind_param("s", $code_to_delete);
    if ($stmt->execute()) { $msg = "✅ Code Deleted."; }
    $stmt->close();
}

// Fetch custom names for mapping
$custom_names = [];
$managed_products_result = $conn->query("SELECT product_id, custom_name FROM managed_products");
if ($managed_products_result) {
    while ($row = $managed_products_result->fetch_assoc()) { $custom_names[$row['product_id']] = $row['custom_name']; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>JZ Store - Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #6366f1; --primary-dark: #4f46e5; --bg: #f8fafc; --card: #ffffff; --text-main: #0f172a; --text-muted: #64748b; --success: #10b981; --danger: #ef4444; --radius: 1rem; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text-main); margin: 0; padding: 0; }
        .container { max-width: 500px; margin: 0 auto; padding: 1rem; }
        header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; }
        .balance-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.5rem; }
        .balance-box { background: var(--card); padding: 1rem; border-radius: var(--radius); border-left: 4px solid var(--primary); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .balance-label { font-size: 0.65rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        .balance-amount { font-size: 1.1rem; font-weight: 800; margin-top: 4px; }
        .card { background: var(--card); border-radius: var(--radius); padding: 1.25rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.04); margin-bottom: 1.25rem; border: 1px solid #f1f5f9; }
        h2 { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px; }
        label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem; color: var(--text-muted); }
        select, input[type="text"] { width: 100%; padding: 0.8rem; border-radius: 0.75rem; border: 1.5px solid #e2e8f0; font-size: 0.95rem; margin-bottom: 1rem; font-family: inherit; }
        .btn { background: var(--primary); color: white; border: none; padding: 0.9rem; border-radius: 0.75rem; font-weight: 700; width: 100%; cursor: pointer; transition: 0.2s; }
        .btn-success { background: var(--success); }
        .alert { padding: 0.9rem; border-radius: 0.75rem; margin-bottom: 1rem; font-size: 0.85rem; font-weight: 600; border-left: 4px solid var(--success); background: #ecfdf5; color: #065f46; }
        .code-row { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid #f1f5f9; }
        .code-text { font-family: monospace; font-weight: 800; color: var(--primary-dark); font-size: 1rem; }
        .code-product { font-size: 0.75rem; color: var(--text-muted); }
        .del-btn { background: none; border: none; color: var(--danger); font-weight: 600; cursor: pointer; font-size: 0.75rem; }
        .edit-link { color: var(--primary); font-size: 0.7rem; text-decoration: underline; cursor: pointer; margin-left: 5px; }
    </style>
</head>
<body>

<div class="container">
    <header><h1>JZ Admin</h1></header>

    <?php if ($msg) echo "<div class='alert'>$msg</div>"; ?>

    <div class="balance-grid">
        <div class="balance-box">
            <div class="balance-label">🇧🇷 Brazil</div>
            <div class="balance-amount">R$ <?= number_format((float)$balanceBRL, 2) ?></div>
        </div>
        <div class="balance-box" style="border-left-color: #facc15;">
            <div class="balance-label">🇵🇭 Philip</div>
            <div class="balance-amount">₱ <?= number_format((float)$balancePH, 2) ?></div>
        </div>
    </div>

    <div class="card">
        <h2><span>🏷️</span> Product Name Settings</h2>
        <form method="POST">
            <label>Select API Product</label>
            <select name="product_id" required>
                <optgroup label="Brazil">
                    <?php foreach($apiDataBRL['data']['product'] ?? [] as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= $p['spu'] ?> (<?= $custom_names[$p['id']] ?? 'Default' ?>)</option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="Philippines">
                    <?php foreach($apiDataPH['data']['product'] ?? [] as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= $p['spu'] ?> (<?= $custom_names[$p['id']] ?? 'Default' ?>)</option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
            <label>Display Name</label>
            <input type="text" name="custom_name" placeholder="Enter new name..." required>
            <button type="submit" name="update_mapping" class="btn btn-success">Save Mapping</button>
        </form>
    </div>

    <div class="card">
        <h2><span>⚡</span> Create Redeem Code</h2>
        <form method="POST">
            <label>Choose Product</label>
            <select name="product_info" required>
                <?php 
                $regions = ['[BRL]' => $apiDataBRL, '[PH]' => $apiDataPH];
                foreach($regions as $prefix => $data): ?>
                    <optgroup label="<?= $prefix ?>">
                    <?php foreach($data['data']['product'] ?? [] as $p): 
                        $disp = $custom_names[$p['id']] ?? $p['spu']; ?>
                        <option value="<?= $p['id'] ?>|<?= $prefix ?> <?= $disp ?>"><?= $prefix ?> <?= $disp ?></option>
                    <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="create_code" class="btn">Generate New Code</button>
        </form>
    </div>

    <div class="card">
        <h2><span>📋</span> Active Codes</h2>
        <?php $codes = $conn->query("SELECT * FROM smileredeem ORDER BY id DESC LIMIT 15");
        while ($r = $codes->fetch_assoc()): ?>
            <div class="code-row">
                <div>
                    <span class="code-text"><?= $r['code'] ?></span><br>
                    <span class="code-product" id="name-<?= $r['id'] ?>"><?= $r['product_name'] ?></span>
                    <span class="edit-link" onclick="editName(<?= $r['id'] ?>, '<?= addslashes($r['product_name']) ?>')">Edit</span>
                </div>
                <form method="POST" onsubmit="return confirm('Delete?');">
                    <input type="hidden" name="code" value="<?= $r['code'] ?>">
                    <button type="submit" name="delete_code" class="del-btn">Delete</button>
                </form>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<script>
function editName(id, currentName) {
    let newName = prompt("Enter new product name for this specific code:", currentName);
    if (newName && newName !== currentName) {
        let form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="code_id" value="${id}">
                          <input type="hidden" name="new_product_name" value="${newName}">
                          <input type="hidden" name="edit_existing_name" value="1">`;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

</body>
</html>