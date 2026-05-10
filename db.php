<?php
$conn = mysqli_connect("localhost","DB_USER","DB_PASS","DB_NAME");
if(!$conn){ die("Database Error"); }

define('PAY_API_URL', 'https://cash.free.jzstore.in/api/create-order');
define('PAY_STATUS_URL', 'https://cash.free.jzstore.in/api/check-order-status');
define('USER_TOKEN', 'YOUR_API_TOKEN');
define('WIN_CHANCE', 200);
?>