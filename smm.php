<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/config.php';

$user_id = $_SESSION['user_id'] ?? null;
$wallet = 0;
if($user_id){
  $uw=$conn->prepare("SELECT wallet_balance FROM users WHERE id=? LIMIT 1");
  $uw->bind_param("i",$user_id);$uw->execute();
  $wallet=(float)($uw->get_result()->fetch_assoc()['wallet_balance']??0);$uw->close();
}

// Load settings (same as index.php)
$setting=['store_name'=>'JZ Store','store_logo'=>'','fav_icon'=>'','facebook'=>'','instagram'=>'','whatsapp'=>'','whatsapp_group'=>'','description'=>''];
$sr=$conn->query("SELECT * FROM fav_setting LIMIT 1");
if($sr&&$row=$sr->fetch_assoc()) foreach($row as $k=>$v) if(!empty($v)) $setting[$k]=$v;

// Load SMM categories + services (safe: check table exists first)
$cats=[]; $svc_map=[]; $total_svc=0; $setup_needed=false;
$tbl_chk=$conn->query("SHOW TABLES LIKE 'smm_services'");
if($tbl_chk && $tbl_chk->num_rows>0){
    $res=$conn->query("SELECT id,provider_id,category,COALESCE(custom_name,original_name) AS display_name,custom_price,original_rate,min_order,max_order,type FROM smm_services WHERE is_active=1 ORDER BY category,id");
    if($res) while($r=$res->fetch_assoc()){
        $cats[$r['category']]=true;
        $r['sell_price']=$r['custom_price']?:(round($r['original_rate']*85*1.3,2));
        $svc_map[$r['category']][]=$r;
    }
    $cats=array_keys($cats);
    $total_svc=array_sum(array_map('count',$svc_map));
}else{
    $setup_needed=true;
}

// Category emoji map
$icons=['Instagram'=>'📸','Facebook'=>'📘','YouTube'=>'▶️','TikTok'=>'🎵','Twitter'=>'🐦','X '=>'🐦','Telegram'=>'✈️','Spotify'=>'🎧','LinkedIn'=>'💼','Discord'=>'🎮','Snapchat'=>'👻','Pinterest'=>'📌'];
function getCatIcon($cat,$icons){foreach($icons as $k=>$v) if(stripos($cat,$k)!==false) return $v; return '⭐';}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>SMM Services – <?=htmlspecialchars($setting['store_name'])?></title>
<link rel="icon" href="<?=htmlspecialchars($setting['fav_icon'])?>">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=DynaPuff:wght@400;600&display=swap" rel="stylesheet">
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          themeDark: '#ffffff', themeBlue: '#557C93', themeGreen: '#80bf15', themePink: '#08203E'
        },
        fontFamily: { poppins: ['Poppins','sans-serif'], dynapuff: ['DynaPuff','cursive'] }
      }
    }
  }
