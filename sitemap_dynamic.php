<?php
header("Content-Type: application/xml; charset=utf-8");
require_once 'includes/config.php';

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

// Core Pages
$pages = [
    '' => 1.0,
    'auth/login' => 0.8,
    'auth/register' => 0.8,
    'wallet' => 0.9,
    'blog' => 0.6,
    'faq' => 0.5,
    'support' => 0.5,
    'notifications' => 0.7,
    'smm_orders' => 0.7
];

foreach ($pages as $path => $priority) {
    echo '  <url>' . PHP_EOL;
    echo '    <loc>' . BASE_URL . '/' . $path . '</loc>' . PHP_EOL;
    echo '    <changefreq>weekly</changefreq>' . PHP_EOL;
    echo '    <priority>' . $priority . '</priority>' . PHP_EOL;
    echo '  </url>' . PHP_EOL;
}

// Dynamic Products/Categories
$categories = $conn->query("SELECT DISTINCT category_name FROM category WHERE status = 1");
if ($categories) {
    while ($cat = $categories->fetch_assoc()) {
        $slug = strtolower(str_replace(' ', '-', $cat['category_name']));
        echo '  <url>' . PHP_EOL;
        echo '    <loc>' . BASE_URL . '/product/mobile-legends/' . $slug . '</loc>' . PHP_EOL;
        echo '    <changefreq>daily</changefreq>' . PHP_EOL;
        echo '    <priority>0.9</priority>' . PHP_EOL;
        echo '  </url>' . PHP_EOL;
    }
}

echo '</urlset>';
?>
