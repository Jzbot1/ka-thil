<?php
// telegram_notify.php
function sendTelegramNotification($message) {
    if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID')) {
        return false; // Configuration missing
    }

    $botToken = TELEGRAM_BOT_TOKEN;
    $chatId   = TELEGRAM_CHAT_ID;

    if (empty($botToken) || empty($chatId)) {
        return false;
    }

    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

    // ✅ FIX: Send JSON (Telegram API requires JSON or URL-encoded, NOT multipart form)
    $payload = json_encode([
        'chat_id'    => $chatId,
        'text'       => $message,
        'parse_mode' => 'HTML'   // ✅ Use HTML mode — more forgiving than Markdown
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,            $url);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        10);           // ✅ Don't hang forever
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        // ✅ Needed on XAMPP/Windows
    curl_setopt($ch, CURLOPT_HTTPHEADER,     [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    // Optional: log failures
    if ($err) {
        error_log("[Telegram] cURL error: $err");
        return false;
    }

    $result = json_decode($response, true);
    return isset($result['ok']) && $result['ok'] === true;
}
?>
