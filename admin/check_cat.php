<?php
include '../config.php';
$res = $conn->query("DESCRIBE categories");
while($row = $res->fetch_array()) { echo "categories: " . $row[0] . " - " . $row[1] . "\n"; }
echo "\n";
$res2 = $conn->query("DESCRIBE game_categories");
if ($res2) { while($row = $res2->fetch_array()) { echo "game_categories: " . $row[0] . " - " . $row[1] . "\n"; } }
?>
