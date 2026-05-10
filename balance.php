<?php
// balance.php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. AUTH CHECK (Admin Only)
$isAdmin = false;
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res && $res['role'] === 'admin') {
        $isAdmin = true;
    }
    $stmt->close();
}

if (!$isAdmin) {
    http_response_code(404);
    echo "<h1>404 Not Found</h1>";
    exit();
}

// Define the absolute path to your internal API
// Note: Using a full URL ensures file_get_contents handles it via HTTP
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$apiUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/api/smile_balance';

$response = @file_get_contents($apiUrl);
$data = json_decode($response, true);

// Map JSON back to existing variables for UI compatibility
$balanceGlobal = $data['br_balance'] ?? null;
$balancePH     = $data['ph_balance'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmileOne Dashboard</title>
    <style>
        :root {
            --primary: #f39c12;
            --brl-green: #27ae60;
            --ph-blue: #0038a8;
            --dark: #2d3436;
        }
        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .container {
            width: 90%;
            max-width: 400px;
        }
        .card {
            background: white;
            border-radius: 24px;
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
        }
        .logo { width: 50px; margin-bottom: 10px; }
        .header h2 { margin: 0; color: var(--dark); font-size: 20px; }

        .wallet-box {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 5px solid #ddd;
            transition: transform 0.2s;
        }
        .wallet-box:hover { transform: scale(1.02); }
        
        .brl-wallet { border-color: var(--brl-green); }
        .ph-wallet { border-color: var(--ph-blue); }

        .wallet-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #636e72;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .wallet-val {
            font-size: 28px;
            font-weight: 800;
            color: var(--dark);
            margin-top: 5px;
        }
        .currency-tag {
            font-size: 14px;
            font-weight: normal;
            color: #b2bec3;
        }

        .refresh-btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 12px;
            background: var(--dark);
            color: white;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        .refresh-btn:hover { opacity: 0.9; }
        
        .footer-info {
            text-align: center;
            font-size: 11px;
            color: #95a5a6;
            margin-top: 15px;
        }
        .error { color: #d63031; font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="header">
            <img src="https://cdn-icons-png.flaticon.com/512/616/616408.png" class="logo" alt="SmileOne Logo">
            <h2>SmileOne Dashboard</h2>
        </div>

        <div class="wallet-box brl-wallet">
            <div class="wallet-label">
                <span>BRL Points</span>
                <span>🇧🇷</span>
            </div>
            <div class="wallet-val">
                <?php if ($balanceGlobal !== null): ?>
                    <span class="currency-tag">R$</span> <?php echo number_format((float)$balanceGlobal, 2); ?>
                <?php else: ?>
                    <span class="error">API Error</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="wallet-box ph-wallet">
            <div class="wallet-label">
                <span>Php Points</span>
                <span>🇵🇭</span>
            </div>
            <div class="wallet-val">
                <?php if ($balancePH !== null): ?>
                    <span class="currency-tag">₱</span> <?php echo number_format((float)$balancePH, 2); ?>
                <?php else: ?>
                    <span class="error">API Error</span>
                <?php endif; ?>
            </div>
        </div>

        <form method="post">
            <button class="refresh-btn" type="submit">
                🔄 Sync All Wallets
            </button>
        </form>
    </div>
</div>

</body>
</html>