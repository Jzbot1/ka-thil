<?php
// Set secure session cookie parameters
session_set_cookie_params([
    'httponly' => true,   // Prevent JS access
    'secure' => isset($_SERVER['HTTPS']), // Send only over HTTPS if available
    'samesite' => 'Strict' // Prevent CSRF
]);

session_start();

// Unset all session variables
session_unset();

// Destroy the session
session_destroy();

// Clear any cookies if used (like auto-login or remember-me)
setcookie('user_id', '', time() - 3600, '/');
setcookie('username', '', time() - 3600, '/');

// Redirect to login or homepage
header("Location: ./login.php");
exit();
?>
