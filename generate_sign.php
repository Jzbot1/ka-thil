<?php
function generateSign($params, $key) {
    ksort($params);
    $str = '';
    foreach ($params as $k => $v) {
        $str .= $k . '=' . $v . '&';
    }
    $str .= $key;
    return md5(md5($str));
}
?>
