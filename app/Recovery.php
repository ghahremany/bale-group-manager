<?php
declare(strict_types=1);

/**
 * کدهای بازیابی پنل + لاگ رمزهای یکبارمصرف
 *
 * چرا لازم است؟
 *   ورود به پنل با OTP بله انجام می‌شود؛ اگر ادمین به بله دسترسی نداشته باشد
 *   (گوشی گم شده، ربات بلاک شده، اینترنت قطع) قفل می‌شود. کد بازیابی راه دوم است.
 *
 * قواعد امنیتی:
 *   - کد به‌صورت «BGM-XXXXX-XXXXX» با الفبای بدون ابهام (بدون 0/O/1/I) تولید می‌شود
 *   - فقط HMAC-SHA256 کد در دیتابیس ذخیره می‌شود + ۴ کاراکتر آخر به‌عنوان hint
 *   - هر کد یک‌بار مصرف است، مقایسه با hash_equals (زمان‌ثابت)
 *   - همه رخدادها در جدول otp_logs ثبت می‌شوند (purpose/channel/status/ip)
 */
final class Recovery
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';   // بدون 0 O 1 I L
    public const COUNT_DEFAULT = 8;

    public static function secret(): string
    {
        return (string)config('app.key', 'bgm-default-key') . '|recovery';
    }

    public static function hash(string $code): string
    {
        return hash_hmac('sha256', self::normalize($code), self::secret());
    }

    /** حذف خط تیره/فاصله و بزرگ‌کردن حروف */
    public static function normalize(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = str_replace(['-', ' '], '', $code);
        return preg_replace('/[^A-Z0-9]/', '', $code) ?? '';
    }

    /** تولید کد تازه (فقط یک‌بار به کاربر نشان داده می‌شود) */
    public static function randomCode(): string
    {
        $raw = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 10; $i++) {
            $raw .= self::ALPHABET[random_int(0, $max)];
        }
        return 'BGM-' . substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
    }

    /**
     * تولید مجموعه کد بازیابی (کدهای قبلی استفاده‌نشده باطل می‌شوند)
     * @return array<int,string> کدهای خام — فقط همین یک‌بار قابل مشاهده‌اند
     */
    public static function generate(int $adminId, int $count = self::COUNT_DEFAULT, ?int $actorId = null): array
    {
        $count = max(1, min(20, $count));
        DB::q("DELETE FROM recovery_codes WHERE admin_id=? AND used_at IS NULL", [$adminId]);
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $code = self::randomCode();
            $codes[] = $code;
            DB::q("INSERT INTO recovery_codes (admin_id, code_hash, hint, used_at, created_at) VALUES (?,?,?,NULL,?)",
                [$adminId, self::hash($code), substr(self::normalize($code), -4), time()]);
        }
        self::log($adminId, 'recovery_generate', 'panel', null, 'generated:' . $count);
        Logger::audit($actorId ?? $adminId, 'panel', 'recovery_generate', 'admin#' . $adminId, 'تولید ' . $count . ' کد بازیابی');
        return $codes;
    }

    public static function remaining(int $adminId): int
    {
        return (int)DB::val("SELECT COUNT(*) FROM recovery_codes WHERE admin_id=? AND used_at IS NULL", [$adminId], 0);
    }

    /** @return array<int,array<string,mixed>> */
    public static function list(int $adminId): array
    {
        return DB::all("SELECT id, hint, used_at, created_at FROM recovery_codes WHERE admin_id=? ORDER BY id DESC", [$adminId]);
    }

    /**
     * بررسی کد بازیابی (یک‌بار مصرف)
     * @return array{ok:bool,reason?:string}
     */
    public static function verify(int $adminId, string $code): array
    {
        $norm = self::normalize($code);
        if (strlen($norm) < 8) {
            self::log($adminId, 'recovery_login', 'panel', null, 'invalid_format');
            return ['ok' => false, 'reason' => 'قالب کد بازیابی درست نیست.'];
        }
        $hash = self::hash($code);
        $rows = DB::all("SELECT id, code_hash FROM recovery_codes WHERE admin_id=? AND used_at IS NULL", [$adminId]);
        foreach ($rows as $r) {
            if (hash_equals((string)$r['code_hash'], $hash)) {
                DB::q("UPDATE recovery_codes SET used_at=? WHERE id=? AND used_at IS NULL", [time(), (int)$r['id']]);
                self::log($adminId, 'recovery_login', 'panel', null, 'success');
                Logger::audit($adminId, 'panel', 'recovery_used', 'admin#' . $adminId, 'ورود با کد بازیابی');
                return ['ok' => true, 'left' => self::remaining($adminId)];
            }
        }
        self::log($adminId, 'recovery_login', 'panel', null, 'mismatch');
        return ['ok' => false, 'reason' => 'کد بازیابی نامعتبر یا قبلاً استفاده شده است.'];
    }

    public static function revokeAll(int $adminId, ?int $actorId = null): int
    {
        $n = (int)DB::val("SELECT COUNT(*) FROM recovery_codes WHERE admin_id=? AND used_at IS NULL", [$adminId], 0);
        DB::q("DELETE FROM recovery_codes WHERE admin_id=? AND used_at IS NULL", [$adminId]);
        self::log($adminId, 'recovery_revoke', 'panel', null, 'revoked:' . $n);
        Logger::audit($actorId, 'panel', 'recovery_revoke', 'admin#' . $adminId, 'ابطال ' . $n . ' کد بازیابی');
        return $n;
    }

    // ---------------- لاگ OTP ----------------

    /** ثبت رخداد رمز یکبارمصرف */
    public static function log(?int $adminId, string $purpose, string $channel, ?string $destination, string $status): void
    {
        try {
            DB::q("INSERT INTO otp_logs (admin_id, purpose, channel, destination, status, ip, created_at)
                   VALUES (?,?,?,?,?,?,?)",
                [$adminId, str_limit($purpose, 30), str_limit($channel, 22),
                 $destination !== null ? str_limit($destination, 60) : null, str_limit($status, 22),
                 client_ip(), time()]);
        } catch (Throwable $e) {
            Logger::warn('otp', 'log failed: ' . $e->getMessage());
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function logs(?int $adminId = null, string $purpose = '', int $limit = 100): array
    {
        $where = [];
        $args  = [];
        if ($adminId !== null) {
            $where[] = 'l.admin_id = ?';
            $args[]  = $adminId;
        }
        if ($purpose !== '') {
            $where[] = 'l.purpose = ?';
            $args[]  = $purpose;
        }
        $sql = "SELECT l.*, a.username FROM otp_logs l LEFT JOIN panel_admins a ON a.id = l.admin_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY l.id DESC LIMIT " . max(1, min(500, $limit));
        return DB::all($sql, $args);
    }

    public static function logSummary(?int $adminId = null, int $days = 7): array
    {
        $from = time() - $days * 86400;
        $args = [$from];
        $cond = '';
        if ($adminId !== null) {
            $cond = ' AND admin_id=?';
            $args[] = $adminId;
        }
        $rows = DB::all("SELECT purpose, status, COUNT(*) c FROM otp_logs WHERE created_at>=?$cond GROUP BY purpose, status", $args);
        $out = [];
        foreach ($rows as $r) {
            $key = $r['purpose'] . '/' . $r['status'];
            $out[$key] = (int)$r['c'];
        }
        return $out;
    }

    /**
     * تعداد تلاش‌های ناموفق بازیابی از «آخرین ورود موفق» به بعد (ضد حمله)
     * اگر بعد از خطاها یک ورود موفق داشته، شمارنده از صفر شروع می‌شود تا کاربر واقعی قفل نشود.
     */
    public static function failures(int $adminId, int $minutes = 15): int
    {
        $from = time() - $minutes * 60;
        $lastSuccess = (int)DB::val(
            "SELECT MAX(created_at) FROM otp_logs
             WHERE admin_id=? AND status='success' AND created_at > ?",
            [$adminId, $from], 0
        );
        $since = max($from, $lastSuccess);
        return (int)DB::val(
            "SELECT COUNT(*) FROM otp_logs
             WHERE admin_id=? AND purpose IN ('recovery_login','password_reset')
               AND status<>'success' AND created_at >= ?",
            [$adminId, $since], 0
        );
    }

    /** پاک‌سازی لاگ‌های قدیمی */
    public static function purgeLogs(int $days = 90): int
    {
        $n = (int)DB::val("SELECT COUNT(*) FROM otp_logs WHERE created_at < ?", [time() - $days * 86400], 0);
        DB::q("DELETE FROM otp_logs WHERE created_at < ?", [time() - $days * 86400]);
        return $n;
    }
}
