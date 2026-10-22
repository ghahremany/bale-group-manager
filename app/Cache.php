<?php
declare(strict_types=1);

/**
 * کش فایل‌محور (بدون نیاز به Redis) + قفل اتمیک
 * برای کشف ادمین، شمارنده اسپم، محدودیت نرخ و ... استفاده می‌شود.
 */
final class Cache
{
    private static function dir(): string
    {
        $d = BGM_ROOT . '/storage/cache';
        ensure_dir($d);
        return $d;
    }

    private static function path(string $key): string
    {
        return self::dir() . '/' . sha1($key) . '.cache';
    }

    public static function set(string $key, $value, int $ttl = 300): void
    {
        $payload = serialize(['exp' => time() + $ttl, 'v' => $value]);
        $tmp     = self::path($key) . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, $payload, LOCK_EX);
        @rename($tmp, self::path($key));
    }

    public static function get(string $key, $default = null)
    {
        $p = self::path($key);
        if (!is_file($p)) {
            return $default;
        }
        $raw = @file_get_contents($p);
        if ($raw === false) {
            return $default;
        }
        $data = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($data) || ($data['exp'] ?? 0) < time()) {
            @unlink($p);
            return $default;
        }
        return $data['v'];
    }

    public static function delete(string $key): void
    {
        @unlink(self::path($key));
    }

    public static function remember(string $key, int $ttl, callable $fn)
    {
        $v = self::get($key, '__miss__');
        if ($v !== '__miss__') {
            return $v;
        }
        $v = $fn();
        self::set($key, $v, $ttl);
        return $v;
    }

    /** شمارنده با پنجره زمانی ثابت (برای آنتی‌اسپم/نرخ) */
    public static function incrWindow(string $key, int $windowSec, int $ttlPad = 5): int
    {
        $bucket = $key . ':' . intdiv(time(), $windowSec);
        return self::incr($bucket, $windowSec + $ttlPad);
    }

    public static function incr(string $key, int $ttl = 300, int $by = 1): int
    {
        $lock = self::lock($key . ':lock', 3);
        $cur  = (int)self::get($key, 0) + $by;
        self::set($key, $cur, $ttl);
        self::unlock($key . ':lock', $lock);
        return $cur;
    }

    /**
     * قفل ساده با flock — اگر قفل گرفته شد true برمی‌گرداند
     * @return resource|false
     */
    public static function lock(string $name, int $ttl = 5)
    {
        $file = self::dir() . '/' . sha1($name) . '.lock';
        $fh   = @fopen($file, 'c');
        if (!$fh) {
            return false;
        }
        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            return false;
        }
        return $fh;
    }

    /** @param resource|false $fh */
    public static function unlock(string $name, $fh): void
    {
        if (is_resource($fh)) {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /**
     * جلوگیری از پردازش هم‌زمان یک update (idempotency + قفل)
     */
    public static function beginOnce(string $key, int $ttl = 120)
    {
        $marker = 'once:' . $key;
        if (self::get($marker)) {
            return false;
        }
        self::set($marker, 1, $ttl);
        return true;
    }

    public static function gc(): int
    {
        $n = 0;
        foreach (glob(self::dir() . '/*.cache') ?: [] as $f) {
            if (@filemtime($f) < time() - 86400) {
                @unlink($f);
                $n++;
            }
        }
        foreach (glob(self::dir() . '/*.lock') ?: [] as $f) {
            if (@filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
        return $n;
    }
}
