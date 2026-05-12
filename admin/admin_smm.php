<?php
require_once __DIR__ . '/strict_admin.php';
// SmmPanelApi is now auto-loaded via config.php

$s_res = $conn->query("SELECT smm_api_url,smm_api_key,smm_cron_token,smm_profit_margin FROM fav_setting LIMIT 1");
$s = ($s_res ? $s_res->fetch_assoc() : []) ?? [];
$api = new SmmPanelApi($s['smm_api_url']??'', $s['smm_api_key']??'');

// ── AJAX handlers ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    header('Content-Type: application/json');
    $act = $_POST['act'] ?? '';

    // Save API settings
    if ($act==='save_api') {
        $url = trim($_POST['url']); $key = trim($_POST['key']); $tok = trim($_POST['tok'])?:bin2hex(random_bytes(16));
        $profit = (float)($_POST['profit']??5);
        $st = $conn->prepare("UPDATE fav_setting SET smm_api_url=?,smm_api_key=?,smm_cron_token=?,smm_profit_margin=? WHERE id=1");
        $st->bind_param("sssd",$url,$key,$tok,$profit); $st->execute(); $st->close();
        echo json_encode(['ok'=>true,'tok'=>$tok]); exit;
    }

    // Test connection
    if ($act==='test') {
        $a = new SmmPanelApi($_POST['url'],$_POST['key']);
        $b = $a->balance();
        if ($b && isset($b->balance)) {
            $bal = (float)$b->balance;
            $cur = $b->currency ?? 'USD';
            if (strtoupper($cur) === 'USD') {
                $bal = $bal * USD_TO_INR;
                $cur = 'INR';
            }
            echo json_encode(['ok'=>true,'bal'=>$bal,'cur'=>$cur]);
        } else {
            echo json_encode(['ok'=>false,'err'=>$a->last_error_msg?:'Bad key']);
        }
        exit;
    }

    // Get live balance
    if ($act==='get_balance') {
        $b = $api->balance();
        if ($b && isset($b->balance)) {
            $usd_bal = (float)$b->balance;
            $inr_bal = number_format($usd_bal * USD_TO_INR, 2);
            echo json_encode(['ok'=>true, 'bal' => $inr_bal, 'cur' => 'INR', 'usd_bal' => $usd_bal]);
        } else {
            echo json_encode(['ok'=>false,'err'=>'API Error']);
        }
        exit;
    }

    // Fetch services from API for selection
    if ($act==='fetch_api_services') {
        $services = $api->services();
        if (!$services) { echo json_encode(['ok'=>false,'err'=>'API error']); exit; }
        echo json_encode(['ok'=>true, 'services'=>$services]); exit;
    }

    // Sync selected services
    if ($act==='sync_selected') {
        $all_services = $api->services();
        $selected_ids = json_decode($_POST['selected_ids'], true) ?? [];
        if (!$all_services) { echo json_encode(['ok'=>false,'err'=>'API error']); exit; }
        
        $profit_margin = (float)($s['smm_profit_margin']??5) / 100;
        $inserted=0; $updated=0;
        foreach ($all_services as $svc) {
            $pid=(int)($svc->service??0);
            if (!in_array($pid, $selected_ids)) continue;

            $cat=$conn->real_escape_string($svc->category??'General');
            $name=$conn->real_escape_string($svc->name??'');
            $rate=(float)($svc->rate??0); 
            $rate_inr = $rate * USD_TO_INR;
            
            $mn=(int)($svc->min??10); $mx=(int)($svc->max??10000);
            $type=$conn->real_escape_string($svc->type??'Default');
            
            // Auto-calculate sell price with profit margin
            $sell_price = round($rate_inr * (1 + $profit_margin), 2);

            $exists=$conn->query("SELECT id FROM smm_services WHERE provider_id=$pid")->fetch_assoc();
            if ($exists) {
                $conn->query("UPDATE smm_services SET category='$cat',original_name='$name',original_rate=$rate_inr,min_order=$mn,max_order=$mx,type='$type',synced_at=NOW() WHERE provider_id=$pid");
                $updated++;
            } else {
                $conn->query("INSERT INTO smm_services (provider_id,category,original_name,original_rate,custom_price,min_order,max_order,type,synced_at) VALUES ($pid,'$cat','$name',$rate_inr,$sell_price,$mn,$mx,'$type',NOW())");
                $inserted++;
            }
        }
        echo json_encode(['ok'=>true,'inserted'=>$inserted,'updated'=>$updated]); exit;
    }

    // Save service overrides (bulk)
    if ($act==='save_services') {
        $rows = json_decode($_POST['rows'],true);
        foreach ($rows as $r) {
            $id=(int)$r['id']; $cn=$conn->real_escape_string($r['custom_name']); $cp=(float)$r['custom_price']; $active=(int)$r['is_active'];
            $conn->query("UPDATE smm_services SET custom_name=".($cn?"'$cn'":'NULL').",custom_price=".($cp>0?$cp:'NULL').",is_active=$active WHERE id=$id");
        }
        echo json_encode(['ok'=>true]); exit;
    }

    // Toggle single service active
    if ($act==='toggle') {
        $id=(int)$_POST['id']; $v=(int)$_POST['v'];
        $conn->query("UPDATE smm_services SET is_active=$v WHERE id=$id");
        echo json_encode(['ok'=>true]); exit;
    }
    // Bulk update markup based on settings
    if ($act==='bulk_markup') {
        $profit_margin = (float)($s['smm_profit_margin']??5) / 100;
        $conn->query("UPDATE smm_services SET custom_price = ROUND(original_rate * (1 + $profit_margin), 2)");
        echo json_encode(['ok'=>true]); exit;
    }
    exit;
}

