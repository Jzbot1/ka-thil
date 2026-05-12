<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'err' => 'Unauthorized']); exit;
}

$user_id = $_SESSION['user_id'];
$vcs = new VirtualCardSystem($conn);
$act = $_POST['act'] ?? '';

// Generate new card
if ($act === 'generate') {
    $pin = $_POST['pin'] ?? '';
    if (!preg_match('/^[0-9]{4}$/', $pin)) {
        echo json_encode(['ok' => false, 'err' => 'PIN must be 4 digits']); exit;
    }
    
    // Limit to 2 cards per user for now
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM virtual_cards WHERE user_id = ? AND status != 'blocked'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()['c'] >= 2) {
        echo json_encode(['ok' => false, 'err' => 'Maximum 2 cards allowed per user']); exit;
    }

    $res = $vcs->generateCard($user_id, $pin);
    echo json_encode($res); exit;
}

// Toggle status
if ($act === 'toggle_status') {
    $card_id = (int)$_POST['card_id'];
    $new_status = $_POST['status'] === 'active' ? 'frozen' : 'active';
    
    $stmt = $conn->prepare("UPDATE virtual_cards SET status = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $new_status, $card_id, $user_id);
    if ($stmt->execute()) echo json_encode(['ok' => true, 'status' => $new_status]);
    else echo json_encode(['ok' => false, 'err' => 'Update failed']);
    exit;
}

// Get CVV (Encrypted data needs careful handling)
if ($act === 'get_cvv') {
    $card_id = (int)$_POST['card_id'];
    $pin = $_POST['pin'] ?? '';
    
    $stmt = $conn->prepare("SELECT cvv_encrypted, pin_hash FROM virtual_cards WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $card_id, $user_id);
    $stmt->execute();
    $card = $stmt->get_result()->fetch_assoc();
    
    if ($card && password_verify($pin, $card['pin_hash'])) {
        echo json_encode(['ok' => true, 'cvv' => $vcs->decrypt($card['cvv_encrypted'])]);
    } else {
        echo json_encode(['ok' => false, 'err' => 'Incorrect PIN']);
    }
    exit;
}

// Change PIN
if ($act === 'change_pin') {
    $card_id = (int)$_POST['card_id'];
    $password = $_POST['password'] ?? '';
    $new_pin = $_POST['new_pin'] ?? '';
    
    if (!preg_match('/^[0-9]{4}$/', $new_pin)) {
        echo json_encode(['ok' => false, 'err' => 'PIN must be 4 digits']); exit;
    }

    // Verify Login Password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['ok' => false, 'err' => 'Incorrect account password']); exit;
    }

    // Update PIN
    $pin_hash = password_hash($new_pin, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE virtual_cards SET pin_hash = ?, wrong_pin_attempts = 0 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sii", $pin_hash, $card_id, $user_id);
    
    if ($stmt->execute()) echo json_encode(['ok' => true]);
    else echo json_encode(['ok' => false, 'err' => 'Update failed']);
    exit;
}

// Delete card
if ($act === 'delete') {
    $card_id = (int)$_POST['card_id'];
    $stmt = $conn->prepare("UPDATE virtual_cards SET status = 'blocked' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $card_id, $user_id);
    if ($stmt->execute()) echo json_encode(['ok' => true]);
    else echo json_encode(['ok' => false]);
    exit;
}

echo json_encode(['ok' => false, 'err' => 'Invalid action']);