</script>
<style>
  :root{--theme-dark:#ffffff}
  body{font-family:'Poppins',sans-serif;
    background:hsla(213,77%,14%,1);
    background:linear-gradient(90deg,hsla(213,77%,14%,1) 0%,hsla(202,27%,45%,1) 100%);
    background:-moz-linear-gradient(90deg,hsla(213,77%,14%,1) 0%,hsla(202,27%,45%,1) 100%);
    background:-webkit-linear-gradient(90deg,hsla(213,77%,14%,1) 0%,hsla(202,27%,45%,1) 100%);
    background-attachment:fixed;color:#ffffff;overflow-x:hidden}
  .glass-panel{background:rgba(255,255,255,.1);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.1)}
  .game-card:active{transform:scale(.95)}

  /* Category pill tabs */
  .cat-scroll{display:flex;gap:8px;padding:0 16px 4px;overflow-x:auto;scrollbar-width:none}
  .cat-scroll::-webkit-scrollbar{display:none}
  .cat-pill{white-space:nowrap;padding:7px 16px;border-radius:99px;font-size:11px;font-weight:700;border:1.5px solid rgba(255,255,255,.15);background:rgba(255,255,255,.1);backdrop-filter:blur(8px);color:#ffffff;cursor:pointer;transition:all .2s;font-family:'Poppins',sans-serif}
  .cat-pill.active{background:#ffffff;color:#08203E;border-color:#ffffff;box-shadow:0 4px 14px rgba(255,255,255,.15)}

  /* Service card */
  .svc-card{background:rgba(255,255,255,.08);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:14px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:12px}
  .svc-card:active{transform:scale(.98);background:rgba(255,255,255,.15)}

  /* Bottom sheet modal */
  .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(8px);z-index:100;display:flex;align-items:flex-end;justify-content:center;opacity:0;pointer-events:none;transition:opacity .3s}
  .modal-overlay.show{opacity:1;pointer-events:all}
  .modal-box{background:linear-gradient(180deg,rgba(8,32,62,.97),rgba(5,20,40,.99));border-radius:32px 32px 0 0;padding:24px 20px 40px;width:100%;max-width:480px;transform:translateY(100%);transition:transform .35s cubic-bezier(.175,.885,.32,1.1);border-top:1px solid rgba(255,255,255,.1)}
  .modal-overlay.show .modal-box{transform:translateY(0)}
  .modal-pill{width:40px;height:4px;background:rgba(255,255,255,.15);border-radius:99px;margin:0 auto 20px}

  /* Pay tab */
  .pay-tab{padding:12px 10px;border-radius:16px;border:2px solid rgba(255,255,255,.1);background:rgba(255,255,255,.08);font-size:11px;font-weight:700;cursor:pointer;text-align:center;transition:all .2s;font-family:'Poppins',sans-serif;color:#ffffff}
  .pay-tab.sel{border-color:#ffffff;background:rgba(255,255,255,.15)}

  /* Input */
  .svc-input{background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.15);border-radius:14px;padding:12px 14px;width:100%;font-size:14px;font-family:'Poppins',sans-serif;color:#ffffff;outline:none;transition:border .2s}
  .svc-input:focus{border-color:#ffffff;background:rgba(255,255,255,.12)}

  /* Scrollbar hidden */
  .no-scrollbar::-webkit-scrollbar{display:none}
  .no-scrollbar{scrollbar-width:none}

  /* Toast */
  #toast{position:fixed;bottom:90px;left:50%;transform:translateX(-50%);z-index:999;white-space:nowrap;display:none}

  @keyframes scrollText{0%{transform:translateX(100%)}100%{transform:translateX(-100%)}}
  .animate-scroll-text{animation:scrollText 14s linear infinite}
</style>
</head>
<body class="pb-32">

<!-- ═══ HEADER (matches index.php exactly) ═══════════════════════════════════ -->
<header class="fixed top-0 w-full z-40 glass-panel h-16">
  <div class="max-w-md mx-auto px-4 h-full flex items-center justify-between">
    <a href="index" class="w-9 h-9 rounded-full bg-white/10 flex items-center justify-center border border-white/10">
      <i class="fa-solid fa-arrow-left text-white text-xs"></i>
    </a>
    <div class="font-bold text-lg text-white font-dynapuff tracking-wider">SMM Services</div>
    <a href="notifications" class="w-9 h-9 rounded-full bg-white/10 flex items-center justify-center border border-white/10">
      <i class="fa-solid fa-bell text-white text-sm"></i>
    </a>
  </div>
</header>

<main class="max-w-md mx-auto pt-20 px-3">

  <!-- Scrolling announcement bar -->
  <div class="mb-5 bg-white/10 backdrop-blur-md border border-white/10 rounded-xl py-2.5 px-3 flex items-center gap-3 overflow-hidden shadow-sm">
    <i class="fa-solid fa-rocket text-white text-xs flex-shrink-0"></i>
    <div class="overflow-hidden w-full relative h-5">
      <p class="animate-scroll-text whitespace-nowrap absolute text-[11px] text-white/60 font-bold top-0">
        🚀 Boost Instagram, YouTube, TikTok &amp; more! ⚡ Instant delivery · 24/7 Support · <?=$total_svc?>+ Services available!
      </p>
    </div>
  </div>

  <!-- Hero stats strip -->
  <div class="glass-panel rounded-2xl p-4 mb-5 flex items-center justify-around text-center">
    <div>
      <div class="text-base font-black text-white font-dynapuff"><?=$total_svc?>+</div>
      <div class="text-[9px] text-white/50 font-bold uppercase tracking-wider">Services</div>
    </div>
    <div class="w-px h-8 bg-white/10"></div>
    <div>
      <div class="text-base font-black text-white font-dynapuff">⚡ Fast</div>
      <div class="text-[9px] text-white/50 font-bold uppercase tracking-wider">Delivery</div>
    </div>
    <div class="w-px h-8 bg-white/10"></div>
    <div>
      <div class="text-base font-black text-white font-dynapuff">24/7</div>
      <div class="text-[9px] text-white/50 font-bold uppercase tracking-wider">Support</div>
    </div>
    <?php if($user_id): ?>
    <div class="w-px h-8 bg-white/10"></div>
    <div>
      <div class="text-base font-black text-white font-dynapuff">₹<?=number_format($wallet,0)?></div>
      <div class="text-[9px] text-white/50 font-bold uppercase tracking-wider">Wallet</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Quick action row -->
  <div class="grid grid-cols-3 gap-2 mb-6">
    <a href="smm_orders" class="flex flex-col items-center gap-2 p-3 glass-panel rounded-2xl text-center">
      <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center"><i class="fa-solid fa-receipt text-white text-sm"></i></div>
      <span class="text-[10px] text-white/60 font-bold">My Orders</span>
    </a>
    <a href="wallet" class="flex flex-col items-center gap-2 p-3 glass-panel rounded-2xl text-center">
      <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center"><i class="fa-solid fa-wallet text-white text-sm"></i></div>
      <span class="text-[10px] text-white/60 font-bold">Add Funds</span>
    </a>
    <a href="support" class="flex flex-col items-center gap-2 p-3 glass-panel rounded-2xl text-center">
      <div class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center"><i class="fa-solid fa-headset text-white text-sm"></i></div>
      <span class="text-[10px] text-white/60 font-bold">Support</span>
    </a>
  </div>

  <?php if($setup_needed): ?>
  <!-- Setup needed (migration not run) -->
  <div class="glass-panel rounded-3xl p-8 text-center border border-yellow-200/50" style="background:rgba(254,243,199,.5)">
    <div class="text-5xl mb-4">⚙️</div>
    <h3 class="font-black text-themeDark text-base mb-2">Setup Required</h3>
    <p class="text-[11px] text-themeDark/60 mb-4">The SMM database tables haven't been created yet.</p>
    <?php if(isset($_SESSION['role'])&&$_SESSION['role']==='admin'): ?>
    <p class="text-[11px] font-bold text-yellow-700 mb-4">Run <code class="bg-yellow-100 px-2 py-0.5 rounded text-xs">smm_migration.sql</code> in phpMyAdmin to get started.</p>
    <a href="admin/admin_smm.php" class="inline-block bg-themeDark text-white text-xs font-black px-5 py-2.5 rounded-xl shadow-lg">Go to Admin → SMM Panel</a>
    <?php else: ?>
    <p class="text-[11px] text-themeDark/50">Services will be available soon. Check back later!</p>
    <?php endif; ?>
  </div>
  <?php elseif(empty($svc_map)): ?>
  <!-- Empty state (tables exist, no services synced) -->
  <div class="glass-panel rounded-3xl p-10 text-center">
    <div class="text-5xl mb-4">📭</div>
    <h3 class="font-black text-themeDark text-base mb-1">No Services Yet</h3>
    <p class="text-[11px] text-themeDark/50">Admin hasn't synced services yet. Check back soon!</p>
  </div>
  <?php else: ?>

  <!-- ═══ CATEGORY TABS ════════════════════════════════════════════════════ -->
  <div class="mb-1">
    <h3 class="text-sm font-bold text-themeDark flex items-center gap-2 mb-3 px-1">
      <span class="w-1 h-4 bg-blue-500 rounded-full shadow-[0_0_8px_rgba(59,130,246,.5)]"></span>
      Browse by Category
    </h3>
  </div>
  <div class="cat-scroll mb-5" id="catBar">
    <button class="cat-pill active" onclick="showCat('__ALL__',this)">🌐 All</button>
    <?php foreach($cats as $c): $ico=getCatIcon($c,$icons); ?>
    <button class="cat-pill" onclick="showCat(<?=json_encode($c)?>,this)"><?=$ico?> <?=htmlspecialchars($c)?></button>
    <?php endforeach; ?>
  </div>

  <!-- ═══ SERVICES ══════════════════════════════════════════════════════════ -->
  <div id="svcContainer" class="space-y-6">
  <?php foreach($svc_map as $cat=>$svcs):
    $ico=getCatIcon($cat,$icons);
    $colors=['bg-blue-500','bg-purple-500','bg-pink-500','bg-orange-500','bg-teal-500','bg-indigo-500','bg-red-500'];
  ?>
  <div class="cat-section" data-cat="<?=htmlspecialchars($cat)?>">
    <h3 class="text-sm font-bold text-white flex items-center gap-2 mb-3 px-1">
      <span class="w-1 h-4 <?=$colors[array_search($cat,$cats)%count($colors)]?> rounded-full"></span>
      <?=$ico?> <?=htmlspecialchars($cat)?>
      <span class="ml-auto text-[10px] text-white/40 font-bold"><?=count($svcs)?> services</span>
    </h3>
    <div class="space-y-2">
    <?php foreach($svcs as $svc):
      $col=$colors[$svc['id']%count($colors)];
    ?>
    <div class="svc-card" onclick="openOrder(<?=json_encode(['id'=>$svc['id'],'provider_id'=>$svc['provider_id'],'name'=>$svc['display_name'],'price'=>$svc['sell_price'],'min'=>$svc['min_order'],'max'=>$svc['max_order'],'type'=>$svc['type'],'cat'=>$cat])?>)">
      <div class="w-10 h-10 rounded-2xl <?=$col?>/20 flex items-center justify-center text-lg flex-shrink-0"><?=$ico?></div>
      <div class="flex-1 min-w-0">
        <div class="text-[13px] font-bold text-white leading-snug line-clamp-2"><?=htmlspecialchars($svc['display_name'])?></div>
        <div class="text-[10px] text-white/50 font-medium mt-0.5">Min <?=number_format($svc['min_order'])?> · Max <?=number_format($svc['max_order'])?> · <?=htmlspecialchars($svc['type'])?></div>
      </div>
      <div class="text-right flex-shrink-0">
        <div class="text-[15px] font-black text-white">₹<?=number_format($svc['sell_price'],2)?></div>
        <div class="text-[9px] text-white/40 font-bold uppercase tracking-wider">per 1000</div>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php include 'footer.php'; ?>
</main>

<!-- ═══ ORDER MODAL (Bottom Sheet) ════════════════════════════════════════════ -->
<div class="modal-overlay" id="orderModal" onclick="closeIfOut(event)">
  <div class="modal-box">
    <div class="modal-pill"></div>

    <!-- Service header -->
    <div class="flex items-center gap-3 mb-5">
      <div id="m_icon" class="w-12 h-12 rounded-2xl bg-white/10 flex items-center justify-center text-2xl flex-shrink-0">⭐</div>
      <div class="flex-1 min-w-0">
        <div id="m_name" class="text-[14px] font-black text-white line-clamp-2 leading-tight"></div>
        <div id="m_range" class="text-[10px] text-white/50 font-bold mt-1"></div>
      </div>
    </div>

    <!-- Target link -->
    <div class="mb-3">
      <label class="text-[10px] font-bold text-white/50 uppercase tracking-wider block mb-1.5">Target Link</label>
      <input class="svc-input" id="m_link" type="url" placeholder="https://instagram.com/p/...">
    </div>

    <!-- Quantity -->
    <div class="mb-4">
      <label class="text-[10px] font-bold text-white/50 uppercase tracking-wider block mb-1.5">Quantity</label>
      <input class="svc-input" id="m_qty" type="number" placeholder="Enter quantity" oninput="calcTotal()">
    </div>

    <!-- Total -->
    <div class="bg-white/10 border border-white/10 rounded-2xl px-4 py-3 flex items-center justify-between mb-4">
      <span class="text-[12px] text-white/60 font-bold">Total Amount</span>
      <span id="m_total" class="text-[20px] font-black text-white">₹0.00</span>
    </div>

    <!-- Payment method -->
    <div class="text-[10px] font-bold text-white/50 uppercase tracking-wider mb-2">Payment Method</div>
    <div class="grid grid-cols-2 gap-2 mb-4" id="payTabs">
      <div class="pay-tab sel" data-method="wallet" onclick="selPay(this)">
        <div class="text-xl mb-1">💰</div>
        <div class="font-black text-white text-[12px]">J-Coin Wallet</div>
        <div class="text-[10px] text-white/40 font-bold mt-0.5">Bal: ₹<?=number_format($wallet,2)?></div>
      </div>
      <div class="pay-tab" data-method="gateway" onclick="selPay(this)">
        <div class="text-xl mb-1">💳</div>
        <div class="font-black text-white text-[12px]">Pay Gateway</div>
        <div class="text-[10px] text-white/40 font-bold mt-0.5">UPI / Card</div>
      </div>
    </div>

    <?php if(!$user_id): ?>
    <div class="glass-panel rounded-xl p-3 text-center mb-4">
      <p class="text-[12px] font-bold text-themeDark/70"><i class="fa-solid fa-triangle-exclamation text-yellow-500 mr-1"></i>
        <a href="auth/login" class="text-themeDark underline font-black">Login</a> to place an order
      </p>
    </div>
    <?php endif; ?>

    <button id="m_btn" onclick="submitOrder()"
      class="w-full py-4 rounded-2xl bg-white text-[#08203E] font-black text-[14px] shadow-xl shadow-white/10 active:scale-95 transition-all flex items-center justify-center gap-2
      <?=!$user_id?'opacity-50 cursor-not-allowed':''?>"
      <?=!$user_id?'disabled':''?>>
      <i class="fa-solid fa-paper-plane"></i> Place Order
    </button>
  </div>
</div>

<!-- Toast -->
<div id="toast">
  <div id="toast-in" class="glass-panel px-5 py-3 rounded-2xl text-[13px] font-bold text-white flex items-center gap-2 shadow-lg"></div>
</div>

<script>
let cur={}, payMethod='wallet';

function openOrder(d){
  cur=d;
  document.getElementById('m_name').textContent=d.name;
  document.getElementById('m_range').textContent=`Min: ${Number(d.min).toLocaleString()} · Max: ${Number(d.max).toLocaleString()} · ${d.type}`;
  const qi=document.getElementById('m_qty');
  qi.min=d.min; qi.max=d.max; qi.value=d.min;
  document.getElementById('m_link').value='';
  calcTotal();
  document.getElementById('orderModal').classList.add('show');
  document.body.style.overflow='hidden';
}
function closeModal(){document.getElementById('orderModal').classList.remove('show');document.body.style.overflow='';}
function closeIfOut(e){if(e.target===document.getElementById('orderModal'))closeModal();}

function calcTotal(){
  const qty=parseInt(document.getElementById('m_qty').value)||0;
  const t=((qty/1000)*(cur.price||0)).toFixed(2);
  document.getElementById('m_total').textContent='₹'+parseFloat(t).toLocaleString('en-IN',{minimumFractionDigits:2});
}

function selPay(el){
  payMethod=el.dataset.method;
  document.querySelectorAll('.pay-tab').forEach(t=>t.classList.remove('sel'));
  el.classList.add('sel');
}

function showCat(cat,btn){
  document.querySelectorAll('.cat-pill').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.cat-section').forEach(s=>{
    s.style.display=(cat==='__ALL__'||s.dataset.cat===cat)?'':'none';
  });
}

function toast(m,ok=true){
  const t=document.getElementById('toast'),i=document.getElementById('toast-in');
  i.innerHTML=`<i class="fa-solid fa-${ok?'circle-check text-green-600':'circle-exclamation text-red-500'}"></i>${m}`;
  t.style.display='block'; setTimeout(()=>t.style.display='none',3500);
}

async function submitOrder(){
  const link=document.getElementById('m_link').value.trim();
  const qty=parseInt(document.getElementById('m_qty').value)||0;
  if(!link){toast('Enter the target link',false);return;}
  if(qty<cur.min||qty>cur.max){toast(`Qty must be ${Number(cur.min).toLocaleString()}–${Number(cur.max).toLocaleString()}`,false);return;}

  const btn=document.getElementById('m_btn');
  btn.disabled=true;
  btn.innerHTML='<i class="fa-solid fa-circle-notch fa-spin"></i> Processing…';

  const fd=new FormData();
  fd.append('service_id',cur.id);fd.append('link',link);
  fd.append('quantity',qty);fd.append('payment_method',payMethod);

  try{
    const r=await fetch('payment/process_smm_payment.php',{method:'POST',body:fd});
    const d=await r.json();
    if(d.ok){
      closeModal();
      toast('Order placed! Ref: '+d.ref);
      if(d.redirect) setTimeout(()=>location.href=d.redirect,800);
      else setTimeout(()=>location.href='smm_orders',1500);
    }else{
      toast(d.error||'Order failed',false);
      btn.disabled=false;
      btn.innerHTML='<i class="fa-solid fa-paper-plane"></i> Place Order';
    }
  }catch(e){
    toast('Connection error',false);
    btn.disabled=false;
    btn.innerHTML='<i class="fa-solid fa-paper-plane"></i> Place Order';
  }
}
</script>
</body>
</html>
