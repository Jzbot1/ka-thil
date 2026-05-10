<?php
require_once dirname(__DIR__) . '/includes/config.php';

$res = $conn->query("SHOW COLUMNS FROM diamonds");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ")<br>";
}
?>
