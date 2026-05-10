<?php
require_once __DIR__ . '/api_core.php';

$reseller = authenticate_api();

// Fetch fresh balance
$stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
$stmt->bind_param("i", $reseller['id']);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();
$stmt->close();

send_api_response(true, "Balance fetched successfully", [
    'email' => $reseller['email'],
    'balance' => (float)$current['wallet_balance']
]);
?>
