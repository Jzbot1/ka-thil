<?php
include __DIR__ . '/../config.php';
$res = $conn->query("DESCRIBE diamonds");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "--- FAV SETTING ---\n";
$res = $conn->query("DESCRIBE fav_setting");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