// ── Load categories + services (safe: check tables exist) ────────────────────
$cats=[]; $svc_map=[]; $total_svc=0; $active_svc=0; $total_orders=0;
$tbl_svc  = $conn->query("SHOW TABLES LIKE 'smm_services'");
$tbl_ord  = $conn->query("SHOW TABLES LIKE 'smm_orders'");
$has_svc  = $tbl_svc  && $tbl_svc->num_rows  > 0;
$has_ord  = $tbl_ord  && $tbl_ord->num_rows  > 0;

if($has_svc){
    $res=$conn->query("SELECT * FROM smm_services ORDER BY category,id");
    if($res) while($r=$res->fetch_assoc()){
        $cats[$r['category']]=true;
        $svc_map[$r['category']][]=$r;
    }
    $cats=array_keys($cats);
    $r1=$conn->query("SELECT COUNT(*) c FROM smm_services"); if($r1) $total_svc=(int)$r1->fetch_assoc()['c'];
    $r2=$conn->query("SELECT COUNT(*) c FROM smm_services WHERE is_active=1"); if($r2) $active_svc=(int)$r2->fetch_assoc()['c'];
}
if($has_ord){
    $r3=$conn->query("SELECT COUNT(*) c FROM smm_orders"); if($r3) $total_orders=(int)$r3->fetch_assoc()['c'];
    
    // Calculate total profit: SUM(price_paid - (charge * USD_TO_INR))
    // We assume charge is in USD if original_rate sync is in USD. 
    $r4=$conn->query("SELECT SUM(price_paid - (charge * " . USD_TO_INR . ")) as profit FROM smm_orders WHERE status='completed'");
    $total_profit = (float)($r4->fetch_assoc()['profit'] ?? 0);
}
$base_url = (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.$_SERVER['HTTP_HOST'];
$cron_url = $base_url.'/mobile/cron/smm_sync.php?cron_token='.($s['smm_cron_token']??'');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SMM Manager</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
body{font-family:'Inter',sans-serif;background:#080f1e;color:#e2e8f0}
.glass{background:rgba(255,255,255,.05);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.08)}
.inp{background:#0f1929;border:1px solid #1e3a5f;color:#e2e8f0;border-radius:8px;padding:8px 12px;width:100%;outline:none;font-size:13px}
.inp:focus{border-color:#6366f1}
.btn{font-weight:700;padding:9px 18px;border-radius:10px;cursor:pointer;transition:all .15s;font-size:13px;display:inline-flex;align-items:center;gap:6px}
.btn:active{transform:scale(.97)}
.btn-p{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff}
.btn-g{background:linear-gradient(135deg,#059669,#10b981);color:#fff}
.btn-o{background:linear-gradient(135deg,#d97706,#f59e0b);color:#fff}
.btn-sm{padding:5px 12px;font-size:11px;border-radius:7px}
.badge{font-size:9px;font-weight:800;padding:2px 8px;border-radius:99px;text-transform:uppercase}
.b-on{background:#064e3b;color:#6ee7b7}.b-off{background:#450a0a;color:#fca5a5}
.b-p{background:#1e3a5f;color:#93c5fd}.b-done{background:#064e3b;color:#6ee7b7}.b-fail{background:#450a0a;color:#fca5a5}
#toast{position:fixed;bottom:20px;right:20px;z-index:9999;display:none}
.svc-card { display: none; }
@media (max-width: 768px) {
  .svc-table-container { display: none; }
  .svc-card { display: block; }
}
</style>
</head>
<body class="pb-20">

<nav class="h-16 glass border-b border-white/10 flex items-center px-6 sticky top-0 z-50 justify-between">
  <div class="flex items-center gap-3">
    <a href="settings.php" class="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center hover:bg-white/20"><i class="fa-solid fa-arrow-left text-sm"></i></a>
    <div><div class="font-black text-white text-sm">SMM Manager</div><div class="text-[10px] text-slate-400">Service catalog & order control</div></div>
  </div>
  <div class="flex gap-3 text-xs">
    <div class="glass rounded-xl px-3 py-2 text-center cursor-pointer hover:bg-white/10" onclick="fetchBalance()">
      <div class="font-black text-rose-400" id="api_bal_val">...</div>
      <div class="text-slate-500 uppercase tracking-tighter" id="api_bal_cur">Balance</div>
    </div>
    <div class="glass rounded-xl px-3 py-2 text-center"><div class="font-black text-indigo-400"><?=$total_svc?></div><div class="text-slate-500">Services</div></div>
    <div class="glass rounded-xl px-3 py-2 text-center"><div class="font-black text-emerald-400"><?=$active_svc?></div><div class="text-slate-500">Active</div></div>
    <div class="glass rounded-xl px-3 py-2 text-center"><div class="font-black text-amber-400">₹<?=number_format($total_profit,0)?></div><div class="text-slate-500">Profit</div></div>
  </div>
</nav>

<div class="max-w-6xl mx-auto px-4 py-6 space-y-6">

  <!-- ① API Config -->
  <div class="glass rounded-2xl p-5">
    <h2 class="font-black text-base mb-4 flex items-center gap-2"><i class="fa-solid fa-key text-indigo-400"></i> API Configuration</h2>
    <div class="grid md:grid-cols-2 gap-4 mb-4">
      <div><label class="text-xs text-slate-400 font-bold block mb-1">Panel API URL</label>
        <input class="inp" id="api_url" value="<?=htmlspecialchars($s['smm_api_url']??'https://cheapestsmmpanels.com/api/v2')?>"></div>
      <div><label class="text-xs text-slate-400 font-bold block mb-1">API Key</label>
        <input class="inp" id="api_key" value="<?=htmlspecialchars($s['smm_api_key']??'')?>"></div>
      <div><label class="text-xs text-slate-400 font-bold block mb-1">Profit Margin (%)</label>
        <input class="inp" id="api_profit" type="number" step="0.1" value="<?=htmlspecialchars($s['smm_profit_margin']??'5.0')?>"></div>
    </div>
    <div class="mb-4">
      <label class="text-xs text-slate-400 font-bold block mb-1">Cron Token</label>
      <div class="flex gap-2">
        <input class="inp flex-1" id="cron_tok" value="<?=htmlspecialchars($s['smm_cron_token']??'')?>">
        <button class="btn glass btn-sm" onclick="newTok()"><i class="fa-solid fa-dice"></i></button>
      </div>
      <div class="mt-2 bg-slate-900 rounded-lg px-3 py-2 text-xs font-mono text-indigo-300 break-all select-all" id="cron_url_el"><?=htmlspecialchars($cron_url)?></div>
      <p class="text-[10px] text-slate-500 mt-1">Add to cPanel Cron: every 10 min → <code>curl -s "URL_ABOVE"</code></p>
    </div>
    <div class="flex flex-wrap gap-3">
      <button class="btn btn-p" onclick="saveApi()"><i class="fa-solid fa-floppy-disk"></i> Save</button>
      <button class="btn btn-g" onclick="testApi()"><i class="fa-solid fa-plug"></i> Test</button>
      <button class="btn btn-o" id="syncBtn" onclick="openSyncModal()"><i class="fa-solid fa-rotate"></i> Sync Selected Services</button>
      <button class="btn glass text-indigo-400" onclick="bulkMarkup()"><i class="fa-solid fa-percent"></i> Apply Markup to All</button>
    </div>
  </div>

  <!-- ② Category Filter + Services Table -->
  <div class="glass rounded-2xl p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <h2 class="font-black text-base flex items-center gap-2"><i class="fa-solid fa-list text-emerald-400"></i> Services Catalog</h2>
      <div class="flex gap-2 flex-wrap items-center">
        <input class="inp" style="width:200px" placeholder="Search services…" id="svcSearch" oninput="filterRows()">
        <button class="btn btn-p btn-sm" onclick="saveAll()"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
      </div>
    </div>

    <!-- Category Tabs -->
    <div class="flex gap-2 flex-wrap mb-4" id="catTabs">
      <button class="btn glass btn-sm active-tab" onclick="filterCat('ALL',this)">All</button>
      <?php foreach($cats as $c): ?>
      <button class="btn glass btn-sm" onclick="filterCat(<?=json_encode($c)?>,this)"><?=htmlspecialchars($c)?></button>
      <?php endforeach; ?>
    </div>

        <div class="svc-table-container overflow-x-auto">
          <table class="w-full text-xs">
            <thead><tr class="text-slate-500 border-b border-white/10 uppercase tracking-wider">
              <th class="pb-3 text-left pr-3 w-8">#</th>
              <th class="pb-3 text-left pr-3">Display Name</th>
              <th class="pb-3 text-left pr-3">Category</th>
              <th class="pb-3 text-right pr-3">Min/Max</th>
              <th class="pb-3 text-right pr-3">Cost ₹</th>
              <th class="pb-3 text-right pr-3">Your Price ₹ (<?=htmlspecialchars($s['smm_profit_margin']??'5')?>%)</th>
              <th class="pb-3 text-center">Active</th>
            </tr></thead>
            <tbody id="svcTable" class="divide-y divide-white/5">
            <?php foreach($svc_map as $cat=>$rows): foreach($rows as $r): 
              $display = $r['custom_name']?:$r['original_name'];
              $price   = $r['custom_price']?:round($r['original_rate'] * 1.05, 2); // 5% profit margin
            ?>
            <tr data-cat="<?=htmlspecialchars($cat)?>" data-id="<?=$r['id']?>" class="svc-row hover:bg-white/5 transition">
              <td class="py-2 pr-3 text-slate-500 font-mono"><?=$r['provider_id']?></td>
              <td class="py-2 pr-3">
                <input class="inp custom-name" style="min-width:200px" value="<?=htmlspecialchars($display)?>" placeholder="<?=htmlspecialchars($r['original_name'])?>">
              </td>
              <td class="py-2 pr-3 text-slate-400"><?=htmlspecialchars($cat)?></td>
              <td class="py-2 pr-3 text-right text-slate-400"><?=number_format($r['min_order'])?>/<?=number_format($r['max_order'])?></td>
              <td class="py-2 pr-3 text-right text-indigo-300 font-mono">₹<?=number_format($r['original_rate'],2)?></td>
              <td class="py-2 pr-3 text-right">
                <input class="inp custom-price text-right" style="width:90px" type="number" step="0.01" min="0" value="<?=number_format($price,2,'.','')?>">
              </td>
              <td class="py-2 text-center">
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" class="sr-only peer svc-toggle" <?=$r['is_active']?'checked':''?> onchange="toggleSvc(<?=$r['id']?>,this.checked?1:0)">
                  <div class="w-9 h-5 bg-slate-700 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                </label>
              </td>
            </tr>
            <?php endforeach; endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Mobile Card Layout -->
        <div class="svc-cards space-y-4" id="svcCards">
          <?php foreach($svc_map as $cat=>$rows): foreach($rows as $r): 
            $display = $r['custom_name']?:$r['original_name'];
            $price   = $r['custom_price']?:round($r['original_rate'] * 1.05, 2);
          ?>
          <div data-cat="<?=htmlspecialchars($cat)?>" data-id="<?=$r['id']?>" class="svc-row svc-card glass p-4 rounded-2xl space-y-3">
            <div class="flex justify-between items-start">
              <div class="text-[10px] font-mono text-slate-500">ID: <?=$r['provider_id']?></div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" class="sr-only peer svc-toggle" <?=$r['is_active']?'checked':''?> onchange="toggleSvc(<?=$r['id']?>,this.checked?1:0)">
                <div class="w-8 h-4 bg-slate-700 rounded-full peer peer-checked:bg-indigo-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
              </label>
            </div>
            <div>
              <label class="text-[9px] text-slate-500 uppercase font-bold">Display Name</label>
              <input class="inp custom-name mt-1" value="<?=htmlspecialchars($display)?>">
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-[9px] text-slate-500 uppercase font-bold">Cost (₹)</label>
                <div class="text-xs font-mono mt-1 text-indigo-300">₹<?=number_format($r['original_rate'],2)?></div>
              </div>
              <div>
                <label class="text-[9px] text-slate-500 uppercase font-bold">Price (₹)</label>
                <input class="inp custom-price text-right mt-1" type="number" step="0.01" value="<?=number_format($price,2,'.','')?>">
              </div>
            </div>
            <div class="text-[10px] text-slate-400 italic"><?=htmlspecialchars($cat)?></div>
          </div>
          <?php endforeach; endforeach; ?>
        </div>
      <?php if(empty($svc_map)): ?>
      <div class="text-center py-16 text-slate-500">
        <i class="fa-solid fa-inbox text-4xl mb-3 block opacity-20"></i>
        <p class="font-bold">No services yet.</p>
        <p class="text-xs mt-1">Click <strong>Sync Services from API</strong> above.</p>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ③ Recent Orders -->
  <div class="glass rounded-2xl p-5">
    <h2 class="font-black text-base mb-4 flex items-center gap-2"><i class="fa-solid fa-receipt text-amber-400"></i> Recent SMM Orders</h2>
    <?php
    $ord_rows=[];
    if($has_ord && $has_svc){
        $orders=$conn->query("SELECT so.*,ss.custom_name,ss.original_name,u.username FROM smm_orders so LEFT JOIN smm_services ss ON so.service_id=ss.id LEFT JOIN users u ON so.user_id=u.id ORDER BY so.id DESC LIMIT 30");
        if($orders) while($r=$orders->fetch_assoc()) $ord_rows[]=$r;
    }
    ?>
    <?php if(empty($ord_rows)): ?>
    <p class="text-slate-500 text-sm text-center py-8">No orders placed yet.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-xs">
        <thead><tr class="text-slate-500 border-b border-white/10 uppercase">
          <th class="pb-2 text-left pr-3">Ref</th>
          <th class="pb-2 text-left pr-3">User</th>
          <th class="pb-2 text-left pr-3">Service</th>
          <th class="pb-2 text-right pr-3">Qty</th>
          <th class="pb-2 text-right pr-3">Paid ₹</th>
          <th class="pb-2 text-right pr-3">Method</th>
          <th class="pb-2 text-left pr-3">Status</th>
          <th class="pb-2 text-right">Remains</th>
        </tr></thead>
        <tbody class="divide-y divide-white/5">
        <?php foreach($ord_rows as $o):
          $st=strtolower($o['status']);
          $bc=['pending'=>'b-p','processing'=>'b-p','completed'=>'b-done','failed'=>'b-fail','canceled'=>'b-fail'][$st]??'b-p';
        ?>
        <tr class="hover:bg-white/5">
          <td class="py-2 pr-3 font-mono text-slate-400"><?=substr($o['order_ref'],0,12)?></td>
          <td class="py-2 pr-3 font-bold"><?=htmlspecialchars($o['username']??'Guest')?></td>
          <td class="py-2 pr-3 text-slate-300 max-w-[180px] truncate" title="<?=htmlspecialchars($o['custom_name']??$o['original_name']??'')?>"><?=htmlspecialchars(substr($o['custom_name']??$o['original_name']??'',0,30))?></td>
          <td class="py-2 pr-3 text-right"><?=number_format($o['quantity'])?></td>
          <td class="py-2 pr-3 text-right text-emerald-400 font-bold">₹<?=number_format($o['price_paid'],2)?></td>
          <td class="py-2 pr-3 text-right text-slate-400"><?=$o['payment_method']?></td>
          <td class="py-2 pr-3"><span class="badge <?=$bc?>"><?=$o['status']?></span></td>
          <td class="py-2 text-right text-amber-400"><?=$o['remains']??'—'?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- SYNC MODAL -->
<div id="syncModal" class="fixed inset-0 z-[100] hidden">
    <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" onclick="closeSyncModal()"></div>
    <div class="absolute inset-x-4 top-20 bottom-10 max-w-4xl mx-auto glass rounded-3xl overflow-hidden flex flex-col">
        <div class="p-5 border-b border-white/10 flex justify-between items-center">
            <h3 class="font-black text-lg">Select Services to Import</h3>
            <div class="flex gap-2">
                <button class="btn glass btn-sm" onclick="selectAllApi(true)">All</button>
                <button class="btn glass btn-sm" onclick="selectAllApi(false)">None</button>
                <button class="btn btn-p btn-sm" onclick="importSelected()"><i class="fa-solid fa-download"></i> Import Selected</button>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-4" id="apiSvcList">
            <div class="text-center py-20 text-slate-500"><i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2"></i><br>Fetching services...</div>
        </div>
    </div>
</div>

<div id="toast"><div id="toast-in" class="rounded-2xl px-5 py-3 text-sm font-bold flex items-center gap-3"></div></div>

<style>
.active-tab{background:linear-gradient(135deg,#6366f1,#8b5cf6)!important;color:#fff!important}
</style>
<script>
function toast(m,ok=true){
  const t=document.getElementById('toast'),i=document.getElementById('toast-in');
  i.className=`rounded-2xl px-5 py-3 text-sm font-bold flex items-center gap-3 ${ok?'bg-emerald-600':'bg-rose-600'} text-white`;
  i.innerHTML=`<i class="fa-solid fa-${ok?'check-circle':'circle-exclamation'}"></i>${m}`;
  t.style.display='block'; setTimeout(()=>t.style.display='none',4000);
}
async function post(d){
  const fd=new FormData();
  for(const[k,v]of Object.entries(d)) fd.append(k,v);
  return(await fetch(location.href,{method:'POST',body:fd})).json();
}
async function saveApi(){
  const r=await post({act:'save_api',url:document.getElementById('api_url').value,key:document.getElementById('api_key').value,tok:document.getElementById('cron_tok').value,profit:document.getElementById('api_profit').value});
  if(r.ok){document.getElementById('cron_tok').value=r.tok;updateCronUrl(r.tok);toast('Saved!');}else toast('Failed',false);
}
async function testApi(){
  toast('Testing…');
  const r=await post({act:'test',url:document.getElementById('api_url').value,key:document.getElementById('api_key').value});
  r.ok?toast(`Connected! Balance: ${r.cur} ${parseFloat(r.bal).toFixed(4)}`):toast('Failed: '+r.err,false);
}
async function openSyncModal() {
  document.getElementById('syncModal').classList.remove('hidden');
  const list = document.getElementById('apiSvcList');
  list.innerHTML = '<div class="text-center py-20 text-slate-500"><i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2"></i><br>Fetching services...</div>';
  const r = await post({act:'fetch_api_services'});
  if(!r.ok) { list.innerHTML = `<div class="text-center py-20 text-rose-500">${r.err}</div>`; return; }
  
  let html = '';
  let currentCat = '';
  r.services.forEach(s => {
    if(s.category !== currentCat) {
        currentCat = s.category;
        html += `<div class="pt-4 pb-2 border-b border-white/5 text-[10px] font-black uppercase tracking-widest text-indigo-400">${currentCat}</div>`;
    }
    html += `
    <label class="flex items-center gap-3 p-3 glass rounded-xl cursor-pointer hover:bg-white/10 transition">
        <input type="checkbox" class="api-svc-check w-4 h-4 rounded bg-slate-800 border-white/10 text-indigo-600" value="${s.service}">
        <div class="flex-1">
            <div class="text-xs font-bold">${s.name}</div>
            <div class="text-[10px] text-slate-500">ID: ${s.service} • Cost: $${s.rate}</div>
        </div>
    </label>`;
  });
  list.innerHTML = html;
}
function closeSyncModal() { document.getElementById('syncModal').classList.add('hidden'); }
function selectAllApi(v) { document.querySelectorAll('.api-svc-check').forEach(c => c.checked = v); }
async function importSelected() {
    const ids = Array.from(document.querySelectorAll('.api-svc-check:checked')).map(c => parseInt(c.value));
    if(!ids.length) return toast('Select at least one service', false);
    toast('Importing...');
    const r = await post({act:'sync_selected', selected_ids: JSON.stringify(ids)});
    if(r.ok) { toast(`Imported ${r.inserted} new, Updated ${r.updated}.`); setTimeout(()=>location.reload(), 1500); }
    else toast(r.err, false);
}
async function saveAll(){
  const rows=[];
  document.querySelectorAll('#svcTable tr[data-id]').forEach(tr=>{
    rows.push({id:tr.dataset.id,custom_name:tr.querySelector('.custom-name').value,custom_price:tr.querySelector('.custom-price').value,is_active:tr.querySelector('.svc-toggle').checked?1:0});
  });
  const r=await post({act:'save_services',rows:JSON.stringify(rows)});
  r.ok?toast('All changes saved!'):toast('Save failed',false);
}
async function bulkMarkup(){
  if(!confirm('This will reset all custom prices based on your Profit Margin setting. Proceed?')) return;
  toast('Updating prices...');
  const r = await post({act:'bulk_markup'});
  if(r.ok){ toast('Prices updated! Reloading...'); setTimeout(()=>location.reload(),1500); }
  else toast('Update failed',false);
}
async function toggleSvc(id,v){await post({act:'toggle',id,v});}
function filterCat(cat,btn){
  document.querySelectorAll('#catTabs button').forEach(b=>b.classList.remove('active-tab'));
  btn.classList.add('active-tab');
  document.querySelectorAll('.svc-row').forEach(r=>{
    r.style.display=(cat==='ALL'||r.dataset.cat===cat)?'':'none';
  });
  filterRows();
}
function filterRows(){
  const q=document.getElementById('svcSearch').value.toLowerCase();
  document.querySelectorAll('.svc-row').forEach(r=>{
    if(r.style.display==='none'&&q==='') return;
    const txt=r.querySelector('.custom-name').value.toLowerCase();
    r.style.display=(!q||txt.includes(q))?'':'none';
  });
}
function newTok(){
  const a=new Uint8Array(20);crypto.getRandomValues(a);
  document.getElementById('cron_tok').value=Array.from(a).map(b=>b.toString(16).padStart(2,'0')).join('');
}
function updateCronUrl(tok){
  document.getElementById('cron_url_el').textContent=location.origin+'/mobile/cron/smm_sync.php?cron_token='+tok;
}
function fetchBalance() {
  document.getElementById('api_bal_val').innerText = '...';
  const fd = new FormData(); fd.append('act','get_balance');
  fetch(location.href,{method:'POST',body:fd}).then(r=>r.json()).then(res=>{
    if(res.ok) {
      document.getElementById('api_bal_val').innerText = '₹' + res.bal;
      document.getElementById('api_bal_cur').innerText = 'INR Balance';
    } else {
      document.getElementById('api_bal_val').innerText = 'ERR';
    }
  });
}
document.addEventListener('DOMContentLoaded', fetchBalance);
</script>
</body></html>
