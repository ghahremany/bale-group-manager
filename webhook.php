<?php
declare(strict_types=1);

/**
 * ورودی وب‌هوک بله
 * آدرس پیشنهادی: https://your-domain/webhook.php?secret=XXXX
 * امنیت: توکن مخفی در query و/یا هدر X-Telegram-Bot-Api-Secret-Token
 */

require_once __DIR__ . '/app/bootstrap.php';

security_headers(false);
header('Content-Type: application/json; charset=utf-8');

if (!is_installed()) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'not installed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    // سلامت‌سنجی ساده؛ اطلاعات حساس برنمی‌گردانیم
    echo json_encode(['ok' => true, 'service' => 'bale-group-manager']);
    exit;
}

$secret = (string)config('bot.webhook_secret', '');
$given  = (string)($_GET['s'] ?? $_GET['secret'] ?? $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
if ($secret !== '' && !hash_equals($secret, $given)) {
    Logger::warn('webhook', 'unauthorized call', ['ip' => client_ip()]);
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

// محدودیت نرخ کلی برای جلوگیری از بمباران
if (RateLimit::hit('webhook:' . client_ip(), 300, 10)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'rate limited']);
    exit;
}

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    echo json_encode(['ok' => true]);
    exit;
}

$update = json_decode($raw, true);
if (!is_array($update)) {
    Logger::warn('webhook', 'invalid json body', ['ip' => client_ip(), 'len' => strlen($raw)]);
    echo json_encode(['ok' => true]);
    exit;
}

try {
    DB::q("INSERT INTO webhook_log (ip, ok, note, ts) VALUES (?,?,?,?)",
        [client_ip(), 1, isset($update['update_id']) ? 'update ' . (int)$update['update_id'] : 'no id', time()]);
    if (random_int(1, 300) === 1) {
        DB::q("DELETE FROM webhook_log WHERE ts < ?", [time() - 7 * 86400]);
    }
} catch (Throwable $e) {
    Logger::warn('webhook', 'log insert: ' . $e->getMessage());
}

// پاسخ سریع به بله و ادامه پردازش در همان درخواست (بدون انتظار کاربر)
echo json_encode(['ok' => true]);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
ignore_user_abort(true);
@set_time_limit(60);

Bot::run($update);
