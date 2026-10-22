<?php
declare(strict_types=1);

/**
 * رمز یکبار مصرف (OTP) برای ورود به پنل
 * - کد ۶ رقمی + لینک جادویی یک‌بارمصرف
 * - ذخیره فقط به‌صورت HMAC (کد و توکن خام هرگز در دیتابیس نمی‌مانند)
 * - انقضا، محدودیت تلاش، محدودیت ارسال مجدد
 */
final class Login
{
    public static function secret(): string
    {
        return (string)config('app.key', 'bgm-default-key');
    }

    private static function hmac(string $v): string
    {
        return hash_hmac('sha256', $v, self::secret());
    }

    public static function channelLabel(array $admin): string
    {
        if (!empty($admin['bale_user_id'])) {
            return 'چت خصوصی بله (آیدی ' . (int)$admin['bale_user_id'] . ')';
        }
        return '—';
    }

    /**
     * ساخت و ارسال OTP
     * @return array{ok:bool,error?:string,code?:string,url?:string}
     */
    public static function send(array $admin, string $via = 'bale'): array
    {
        $adminId = (int)$admin['id'];
        $resend  = (int)config('security.otp_resend_after', 30);
        $last = DB::one("SELECT created_at, expires_at, used_at FROM login_otps WHERE admin_id=? ORDER BY id DESC LIMIT 1", [$adminId]);
        $lastFresh = $last && empty($last['used_at']) && (int)$last['expires_at'] > time();
        if ($lastFresh && time() - (int)$last['created_at'] < $resend) {
            // کد معتبر قبلی وجود دارد: نیازی به ارسال دوباره نیست
            Recovery::log($adminId, 'login_otp', 'bale', (string)($admin['bale_user_id'] ?? ''), 'reused');
            return ['ok' => true, 'reused' => true, 'note' => 'کد ارسال‌شده هنوز معتبر است؛ همان را وارد کن.'];
        }
        // محدودیت کلی: حداکثر ۱۰ کد در ساعت
        $hourCount = (int)DB::val("SELECT COUNT(*) FROM login_otps WHERE admin_id=? AND created_at>?", [$adminId, time() - 3600], 0);
        if ($hourCount >= 10) {
            Recovery::log($adminId, 'login_otp', 'bale', null, 'rate_limited');
            return ['ok' => false, 'error' => 'تعداد درخواست‌ها زیاد است؛ یک ساعت دیگر تلاش کن.'];
        }

        $code  = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = bin2hex(random_bytes(24));
        $ttl   = (int)config('security.otp_ttl', 180);

        DB::q("INSERT INTO login_otps (admin_id,code_hash,token_hash,channel,chat_id,expires_at,attempts,created_at,ip)
               VALUES (?,?,?,?,?,?,0,?,?)",
            [$adminId, self::hmac($code), self::hmac($token), 'private',
             $admin['bale_user_id'] ?: null, time() + $ttl, time(), client_ip()]);
        $otpId = (int)DB::pdo()->lastInsertId();

        // پاک‌سازی کدهای قدیمی
        DB::q("DELETE FROM login_otps WHERE expires_at < ? - 86400", [time()]);

        $magic = url('panel/login.php', ['t' => $token]);
        $text  = "🔐 <b>کد ورود به پنل مدیریت</b>\n\n<code>{$code}</code>\n\n"
            . "⏱ اعتبار: " . digits_fa((int)round($ttl / 60)) . " دقیقه\n"
            . "یا ورود سریع با یک کلیک:\n{$magic}\n\n"
            . "اگر شما درخواست ندادی، این پیام را نادیده بگیر و رمز پنل را عوض کن.";

        $sent = false;
        $target = (int)($admin['bale_user_id'] ?? 0);
        if ($target && BaleApi::hasToken()) {
            $sent = BaleApi::sendMessage($target, $text, null, null, 'HTML') !== null;
        }
        if (!$sent) {
            // پشتیبان: گروه/کانال امن تنظیم‌شده در نصب
            $fallback = (int)(Schema::getSetting('otp_chat_id', '0') ?: 0);
            if ($fallback && BaleApi::hasToken()) {
                $sent = BaleApi::sendMessage($fallback, $text . "\n\n👤 ادمین: <code>" . h((string)$admin['username']) . "</code>", null, null, 'HTML') !== false;
            }
        }
        if (!$sent) {
            Logger::warn('otp', 'send failed', ['admin' => $adminId, 'bale_user_id' => $target]);
            Recovery::log($adminId, 'login_otp', 'bale', $target ? (string)$target : null, 'send_failed');
            return ['ok' => false, 'error' => 'ارسال کد ناموفق بود. اتصال ربات/آیدی بله را در تنظیمات بررسی کنید.'];
        }
        Recovery::log($adminId, 'login_otp', 'bale', (string)$target, 'sent');
        Logger::audit($adminId, (string)$admin['username'], 'otp_sent', 'otp#' . $otpId, 'کد یکبارمصرف ارسال شد');
        return ['ok' => true];
    }

