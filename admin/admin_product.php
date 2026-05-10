<?php
require_once __DIR__ . '/strict_admin.php';


// CURRENCY SETTINGS (Using global constants from config.php)
if (!defined('BRL_TO_INR_RATE')) define('BRL_TO_INR_RATE', BRL_TO_INR); 

// --- DIRECTORY SETTINGS ---
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/'); 
define('DISPLAY_PATH', 'uploads/');

// --- INTERNAL API LOGIC ---
$action = $_REQUEST['action'] ?? '';

if (!empty($action)) {
    header('Content-Type: application/json');
    
    $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    try {
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
        $input = json_decode(file_get_contents('php://input'), true);

        switch ($action) {
            case 'getCategories':
                $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
                echo json_encode(['success' => true, 'categories' => $stmt->fetchAll()]);
                break;

            case 'getGames':
                $stmt = $pdo->query("SELECT id, title FROM games ORDER BY title ASC");
                echo json_encode(['success' => true, 'games' => $stmt->fetchAll()]);
                break;

            case 'getProducts':
                $stmt = $pdo->query("SELECT d.*, c.name as category_name, g.title as game_title 
                                    FROM diamonds d 
                                    LEFT JOIN categories c ON d.category_id = c.id 
                                    LEFT JOIN games g ON d.game_id = g.id
                                    ORDER BY c.name ASC, d.last_updated DESC");
                echo json_encode(['success' => true, 'products' => $stmt->fetchAll()]);
                break;

            case 'saveProduct':
                $origPriceBRL = !empty($input['original_price']) ? floatval($input['original_price']) : 0;
                $sellPriceINR = !empty($input['price']) ? floatval($input['price']) : null;
                $categoryId = !empty($input['category_id']) ? $input['category_id'] : null;
                $gameId = !empty($input['game_id']) ? $input['game_id'] : null;
                $region = !empty($input['region']) ? $input['region'] : 'BR';
                $smileoneGame = $input['smileone_game'] ?? '';

                if (($sellPriceINR === null || $sellPriceINR == 0) && $origPriceBRL > 0) {
                    $costInINR = floatval($origPriceBRL) * BRL_TO_INR_RATE;
                    $sellPriceINR = $costInINR * 1.15; 
                    $originalPriceToStore = $costInINR; 
                } else {
                    $originalPriceToStore = $origPriceBRL; 
                }

                $isFlashSale = !empty($input['is_flash_sale']) ? 1 : 0;
                $flashPrice = !empty($input['flash_price']) ? floatval($input['flash_price']) : 0.00;
                $flashSold = !empty($input['flash_sold_percent']) ? intval($input['flash_sold_percent']) : 0;

                $sql = "INSERT INTO diamonds (product_id, game_id, category_id, region, smileone_game, spu, price, reseller_price, original_price, image_url, is_flash_sale, flash_price, flash_sold_percent) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            game_id = VALUES(game_id),
                            category_id = VALUES(category_id),
                            region = VALUES(region),
                            smileone_game = VALUES(smileone_game),
                            spu = VALUES(spu),
                            price = VALUES(price),
                            reseller_price = VALUES(reseller_price),
                            original_price = VALUES(original_price),
                            image_url = VALUES(image_url),
                            is_flash_sale = VALUES(is_flash_sale),
                            flash_price = VALUES(flash_price),
                            flash_sold_percent = VALUES(flash_sold_percent)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $input['product_id'],
                    $gameId,
                    $categoryId,
                    $region,
                    $smileoneGame,
                    $input['spu'],
                    $sellPriceINR,
                    !empty($input['reseller_price']) ? floatval($input['reseller_price']) : ($sellPriceINR * 0.95),
                    $originalPriceToStore,
                    $input['image_url'] ?? null,
                    $isFlashSale,
                    $flashPrice,
                    $flashSold
                ]);
                echo json_encode(['success' => true]);
                break;

            case 'deleteProduct':
                $stmt = $pdo->prepare("DELETE FROM diamonds WHERE product_id = ?");
                $stmt->execute([$input['product_id']]);
                echo json_encode(['success' => true]);
                break;

            case 'uploadImage': 
                if (isset($_FILES['image'])) {
                    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
                    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $fileName = uniqid() . '.' . $ext;
                    $targetPath = UPLOAD_DIR . $fileName;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                        echo json_encode(['success' => true, 'filePath' => DISPLAY_PATH . $fileName]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Upload failed']);
                    }
                }
                break;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Inventory Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap');
        body { font-family: 'Outfit', sans-serif; background-color: #F1F5F9; }
        .glass { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); }
        .modal-sheet { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); transform: translateY(100%); }
        .modal-sheet.active { transform: translateY(0); }
        .product-card { transition: all 0.2s; }
        .product-card:active { transform: scale(0.97); opacity: 0.8; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>
</head>
<body class="pb-20">

    <header class="sticky top-0 z-40 glass border-b border-white/20 px-4 py-4">
        <div class="max-w-md mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-xl font-800 text-slate-900 tracking-tight">Inventory</h1>
                <p class="text-[10px] font-bold text-blue-600 uppercase tracking-widest">Management</p>
            </div>
            <div class="flex gap-2">
                <select id="regionSelect" class="bg-white border border-slate-200 text-[10px] font-bold rounded-xl px-2 outline-none">
                    <option value="BR">BRAZIL (BR)</option>
                    <option value="PH">PHILIPPINES (PH)</option>
                </select>
                <button id="fetchApiBtn" class="bg-indigo-600 text-white px-4 py-2 rounded-2xl text-[11px] font-bold shadow-lg flex items-center gap-2">
                    <i class="fa-solid fa-cloud-arrow-down"></i> SYNC
                </button>
            </div>
        </div>
    </header>

    <main class="max-w-md mx-auto p-4 space-y-4">
        <div class="relative group">
            <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" id="searchInput" placeholder="Search diamonds..." 
                   class="w-full pl-12 pr-4 py-4 rounded-3xl border-none shadow-sm outline-none font-medium text-slate-600">
        </div>

        <div id="productList" class="space-y-6">
            <div class="animate-pulse flex flex-col items-center py-20 text-slate-300">
                <i class="fa-solid fa-circle-notch animate-spin text-3xl mb-4"></i>
                <p class="text-xs font-bold uppercase tracking-widest">Loading Catalog...</p>
            </div>
        </div>
    </main>

    <button id="fabAdd" class="fixed bottom-6 right-6 w-14 h-14 bg-slate-900 text-white rounded-2xl shadow-2xl flex items-center justify-center text-xl z-30">
        <i class="fa-solid fa-plus"></i>
    </button>

    <!-- Product Modal -->
    <div id="productModal" class="fixed inset-0 z-[50] hidden">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" onclick="toggleModal('productModal', false)"></div>
        <div class="absolute inset-x-0 bottom-0 bg-white rounded-t-[32px] modal-sheet p-6 max-h-[95vh] overflow-y-auto no-scrollbar">
            <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-6"></div>
            <form id="productForm" class="space-y-6">
                <input type="hidden" id="image_url" name="image_url">
                <div class="flex flex-col items-center mb-4">
                    <div class="relative group">
                        <img id="imagePreview" src="https://placehold.co/100" class="w-24 h-24 rounded-3xl object-cover border-4 border-slate-50 shadow-md">
                        <label class="absolute -bottom-2 -right-2 bg-blue-600 text-white p-2 rounded-xl shadow-lg cursor-pointer">
                            <i class="fa-solid fa-camera text-xs"></i>
                            <input type="file" id="imageUploadInput" accept="image/*" class="hidden">
                        </label>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-[10px] font-bold text-slate-400 uppercase ml-2">Region</label>
                            <select id="region" name="region" class="w-full mt-1 px-4 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl font-semibold outline-none">
                                <option value="BR">BR (Brazil)</option>
                                <option value="PH">PH (Philippines)</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-indigo-600 uppercase ml-2">SmileOne Product Key</label>
                            <input type="text" id="smileone_game" name="smileone_game" placeholder="e.g. mobilelegends" class="w-full mt-1 px-4 py-3.5 bg-indigo-50 border border-indigo-100 rounded-2xl font-bold outline-none">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                         <div>
                            <label class="text-[10px] font-bold text-slate-400 uppercase ml-2">Game Title (UI)</label>
                            <select id="game_id" name="game_id" class="w-full mt-1 px-4 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl font-semibold outline-none">
                                <option value="">-- No Game --</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-slate-400 uppercase ml-2">Category</label>
                            <select id="category_id" name="category_id" class="w-full mt-1 px-4 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl font-semibold outline-none"></select>
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 uppercase ml-2">Product ID (Unique)</label>
                        <input type="text" id="product_id" name="product_id" class="w-full mt-1 px-4 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl font-semibold outline-none" required>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-slate-400 uppercase ml-2">Display Name (SPU)</label>
                        <input type="text" id="spu" name="spu" class="w-full mt-1 px-4 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl font-semibold outline-none" required>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="text-[10px] font-bold text-slate-400 uppercase ml-2">Base Cost</label>
                            <input type="number" step="0.01" id="original_price" name="original_price" class="w-full mt-1 px-4 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl font-semibold outline-none">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-slate-400 uppercase ml-2">Sale (INR)</label>
                            <input type="number" step="0.01" id="price" name="price" class="w-full mt-1 px-4 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl font-semibold outline-none" required>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-indigo-600 uppercase ml-2">Reseller</label>
                            <input type="number" step="0.01" id="reseller_price" name="reseller_price" class="w-full mt-1 px-4 py-3.5 bg-indigo-50 border border-indigo-100 rounded-2xl font-bold outline-none">
                        </div>
                    </div>

                    <!-- FLASH SALE FIELDS -->
                    <div class="bg-orange-50 p-4 rounded-[28px] border border-orange-100 space-y-4">
                        <div class="flex items-center justify-between px-2">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-bolt-lightning text-orange-500"></i>
                                <span class="text-[11px] font-black text-orange-900 uppercase">Flash Sale Mode</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="is_flash_sale" name="is_flash_sale" value="1" class="sr-only peer">
                                <div class="w-11 h-6 bg-orange-200 rounded-full peer peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                            </label>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-[9px] font-bold text-orange-600 uppercase ml-2 tracking-wider">Flash Price</label>
                                <input type="number" step="0.01" id="flash_price" name="flash_price" placeholder="0.00" class="w-full mt-1 px-4 py-3 bg-white border border-orange-200 rounded-2xl font-bold text-orange-900 outline-none focus:ring-2 focus:ring-orange-500">
                            </div>
                            <div>
                                <label class="text-[9px] font-bold text-orange-600 uppercase ml-2 tracking-wider">Sold % (Fake)</label>
                                <input type="number" id="flash_sold_percent" name="flash_sold_percent" placeholder="0" class="w-full mt-1 px-4 py-3 bg-white border border-orange-200 rounded-2xl font-bold text-orange-900 outline-none focus:ring-2 focus:ring-orange-500">
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="w-full bg-slate-900 text-white font-bold py-4 rounded-2xl shadow-xl active:scale-95 transition-all">SAVE PRODUCT</button>
            </form>
        </div>
    </div>

    <!-- API Sync Modal -->
    <div id="apiModal" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-slate-900/60" onclick="toggleModal('apiModal', false)"></div>
        <div class="absolute inset-x-0 bottom-0 top-20 bg-slate-50 rounded-t-[32px] modal-sheet flex flex-col overflow-hidden">
            <div class="p-6 bg-white border-b flex flex-col gap-4">
                <div class="flex justify-between items-center">
                    <h3 class="font-800 text-lg">Smile.One API Sync</h3>
                    <button onclick="toggleModal('apiModal', false)" class="text-slate-400 text-2xl">&times;</button>
                </div>
                <div class="flex gap-2">
                    <input type="text" id="apiGameKey" placeholder="Product Key (e.g. mobilelegends)" class="flex-1 px-4 py-2 bg-slate-100 rounded-xl text-sm font-bold outline-none border border-slate-200">
                    <button id="runSyncBtn" class="bg-indigo-600 text-white px-6 rounded-xl text-[10px] font-black uppercase">Fetch</button>
                </div>
            </div>
            <div id="apiList" class="flex-1 overflow-y-auto p-4 space-y-3 no-scrollbar">
                <div class="py-20 text-center text-slate-400">
                    <i class="fa-solid fa-keyboard text-3xl mb-2"></i>
                    <p class="text-[10px] font-bold uppercase">Enter product key and click Fetch</p>
                </div>
            </div>
        </div>
    </div>

    <div id="toast" class="fixed bottom-24 left-1/2 -translate-x-1/2 bg-slate-900/90 text-white px-6 py-3 rounded-2xl text-[10px] font-bold tracking-widest uppercase opacity-0 transition-all z-[100]"></div>

    <script>
        const API_URL = window.location.pathname;
        let allProducts = [];

        function toggleModal(id, show) {
            const el = document.getElementById(id);
            const sheet = el.querySelector('.modal-sheet');
            if(show) {
                el.classList.remove('hidden');
                setTimeout(() => sheet.classList.add('active'), 10);
            } else {
                sheet.classList.remove('active');
                setTimeout(() => el.classList.add('hidden'), 300);
            }
        }

        function showToast(msg) {
            const t = document.getElementById('toast');
            t.innerText = msg;
            t.classList.replace('opacity-0', 'opacity-100');
            setTimeout(() => t.classList.replace('opacity-100', 'opacity-0'), 2000);
        }

        async function fetchProducts() {
            const res = await fetch(`${API_URL}?action=getProducts`);
            const data = await res.json();
            if(data.success) {
                allProducts = data.products;
                renderProductsByCategory(allProducts);
            }
        }

        function renderProductsByCategory(products) {
            const list = document.getElementById('productList');
            if (products.length === 0) {
                list.innerHTML = `<div class="py-20 text-center text-slate-300"><p class="text-xs font-bold uppercase">No Products Found</p></div>`;
                return;
            }
            const groups = products.reduce((acc, p) => {
                const cat = p.category_name || "Uncategorized";
                if (!acc[cat]) acc[cat] = [];
                acc[cat].push(p);
                return acc;
            }, {});

            let html = '';
            for (const category in groups) {
                html += `
                    <div class="category-section">
                        <div class="flex items-center gap-3 mb-3 ml-2">
                             <div class="h-px flex-1 bg-slate-200"></div>
                             <h3 class="text-[10px] font-black text-indigo-500 uppercase tracking-[0.2em] whitespace-nowrap">${category}</h3>
                             <div class="h-px flex-1 bg-slate-200"></div>
                        </div>
                        <div class="grid grid-cols-1 gap-3">
                            ${groups[category].map(p => renderProductCard(p)).join('')}
                        </div>
                    </div>
                `;
            }
            list.innerHTML = html;
        }

        function renderProductCard(p) {
            let imgSrc = p.image_url || 'https://placehold.co/80';
            if (imgSrc.indexOf('http') !== 0 && imgSrc !== 'https://placehold.co/80') {
                imgSrc = '../' + imgSrc.replace(/^\/+/, '');
            }
            return `
                <div class="bg-white p-4 rounded-[24px] shadow-sm border border-slate-100 flex items-center gap-4 product-card">
                    <div class="relative">
                        <img src="${imgSrc}" class="w-14 h-14 rounded-2xl object-cover bg-slate-50 border">
                        <span class="absolute -top-1 -left-1 bg-slate-900 text-white text-[8px] px-1 rounded-md font-bold">${p.region}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <h4 class="font-800 text-slate-900 text-sm truncate">${p.spu}</h4>
                            ${p.game_title ? `<span class="bg-blue-50 text-blue-600 text-[7px] px-1.5 py-0.5 rounded-md font-black uppercase">${p.game_title}</span>` : ''}
                        </div>
                        <p class="text-[9px] text-slate-400 font-bold mb-1">SmileOne: ${p.smileone_game || 'N/A'}</p>
                        <div class="flex items-center gap-2">
                            <span class="text-base font-800 text-slate-900 leading-none">₹${parseFloat(p.price).toFixed(2)}</span>
                        </div>
                    </div>
                    <div class="flex gap-1">
                        <button onclick="editProduct('${p.product_id}')" class="w-9 h-9 flex items-center justify-center text-blue-600 bg-blue-50 rounded-xl"><i class="fa-solid fa-pen text-xs"></i></button>
                        <button onclick="deleteProduct('${p.product_id}')" class="w-9 h-9 flex items-center justify-center text-red-500 bg-red-50 rounded-xl"><i class="fa-solid fa-trash-can text-xs"></i></button>
                    </div>
                </div>
            `;
        }

        document.getElementById('fetchApiBtn').onclick = () => toggleModal('apiModal', true);

        // ✅ UPDATED AJAX REQUEST TO USE THE EXTERNAL API FILE
        document.getElementById('runSyncBtn').onclick = async () => {
            const region = document.getElementById('regionSelect').value;
            const gameKey = document.getElementById('apiGameKey').value;
            if(!gameKey) return alert("Enter SmileOne Product Key first");

            const list = document.getElementById('apiList');
            list.innerHTML = `<div class="py-20 text-center"><i class="fa-solid fa-circle-notch animate-spin text-2xl text-slate-300"></i></div>`;
            
            try {
                const res = await fetch(`/api/smileone_product?action=fetchProducts&region=${region}&game_key=${gameKey}`);
                const result = await res.json();
                if(result.success) {
                    list.innerHTML = result.data.map(item => `
                        <div class="bg-white p-4 rounded-2xl flex justify-between items-center shadow-sm border border-slate-100">
                            <div>
                                <p class="font-bold text-slate-800 text-sm">${item.spu}</p>
                                <p class="text-blue-600 font-800 text-xs">${region === 'BR' ? 'R$' : '₱'} ${item.price}</p>
                            </div>
                            <button onclick="importApi('${item.id}', '${item.spu.replace(/'/g, "\\'")}', '${item.price}', '${region}', '${gameKey}')" 
                                    class="bg-slate-900 text-white px-4 py-2 rounded-xl text-[10px] font-bold">IMPORT</button>
                        </div>
                    `).join('');
                } else {
                    list.innerHTML = `<div class="py-10 text-center text-red-500 font-bold">${result.message}</div>`;
                }
            } catch (e) {
                list.innerHTML = `<div class="py-10 text-center text-red-500 font-bold text-xs uppercase">Connection Failed</div>`;
            }
        };

        window.importApi = async (id, spu, price, region, gameKey) => {
            showToast("Importing...");
            const res = await fetch(`${API_URL}?action=saveProduct`, {
                method: 'POST',
                body: JSON.stringify({ 
                    product_id: id, 
                    spu: spu, 
                    original_price: price, 
                    region: region,
                    smileone_game: gameKey
                })
            });
            if((await res.json()).success) {
                showToast("Imported!");
                fetchProducts();
            }
        };

        document.getElementById('productForm').onsubmit = async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target).entries());
            const res = await fetch(`${API_URL}?action=saveProduct`, {
                method: 'POST',
                body: JSON.stringify(data)
            });
            if((await res.json()).success) {
                showToast("SAVED");
                toggleModal('productModal', false);
                fetchProducts();
            }
        };

        (async () => {
            const [catRes, gameRes] = await Promise.all([
                fetch(`${API_URL}?action=getCategories`),
                fetch(`${API_URL}?action=getGames`)
            ]);
            const cats = await catRes.json();
            document.getElementById('category_id').innerHTML = cats.categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
            const games = await gameRes.json();
            const gameSelect = document.getElementById('game_id');
            games.games.forEach(g => {
                const opt = document.createElement('option');
                opt.value = g.id; opt.textContent = g.title;
                gameSelect.appendChild(opt);
            });
            fetchProducts();
        })();

        window.editProduct = (id) => {
            const p = allProducts.find(x => x.product_id == id);
            const f = document.getElementById('productForm');
            f.reset();
            document.getElementById('product_id').value = p.product_id;
            document.getElementById('region').value = p.region;
            document.getElementById('smileone_game').value = p.smileone_game || "";
            document.getElementById('game_id').value = p.game_id || "";
            document.getElementById('spu').value = p.spu;
            document.getElementById('price').value = p.price;
            document.getElementById('reseller_price').value = p.reseller_price || "";
            document.getElementById('original_price').value = p.original_price;
            document.getElementById('category_id').value = p.category_id;
            document.getElementById('image_url').value = p.image_url;
            
            // Flash Sale fields
            document.getElementById('is_flash_sale').checked = (p.is_flash_sale == 1);
            document.getElementById('flash_price').value = p.flash_price || "";
            document.getElementById('flash_sold_percent').value = p.flash_sold_percent || "";

            if (p.image_url) {
                let previewSrc = p.image_url;
                if (previewSrc.indexOf('http') !== 0) {
                    previewSrc = '../' + previewSrc.replace(/^\/+/, '');
                }
                document.getElementById('imagePreview').src = previewSrc;
            } else {
                document.getElementById('imagePreview').src = 'https://placehold.co/100';
            }
            toggleModal('productModal', true);
        };

        window.deleteProduct = async (id) => {
            if(!confirm("Delete this product?")) return;
            const res = await fetch(`${API_URL}?action=deleteProduct`, {
                method: 'POST',
                body: JSON.stringify({ product_id: id })
            });
            if((await res.json()).success) {
                showToast("DELETED");
                fetchProducts();
            }
        };

        document.getElementById('fabAdd').onclick = () => {
            document.getElementById('productForm').reset();
            document.getElementById('imagePreview').src = 'https://placehold.co/100';
            toggleModal('productModal', true);
        };

        document.getElementById('imageUploadInput').onchange = async function() {
            const fd = new FormData();
            fd.append('image', this.files[0]);
            showToast("Uploading...");
            const res = await fetch(`${API_URL}?action=uploadImage`, { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                document.getElementById('image_url').value = data.filePath;
                document.getElementById('imagePreview').src = '../' + data.filePath;
            }
        };

        document.getElementById('searchInput').oninput = function() {
            const q = this.value.toLowerCase();
            const filtered = allProducts.filter(p => 
                p.spu.toLowerCase().includes(q) || 
                p.product_id.includes(q) ||
                (p.category_name && p.category_name.toLowerCase().includes(q))
            );
            renderProductsByCategory(filtered);
        };
    </script>
</body>
</html>