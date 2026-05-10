<?php
/**
 * Global Helper Functions
 * JZStore - Premium Game Store Logic
 */

// Prevent direct access
if (!defined('BASE_URL') && !isset($conn)) {
    exit('Direct access not permitted.');
}

/**
 * Sanitize user input for HTML output
 */
function clean($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency to INR
 */
function format_price($amount) {
    return '₹' . number_format((float)$amount, 2);
}

/**
 * Handle Image Uploads to a central directory
 */
function handle_upload($fileInputName, $targetDir = "uploads/") {
    // Determine the absolute path to the root uploads folder
    // Since this function is in /includes/functions.php, the root is one level up
    $rootPath = dirname(__DIR__) . '/';
    $absoluteTargetDir = $rootPath . ltrim($targetDir, '/');

    if (!empty($_FILES[$fileInputName]['name'])) {
        if (!is_dir($absoluteTargetDir)) {
            mkdir($absoluteTargetDir, 0777, true);
        }
        
        $filename = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "", basename($_FILES[$fileInputName]["name"]));
        $targetFile = $absoluteTargetDir . $filename;
        $check = getimagesize($_FILES[$fileInputName]["tmp_name"]);
        
        if($check !== false) {
            if (move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $targetFile)) {
                // Return path relative to the store root
                return ltrim($targetDir, '/') . $filename;
            }
        }
    }
    return false;
}

/**
 * Check if a database table exists
 */
function table_exists($tableName) {
    global $conn;
    $res = $conn->query("SHOW TABLES LIKE '$tableName'");
    return ($res && $res->num_rows > 0);
}

/**
 * Generate a clean SEO slug from a string
 */
function create_slug($string) {
    $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', strtolower($string));
    return trim($slug, '-');
}

/**
 * Get store settings with fallbacks
 */
function get_settings() {
    global $conn;
    $default = [
        'store_name' => 'JZ Store',
        'is_banner_on' => 1,
        'is_maintenance' => 0
    ];
    
    $res = $conn->query("SELECT * FROM fav_setting LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return array_merge($default, array_filter($row));
    }
    return $default;
}
?>
