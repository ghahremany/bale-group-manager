<?php
declare(strict_types=1);

/**
 * رویدادهای سیستمی و امنیتی (نسخه ۱.۶)
 *
 * هسته در نقاط حساس یک رویداد ثبت می‌کند (ورود ناموفق، CSRF نامعتبر،
 * درخواست جعلی وب‌هوک، عبور از محدودیت نرخ، …). هر رویداد:
 *   ۱) در جدول `event_log` ذخیره می‌شود (برای گزارش و سابقه)
 *   ۲) به ماژول‌های فعال داده می‌شود: Module::onEvent($type, $data)
 *      تا مثلاً ماژول «مدیریت با ربات» فوراً در بله هشدار بدهد.
 *
 * این کلاس هیچ وابستگی‌ای به ماژول خاصی ندارد؛ اگر هیچ ماژولی رویدادها را
 * مصرف نکند، فقط در جدول ثبت می‌شود (کرون هم بعداً آن‌ها را بررسی می‌کند).
 */
final class Events
{
    /** ترتیب شدت */
    public const LEVELS = ['info' => 0, 'warn' => 1, 'critical' => 2];

    /** @var array<int,array<string,mixed>> صف رویدادهای این درخواست (برای تست) */
    private static array $fired = [];

    /**
     * ثبت یک رویداد
     * @param array<string,mixed> $data اطلاعات تکمیلی (ip/actor_id/detail/…)
     */
    public static function emit(string $type, array $data = [], string $severity = 'warn'): int
    {
        $type = substr(preg_replace('~[^a-z0-9_.:]~i', '', $type) ?: 'event', 0, 60);
        if (!isset(self::LEVELS[$severity])) {
            $severity = 'warn';
        }
        try {
            $ip      = (string)($data['ip'] ?? (PHP_SAPI === 'cli' ? 'cli' : client_ip()));
            $actor   = isset($data['actor_id']) && is_numeric($data['actor_id']) ? (int)$data['actor_id'] : null;
            $detail  = str_limit((string)($data['detail'] ?? ''), 240);
            $metaArr = $data;
            unset($metaArr['ip'], $metaArr['actor_id'], $metaArr['detail']);
            $meta = $metaArr !== [] ? str_limit((string)json_encode($metaArr, JSON_UNESCAPED_UNICODE), 1900) : null;

            DB::q("INSERT INTO event_log (etype,severity,ip,actor_id,detail,meta,notified,created_at)
                   VALUES (?,?,?,?,?,?,0,?)",
                [$type, $severity, str_limit($ip, 44), $actor, $detail ?: null, $meta, time()]);
            $id = (int)DB::pdo()->lastInsertId();
        } catch (Throwable $e) {
            Logger::warn('events', 'insert failed: ' . $e->getMessage());
            return 0;
        }

        $payload = ['id' => $id, 'type' => $type, 'severity' => $severity,
                    'ip' => $ip, 'actor_id' => $actor, 'detail' => $detail, 'data' => $data];
        self::$fired[] = $payload;

        try {
            ModuleManager::dispatchEvent($type, $payload);
        } catch (Throwable $e) {
            Logger::warn('events', 'dispatch failed: ' . $e->getMessage());
        }
        if (!empty($data['log']) && class_exists('Logger')) {
            Logger::warn('security', $type . ($detail !== '' ? ' — ' . $detail : ''), ['ip' => $ip]);
        }
        return $id;
    }

    /** رویدادهای همین درخواست (برای تست/دیباگ) */
    public static function fired(): array
    {
        return self::$fired;
    }

    public static function resetFired(): void
    {
        self::$fired = [];
    }

    /** علامت‌گذاری رویداد به‌عنوان «هشدار داده شد» */
    public static function markNotified(int $id): void
    {
        if ($id > 0) {
            DB::q("UPDATE event_log SET notified=1 WHERE id=?", [$id]);
        }
    }

    /** آخرین رویدادها @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 30, string $severity = ''): array
    {
        $limit = max(1, min(500, $limit));
        if ($severity !== '' && isset(self::LEVELS[$severity])) {
            return DB::all("SELECT * FROM event_log WHERE severity=? ORDER BY id DESC LIMIT {$limit}", [$severity]);
        }
        return DB::all("SELECT * FROM event_log ORDER BY id DESC LIMIT {$limit}");
    }

    /** رویدادهایی که هنوز هشدار داده نشده‌اند */
    public static function pending(int $limit = 20, int $maxAgeSec = 86400): array
    {
        $limit = max(1, min(200, $limit));
        return DB::all("SELECT * FROM event_log WHERE notified=0 AND created_at > ? ORDER BY id ASC LIMIT {$limit}",
            [time() - max(60, $maxAgeSec)]);
    }

    /** شمارش رویداد در بازه زمانی */
    public static function countSince(string $type = '', int $seconds = 3600, string $severity = ''): int
    {
        $sql = "SELECT COUNT(*) FROM event_log WHERE created_at > ?";
        $p   = [time() - max(1, $seconds)];
        if ($type !== '') {
            $sql .= " AND etype=?";
            $p[] = $type;
        }
        if ($severity !== '') {
            $sql .= " AND severity=?";
            $p[] = $severity;
        }
        return (int)DB::val($sql, $p, 0);
    }

    /** پاک‌سازی رویدادهای قدیمی */
    public static function purge(int $days = 30): int
    {
        return DB::exec("DELETE FROM event_log WHERE created_at < ?", [time() - max(1, $days) * 86400]);
    }

    /** ترتیب/برچسب فارسی شدت */
    public static function severityFa(string $severity): string
    {
        return ['info' => 'اطلاع', 'warn' => 'هشدار', 'critical' => 'بحرانی'][$severity] ?? $severity;
    }

    public static function levelValue(string $severity): int
    {
        return self::LEVELS[$severity] ?? 1;
    }
}
