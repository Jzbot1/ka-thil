<?php
/**
 * Reseller API Core & Authentication
 */
require_once dirname(dirname(__DIR__)) . '/includes/config.php';

header('Content-Type: application/json');

function send_api_response($status, $message, $data = null) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit();
}

function authenticate_api() {
    global $conn;

    // Check Headers
    $headers = apache_request_headers();
    $partner_id = $_POST['partner_id'] ?? $_GET['partner_id'] ?? $headers['Partner-ID'] ?? '';
    $secret = $_POST['secret'] ?? $_GET['secret'] ?? $headers['Secret-Key'] ?? '';

    if (empty($partner_id) || empty($secret)) {
        send_api_response(false, "Authentication credentials missing.");
    }

    // Check Database
    $stmt = $conn->prepare("SELECT id, email, wallet_balance, role, api_ip_whitelist FROM users WHERE api_partner_id = ? AND api_secret = ? LIMIT 1");
    $stmt->bind_param("ss", $partner_id, $secret);
    $stmt->execute();
    $reseller = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$reseller) {
        send_api_response(false, "Invalid Partner ID or Secret Key.");
    }

    if ($reseller['role'] !== 'reseller') {
        send_api_response(false, "Account is not authorized for API access.");
    }

    // IP Whitelist Check
    $client_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $whitelist = array_map('trim', explode(',', $reseller['api_ip_whitelist']));
    
    // If whitelist is not empty and not just empty strings
    $whitelist = array_filter($whitelist);
    if (!empty($whitelist) && !in_array($client_ip, $whitelist)) {
        send_api_response(false, "IP address ($client_ip) is not whitelisted.");
    }

    return $reseller;
}
?>
