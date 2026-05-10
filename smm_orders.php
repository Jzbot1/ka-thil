<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/includes/config.php';
if(!isset($_SESSION['user_id'])){header("Location: auth/login");exit;}
$user_id=(int)$_SESSION['user_id'];

$setting=['store_name'=>'JZ Store','whatsapp'=>'','facebook'=>'','instagram'=>''];
$sr=$conn->query("SELECT * FROM fav_setting LIMIT 1");
if($sr&&$row=$sr->fetch_assoc()) foreach($row as $k=>$v) if(!empty($v)) $setting[$k]=$v;

$orders=[];
$table_check=$conn->query("SHOW TABLES LIKE 'smm_orders'");
$tables_exist=($table_check&&$table_check->num_rows>0);

if($tables_exist){
    $res=$conn->prepare("SELECT so.*,COALESCE(ss.custom_name,ss.original_name) AS svc_name,ss.category FROM smm_orders so LEFT JOIN smm_services ss ON so.service_id=ss.id WHERE so.user_id=? ORDER BY so.id DESC LIMIT 50");
    if($res){
        $res->bind_param("i",$user_id);
        $res->execute();
        $r=$res->get_result();
        while($row=$r->fetch_assoc()) $orders[]=$row;
        $res->close();
    }
}

$status_colors=['completed'=>'bg-green-500/10 text-green-400','processing'=>'bg-blue-500/10 text-blue-400','pending'=>'bg-yellow-500/10 text-yellow-400','failed'=>'bg-red-500/10 text-red-400','canceled'=>'bg-white/10 text-white/60','partial'=>'bg-orange-500/10 text-orange-400','awaiting_payment'=>'bg-purple-500/10 text-purple-400'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>SMM Orders – <?=htmlspecialchars($setting['store_name'])?></title>
<link rel="icon" href="<?=htmlspecialchars($setting['fav_icon']??'')?>">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=DynaPuff:wght@400;600&display=swap" rel="stylesheet">
<script>
tailwind.config={theme:{extend:{colors:{themeDark:'#ffffff',themeBlue:'#557C93',themeGreen:'#80bf15'},fontFamily:{poppins:['Poppins'],dynapuff:['DynaPuff','cursive']}}}}
</script>
<style>
body{font-family:'Poppins',sans-serif;
            background: hsla(213, 77%, 14%, 1);
            background: linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -moz-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            background: -webkit-linear-gradient(90deg, hsla(213, 77%, 14%, 1) 0%, hsla(202, 27%, 45%, 1) 100%);
            filter: progid: DXImageTransform.Microsoft.gradient( startColorstr="#08203E", endColorstr="#557C93", GradientType=1 );
            background-attachment:fixed;color:#ffffff;overflow-x:hidden}
.glass-panel{background:rgba(255,255,255,.1);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.1)}
.order-card:active{transform:scale(.98)}
.detail-panel{position:fixed;inset:0;z-index:100;display:flex;align-items:flex-end;justify-content:center;background:rgba(0,0,0,.6);backdrop-filter:blur(8px);opacity:0;pointer-events:none;transition:opacity .25s}
.detail-panel.show{opacity:1;pointer-events:all}
.detail-box{background:linear-gradient(180deg,rgba(8,32,62,.97),#08203E);border-radius:32px 32px 0 0;padding:24px 20px 44px;width:100%;max-width:480px;transform:translateY(100%);transition:transform .35s cubic-bezier(.175,.885,.32,1.1);border-top:1px solid rgba(255,255,255,.1)}
.detail-panel.show .detail-box{transform:translateY(0)}
.d-pill{width:40px;height:4px;background:rgba(255,255,255,.1);border-radius:99px;margin:0 auto 20px}
.d-row{display:flex;justify-content:space-between;align-items:flex-start;padding:10px 0;border-bottom:1px solid rgba(255,255,255,.06)}
.d-row:last-child{border:none}
.progress-track{height:5px;border-radius:99px;background:rgba(255,255,255,.08);overflow:hidden;margin-top:8px}
.progress-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,#3b82f6,#8b5cf6);transition:width .5s}
</style>
</head>
<body class="pb-32">

<!-- HEADER -->
<header class="fixed top-0 w-full z-40 glass-panel h-16">
  <div class="max-w-md mx-auto px-4 h-full flex items-center justify-between">
    <a href="smm" class="w-9 h-9 rounded-full bg-white/10 flex items-center justify-center border border-white/10">
      <i class="fa-solid fa-arrow-left text-themeDark text-xs"></i>
    </a>
    <div class="font-bold text-lg text-white font-dynapuff tracking-wider">My SMM Orders</div>
    <a href="smm" class="px-3 py-1.5 rounded-full bg-white text-black text-xs font-bold shadow-lg">+ New</a>
  </div>
</header>

<main class="max-w-md mx-auto pt-20 px-3">

  <!-- Stats row -->
  <?php
  $counts=['total'=>count($orders),'processing'=>0,'completed'=>0,'pending'=>0];
  foreach($orders as $o){
    $s=strtolower($o['status']);
    if(isset($counts[$s])) $counts[$s]++;
  }
  ?>
  <div class="grid grid-cols-4 gap-2 mb-5">
    <?php foreach(['total'=>['All','fa-list'],'pending'=>['Pending','fa-clock'],'processing'=>['Active','fa-bolt'],'completed'=>['Done','fa-check']] as $k=>[$label,$ico]): ?>
    <div class="glass-panel rounded-2xl p-3 text-center">
      <i class="fa-solid <?=$ico?> text-white/30 text-xs mb-1"></i>
      <div class="text-base font-black text-white"><?=$counts[$k]?></div>
      <div class="text-[9px] text-white/40 font-bold uppercase tracking-wider"><?=$label?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if(empty($orders)): ?>
  <div class="glass-panel rounded-3xl p-10 text-center">
    <div class="text-5xl mb-4">📭</div>
    <h3 class="font-black text-white text-base mb-2">No orders yet</h3>
    <p class="text-[11px] text-white/50 mb-5">Browse services and place your first order!</p>
    <a href="smm" class="inline-block bg-white text-black font-black text-sm px-6 py-3 rounded-2xl shadow-lg">Browse Services →</a>
  </div>
  <?php else: ?>

  <h3 class="text-sm font-bold text-white flex items-center gap-2 mb-3 px-1">
    <span class="w-1 h-4 bg-blue-500 rounded-full"></span> Recent Orders
  </h3>

  <div class="space-y-3">
  <?php foreach($orders as $o):
    $st=strtolower($o['status']);
    $sc=$status_colors[$st]??'bg-gray-100 text-gray-600';
    $qty=(int)$o['quantity'];
    $rem=(int)($o['remains']??$qty);
    $done_pct=$qty>0?min(100,round((($qty-$rem)/$qty)*100)):0;
    $icons2=['Instagram'=>'📸','Facebook'=>'📘','YouTube'=>'▶️','TikTok'=>'🎵','Twitter'=>'🐦','Telegram'=>'✈️'];
    $ico='⭐'; foreach($icons2 as $k=>$v) if(stripos($o['category']??'',$k)!==false){$ico=$v;break;}
  ?>
  <div class="glass-panel rounded-2xl p-4 order-card cursor-pointer transition" onclick='showDetail(<?=json_encode($o)?>)'>
    <div class="flex items-start gap-3">
      <div class="w-10 h-10 rounded-2xl bg-white/10 flex items-center justify-center text-xl flex-shrink-0"><?=$ico?></div>
      <div class="flex-1 min-w-0">
        <div class="text-[13px] font-black text-white truncate"><?=htmlspecialchars($o['svc_name']??'SMM Service')?></div>
        <div class="text-[10px] text-white/50 font-medium mt-0.5"><?=htmlspecialchars($o['category']??'')?> · <?=date('M j, g:i A',strtotime($o['created_at']))?></div>
        <?php if($st==='processing'||$st==='partial'): ?>
        <div class="progress-track mt-2">
          <div class="progress-fill" style="width:<?=$done_pct?>%"></div>
        </div>
        <div class="text-[9px] text-white/40 font-bold text-right mt-1"><?=$done_pct?>% done</div>
        <?php endif; ?>
      </div>
      <div class="text-right flex-shrink-0">
        <span class="inline-block text-[9px] font-black px-2 py-1 rounded-lg <?=$sc?>"><?=$o['status']?></span>
        <div class="text-[14px] font-black text-white mt-1">₹<?=number_format($o['price_paid'],2)?></div>
        <div class="text-[9px] text-white/40 font-bold"><?=number_format($qty)?> qty</div>
      </div>
    </div>
    <?php if($o['smm_order_id']&&$rem>0): ?>
    <div class="mt-2 flex gap-2">
      <span class="text-[9px] bg-white/10 px-2 py-1 rounded-lg font-bold text-white/60">ID #<?=$o['smm_order_id']?></span>
      <span class="text-[9px] bg-yellow-500/10 text-yellow-400 px-2 py-1 rounded-lg font-bold">Remains: <?=number_format($rem)?></span>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php include 'footer.php'; ?>
</main>

<!-- DETAIL PANEL -->
<div class="detail-panel" id="detPanel" onclick="closePanelOut(event)">
  <div class="detail-box">
    <div class="d-pill"></div>
    <div id="panelContent"></div>
  </div>
</div>

<script>
function showDetail(o){
  const sc={completed:'bg-green-500/10 text-green-400',processing:'bg-blue-500/10 text-blue-400',pending:'bg-yellow-500/10 text-yellow-400',failed:'bg-red-500/10 text-red-400',canceled:'bg-white/10 text-white/60',partial:'bg-orange-500/10 text-orange-400'}
  const badge=`<span class="text-[10px] font-black px-2 py-1 rounded-lg ${sc[o.status]||'bg-white/10 text-white/60'}">${o.status}</span>`;
  const rows=[
    ['Order Ref',`<span class="font-mono text-[11px]">${o.order_ref}</span>`],
    ['Service',o.svc_name||'—'],
    ['Category',o.category||'—'],
    ['Link',`<a href="${o.target_link}" target="_blank" class="text-blue-600 text-[11px] break-all underline">${o.target_link}</a>`],
    ['Quantity',Number(o.quantity).toLocaleString()],
    ['Amount Paid','₹'+parseFloat(o.price_paid).toFixed(2)],
    ['Payment',o.payment_method],
    ['SMM Order ID',o.smm_order_id?'#'+o.smm_order_id:'Not sent yet'],
    ['Status',badge],
    ['Remains',o.remains!=null?Number(o.remains).toLocaleString():'—'],
    ['Start Count',o.start_count!=null?Number(o.start_count).toLocaleString():'—'],
    ['Date',new Date(o.created_at).toLocaleString()],
  ];
  document.getElementById('panelContent').innerHTML=`
    <div class="text-[15px] font-black text-white mb-4">${o.svc_name||'Order Details'}</div>
    ${rows.map(([k,v])=>`<div class="d-row"><span class="text-[10px] text-white/50 font-bold uppercase tracking-wide">${k}</span><span class="text-[12px] font-bold text-white text-right max-w-[55%]">${v||'—'}</span></div>`).join('')}
    ${o.notes?`<div class="mt-3 bg-red-50 border border-red-100 rounded-xl p-3 text-[11px] text-red-600 font-bold"><i class="fa-solid fa-circle-info mr-1"></i>${o.notes}</div>`:''}
  `;
  document.getElementById('detPanel').classList.add('show');
  document.body.style.overflow='hidden';
}
function closePanelOut(e){if(e.target===document.getElementById('detPanel')){document.getElementById('detPanel').classList.remove('show');document.body.style.overflow='';}}
</script>
</body>
</html>
