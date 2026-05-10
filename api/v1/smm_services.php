<?php
/**
 * api/v1/smm_services.php
 * ─────────────────────────────────────────────────────────────────────────────
 * GET all active SMM services (category-wise) for resellers/API users.
 *
 * Auth: partner_id + secret (same as all v1 endpoints)
 *
 * Request (GET or POST):
 *   partner_id  = your partner ID
 *   secret      = your secret key
 *   category    = (optional) filter by category name
 *
 * Response:
 *   { status: true, message: "...", data: { categories: [...], services: [...] } }
 */
require_once __DIR__ . '/api_core.php';

$reseller = authenticate_api();

// Check table exists
$tbl = $conn->query("SHOW TABLES LIKE 'smm_services'");
if (!$tbl || $tbl->num_rows === 0) {
    send_api_response(false, "SMM services are not available yet.");
}

$filter_cat = trim($_POST['category'] ?? $_GET['category'] ?? '');

$sql  = "SELECT id AS service_id, provider_id, category,
                COALESCE(custom_name, original_name) AS name,
                COALESCE(custom_price, ROUND(original_rate * 85 * 1.3, 2)) AS rate,
                min_order, max_order, type
         FROM smm_services
         WHERE is_active = 1";
$params = [];
if ($filter_cat !== '') {
    $sql .= " AND category = ?";
    $params[] = $filter_cat;
}
$sql .= " ORDER BY category, id";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $params[0]);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
} else {
    $res = $conn->query($sql);
}

$services   = [];
$categories = [];
while ($row = $res->fetch_assoc()) {
    $row['rate']      = (float)$row['rate'];
    $row['min_order'] = (int)$row['min_order'];
    $row['max_order'] = (int)$row['max_order'];
    $services[] = $row;
    $categories[$row['category']] = true;
}

send_api_response(true, count($services) . " services found.", [
    'categories' => array_keys($categories),
    'count'      => count($services),
    'services'   => $services,
]);
