<?php
declare(strict_types=1);

/**
 * صف ارسال همگانی (Broadcast Queue) با زمان‌بندی و نرخ‌سنجی
 *
 * چرا صف؟
 *   ارسال همگانی در حلقه‌ی همگام (نسخه ۱.۰) هم تایم‌اوت می‌خورد، هم به سقف نرخ بله
 *   برخورد می‌کرد (429) و هم اگر صفحه بسته می‌شد نیمه‌کاره می‌ماند.
 *
 * این کلاس:
 *   ۱) پیام‌ها را «قبل از ارسال» در جدول broadcast_queue می‌نویسد (durable)
 *   ۲) کرون هر دقیقه بخشی از صف را در بودجه‌ی زمانی مشخص می‌فرستد
 *   ۳) نرخ ارسال را با فاصله‌گذاری میکروثانیه‌ای کنترل می‌کند (پیش‌فرض ۱۵ پیام بر ثانیه)
 *   ۴) خطاها را با backoff دوباره امتحان می‌کند و بعد failed می‌کند
 *   ۵) قابل توقف، ادامه و گزارش پیشرفت است
 */
final class Broadcast
{
    public const MAX_RATE = 25;          // سقف ایمن بله
    public const RETRY_LIMIT = 2;
    public const CHUNK_INSERT = 500;

    /** انواع مخاطب */
    public static function audiences(): array
    {
        return [
            'groups'   => 'همه گروه‌های فعال',
            'users'    => 'همه کاربران (چت خصوصی)',
            'users30'  => 'کاربران فعال ۳۰ روز اخیر',
            'admins'   => 'فقط ادمین‌های گروه‌ها',
        ];
    }

