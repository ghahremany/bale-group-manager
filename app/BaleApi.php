<?php
declare(strict_types=1);

/**
 * کلاینت API بله (tapi.bale.ai)
 * - cURL با connect/read timeout جداگانه (کد قبلی timeout 30 بدون connect timeout داشت و کل ربات را قفل می‌کرد)
 * - retry هوشمند + احترام به 429 (retry_after)
 * - محدودکننده نرخ ارسال (Bale حدود 30 پیام در ثانیه برای هر بات)
 * - بدون هیچ throw به بیرون: همیشه null/آرایه برمی‌گرداند تا حلقه main از کار نیفتد
 */
final class BaleApi
{
    private static ?array $cfg = null;
    private static float $lastSend = 0.0;
    private static int $sendWindowCount = 0;
    private static int $sendWindowStart = 0;
    public static ?string $lastError = null;

    public static function cfg(): array
    {
        if (self::$cfg === null) {
            self::$cfg = (array)config('bot', []);
        }
        return self::$cfg;
    }

    public static function hasToken(): bool
    {
        return trim((string)(self::cfg()['token'] ?? '')) !== '';
    }

    public static function base(): string
    {
        return rtrim((string)(self::cfg()['api_base'] ?? 'https://tapi.bale.ai'), '/');
    }

