<?php
include '../config.php';
$res = $conn->query("DESCRIBE games");
while($row = $res->fetch_array()) { echo "games: " . $row[0] . " - " . $row[1] . "\n"; }
?>
