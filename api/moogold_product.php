<?php
/**
 * MooGold API Communication Layer
 */

/**
 * Generic requester with MooGold Authentication
 */
function sendMooRequest($endpoint, $data) {
    $timestamp = time();
    $string_to_sign = json_encode($data) . $timestamp . $endpoint;
    $auth_signature = hash_hmac('SHA256', $string_to_sign, MOOGOLD_SECRET_KEY);
    $basic_auth = base64_encode(MOOGOLD_PARTNER_ID . ":" . MOOGOLD_SECRET_KEY);

    $ch = curl_init(MOOGOLD_BASE_URL . '/' . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Basic $basic_auth",
        "auth: $auth_signature",
        "timestamp: $timestamp"
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) return ['error' => true, 'message' => $error];
    return json_decode($response, true);
}

/**
 * Fetches details for a single product
 */
function getMooProduct($product_id) {
    return sendMooRequest('product/product_detail', [
        "path" => "product/product_detail", 
        "product_id" => $product_id
    ]);
}

/**
 * Fetches list of products within a category
 */
function getMooCategoryProducts($category_id) {
    return sendMooRequest('product/list_product', [
        "path" => "product/list_product", 
        "category_id" => (int)$category_id
    ]);
}