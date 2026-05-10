<?php
/**
 * strict_admin.php
 * Protects admin files from unauthorized access.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/includes/config.php';

$isAdmin = false;
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res && $res['role'] === 'admin') {
        $isAdmin = true;
    }
    $stmt->close();
}

if (!$isAdmin) {
    http_response_code(404);
    echo "<h1>404 Not Found</h1>";
    echo "The page you are looking for does not exist.";
    exit();
}
?>
