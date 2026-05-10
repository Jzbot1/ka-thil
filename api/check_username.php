<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
include __DIR__ . '/../generate_sign.php';

$response = [
    'success' => false,
    'username' => '',
    'message' => 'Invalid Request',
    'raw_response' => []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id    = trim($_POST['user_id'] ?? '');
    $zone_id    = trim($_POST['zone_id'] ?? '');
    $product_id = $_POST['product_id'] ?? '';

    if (empty($user_id) || empty($product_id)) {
        $response['message'] = "Missing User ID or Product selection";
        echo json_encode($response);
        exit;
    }

    // Default settings
    $region = 'br';
    $smileone_product = 'mobilelegends';

    // Fetch region and game type from diamonds table
    $stmt_reg = $conn->prepare("SELECT region, smileone_game FROM diamonds WHERE product_id = ? LIMIT 1");
    $stmt_reg->bind_param("s", $product_id);
    $stmt_reg->execute();
    $res_reg = $stmt_reg->get_result()->fetch_assoc();

    if ($res_reg) {
        $region = strtolower($res_reg['region']);
        if (!empty($res_reg['smileone_game'])) {
            $smileone_product = $res_reg['smileone_game'];
        }
    }

    $email_api = defined('SMILE_API_EMAIL') ? SMILE_API_EMAIL : '';
    $uid_api   = defined('SMILE_API_UID') ? SMILE_API_UID : '';
    $key_api   = defined('SMILE_API_KEY') ? SMILE_API_KEY : '';
    $time      = time();

    $params = [
        'email'     => $email_api,
        'uid'       => $uid_api,
        'userid'    => $user_id,
        'zoneid'    => ($zone_id === 'none' ? '' : $zone_id),
        'product'   => $smileone_product,
        'productid' => $product_id,
        'time'      => $time
    ];

    $sign = generateSign($params, $key_api);
    $params['sign'] = $sign;

    $api_url = ($region === 'ph') ? 'https://www.smile.one/ph/smilecoin/api/getrole' : 'https://www.smile.one/br/smilecoin/api/getrole';

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $api_raw = curl_exec($ch);
    $api_data = json_decode($api_raw, true);
    curl_close($ch);

    $response['raw_response'] = $api_data;

    if ($api_data && isset($api_data['status']) && $api_data['status'] == 200) {
        $response['success'] = true;
        $response['username'] = $api_data['username'];
        $response['message'] = "Success";
    } else {
        $response['message'] = $api_data['message'] ?? 'Invalid account details';
    }
}

echo json_encode($response);
exit;