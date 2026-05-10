<?php
require_once __DIR__ . '/includes/config.php';

// Fetch Store Settings
$setting = ['store_name' => 'JZ Store'];
$res_s = $conn->query("SELECT * FROM fav_setting LIMIT 1");
if ($res_s && $row_s = $res_s->fetch_assoc()) {
    foreach ($row_s as $k => $v)
        if (!empty($v))
            $setting[$k] = $v;
}

$base_url = BASE_URL . '/api/v1/';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Reseller API Documentation - <?= htmlspecialchars($setting['store_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Fira+Code:wght@400;600&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(177deg, #fbc2eb, #a6c1ee, #80bf15);
            background-attachment: fixed;
            color: #0f172a;
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        code,
        pre {
            font-family: 'Fira Code', monospace;
        }

        .endpoint-card {
            transition: all 0.3s ease;
        }

        .endpoint-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>

<body class="pb-32">
    <!-- HEADER -->
    <header class="fixed top-0 w-full z-50 bg-white/30 backdrop-blur-xl h-16 border-b border-white/30">
        <div class="max-w-4xl mx-auto px-5 h-full flex items-center justify-between">
            <a href="profile"
                class="w-10 h-10 rounded-xl bg-white/50 flex items-center justify-center border border-white/40 hover:bg-white/70 transition"><i
                    class="fa-solid fa-arrow-left text-themeDark text-sm"></i></a>
            <div class="font-black text-lg text-themeDark tracking-tight">API Documentation</div>
            <div class="w-10"></div>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 mt-24 space-y-8">

        <!-- INTRODUCTION -->
        <div class="glass-panel rounded-[2rem] p-8">
            <h1 class="text-3xl font-black text-themeDark mb-4">Reseller Integration API</h1>
            <p class="text-sm text-themeDark/70 font-medium mb-6 leading-relaxed">
                Welcome to the <?= htmlspecialchars($setting['store_name']) ?> API. You can use these endpoints to fully
                automate your game top-up business.
                Our API allows you to fetch real-time products, verify usernames, and deduct directly from your wallet
                balance to create orders instantly.
            </p>

            <div class="bg-blue-600/10 border border-blue-600/20 rounded-2xl p-6">
                <h3 class="text-xs font-black text-blue-800 uppercase tracking-widest mb-3"><i
                        class="fa-solid fa-lock mr-2"></i> Authentication</h3>
                <p class="text-sm text-themeDark/70 mb-4">
                    All API requests must include your <strong>Partner ID</strong> and <strong>Secret Key</strong>. You
                    can send these as POST/GET parameters, or securely via HTTP Headers.
                </p>
                <div class="bg-slate-900 rounded-xl p-4 text-xs text-blue-300 font-mono overflow-x-auto">
                    Partner-ID: PXXXXXXXXXX<br>
                    Secret-Key: your_16_byte_secret_key
                </div>
            </div>
        </div>

        <!-- ENDPOINTS -->
        <h2 class="text-xl font-black text-themeDark px-2">API Endpoints</h2>

        <!-- 1. Get Balance -->
        <div class="glass-panel rounded-[2rem] p-8 endpoint-card">
            <div class="flex items-center gap-3 mb-4">
                <span
                    class="px-3 py-1 bg-emerald-100 text-emerald-700 font-black text-xs rounded-lg uppercase tracking-wider">GET
                    / POST</span>
                <h3 class="text-lg font-black text-themeDark">Check Wallet Balance</h3>
            </div>
            <p class="text-sm text-themeDark/60 mb-4">Returns your current available wallet balance.</p>

            <div class="bg-white/50 rounded-xl p-4 mb-4 border border-white/50">
                <p class="text-xs font-bold text-themeDark/50 uppercase mb-1">Endpoint URL</p>
                <code class="text-sm font-bold text-blue-600"><?= $base_url ?>get_balance</code>
            </div>

            <p class="text-xs font-bold text-themeDark/50 uppercase mb-2">Example Response</p>
            <pre class="bg-slate-900 text-green-400 p-4 rounded-xl text-xs overflow-x-auto">
{
  "status": true,
  "message": "Balance fetched successfully",
  "data": {
    "email": "reseller@example.com",
    "balance": 1500.50
  }
}</pre>
        </div>

        <!-- 2. Get Products -->
        <div class="glass-panel rounded-[2rem] p-8 endpoint-card">
            <div class="flex items-center gap-3 mb-4">
                <span
                    class="px-3 py-1 bg-emerald-100 text-emerald-700 font-black text-xs rounded-lg uppercase tracking-wider">GET
                    / POST</span>
                <h3 class="text-lg font-black text-themeDark">Get Product List</h3>
            </div>
            <p class="text-sm text-themeDark/60 mb-4">Returns all active games, their items, and your specific reseller
                pricing.</p>

            <div class="bg-white/50 rounded-xl p-4 mb-4 border border-white/50">
                <code class="text-sm font-bold text-blue-600"><?= $base_url ?>get_product</code>
            </div>

            <pre class="bg-slate-900 text-green-400 p-4 rounded-xl text-xs overflow-x-auto">
{
  "status": true,
  "message": "Products fetched successfully",
  "data": [
    {
      "game": "Mobile Legends",
      "product_id": "ml_112_diamonds",
      "name": "112 Diamonds",
      "price": 145.00
    }
  ]
}</pre>
        </div>

        <!-- 3. Verify Username -->
        <div class="glass-panel rounded-[2rem] p-8 endpoint-card">
            <div class="flex items-center gap-3 mb-4">
                <span
                    class="px-3 py-1 bg-blue-100 text-blue-700 font-black text-xs rounded-lg uppercase tracking-wider">POST</span>
                <h3 class="text-lg font-black text-themeDark">Verify Game Username</h3>
            </div>
            <p class="text-sm text-themeDark/60 mb-4">Verify a player's Game ID and Zone ID before placing an order.</p>

            <div class="bg-white/50 rounded-xl p-4 mb-4 border border-white/50">
                <code class="text-sm font-bold text-blue-600"><?= $base_url ?>get_username</code>
            </div>

            <div class="mb-4">
                <p class="text-xs font-bold text-themeDark/50 uppercase mb-2">Required Parameters</p>
                <ul class="text-sm text-themeDark/70 space-y-1 list-disc pl-5">
                    <li><code>product_id</code>: The ID of the product.</li>
                    <li><code>game_user_id</code>: The player's User ID.</li>
                    <li><code>game_zone_id</code>: (Optional) The player's Zone/Server ID.</li>
                </ul>
            </div>

            <pre class="bg-slate-900 text-green-400 p-4 rounded-xl text-xs overflow-x-auto">
{
  "status": true,
  "message": "Username retrieved successfully",
  "data": {
    "username": "JhonDoeGaming",
    "game_user_id": "12345678",
    "game_zone_id": "1234"
  }
}</pre>
        </div>

        <!-- 4. Create Order -->
        <div class="glass-panel rounded-[2rem] p-8 endpoint-card relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-amber-500/10 rounded-full blur-3xl -mr-10 -mt-10"></div>
            <div class="flex items-center gap-3 mb-4">
                <span
                    class="px-3 py-1 bg-blue-100 text-blue-700 font-black text-xs rounded-lg uppercase tracking-wider">POST</span>
                <h3 class="text-lg font-black text-themeDark">Create Order</h3>
            </div>
            <p class="text-sm text-themeDark/60 mb-4">
                Deducts the product price from your wallet and instantly fulfills the top-up to the customer.
                <strong class="text-rose-600">This action is irreversible.</strong>
            </p>

            <div class="bg-white/50 rounded-xl p-4 mb-4 border border-white/50">
                <code class="text-sm font-bold text-blue-600"><?= $base_url ?>create_order</code>
            </div>

            <div class="mb-4">
                <p class="text-xs font-bold text-themeDark/50 uppercase mb-2">Required Parameters</p>
                <ul class="text-sm text-themeDark/70 space-y-1 list-disc pl-5">
                    <li><code>product_id</code>: The item identifier.</li>
                    <li><code>game_user_id</code>: Player's game ID.</li>
                    <li><code>game_zone_id</code>: Player's server ID.</li>
                    <li><code>partner_order_id</code>: (Optional) Your website's reference ID.</li>
                </ul>
            </div>

            <pre class="bg-slate-900 text-green-400 p-4 rounded-xl text-xs overflow-x-auto">
{
  "status": true,
  "message": "Order created successfully",
  "data": {
    "order_id": "API_ABC123XYZ",
    "partner_order_id": "YOUR_WEB_999",
    "status": "completed",
    "price_deducted": 145.00,
    "remaining_balance": 1355.50
  }
}</pre>
        </div>

        <!-- 5. Check Status -->
        <div class="glass-panel rounded-[2rem] p-8 endpoint-card mb-10">
            <div class="flex items-center gap-3 mb-4">
                <span
                    class="px-3 py-1 bg-emerald-100 text-emerald-700 font-black text-xs rounded-lg uppercase tracking-wider">GET
                    / POST</span>
                <h3 class="text-lg font-black text-themeDark">Check Order Status</h3>
            </div>

            <div class="bg-white/50 rounded-xl p-4 mb-4 border border-white/50">
                <code class="text-sm font-bold text-blue-600"><?= $base_url ?>status</code>
            </div>

            <div class="mb-4">
                <p class="text-xs font-bold text-themeDark/50 uppercase mb-2">Required Parameters</p>
                <ul class="text-sm text-themeDark/70 space-y-1 list-disc pl-5">
                    <li><code>order_id</code>: The System Order ID returned when you created the order.</li>
                </ul>
            </div>

            <pre class="bg-slate-900 text-green-400 p-4 rounded-xl text-xs overflow-x-auto">
{
  "status": true,
  "message": "Order status retrieved",
  "data": {
    "order_id": "API_ABC123XYZ",
    "status": "completed",
    "product": "112 Diamonds",
    "price": 145.00,
    "date": "2026-05-10 14:30:00"
  }
}</pre>
        </div>

        <!-- ═══ SMM SERVICES SECTION ═══════════════════════════════════════════ -->
        <div class="glass-panel rounded-[2rem] p-8 relative overflow-hidden"
            style="background:rgba(139,92,246,.08);border:1px solid rgba(139,92,246,.2)">
            <div class="absolute top-0 right-0 w-40 h-40 bg-purple-400/10 rounded-full blur-3xl -mr-10 -mt-10"></div>
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 rounded-2xl bg-purple-100 flex items-center justify-center text-xl">🚀</div>
                <h2 class="text-2xl font-black text-themeDark">SMM Services API</h2>
            </div>
            <p class="text-sm text-themeDark/60 mb-0">
                Place social media marketing orders (followers, likes, views, etc.) directly via API. Charged from your
                wallet balance. Orders sync automatically every 10 minutes via cron.
            </p>
        </div>

        <!-- SMM 1: Get Services -->
        <div class="glass-panel rounded-[2rem] p-8 endpoint-card">
            <div class="flex items-center gap-3 mb-4">
                <span
                    class="px-3 py-1 bg-emerald-100 text-emerald-700 font-black text-xs rounded-lg uppercase tracking-wider">GET
                    / POST</span>
                <h3 class="text-lg font-black text-themeDark">Get SMM Services</h3>
            </div>
            <p class="text-sm text-themeDark/60 mb-4">Returns all active SMM services with your selling price per 1000
                units.</p>

            <div class="bg-white/50 rounded-xl p-4 mb-4 border border-white/50">
                <p class="text-xs font-bold text-themeDark/50 uppercase mb-1">Endpoint URL</p>
                <code class="text-sm font-bold text-blue-600"><?= $base_url ?>smm_services</code>
            </div>

            <div class="mb-4">
                <p class="text-xs font-bold text-themeDark/50 uppercase mb-2">Optional Parameters</p>
                <ul class="text-sm text-themeDark/70 space-y-1 list-disc pl-5">
                    <li><code>category</code>: Filter by category name (e.g. "Instagram", "YouTube").</li>
                </ul>
            </div>

            <pre class="bg-slate-900 text-green-400 p-4 rounded-xl text-xs overflow-x-auto">
{
  "status": true,
  "message": "156 services found.",
  "data": {
    "categories": ["Instagram", "YouTube", "TikTok"],
    "count": 156,
    "services": [
      {
        "service_id": 12,
        "provider_id": 1001,
        "category": "Instagram",
        "name": "Instagram Followers [Real]",
        "rate": 85.00,
        "min_order": 100,
        "max_order": 50000,
        "type": "Default"
      }
    ]
  }
}</pre>
        </div>

        <!-- SMM 2: Place Order -->
        <div class="glass-panel rounded-[2rem] p-8 endpoint-card relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-purple-500/10 rounded-full blur-3xl -mr-10 -mt-10"></div>
            <div class="flex items-center gap-3 mb-4">
                <span
                    class="px-3 py-1 bg-blue-100 text-blue-700 font-black text-xs rounded-lg uppercase tracking-wider">POST</span>
                <h3 class="text-lg font-black text-themeDark">Place SMM Order</h3>
            </div>
            <p class="text-sm text-themeDark/60 mb-4">
                Deducts cost from your wallet and queues the SMM order. Order is placed with the provider on next cron
                cycle (≤10 min).
                <strong class="text-rose-600">Wallet deduction is immediate and irreversible.</strong>
            </p>

            <div class="bg-white/50 rounded-xl p-4 mb-4 border border-white/50">
                <code class="text-sm font-bold text-blue-600"><?= $base_url ?>smm_order</code>
            </div>

            <div class="mb-4">
                <p class="text-xs font-bold text-themeDark/50 uppercase mb-2">Required Parameters</p>
                <ul class="text-sm text-themeDark/70 space-y-1 list-disc pl-5">
                    <li><code>service_id</code>: ID from <code>smm_services</code> endpoint.</li>
                    <li><code>link</code>: Full target URL (post, channel, profile, etc.).</li>
                    <li><code>quantity</code>: Number of units (within service min/max).</li>
                </ul>
                <p class="text-xs font-bold text-themeDark/50 uppercase mb-2 mt-3">Optional Parameters</p>
                <ul class="text-sm text-themeDark/70 space-y-1 list-disc pl-5">
                    <li><code>partner_order_id</code>: Your own reference ID.</li>
                    <li><code>runs</code>: Number of drip-feed runs.</li>
                    <li><code>interval</code>: Minutes between drip-feed runs.</li>
                </ul>
            </div>

            <pre class="bg-slate-900 text-green-400 p-4 rounded-xl text-xs overflow-x-auto">
{
  "status": true,
  "message": "SMM order queued successfully.",
  "data": {
    "order_ref": "RSMM_A1B2C3D4E5",
    "partner_order_id": "MY_REF_001",
    "service": "Instagram Followers [Real]",
    "quantity": 1000,
    "status": "pending",
    "price_deducted": 85.00,
    "remaining_balance": 1415.00,
    "note": "Order will be placed with provider on next cron cycle (every 10 min)."
  }
}</pre>
        </div>

        <!-- SMM 3: Check Status -->
        <div class="glass-panel rounded-[2rem] p-8 endpoint-card mb-10">
            <div class="flex items-center gap-3 mb-4">
                <span
                    class="px-3 py-1 bg-emerald-100 text-emerald-700 font-black text-xs rounded-lg uppercase tracking-wider">GET
                    / POST</span>
                <h3 class="text-lg font-black text-themeDark">Check SMM Order Status</h3>
            </div>
            <p class="text-sm text-themeDark/60 mb-4">Check real-time status of one or multiple SMM orders.</p>

            <div class="bg-white/50 rounded-xl p-4 mb-4 border border-white/50">
                <code class="text-sm font-bold text-blue-600"><?= $base_url ?>smm_status</code>
            </div>

            <div class="mb-4">
                <p class="text-xs font-bold text-themeDark/50 uppercase mb-2">Parameters (use one)</p>
                <ul class="text-sm text-themeDark/70 space-y-1 list-disc pl-5">
                    <li><code>order_ref</code>: Single order reference (e.g. <code>RSMM_A1B2C3D4E5</code>).</li>
                    <li><code>order_refs</code>: Comma-separated refs for batch check (max 100).</li>
                </ul>
            </div>

            <div class="grid grid-cols-2 gap-2 text-xs mb-4">
                <?php foreach (['pending' => ['Queued, not yet sent', 'bg-yellow-100 text-yellow-700'], 'processing' => ['Sent to provider, running', 'bg-blue-100 text-blue-700'], 'completed' => ['Fully delivered', 'bg-green-100 text-green-700'], 'partial' => ['Partial delivery, stopped', 'bg-orange-100 text-orange-700'], 'canceled' => ['Canceled by provider', 'bg-gray-100 text-gray-600'], 'failed' => ['Failed to place/deliver', 'bg-red-100 text-red-700']] as $st => [$desc, $cls]): ?>
                    <div class="bg-white/40 rounded-xl p-2 flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-lg font-black text-[9px] <?= $cls ?>"><?= $st ?></span>
                        <span class="text-themeDark/60"><?= $desc ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <pre class="bg-slate-900 text-green-400 p-4 rounded-xl text-xs overflow-x-auto">
{
  "status": true,
  "message": "Order status: processing",
  "data": {
    "order_ref": "RSMM_A1B2C3D4E5",
    "smm_order_id": 98765,
    "service": "Instagram Followers [Real]",
    "quantity": 1000,
    "status": "processing",
    "remains": 650,
    "start_count": 1200,
    "charge": 0.0850,
    "price_paid": 85.00,
    "created_at": "2026-05-10 14:30:00",
    "last_synced": "2026-05-10 14:40:00"
  }
}</pre>
        </div>

    </main>

    <?php include 'footer.php'; ?>
</body>

</html>