    /** شناسه چت‌های مخاطب */
    public static function targets(string $audience): array
    {
        switch ($audience) {
            case 'users':
                $rows = DB::all("SELECT user_id AS id FROM users WHERE is_bot=0 ORDER BY user_id");
                break;
            case 'users30':
                $rows = DB::all("SELECT user_id AS id FROM users WHERE is_bot=0 AND last_seen_at > ?", [time() - 30 * 86400]);
                break;
            case 'admins':
                $rows = DB::all("SELECT DISTINCT user_id AS id FROM chat_admins WHERE user_id > 0");
                break;
            case 'groups':
            default:
                $rows = DB::all("SELECT chat_id AS id FROM chat_settings WHERE is_active=1");
                break;
        }
        $out = [];
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            if ($id !== 0) {
                $out[$id] = true;
            }
        }
        return array_keys($out);
    }

    public static function countTargets(string $audience): int
    {
        return count(self::targets($audience));
    }

    /**
     * ساخت دسته (batch) و ریختن پیام‌ها در صف
     * @param array{text:string,audience?:string,title?:string,parse_mode?:string,scheduled_at?:int,rate?:int,admin_id?:int,chats?:array} $o
     */
    public static function enqueue(array $o): array
    {
        $text = trim((string)($o['text'] ?? ''));
        if ($text === '') {
            return ['ok' => false, 'error' => 'متن پیام خالی است.'];
        }
        if (mb_strlen($text) > 4000) {
            return ['ok' => false, 'error' => 'متن بیش از ۴۰۰۰ کاراکتر است.'];
        }
        $audience = (string)($o['audience'] ?? 'groups');
        if (!isset(self::audiences()[$audience])) {
            $audience = 'groups';
        }
        $mode = strtoupper((string)($o['parse_mode'] ?? 'HTML'));
        if (!in_array($mode, ['HTML', ''], true)) {
            $mode = 'HTML';
        }
        $rate = (int)($o['rate'] ?? 0);
        if ($rate <= 0) {
            $rate = (int)(Schema::getSetting('broadcast_rate', '15') ?: 15);
        }
        $rate = max(1, min(self::MAX_RATE, $rate));

        if (array_key_exists('chats', $o)) {
            $targets = array_values(array_unique(array_filter(array_map('intval', (array)$o['chats']))));
        } else {
            $targets = self::targets($audience);
        }
        if (!$targets) {
            return ['ok' => false, 'error' => 'مخاطبی پیدا نشد (گروه فعال/کاربر ثبت‌شده‌ای وجود ندارد).'];
        }

        $when = (int)($o['scheduled_at'] ?? 0);
        if ($when > 0 && $when < time()) {
            $when = 0;
        }
        $now = time();
        DB::q("INSERT INTO broadcast_batches
               (admin_id,title,text,parse_mode,audience,target_count,rate_per_sec,status,scheduled_at,created_at)
               VALUES (?,?,?,?,?,?,?, 'queued', ?, ?)",
            [
                $o['admin_id'] ?? null,
                str_limit((string)($o['title'] ?? mb_substr($text, 0, 60)), 118),
                $text, $mode, $audience, count($targets), $rate, $when, $now,
            ]);
        $batchId = (int)DB::pdo()->lastInsertId();

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $chunk = [];
            foreach ($targets as $tid) {
                $chunk[] = $tid;
                if (count($chunk) >= self::CHUNK_INSERT) {
                    self::insertRows($batchId, $chunk, $text, $now);
                    $chunk = [];
                }
            }
            if ($chunk) {
                self::insertRows($batchId, $chunk, $text, $now);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            DB::q("DELETE FROM broadcast_queue WHERE batch_id=?", [$batchId]);
            DB::q("DELETE FROM broadcast_batches WHERE id=?", [$batchId]);
            Logger::error('broadcast', 'enqueue failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'ثبت در صف ناموفق بود: ' . $e->getMessage()];
        }

        Logger::audit(
            isset($o['admin_id']) ? (int)$o['admin_id'] : null,
            'panel',
            'broadcast_enqueue',
            'batch#' . $batchId,
            'مخاطب: ' . $audience . ' · تعداد: ' . count($targets) . ($when ? ' · زمان‌بندی: ' . dt($when) : ' · فوری')
        );
        return ['ok' => true, 'batch' => $batchId, 'queued' => count($targets), 'scheduled_at' => $when];
    }

    private static function insertRows(int $batchId, array $chats, string $text, int $now): void
    {
        $values = [];
        $args   = [];
        foreach ($chats as $c) {
            $values[] = "(?,?,?,?, 'pending', 0, 0)";
            array_push($args, $batchId, (int)$c, $text, $now);
        }
        DB::q("INSERT INTO broadcast_queue (batch_id, chat_id, text, created_at, status, attempts, next_try_at)
               VALUES " . implode(',', $values), $args);
    }

    /**
     * برداشت بخشی از صف و ارسال
     * @return array{sent:int,failed:int,batches:int,left:int,elapsed:float}
     */
    public static function drain(int $budgetSeconds = 20, ?int $onlyBatch = null): array
    {
        $start = microtime(true);
        $stats = ['sent' => 0, 'failed' => 0, 'batches' => 0, 'left' => self::pendingCount(), 'elapsed' => 0.0];

        $lock = Cache::lock('broadcast', 10);
        if ($lock === false) {
            return $stats;   // اجرای هم‌زمان دیگر در جریان است
        }
        try {
            $batches = $onlyBatch
                ? DB::all("SELECT * FROM broadcast_batches WHERE id=?", [$onlyBatch])
                : DB::all("SELECT * FROM broadcast_batches
                           WHERE status IN ('queued','sending') AND scheduled_at <= ?
                           ORDER BY scheduled_at ASC, id ASC LIMIT 5", [time()]);
            foreach ($batches as $b) {
                if (microtime(true) - $start > $budgetSeconds) {
                    break;
                }
                $batchId = (int)$b['id'];
                if ($b['status'] === 'queued') {
                    DB::q("UPDATE broadcast_batches SET status='sending', started_at=? WHERE id=? AND status='queued'", [time(), $batchId]);
                }
                $stats['batches']++;
                $rate   = max(1, min(self::MAX_RATE, (int)$b['rate_per_sec']));
                $gap    = 1.0 / $rate;           // فاصله بین دو ارسال
                $mode   = (string)($b['parse_mode'] ?: 'HTML');
                $lastAt = 0.0;

                while (microtime(true) - $start < $budgetSeconds) {
                    $rows = DB::all("SELECT id, chat_id FROM broadcast_queue
                                     WHERE batch_id=? AND status='pending' AND next_try_at <= ?
                                     ORDER BY id LIMIT 20", [$batchId, time()]);
                    if (!$rows) {
                        break;
                    }
                    foreach ($rows as $row) {
                        if (microtime(true) - $start >= $budgetSeconds) {
                            break;
                        }
                        // نرخ‌سنجی: فاصله‌گذاری بین ارسال‌ها
                        $since = microtime(true) - $lastAt;
                        if ($lastAt > 0 && $since < $gap) {
                            usleep((int)(($gap - $since) * 1_000_000));
                        }
                        $lastAt = microtime(true);

                        $chatId = (int)$row['chat_id'];
                        $ok = false;
                        $err = '';
                        try {
                            $ok = BaleApi::sendMessage($chatId, self::render((string)$b['text'], $chatId), null, null, $mode) !== null;
                        } catch (Throwable $e) {
                            $err = $e->getMessage();
                        }

                        if (!$ok) {
                            $err = $err !== '' ? $err : (string)(BaleApi::$lastError ?? 'ارسال ناموفق');
                        }
                        if ($ok) {
                            DB::q("UPDATE broadcast_queue SET status='sent', sent_at=? WHERE id=?", [time(), (int)$row['id']]);
                            DB::q("UPDATE broadcast_batches SET sent_count=sent_count+1 WHERE id=?", [$batchId]);
                            $stats['sent']++;
                        } else {
                            $att = (int)DB::val("SELECT attempts FROM broadcast_queue WHERE id=?", [(int)$row['id']], 0);
                            $att++;
                            if ($att >= self::RETRY_LIMIT) {
                                DB::q("UPDATE broadcast_queue SET status='failed', attempts=?, error=? WHERE id=?",
                                    [$att, str_limit($err, 250), (int)$row['id']]);
                                DB::q("UPDATE broadcast_batches SET failed_count=failed_count+1 WHERE id=?", [$batchId]);
                                $stats['failed']++;
                            } else {
                                // backoff کوتاه: ۱ یا ۲ دقیقه بعد دوباره
                                DB::q("UPDATE broadcast_queue SET attempts=?, error=?, next_try_at=? WHERE id=?",
                                    [$att, str_limit($err, 250), time() + 60 * $att, (int)$row['id']]);
                            }
                        }
                    }
                }

                $pending = (int)DB::val("SELECT COUNT(*) FROM broadcast_queue WHERE batch_id=? AND status='pending'", [$batchId], 0);
                if ($pending === 0) {
                    DB::q("UPDATE broadcast_batches SET status='done', finished_at=? WHERE id=? AND status IN ('queued','sending')",
                        [time(), $batchId]);
                }
            }
        } finally {
            Cache::unlock('broadcast', $lock);
            $stats['elapsed'] = round(microtime(true) - $start, 2);
            $stats['left']    = self::pendingCount();
        }
        return $stats;
    }

    /** جانشینی متغیرهای متنی برای هر مخاطب */
    public static function render(string $text, int $chatId): string
    {
        if (!str_contains($text, '{')) {
            return $text;
        }
        $title = (string)(DB::val("SELECT title FROM chat_settings WHERE chat_id=?", [$chatId], null) ?? '');
        $map = [
            '{group}'   => $title !== '' ? $title : (string)$chatId,
            '{chat_id}' => (string)$chatId,
            '{panel}'    => url('panel/'),
            '{bot}'      => (string)(Schema::getSetting('bot_username', (string)config('bot.username', '')) ?: ''),
        ];
        return strtr($text, $map);
    }

    public static function pendingCount(): int
    {
        return (int)DB::val("SELECT COUNT(*) FROM broadcast_queue WHERE status='pending'", [], 0);
    }

    public static function batch(int $id): ?array
    {
        $b = DB::one("SELECT * FROM broadcast_batches WHERE id=?", [$id]);
        return $b ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function batches(int $limit = 25): array
    {
        $rows = DB::all("SELECT * FROM broadcast_batches ORDER BY id DESC LIMIT " . max(1, min(100, $limit)));
        foreach ($rows as &$r) {
            $r['progress'] = self::progress($r);
        }
        return $rows;
    }

    public static function progress(?array $b): array
    {
        if (!$b) {
            return ['percent' => 0, 'done' => 0, 'left' => 0];
        }
        $target = max(1, (int)$b['target_count']);
        $done   = (int)$b['sent_count'] + (int)$b['failed_count'];
        return [
            'percent' => (int)min(100, round($done / $target * 100)),
            'done'    => $done,
            'left'    => max(0, (int)$b['target_count'] - $done),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function queueRows(int $batchId, string $status = '', int $limit = 40): array
    {
        $args = [$batchId];
        $where = 'batch_id=?';
        if (in_array($status, ['pending', 'sent', 'failed'], true)) {
            $where .= ' AND status=?';
            $args[] = $status;
        }
        return DB::all("SELECT * FROM broadcast_queue WHERE $where ORDER BY id LIMIT " . max(1, min(200, $limit)), $args);
    }

    public static function cancel(int $batchId, ?int $adminId = null): bool
    {
        $b = self::batch($batchId);
        if (!$b || in_array($b['status'], ['done', 'canceled'], true)) {
            return false;
        }
        DB::q("UPDATE broadcast_batches SET status='canceled', finished_at=? WHERE id=?", [time(), $batchId]);
        DB::q("UPDATE broadcast_queue SET status='failed', error='canceled by admin' WHERE batch_id=? AND status='pending'", [$batchId]);
        Logger::audit($adminId, 'panel', 'broadcast_cancel', 'batch#' . $batchId, 'توقف دسته ارسال');
        return true;
    }

    /** ادامه یک دسته متوقف/زمان‌بندی‌شده (ردیف‌های لغوشده دوباره در صف می‌روند) */
    public static function resume(int $batchId, ?int $adminId = null): bool
    {
        $b = self::batch($batchId);
        if (!$b || $b['status'] === 'done') {
            return false;
        }
        $restored = (int)DB::val("SELECT COUNT(*) FROM broadcast_queue
                                  WHERE batch_id=? AND status='failed' AND error='canceled by admin'", [$batchId], 0);
        DB::q("UPDATE broadcast_queue SET status='pending', error=NULL, attempts=0, next_try_at=0
               WHERE batch_id=? AND status='failed' AND error='canceled by admin'", [$batchId]);
        if ($restored > 0) {
            DB::q("UPDATE broadcast_batches SET failed_count=GREATEST(failed_count-?,0) WHERE id=?", [$restored, $batchId]);
        }
        DB::q("UPDATE broadcast_batches SET status='queued', finished_at=NULL, scheduled_at=? WHERE id=?", [time(), $batchId]);
        Logger::audit($adminId, 'panel', 'broadcast_resume', 'batch#' . $batchId, 'ادامه ارسال');
        return true;
    }

    /** پاک‌سازی ردیف‌های قدیمی صف */
    public static function purgeOld(int $days = 30): int
    {
        $cut = time() - max(1, $days) * 86400;
        $n = (int)DB::val("SELECT COUNT(*) FROM broadcast_queue WHERE created_at < ? AND status IN ('sent','failed')", [$cut], 0);
        DB::q("DELETE FROM broadcast_queue WHERE created_at < ? AND status IN ('sent','failed')", [$cut]);
        DB::q("DELETE FROM broadcast_batches WHERE created_at < ? AND status IN ('done','canceled')", [$cut]);
        return $n;
    }

    /** پیش‌نمایش: چند نفر/چت پیام می‌گیرند */
    public static function preview(string $audience): array
    {
        $count = self::countTargets($audience);
        return [
            'count' => $count,
            'label' => self::audiences()[$audience] ?? $audience,
            'eta'   => $count > 0 ? (int)ceil($count / max(1, (int)(Schema::getSetting('broadcast_rate', '15') ?: 15))) : 0,
        ];
    }
}
