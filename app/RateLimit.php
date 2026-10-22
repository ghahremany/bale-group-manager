<?php
declare(strict_types=1);

/**
 * محدودیت نرخ (ضد اسپم گروه + ضد حمله به پنل/ربات)
 */
final class RateLimit
{
    /** پنجره لغزان ساده و سبک روی کش فایل */
    public static function hit(string $bucket, int $limit, int $windowSec): bool
    {
        return self::count($bucket, $windowSec) > $limit;
    }

    public static function count(string $bucket, int $windowSec): int
    {
        $key = 'rl:' . $bucket . ':' . intdiv(time(), max(1, $windowSec));
        $n   = Cache::incr($key, $windowSec + 5);
        return $n;
    }

    /** برای ورود پنل: قفل بعد از N تلاش ناموفق */
    public static function loginBlocked(string $ip, string $username): bool
    {
        $max  = (int)config('security.login_max_fails', 6);
        $mins = (int)config('security.login_lock_minutes', 15);
        $fails = (int)Cache::get("login_fail:$ip:$username", 0);
        return $fails >= $max && $mins > 0;
    }

    public static function loginFail(string $ip, string $username): void
    {
        $mins = (int)config('security.login_lock_minutes', 15);
        Cache::incr("login_fail:$ip:$username", max(60, $mins * 60));
        Cache::incr("login_fail_ip:$ip", max(60, $mins * 60));
    }

    public static function loginSuccess(string $ip, string $username): void
    {
        Cache::delete("login_fail:$ip:$username");
    }

    public static function remainingAttempts(string $ip, string $username): int
    {
        $max = (int)config('security.login_max_fails', 6);
        return max(0, $max - (int)Cache::get("login_fail:$ip:$username", 0));
    }
}
