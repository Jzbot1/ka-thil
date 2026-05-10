<?php
/**
 * api/v1/smm_status.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Check status of one or multiple SMM orders.
 *
 * Auth: partner_id + secret
 *
 * Request (GET or POST):
 *   partner_id  = your partner ID
 *   secret      = your secret key
 *   order_ref   = single order ref (e.g. RSMM_ABC123)
 *   --- OR ---
 *   order_refs  = comma-separated refs (e.g. RSMM_ABC,RSMM_XYZ)
 *
 * Response:
 *   { status: true, data: [ { order_ref, service, quantity, status, remains, start_count, charge, price_paid, created_at } ] }
 */
require_once __DIR__ . '/api_core.php';

$reseller = authenticate_api();

// ── Check table exists ──────────────────────────────────────────────────────
$tbl = $conn->query("SHOW TABLES LIKE 'smm_orders'");
if (!$tbl || $tbl->num_rows === 0) {
    send_api_response(false, "SMM order system not configured.");
}

// ── Parse input ─────────────────────────────────────────────────────────────
$single = trim($_POST['order_ref']  ?? $_GET['order_ref']  ?? '');
$multi  = trim($_POST['order_refs'] ?? $_GET['order_refs'] ?? '');

if ($single !== '') {
    $refs = [$single];
} elseif ($multi !== '') {
    $refs = array_filter(array_map('trim', explode(',', $multi)));
} else {
    send_api_response(false, "Provide 'order_ref' or 'order_refs'.");
}

if (empty($refs)) send_api_response(false, "No valid order references provided.");
if (count($refs) > 100) send_api_response(false, "Max 100 orders per request.");

// ── Fetch orders (only this reseller's orders) ──────────────────────────────
$placeholders = implode(',', array_fill(0, count($refs), '?'));
$types        = str_repeat('s', count($refs));

$sql = "SELECT so.order_ref, so.smm_order_id, so.status, so.quantity,
               so.remains, so.start_count, so.charge, so.price_paid,
               so.target_link, so.created_at, so.last_checked,
               COALESCE(ss.custom_name, ss.original_name) AS service_name,
               ss.category
        FROM smm_orders so
        LEFT JOIN smm_services ss ON so.service_id = ss.id
        WHERE so.order_ref IN ($placeholders)
          AND so.user_id = ?
        ORDER BY so.id DESC";

$stmt = $conn->prepare($sql);
// Bind: all refs as strings + reseller id as int
$bind_types = $types . 'i';
$bind_values = array_merge($refs, [$reseller['id']]);

// Use spread with call_user_func_array for dynamic bind
$bind_params = array_merge([$bind_types], $bind_values);
$refs_by_ref = [];
foreach ($bind_values as $k => &$v) $refs_by_ref[] = &$v;
array_unshift($refs_by_ref, $bind_types);
call_user_func_array([$stmt, 'bind_param'], $refs_by_ref);

$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

$results = [];
while ($row = $res->fetch_assoc()) {
    $results[] = [
        'order_ref'    => $row['order_ref'],
        'smm_order_id' => $row['smm_order_id'],
        'service'      => $row['service_name'] ?? 'Unknown',
        'category'     => $row['category'] ?? '',
        'link'         => $row['target_link'],
        'quantity'     => (int)$row['quantity'],
        'status'       => $row['status'],
        'remains'      => $row['remains'] !== null ? (int)$row['remains'] : null,
        'start_count'  => $row['start_count'] !== null ? (int)$row['start_count'] : null,
        'charge'       => $row['charge'] !== null ? (float)$row['charge'] : null,
        'price_paid'   => (float)$row['price_paid'],
        'created_at'   => $row['created_at'],
        'last_synced'  => $row['last_checked'],
    ];
}

if (empty($results)) {
    send_api_response(false, "No orders found for the provided references.");
}

$msg = count($results) === 1
    ? "Order status: " . $results[0]['status']
    : count($results) . " orders found.";

send_api_response(true, $msg, count($results) === 1 ? $results[0] : $results);
