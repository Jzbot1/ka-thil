<?php
require_once __DIR__ . '/api_core.php';

$reseller = authenticate_api();

$product_id = $_POST['product_id'] ?? $_GET['product_id'] ?? '';
$game_user_id = $_POST['game_user_id'] ?? $_GET['game_user_id'] ?? '';
$game_zone_id = $_POST['game_zone_id'] ?? $_GET['game_zone_id'] ?? 'none';

if (empty($product_id) || empty($game_user_id)) {
    send_api_response(false, "Missing required fields: product_id, game_user_id");
}

// 1. Get Product Region / Game mapping
$stmt = $conn->prepare("SELECT region, smileone_game FROM diamonds WHERE product_id = ? LIMIT 1");
$stmt->bind_param("s", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    send_api_response(false, "Invalid product_id");
}

// 2. SmileOne Verification
require_once dirname(dirname(__DIR__)) . '/payment/generate_sign.php';

$region = strtolower($product['region'] ?? 'br');
$smileone_product = !empty($product['smileone_game']) ? $product['smileone_game'] : 'mobilelegends';

$params = [
    'email'     => SMILE_API_EMAIL,
    'uid'       => SMILE_API_UID,
    'userid'    => $game_user_id,
    'zoneid'    => ($game_zone_id === 'none') ? '' : $game_zone_id,
    'product'   => $smileone_product,
    'productid' => $product_id,
    'time'      => time()
];

$params['sign'] = generateSign($params, SMILE_API_KEY);

$api_url = ($region === 'ph') ? 'https://www.smile.one/ph/smilecoin/api/getrole' : 'https://www.smile.one/br/smilecoin/api/getrole';

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$api_raw = curl_exec($ch);
$api_data = json_decode($api_raw, true);
curl_close($ch);

if ($api_data && isset($api_data['status']) && $api_data['status'] == 200) {
    send_api_response(true, "Username retrieved successfully", [
        'username' => $api_data['username'],
        'game_user_id' => $game_user_id,
        'game_zone_id' => $game_zone_id
    ]);
} else {
    send_api_response(false, $api_data['message'] ?? "Invalid game ID or zone ID.");
}
?>
