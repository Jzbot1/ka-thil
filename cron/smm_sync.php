<?php
/**
 * cron/smm_sync.php
 * -----------------------------------------------------------------------
 * CRON JOB — Sync pending SMM orders with cheapestsmmpanels.com
 *
 * Schedule: Every 5–15 minutes (recommended)
 *
 * cPanel Cron Command (HTTP):
 *   curl -s "https://yourdomain.com/mobile/cron/smm_sync.php?cron_token=YOUR_TOKEN"
 *
 * cPanel Cron Command (CLI):
 *   php /home/user/public_html/mobile/cron/smm_sync.php
 *
 * SECURITY: Requests via HTTP must include a matching cron_token.
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../SmmPanelApi.php';

// ─── Logging Helper ───────────────────────────────────────────────────────────
function log_smm(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents(__DIR__ . '/../fulfillment_error_log.txt', $line, FILE_APPEND);
    if (php_sapi_name() === 'cli') {
        echo $line;
    }
}

// ─── Load SMM Settings ────────────────────────────────────────────────────────
$setting_res = $conn->query("SELECT smm_api_url, smm_api_key, smm_cron_token FROM fav_setting LIMIT 1");
$setting = $setting_res ? $setting_res->fetch_assoc() : [];

// ─── Security Gate (HTTP only) ───────────────────────────────────────────────
if (php_sapi_name() !== 'cli') {
    $provided_token = $_GET['cron_token'] ?? '';
    $stored_token   = $setting['smm_cron_token'] ?? '';

    if (empty($stored_token) || !hash_equals($stored_token, $provided_token)) {
        http_response_code(403);
        die("403 Forbidden: Invalid or missing cron token.");
    }
}

// ─── Validate API Credentials ─────────────────────────────────────────────────
if (empty($setting['smm_api_url']) || empty($setting['smm_api_key'])) {
    log_smm("SKIP: SMM API URL or Key not configured in admin settings.");
    exit(0);
}

$api = new SmmPanelApi($setting['smm_api_url'], $setting['smm_api_key']);

// ─── STEP 1: Place Queued Orders (not yet sent to SMM panel) ─────────────────
$queued = [];
$res = $conn->query("
    SELECT * FROM smm_orders
    WHERE smm_order_id IS NULL AND status = 'pending'
    ORDER BY id ASC
    LIMIT 20
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $queued[] = $row;
    }
}

foreach ($queued as $q) {
    $params = [
        'service'  => (int)$q['smm_service_id'],
        'link'     => $q['target_link'],
        'quantity' => (int)$q['quantity'],
    ];
    if (!empty($q['runs']))     $params['runs']     = (int)$q['runs'];
    if (!empty($q['interval'])) $params['interval'] = (int)$q['interval'];

    $result = $api->order($params);

    if ($result && !empty($result->order)) {
        $smm_oid = (int)$result->order;
        $stmt = $conn->prepare("UPDATE smm_orders SET smm_order_id = ?, status = 'processing', sent_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $smm_oid, $q['id']);
        $stmt->execute();
        $stmt->close();
        log_smm("PLACED: Local SMM #" . $q['id'] . " → Provider Order #{$smm_oid}");
    } else {
        $err = isset($result->error) ? $result->error : ($api->last_error_msg ?: 'Unknown API error');
        $stmt = $conn->prepare("UPDATE smm_orders SET status = 'failed', notes = ?, last_checked = NOW() WHERE id = ?");
        $stmt->bind_param("si", $err, $q['id']);
        $stmt->execute();
        $stmt->close();
        log_smm("PLACE_FAIL: Local SMM #" . $q['id'] . " — " . $err);
    }
}

// ─── STEP 2: Sync Active Order Statuses ───────────────────────────────────────
$active = [];
$res2 = $conn->query("
    SELECT * FROM smm_orders
    WHERE smm_order_id IS NOT NULL
      AND status IN ('pending', 'processing', 'in progress', 'partial')
    ORDER BY last_checked ASC
    LIMIT 50
");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        $active[] = $row;
    }
}

if (!empty($active)) {
    // Batch check if >1, single check otherwise
    $smm_ids = array_map('intval', array_column($active, 'smm_order_id'));

    $batch_statuses = (count($smm_ids) > 1) ? $api->multiStatus($smm_ids) : [];

    foreach ($active as $ao) {
        $smm_oid = (int)$ao['smm_order_id'];
        $local_id = (int)$ao['id'];

        // Use batch result if available, else fallback to single request
        if (!empty($batch_statuses) && isset($batch_statuses[$smm_oid])) {
            $sd = (object)$batch_statuses[$smm_oid];
        } else {
            $sd = $api->status($smm_oid);
        }

        if (!$sd || !empty($sd->error)) {
            $note = isset($sd->error) ? $sd->error : 'API error';
            $conn->query("UPDATE smm_orders SET notes = '" . $conn->real_escape_string($note) . "', last_checked = NOW() WHERE id = $local_id");
            log_smm("STATUS_ERR: Local #{$local_id} → " . $note);
            continue;
        }

        // Normalize status
        $raw_status = strtolower(trim($sd->status ?? ''));
        $local_status = match(true) {
            in_array($raw_status, ['completed'])               => 'completed',
            in_array($raw_status, ['partial'])                 => 'partial',
            in_array($raw_status, ['canceled', 'cancelled'])   => 'canceled',
            in_array($raw_status, ['failed'])                  => 'failed',
            default                                            => 'processing',
        };

        $remains     = (int)($sd->remains ?? 0);
        $start_count = (int)($sd->start_count ?? 0);
        $charge      = (float)($sd->charge ?? 0);

        // Safe update
        $conn->query("
            UPDATE smm_orders 
            SET status      = '" . $conn->real_escape_string($local_status) . "',
                remains     = $remains,
                start_count = $start_count,
                charge      = $charge,
                last_checked = NOW()
            WHERE id = $local_id
        ");

        log_smm("SYNCED: Local #{$local_id} (SMM #{$smm_oid}) → {$local_status} | Remains: {$remains}");

        // If completed + linked to a local order, update that too
        if ($local_status === 'completed' && !empty($ao['local_order_id'])) {
            $safe_oid = $conn->real_escape_string($ao['local_order_id']);
            $conn->query("UPDATE orders SET status = 'completed' WHERE order_id = '$safe_oid'");
            log_smm("LOCAL ORDER COMPLETED: " . $ao['local_order_id']);
        }
    }
}

log_smm("Cron done. Queued={" . count($queued) . "} Synced={" . count($active) . "}");
echo "OK\n";