    /**
     * فراخوانی متد API
     * @param array<string,mixed> $data
     * @return array<string,mixed>|null
     */
    public static function call(string $method, array $data = [], int $timeout = 20): ?array
    {
        self::$lastError = null;
        $token = (string)(self::cfg()['token'] ?? '');
        if ($token === '') {
            self::$lastError = 'token not set';
            return null;
        }
        $url      = self::base() . '/bot' . $token . '/' . $method;
        $attempts = max(1, (int)(self::cfg()['max_retries'] ?? 3));

        for ($try = 1; $try <= $attempts; $try++) {
            if (!function_exists('curl_init')) {
                // سرورهای اشتراکی بدون cURL: انتقال جایگزین با استریم‌های PHP
                $res = self::callViaStream($url, $data, $timeout);
                if ($res !== null) {
                    return $res;
                }
                continue;
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_USERAGENT      => 'BaleGroupManager/1.0',
            ]);
            $body   = curl_exec($ch);
            $errNo  = curl_errno($ch);
            $errMsg = curl_error($ch);
            $code   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errNo === 0 && is_string($body) && $body !== '') {
                $json = json_decode($body, true);
                if (is_array($json)) {
                    if (($json['ok'] ?? false) === true) {
                        return $json;
                    }
                    $desc = (string)($json['description'] ?? 'api error');
                    $params = (array)($json['parameters'] ?? []);
                    self::$lastError = $code . ': ' . $desc;
                    // 429 → صبر کن
                    if ($code === 429 || str_contains(mb_strtolower($desc), 'too many')) {
                        $wait = (int)($params['retry_after'] ?? 2) + 1;
                        sleep(min($wait, 30));
                        continue;
                    }
                    // خطای منطقی (مثلاً پیام حذف‌شده) → بدون retry
                    self::logError($method, self::$lastError, $data);
                    return null;
                }
                self::$lastError = 'invalid json';
            } else {
                self::$lastError = 'curl ' . $errNo . ': ' . $errMsg;
            }
            // خطای شبکه/5xx → backoff نمایی
            if ($try < $attempts) {
                usleep((int)(300000 * $try));
            }
        }
        self::logError($method, (string)self::$lastError, $data);
        return null;
    }

    /**
     * انتقال جایگزین در نبود cURL (allow_url_fopen + استریم HTTPS)
     * @return array<string,mixed>|null
     */
    private static function callViaStream(string $url, array $data, int $timeout): ?array
    {
        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            self::$lastError = 'curl disabled and allow_url_fopen=Off';
            return null;
        }
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nUser-Agent: BaleGroupManager/1.0\r\n",
                'content'       => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'timeout'       => $timeout,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if (!is_string($body) || $body === '') {
            self::$lastError = 'stream request failed';
            return null;
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            self::$lastError = 'invalid json (stream)';
            return null;
        }
        if (($json['ok'] ?? false) === true) {
            return $json;
        }
        self::$lastError = (string)($json['description'] ?? 'api error');
        return null;
    }

    /** @param array<string,mixed> $payload */
    private static function logError(string $method, string $error, array $payload): void
    {
        if (in_array($method, ['deleteMessage', 'getChatMember'], true)) {
            return; // نویز زیاد
        }
        Logger::warn('api', "$method failed: $error", ['payload' => self::sanitize($payload)]);
    }

    private static function sanitize(array $p): array
    {
        foreach (['text', 'caption'] as $k) {
            if (isset($p[$k])) {
                $p[$k] = str_limit((string)$p[$k], 80);
            }
        }
        return $p;
    }

    /** throttle ارسال برای رعایت نرخ بله */
    private static function throttle(): void
    {
        $now = microtime(true);
        if ($now - self::$sendWindowStart >= 1.0) {
            self::$sendWindowStart = (int)$now;
            self::$sendWindowCount = 0;
        }
        self::$sendWindowCount++;
        if (self::$sendWindowCount > 28) {
            $sleep = 1 - ($now - self::$sendWindowStart);
            if ($sleep > 0) {
                usleep((int)($sleep * 1000000));
            }
            self::$sendWindowStart = (int)microtime(true);
            self::$sendWindowCount = 0;
        }
        self::$lastSend = microtime(true);
    }

    // ---------------- متدهای پرکاربرد ----------------

    /**
     * @param array<string,mixed>|null $replyMarkup
     * @return int|null message_id
     */
    public static function sendMessage(
        int|string $chatId,
        string $text,
        ?int $replyTo = null,
        ?array $replyMarkup = null,
        string $parseMode = '',
        bool $disablePreview = true
    ): ?int {
        self::throttle();
        $payload = [
            'chat_id' => $chatId,
            'text'    => str_limit($text, 4000),
        ];
        if ($replyTo) {
            $payload['reply_to_message_id'] = $replyTo;
        }
        if ($parseMode !== '') {
            $payload['parse_mode'] = $parseMode;
        }
        if ($replyMarkup) {
            $payload['reply_markup'] = $replyMarkup;
        }
        if ($disablePreview) {
            $payload['disable_web_page_preview'] = true;
        }
        $res = self::call('sendMessage', $payload);
        if ($res === null && ($payload['parse_mode'] ?? '') !== '') {
            // سازگاری: اگر کلاینت/API تگ‌ها را نپذیرفت، بدون فرمت دوباره بفرست
            $plain = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            unset($payload['parse_mode']);
            $payload['text'] = $plain;
            $res = self::call('sendMessage', $payload);
        }
        return isset($res['result']['message_id']) ? (int)$res['result']['message_id'] : null;
    }

    /**
     * ارسال فایل/سند (برای بکاپ). بله محدودیت حجم دارد (معمولاً ~۲۰MB).
     * @return array<string,mixed>|null
     */
    public static function sendDocument(int|string $chatId, string $filePath, string $caption = ''): ?array
    {
        if (!is_file($filePath)) {
            self::$lastError = 'file not found';
            return null;
        }
        $size = (int)filesize($filePath);
        if ($size > 20 * 1024 * 1024) {
            self::$lastError = 'file too large for Bale (>20MB)';
            return null;
        }
        $token = (string)(self::cfg()['token'] ?? '');
        if ($token === '' || !function_exists('curl_init')) {
            self::$lastError = 'token/curl missing';
            return null;
        }
        self::throttle();
        $url = self::base() . '/bot' . $token . '/sendDocument';
        $fields = [
            'chat_id'  => (string)$chatId,
            'document' => new CURLFile($filePath, 'application/sql', basename($filePath)),
        ];
        if ($caption !== '') {
            $fields['caption'] = str_limit($caption, 900);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $fields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);
        if (!is_string($body) || $body === '') {
            self::$lastError = 'upload failed: ' . $err;
            Logger::warn('api', 'sendDocument failed', ['error' => $err, 'size' => $size]);
            return null;
        }
        $json = json_decode($body, true);
        if (!is_array($json) || ($json['ok'] ?? false) !== true) {
            self::$lastError = 'upload rejected: ' . (string)($json['description'] ?? '');
            Logger::warn('api', 'sendDocument rejected', ['description' => self::$lastError]);
            return null;
        }
        return $json;
    }

    public static function editMessage(int|string $chatId, int $messageId, string $text, ?array $replyMarkup = null): ?array
    {
        $p = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => str_limit($text, 4000)];
        if ($replyMarkup) {
            $p['reply_markup'] = $replyMarkup;
        }
        return self::call('editMessageText', $p);
    }

    public static function deleteMessage(int|string $chatId, int $messageId): bool
    {
        self::throttle();
        return self::call('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId], 12) !== null;
    }

    public static function forwardMessage(int|string $chatId, int|string $fromChatId, int $messageId): ?array
    {
        return self::call('forwardMessage', [
            'chat_id'      => $chatId,
            'from_chat_id' => $fromChatId,
            'message_id'   => $messageId,
        ]);
    }

    public static function sendChatAction(int|string $chatId, string $action = 'typing'): void
    {
        self::call('sendChatAction', ['chat_id' => $chatId, 'action' => $action], 8);
    }

    public static function answerCallback(string $id, string $text = '', bool $alert = false): void
    {
        self::call('answerCallbackQuery', ['callback_query_id' => $id, 'text' => str_limit($text, 190), 'show_alert' => $alert], 10);
    }

    public static function getMe(): ?array
    {
        $r = self::call('getMe', [], 15);
        return $r['result'] ?? null;
    }

    public static function getChat(int|string $chatId): ?array
    {
        $r = self::call('getChat', ['chat_id' => $chatId], 15);
        return $r['result'] ?? null;
    }

    public static function getChatMember(int|string $chatId, int $userId): ?array
    {
        $r = self::call('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId], 12);
        return $r['result'] ?? null;
    }

    /** @return array<int,array<string,mixed>>|null */
    public static function getChatAdministrators(int|string $chatId): ?array
    {
        $r = self::call('getChatAdministrators', ['chat_id' => $chatId], 15);
        if (!$r) {
            return null;
        }
        return is_array($r['result'] ?? null) ? $r['result'] : null;
    }

    public static function getChatMemberCount(int|string $chatId): ?int
    {
        $r = self::call('getChatMembersCount', ['chat_id' => $chatId], 12);
        if (!$r) {
            $r = self::call('getChatMemberCount', ['chat_id' => $chatId], 12);
        }
        return isset($r['result']) && is_numeric($r['result']) ? (int)$r['result'] : null;
    }

    public static function isAdmin(int|string $chatId, int $userId, bool $useCache = true): bool
    {
        $key = "adm:$chatId:$userId";
        if ($useCache) {
            $hit = Cache::get($key);
            if ($hit !== null) {
                return (bool)$hit;
            }
        }
        $m      = self::getChatMember($chatId, $userId);
        $status = (string)($m['status'] ?? '');
        $ok     = in_array($status, ['administrator', 'creator'], true);
        // ادمین‌ها را ۵ دقیقه کش می‌کنیم؛ غیرادمین فقط ۶۰ ثانیه (تا ارتقا زود اثر کند)
        Cache::set($key, $ok, $ok ? 300 : 60);
        return $ok;
    }

    /**
     * محدود کردن کاربر. Bale در نسخه‌های مختلف دو ساختار را می‌پذیرد؛
     * اول ساختار تو‌در‌تو (permissions) و در صورت خطا ساختار تخت (legacy).
     */
    public static function restrict(int|string $chatId, int $userId, bool $canSend, ?int $untilDate = null, array $extra = []): bool
    {
        $perms = [
            'can_send_messages'         => $canSend,
            'can_send_media_messages'   => $canSend,
            'can_send_polls'            => $canSend,
            'can_send_other_messages'   => $canSend,
            'can_add_web_page_previews' => $canSend,
            'can_change_info'           => false,
            'can_invite_users'          => $canSend,
            'can_pin_messages'          => false,
        ];
        $payload = ['chat_id' => $chatId, 'user_id' => $userId, 'permissions' => $perms] + $extra;
        if ($untilDate && $untilDate > 0) {
            $payload['until_date'] = $untilDate;
        }
        if (self::call('restrictChatMember', $payload, 15)) {
            return true;
        }
        // fallback: ساختار تخت
        $legacy = ['chat_id' => $chatId, 'user_id' => $userId] + $perms + $extra;
        if ($untilDate && $untilDate > 0) {
            $legacy['until_date'] = $untilDate;
        }
        return self::call('restrictChatMember', $legacy, 15) !== null;
    }

    public static function setWebhook(string $url, string $secret = ''): ?array
    {
        $p = ['url' => $url];
        if ($secret !== '') {
            $p['secret_token'] = $secret;
        }
        // برخی نسخه‌های بله پارامترهای اضافی را نمی‌پذیرند → دو مرحله‌ای
        $r = self::call('setWebhook', $p, 20);
        if (!$r) {
            $r = self::call('setWebhook', ['url' => $url], 20);
        }
        return $r;
    }

    public static function deleteWebhook(): ?array
    {
        return self::call('deleteWebhook', [], 15);
    }

    public static function getWebhookInfo(): ?array
    {
        $r = self::call('getWebhookInfo', [], 15);
        return $r['result'] ?? null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function getUpdates(?int $offset = null, int $timeout = 0, int $limit = 50): array
    {
        $p = ['timeout' => $timeout, 'limit' => $limit];
        if ($offset !== null) {
            $p['offset'] = $offset;
        }
        $r = self::call('getUpdates', $p, $timeout + 15);
        return is_array($r['result'] ?? null) ? $r['result'] : [];
    }
}
