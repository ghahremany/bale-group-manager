<?php
declare(strict_types=1);

/**
 * احراز هویت پنل فروشگاه (کاربران)
 *
 * ورود فقط با حساب بله (تصمیم پروژه): لینک یک‌بارمصرف ارسال‌شده در چت خصوصی ربات.
 * - بدون رمز عبور، بدون پیامک؛ هویت = حساب بله
 * - توکن‌ها HMAC می‌شوند و یک‌بار مصرف‌اند (shop_tokens)
 * - نشست در همان کوکی امن پنل، ولی با کلید جدا (shop_user)
 */
final class ShopAuth
{
    private const KEY = 'shop_user';

    private static function hmac(string $v): string
    {
        return hash_hmac('sha256', 'shop|' . $v, (string)config('app.key', 'bgm-default-key'));
    }

    public static function current(): ?array
    {
        $id = (int)($_SESSION[self::KEY] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $u = DB::one("SELECT * FROM users WHERE user_id=?", [$id]);
        if (!$u) {
            unset($_SESSION[self::KEY]);
            return null;
        }
        return $u;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION[self::KEY] = $userId;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::KEY]);
        session_regenerate_id(true);
    }

    public static function check(): bool
    {
        return self::current() !== null;
    }

    public static function require(): array
    {
        $u = self::current();
        if (!$u) {
            $_SESSION['shop_redirect'] = (string)($_SERVER['REQUEST_URI'] ?? '/shop/');
            redirect('shop/login.php');
        }
        return $u;
    }

    /**
     * ساخت لینک ورود یک‌بارمصرف و ارسال آن در چت خصوصی بله
     * @return array{ok:bool,link?:string,error?:string}
     */
    public static function sendLoginLink(int $baleUserId): array
    {
        $user = DB::one("SELECT * FROM users WHERE user_id=?", [$baleUserId]);
        if (!$user) {
            return ['ok' => false, 'error' => 'کاربر پیدا نشد.'];
        }
        if (RateLimit::hit('shop_link:' . $baleUserId, 3, 600)) {
            return ['ok' => false, 'error' => 'درخواست زیاد بود؛ ۱۰ دقیقه دیگر تلاش کن.'];
        }
        $token = bin2hex(random_bytes(24));
        DB::q("INSERT INTO shop_tokens (user_id, token_hash, purpose, expires_at, created_at, ip)
               VALUES (?,?, 'login', ?, ?, ?)",
            [$baleUserId, self::hmac($token), time() + 600, time(), client_ip()]);
        $link = url('shop/login.php', ['t' => $token]);
        $sent = BaleApi::hasToken()
            ? BaleApi::sendMessage($baleUserId,
                "🔐 <b>ورود به فروشگاه</b>\n\nاین لینک ۱۰ دقیقه اعتبار دارد و یک‌بار مصرف است:\n" . $link
                . "\n\nاگر درخواست ندادی، نادیده بگیر.", null, null, 'HTML')
            : null;
        Recovery::log($baleUserId, 'shop_link', 'bale', (string)$baleUserId, $sent ? 'sent' : 'send_failed');
        if (!$sent) {
            // برای نصب‌های بدون ربات فعال، لینک را برمی‌گردانیم تا در لاگ/پنل قابل استفاده باشد
            Logger::warn('shop', 'login link send failed', ['user' => $baleUserId]);
            return ['ok' => true, 'link' => $link, 'warning' => 'ارسال در بله ناموفق بود؛ لینک ساخته شد.'];
        }
        return ['ok' => true, 'link' => $link];
    }

    /** بررسی توکن لینک ورود */
    public static function verifyToken(string $token): ?array
    {
        if (!preg_match('~^[a-f0-9]{48}$~', $token)) {
            return null;
        }
        $row = DB::one("SELECT * FROM shop_tokens WHERE token_hash=? AND used_at IS NULL ORDER BY id DESC LIMIT 1",
            [self::hmac($token)]);
        if (!$row || (int)$row['expires_at'] < time()) {
            return null;
        }
        DB::q("UPDATE shop_tokens SET used_at=? WHERE id=?", [time(), (int)$row['id']]);
        Recovery::log((int)$row['user_id'], 'shop_login', 'link', null, 'success');
        return DB::one("SELECT * FROM users WHERE user_id=?", [(int)$row['user_id']]) ?: null;
    }

    /** ثبت‌نام/به‌روزرسانی کاربر از داده‌های بله */
    public static function touchUser(array $from): int
    {
        $id = (int)($from['id'] ?? 0);
        if ($id <= 0) {
            return 0;
        }
        DB::q("INSERT INTO users (user_id, username, first_name, last_name, is_bot, messages_count, first_seen_at, last_seen_at)
               VALUES (?,?,?,?,?,0,?,?)
               ON DUPLICATE KEY UPDATE username=VALUES(username), first_name=VALUES(first_name),
                 last_name=VALUES(last_name), last_seen_at=VALUES(last_seen_at)",
            [$id, (string)($from['username'] ?? '') ?: null, (string)($from['first_name'] ?? '') ?: null,
             (string)($from['last_name'] ?? '') ?: null, !empty($from['is_bot']) ? 1 : 0, time(), time()]);
        return $id;
    }

    public static function displayName(array $u): string
    {
        $n = trim(((string)($u['first_name'] ?? '')) . ' ' . ((string)($u['last_name'] ?? '')));
        if ($n === '') {
            $n = (string)($u['username'] ?? '');
        }
        return $n !== '' ? $n : 'کاربر ' . (int)$u['user_id'];
    }

    /** پاک‌سازی توکن‌های منقضی (کرون) */
    public static function purgeTokens(): int
    {
        $n = (int)DB::val("SELECT COUNT(*) FROM shop_tokens WHERE expires_at < ?", [time() - 86400], 0);
        DB::q("DELETE FROM shop_tokens WHERE expires_at < ?", [time() - 86400]);
        return $n;
    }
}
