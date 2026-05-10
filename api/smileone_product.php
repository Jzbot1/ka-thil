<?php
/**
 * SMILEONE API SERVICE
 * Handles communication with Smile.one endpoints
 */

// ✅ 1. CONFIGURATION
// Ensure this path correctly points to your config.php relative to the /api/ folder
include __DIR__ . '/../config.php'; 

header('Content-Type: application/json');

/**
 * Reusable function to fetch products from SmileOne
 * Supports BR (Brazil) and PH (Philippines)
 */
function fetchSmileOneProducts($region, $gameKey) {
    // API Credentials sourced from config.php
    $smile_uid   = SMILE_API_UID;
    $smile_email = SMILE_API_EMAIL;
    $smile_key   = SMILE_API_KEY;

    $endpoints = [
        'BR' => 'https://www.smile.one/br/smilecoin/api/productlist',
        'PH' => 'https://www.smile.one/ph/smilecoin/api/productlist'
    ];

    $apiUrl = $endpoints[$region] ?? $endpoints['BR'];
    $time = time();
    
    // Prepare Parameters
    $params = [
        'uid' => $smile_uid, 
        'email' => $smile_email, 
        'product' => $gameKey, 
        'time' => $time
    ];
    
    // Generate SmileOne Signature
    ksort($params);
    $str = '';
    foreach ($params as $k => $v) { 
        $str .= $k . '=' . $v . '&'; 
    }
    $str .= $smile_key; 
    $sign = md5(md5($str));
    $params['sign'] = $sign;

    // Execute CURL Request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'message' => 'CURL Error: ' . $error];
    }

    $data = json_decode($response, true);
    
    // Return structured data for the frontend
    if (isset($data['status']) && $data['status'] == 200) {
        return [
            'success' => true, 
            'data' => $data['data']['product'] ?? []
        ];
    } else {
        return [
            'success' => false, 
            'message' => $data['message'] ?? 'API Error'
        ];
    }
}

// --- ROUTING ---
$action = $_GET['action'] ?? '';

if ($action === 'fetchProducts') {
    $region = $_GET['region'] ?? 'BR';
    $gameKey = $_GET['game_key'] ?? '';

    if (empty($gameKey)) {
        echo json_encode(['success' => false, 'message' => 'Game key is required']);
        exit;
    }

    $result = fetchSmileOneProducts($region, $gameKey);
    echo json_encode($result);
    exit;
}

// Default error for unknown actions
echo json_encode(['success' => false, 'message' => 'Invalid Endpoint']);