<?php
/**
 * Global Configuration File
 * JZStore - Mobile Legends Product System
 */

// 1. LOAD ENVIRONMENT VARIABLES
function loadEnv($path) {
    if (!file_exists($path)) return false;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
        }
    }
}
loadEnv(dirname(__DIR__) . '/.env');

// Helper to get env with fallback
function env($key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// 2. DATABASE CONFIGURATION
$DB_HOST = env('DB_HOST', 'localhost');
$DB_USER = env('DB_USER', 'root');
$DB_PASS = env('DB_PASS', '');
$DB_NAME = env('DB_NAME', 'moba');

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

// 3. API CREDENTIALS
define('SMILE_API_EMAIL', env('SMILE_API_EMAIL'));
define('SMILE_API_UID', env('SMILE_API_UID'));
define('SMILE_API_KEY', env('SMILE_API_KEY'));
define('SMILE_API_URL', env('SMILE_API_URL', 'https://www.smile.one/smilecoin/api/getrole'));
define('SMILE_CREATE_ORDER_URL', env('SMILE_CREATE_ORDER_URL', 'https://www.smile.one/smilecoin/api/createorder'));

define('PAY_API_URL', env('PAY_API_URL'));
define('PAY_STATUS_URL', env('PAY_STATUS_URL'));
define('PAY0_USER_TOKEN', env('PAY_USER_TOKEN'));

define('MOOGOLD_PARTNER_ID', env('MOOGOLD_PARTNER_ID'));
define('MOOGOLD_SECRET_KEY', env('MOOGOLD_SECRET_KEY'));
define('MOOGOLD_BASE_URL', env('MOOGOLD_BASE_URL', 'https://moogold.com/wp-json/v1/api'));

define('TELEGRAM_BOT_TOKEN', env('TELEGRAM_BOT_TOKEN'));
define('TELEGRAM_CHAT_ID', env('TELEGRAM_CHAT_ID'));

// 4. CURRENCY CONVERSION RATES
define('BRL_TO_INR', (float)env('BRL_TO_INR', 15.0));
define('USD_TO_INR', (float)env('USD_TO_INR', 85.0));

define('CALLBACK_SECRET', env('CALLBACK_SECRET'));

// Detect Base URL Dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

// Get the directory of the current file (includes/config.php) and go up one level to find the project root
$project_root_dir = str_replace('\\', '/', dirname(__DIR__));
$document_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);

// Remove document_root from project_root_dir to get the web path (e.g., /mobile)
$web_path = str_replace($document_root, '', $project_root_dir);
$base_url = rtrim($protocol . $host . '/' . ltrim($web_path, '/'), '/');

define('BASE_URL', $base_url);

// Include Global Helpers
require_once __DIR__ . '/functions.php';

// Include SMM API if exists
if (file_exists(__DIR__ . '/SmmPanelApi.php')) {
    require_once __DIR__ . '/SmmPanelApi.php';
}
?>
