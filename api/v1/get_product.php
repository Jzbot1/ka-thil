<?php
require_once __DIR__ . '/api_core.php';

$reseller = authenticate_api();

$query = "SELECT d.product_id, g.title as game_name, d.name as product_name, d.original_price, d.reseller_price 
          FROM diamonds d 
          JOIN games g ON d.game_id = g.id 
          ORDER BY g.title ASC, d.original_price ASC";

$result = $conn->query($query);
$products = [];

while ($row = $result->fetch_assoc()) {
    $products[] = [
        'game' => $row['game_name'],
        'product_id' => $row['product_id'],
        'name' => $row['product_name'],
        'price' => (float)($row['reseller_price'] > 0 ? $row['reseller_price'] : $row['original_price'])
    ];
}

send_api_response(true, "Products fetched successfully", $products);
?>
