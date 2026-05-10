<?php
// api/smile_balance.php
header('Content-Type: application/json');
require_once '../config.php';

function getSmileBalance($endpoint, $uid, $email, $time, $key) {
    $params = ['uid' => $uid, 'email' => $email, 'time' => $time];
    ksort($params);
    $str = '';
    foreach ($params as $k => $v) { $str .= $k . '=' . $v . '&'; }
    $str .= $key;
    $sign = md5(md5($str));
    $params['sign'] = $sign;

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded",
            'method'  => 'POST',
            'content' => http_build_query($params),
            'timeout' => 8
        ]
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents($endpoint, false, $context);
    
    if ($response === false) return null;
    
    $data = json_decode($response, true);
    return $data['smile_points'] ?? ($data['data']['smile_points'] ?? null);
}

$time = time();

$br = getSmileBalance(
    "https://www.smile.one/smilecoin/api/querypoints", 
    SMILE_API_UID, 
    SMILE_API_EMAIL, 
    $time, 
    SMILE_API_KEY
);

$ph = getSmileBalance(
    "https://www.smile.one/ph/smilecoin/api/querypoints", 
    SMILE_API_UID, 
    SMILE_API_EMAIL, 
    $time, 
    SMILE_API_KEY
);

echo json_encode([
    "br_balance" => $br,
    "ph_balance" => $ph
]);