<?php
require_once 'includes/config.php';

echo "Checking diamonds for flash sale...\n";
$res = $conn->query("SELECT d.*, g.slug as game_slug FROM diamonds d LEFT JOIN games g ON d.game_id = g.id WHERE d.is_flash_sale = 1 LIMIT 4");
if (!$res) {
    echo "Error: " . $conn->error . "\n";
} else {
    echo "Found " . $res->num_rows . " flash sale items in diamonds.\n";
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
}
?>
