<?php
/**
 * Project: Unified MooGold Sync Dashboard
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(600); 

// Adjust path based on your config location
include __DIR__ . '/../config.php'; 
include __DIR__ . '/api/moogold_product.php';

/**
 * Database Upsert Logic (Kept in Dashboard for DB context)
 */
function upsertVariations($pdo, $details) {
    if (!isset($details['Variation']) || !is_array($details['Variation'])) return false;

    $sql = "INSERT INTO diamonds (product_id, region, smileone_game, spu, price, original_price, image_url) 
            VALUES (:pid, :reg, :sg, :spu, :pr, :opr, :img) 
            ON DUPLICATE KEY UPDATE 
            price = VALUES(price), 
            spu = VALUES(spu),
            image_url = VALUES(image_url),
            original_price = VALUES(original_price)";
            
    $stmt = $pdo->prepare($sql);
    $count = 0;

    foreach ($details['Variation'] as $v) {
        $stmt->execute([
            ':pid' => $v['variation_id'],
            ':reg' => null,
            ':sg'  => null,
            ':spu' => $v['variation_name'],
            ':pr'  => $v['variation_price'],
            ':opr' => $v['variation_price'],
            ':img' => $details['Image_URL'] ?? ''
        ]);
        $count++;
    }
    return $count;
}

$sync_log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // OPTION A: Bulk Sync
    if (isset($_POST['sync_bulk'])) {
        $category_ids = [50, 51, 1391, 1444, 766, 538, 2433, 1223, 874, 765, 451, 1261, 992, 993, 2377, 3381, 3351, 3075, 3382];
        
        foreach ($category_ids as $cat_id) {
            $list = getMooCategoryProducts($cat_id);
            
            if (isset($list['category_products']) && is_array($list['category_products'])) {
                foreach (array_slice($list['category_products'], 0, 5) as $prod) {
                    $details = getMooProduct($prod['ID']);
                    if (upsertVariations($pdo, $details)) {
                        $sync_log[] = "Bulk Updated: " . $prod['post_title'];
                    }
                }
            }
        }
    }

    // OPTION B: Single Product Sync
    if (isset($_POST['sync_single']) && !empty($_POST['product_id'])) {
        $single_id = $_POST['product_id'];
        $details = getMooProduct($single_id);
        
        if (upsertVariations($pdo, $details)) {
            $sync_log[] = "Manual Sync Success: " . ($details['Product_Name'] ?? "ID $single_id");
        } else {
            $sync_log[] = "Error: Product ID $single_id not found or no variations.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MooGold Unified Sync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .btn-sync { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; font-weight: 600; }
        .log-container { max-height: 300px; overflow-y: auto; background: #fff; padding: 15px; font-family: monospace; font-size: 0.85rem; border-radius: 10px; }
        .status-dot { height: 8px; width: 8px; background-color: #2ecc71; border-radius: 50%; display: inline-block; margin-right: 8px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="fw-bold">MooGold Sync Manager</h1>
                <span class="badge bg-dark">Partner ID: <?php echo MOOGOLD_PARTNER_ID; ?></span>
            </div>

            <div class="row">
                <div class="col-md-5">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>Single Sync</h5>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label small text-muted">MooGold Product ID</label>
                                    <input type="number" name="product_id" class="form-control" placeholder="e.g. 428075" required>
                                </div>
                                <button type="submit" name="sync_single" class="btn btn-primary w-100">Sync Single Product</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-7">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <h5 class="fw-bold mb-3"><i class="fa-solid fa-layer-group me-2"></i>Bulk Category Sync</h5>
                            <p class="small text-muted">Syncs the top 5 products from all predefined categories.</p>
                            <form method="POST">
                                <button type="submit" name="sync_bulk" class="btn btn-sync btn-lg w-100 py-3" onclick="this.innerHTML='<i class=\'fas fa-spinner fa-spin me-2\'></i> Syncing...'">
                                    <i class="fa-solid fa-cloud-arrow-down me-2"></i> Start Full Bulk Sync
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-terminal me-2"></i> Execution Log</h6>
                    <div class="log-container border">
                        <?php if (empty($sync_log)): ?>
                            <span class="text-muted">No activity yet.</span>
                        <?php else: ?>
                            <?php foreach ($sync_log as $entry): ?>
                                <div class="mb-2 border-bottom pb-1">
                                    <span class="status-dot"></span> <?php echo htmlspecialchars($entry); ?>
                                    <span class="float-end text-muted small"><?php echo date('H:i:s'); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>