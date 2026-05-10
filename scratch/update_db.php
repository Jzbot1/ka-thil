<?php
include __DIR__ . '/../config.php';

$queries = [
    "ALTER TABLE diamonds ADD COLUMN IF NOT EXISTS is_flash_sale TINYINT(1) DEFAULT 0",
    "ALTER TABLE diamonds ADD COLUMN IF NOT EXISTS flash_price DECIMAL(10,2) DEFAULT 0.00",
    "ALTER TABLE diamonds ADD COLUMN IF NOT EXISTS flash_sold_percent INT DEFAULT 0",
    "ALTER TABLE fav_setting ADD COLUMN IF NOT EXISTS flash_sale_end DATETIME NULL"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Success: $q\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}
?>