    /** بررسی کد ۶ رقمی */
    public static function verify(int $adminId, string $code): array
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        $row  = DB::one("SELECT * FROM login_otps WHERE admin_id=? AND used_at IS NULL ORDER BY id DESC LIMIT 1", [$adminId]);
        if (!$row) {
            return [false, 'کدی برای این حساب فعال نیست؛ دوباره درخواست بده.'];
        }
        if ((int)$row['expires_at'] < time()) {
            return [false, 'کد منقضی شده؛ کد جدید بگیر.'];
        }
        $max = (int)config('security.otp_max_attempts', 5);
        if ((int)$row['attempts'] >= $max) {
            return [false, 'تعداد تلاش‌ها بیش از حد مجاز است؛ کد جدید بگیر.'];
        }
        DB::q("UPDATE login_otps SET attempts=attempts+1 WHERE id=?", [(int)$row['id']]);
        if (!hash_equals((string)$row['code_hash'], self::hmac($code))) {
            Recovery::log($adminId, 'login_otp', 'panel', null, 'wrong_code');
            return [false, 'کد وارد‌شده اشتباه است.'];
        }
        DB::q("UPDATE login_otps SET used_at=? WHERE id=?", [time(), (int)$row['id']]);
        Recovery::log($adminId, 'login_otp', 'panel', null, 'success');
        return [true, 'ok'];
    }

    /** ورود با لینک جادویی */
    public static function verifyToken(string $token): ?array
    {
        if (!preg_match('~^[a-f0-9]{48}$~', $token)) {
            return null;
        }
        $row = DB::one("SELECT * FROM login_otps WHERE token_hash=? AND used_at IS NULL ORDER BY id DESC LIMIT 1", [self::hmac($token)]);
        if (!$row || (int)$row['expires_at'] < time()) {
            return null;
        }
        DB::q("UPDATE login_otps SET used_at=? WHERE id=?", [time(), (int)$row['id']]);
        Recovery::log((int)$row['admin_id'], 'magic_link', 'link', null, 'success');
        $admin = DB::one("SELECT * FROM panel_admins WHERE id=? AND is_active=1", [(int)$row['admin_id']]);
        return $admin ?: null;
    }

    /**
     * لینک ورود برای دستور «ورود» در چت خصوصی ربات.
     * چون کاربر با حساب بله‌ی متصل به پنل صحبت می‌کند، همین چت عامل دوم است.
     */
    public static function issueLinkForBaleUser(int $baleUserId): ?string
    {
        $admin = DB::one("SELECT * FROM panel_admins WHERE bale_user_id=? AND is_active=1 LIMIT 1", [$baleUserId]);
        if (!$admin) {
            return null;
        }
        // ضد بمباران: حداکثر ۵ لینک در ۱۰ دقیقه
        if (RateLimit::hit("magiclink:$baleUserId", 5, 600)) {
            return null;
        }
        $link = self::createMagicLink((int)$admin['id'], (int)config('security.otp_ttl', 180));
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'magic_link', 'bot-private', 'لینک ورود از چت بله');
        return $link;
    }

    /** ساخت لینک یک‌بارمصرف بدون نیاز به کد (برای اعلان امنیتی) */
    public static function createMagicLink(int $adminId, int $ttl = 180): ?string
    {
        $admin = DB::one("SELECT * FROM panel_admins WHERE id=? AND is_active=1", [$adminId]);
        if (!$admin) {
            return null;
        }
        $token = bin2hex(random_bytes(24));
        DB::q("INSERT INTO login_otps (admin_id,code_hash,token_hash,channel,chat_id,expires_at,attempts,created_at,ip)
               VALUES (?,?,?,?,?,?,0,?,?)",
            [$adminId, self::hmac('nolink'), self::hmac($token), 'private', $admin['bale_user_id'] ?: null, time() + $ttl, time(), client_ip()]);
        return url('panel/login.php', ['t' => $token]);
    }
}
