<?php
require_once 'includes/config.php';

echo "Checking flash_sales table...\n";
$res = $conn->query("SELECT * FROM flash_sales");
if (!$res) {
    echo "Error: " . $conn->error . "\n";
} else {
    echo "Total rows in flash_sales: " . $res->num_rows . "\n";
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
}

echo "\nChecking fav_setting for flash_sale_end...\n";
$res2 = $conn->query("SELECT flash_sale_end FROM fav_setting LIMIT 1");
if ($res2 && $row2 = $res2->fetch_assoc()) {
    echo "Flash Sale End: " . $row2['flash_sale_end'] . "\n";
}
?>
