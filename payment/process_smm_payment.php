<?php
/**
 * payment/process_smm_payment.php
 * Handles SMM orders — Wallet (J-Coin) or Payment Gateway
 */
if(session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/../config.php';
header('Content-Type: application/json');

function fail($msg){echo json_encode(['ok'=>false,'error'=>$msg]);exit;}
function ok($d){echo json_encode(array_merge(['ok'=>true],$d));exit;}

// Auth
$user_id=(int)($_SESSION['user_id']??0);
if(!$user_id) fail('Please login first.');
if($_SERVER['REQUEST_METHOD']!=='POST') fail('Invalid request.');

$service_id=(int)($_POST['service_id']??0);
$link=trim($_POST['link']??'');
$quantity=(int)($_POST['quantity']??0);
$payment_method=trim($_POST['payment_method']??'wallet');

if(!$service_id||!$link||!$quantity) fail('Missing required fields.');
if(!filter_var($link, FILTER_VALIDATE_URL)) fail('Invalid target URL.');

// Load service
$st=$conn->prepare("SELECT id,provider_id,COALESCE(custom_name,original_name) AS svc_name,custom_price,original_rate,min_order,max_order,is_active FROM smm_services WHERE id=? LIMIT 1");
$st->bind_param("i",$service_id); $st->execute();
$svc=$st->get_result()->fetch_assoc(); $st->close();

if(!$svc) fail('Service not found.');
if(!$svc['is_active']) fail('This service is currently unavailable.');
if($quantity<$svc['min_order']||$quantity>$svc['max_order']) fail("Quantity must be between {$svc['min_order']} and {$svc['max_order']}.");

// Calculate price
$rate_per_k=$svc['custom_price']?:(round($svc['original_rate']*85*1.3,2));
$total_price=round(($quantity/1000)*$rate_per_k,2);
if($total_price<=0) fail('Invalid price calculation.');

$order_ref='SMM_'.strtoupper(bin2hex(random_bytes(5)));

$conn->begin_transaction();
try{
  // ── WALLET PAYMENT ──────────────────────────────────────────────────────────
  if($payment_method==='wallet'){
    // Lock user row and check balance
    $st=$conn->prepare("SELECT wallet_balance,wallet_approved FROM users WHERE id=? FOR UPDATE");
    $st->bind_param("i",$user_id); $st->execute();
    $u=$st->get_result()->fetch_assoc(); $st->close();

    if(!$u) throw new Exception("User not found.");
    if(!(int)$u['wallet_approved']) throw new Exception("Wallet payments not approved for your account. Contact admin.");

    $bal=(float)$u['wallet_balance'];
    if($bal<$total_price) throw new Exception("Insufficient J-Coin balance. Need ₹{$total_price}, have ₹{$bal}.");

    // Deduct
    $new_bal=$bal-$total_price;
    $st=$conn->prepare("UPDATE users SET wallet_balance=? WHERE id=?");
    $st->bind_param("di",$new_bal,$user_id); $st->execute(); $st->close();

    // Wallet log
    $desc="SMM Order: {$svc['svc_name']} x{$quantity} [{$order_ref}]";
    $st=$conn->prepare("INSERT INTO wallet_logs(user_id,order_id,type,amount,balance_before,balance_after,description) VALUES(?,?,'debit',?,?,?,?)");
    $st->bind_param("isddds",$user_id,$order_ref,$total_price,$bal,$new_bal,$desc);
    $st->execute(); $st->close();

    // Create SMM order
    $st=$conn->prepare("INSERT INTO smm_orders(order_ref,user_id,service_id,provider_id,target_link,quantity,price_paid,payment_method,status,created_at) VALUES(?,?,?,?,?,?,?,'wallet','pending',NOW())");
    $st->bind_param("siiisid",$order_ref,$user_id,$service_id,$svc['provider_id'],$link,$quantity,$total_price);
    $st->execute(); $st->close();

    $conn->commit();
    ok(['ref'=>$order_ref,'new_balance'=>$new_bal,'total'=>$total_price]);

  // ── GATEWAY PAYMENT ──────────────────────────────────────────────────────────
  } elseif($payment_method==='gateway'){
    // Create pending SMM order first (payment_status=unpaid)
    $st=$conn->prepare("INSERT INTO smm_orders(order_ref,user_id,service_id,provider_id,target_link,quantity,price_paid,payment_method,status,created_at) VALUES(?,?,?,?,?,?,?,'gateway','awaiting_payment',NOW())");
    $st->bind_param("siiisid",$order_ref,$user_id,$service_id,$svc['provider_id'],$link,$quantity,$total_price);
    $st->execute(); $new_id=$st->insert_id; $st->close();

    $conn->commit();

    // Redirect to payment gateway (reuse existing pay flow)
    $pay_url=defined('PAY_API_URL')?PAY_API_URL:'';
    $token=defined('PAY0_USER_TOKEN')?PAY0_USER_TOKEN:'';
    
    // Create gateway order
    $ch=curl_init($pay_url);
    curl_setopt_array($ch,[
      CURLOPT_POST=>1,CURLOPT_RETURNTRANSFER=>1,
      CURLOPT_POSTFIELDS=>http_build_query([
        'user_token'=>$token,'amount'=>$total_price,
        'order_id'=>$order_ref,'redirect_url'=>(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.$_SERVER['HTTP_HOST'].'/mobile/smm_pay_return.php?ref='.$order_ref,
      ])
    ]);
    $res=json_decode(curl_exec($ch),true); curl_close($ch);

    if(!empty($res['payment_url'])){
      ok(['ref'=>$order_ref,'redirect'=>$res['payment_url']]);
    }else{
      throw new Exception('Gateway error: '.($res['message']??'Could not create payment link.'));
    }
  } else {
    throw new Exception('Invalid payment method.');
  }

}catch(Exception $e){
  if($conn->in_transaction) $conn->rollback();
  fail($e->getMessage());
}
