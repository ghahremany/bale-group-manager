<?php
declare(strict_types=1);

namespace Modules;

use BaleApi;
use DB;
use Logger;
use Module;
use Schema;
use digits_fa;
use dt;
use h;

/**
 * ماژول «اخبار کانال»
 * - نوشتن خبر با عنوان/متن/عکس
 * - ذخیره پیش‌نویس، زمان‌بندی و ارسال فوری به یک یا همه کانال‌ها
 * - کرون هر دقیقه خبرهای سررسیده را می‌فرستد
 * - دستور ربات `اخبار` آخرین خبرهای منتشرشده را نشان می‌دهد
 */
final class News extends Module
{
    public static function key(): string
    {
        return 'news';
    }

    public static function title(): string
    {
        return 'اخبار کانال';
    }

    public static function description(): string
    {
        return 'نوشتن، زمان‌بندی و انتشار خبر در کانال‌ها + نمایش آخرین اخبار در ربات';
    }

    public static function icon(): string
    {
        return '📰';
    }

    public static function badge(): string
    {
        $sched = (int)DB::val("SELECT COUNT(*) FROM news_posts WHERE status='scheduled'", [], 0);
        $sent  = (int)DB::val("SELECT COUNT(*) FROM news_posts WHERE status='sent'", [], 0);
        return digits_fa($sched) . ' زمان‌بندی · ' . digits_fa($sent) . ' ارسال‌شده';
    }

    public static function commands(): array
    {
        return ['اخبار' => 'latest', 'خبر' => 'latest'];
    }

    /** نمایش آخرین خبرهای منتشرشده */
    public static function latest(array $ctx): void
    {
        $rows = DB::all("SELECT * FROM news_posts WHERE status='sent' ORDER BY id DESC LIMIT 3");
        if (!$rows) {
            self::say($ctx, "📰 هنوز خبری منتشر نشده است.");
            return;
        }
        $out = ["📰 <b>آخرین اخبار</b>"];
        foreach ($rows as $n) {
            $out[] = '• <b>' . h((string)($n['title'] ?: 'خبر')) . '</b>'
                . "\n  " . h(mb_strimwidth((string)$n['body'], 0, 180, '…'))
                . "\n  <span style=\"color:#8098bb\">" . dt((int)($n['sent_at'] ?: $n['created_at']), 'Y/m/d') . '</span>';
        }
        self::say($ctx, implode("\n\n", $out));
    }

    public static function save(array $in): array
    {
        $body = trim((string)($in['body'] ?? ''));
        if (mb_strlen($body) < 5) {
            return ['ok' => false, 'error' => 'متن خبر حداقل ۵ کاراکتر باشد.'];
        }
        $id     = (int)($in['id'] ?? 0);
        $when   = self::parseWhen($in['scheduled_at'] ?? 0);
        $status = $when > time() + 30 ? 'scheduled' : 'draft';
        $channel = (int)($in['channel_id'] ?? 0);
        if ($id > 0) {
            DB::q("UPDATE news_posts SET channel_id=?, title=?, body=?, media_url=?, scheduled_at=?, status=?, error=NULL WHERE id=?",
                [$channel ?: null, str_limit((string)($in['title'] ?? ''), 198), $body,
                 str_limit((string)($in['media_url'] ?? ''), 250) ?: null, $when, $status, $id]);
            return ['ok' => true, 'id' => $id, 'status' => $status];
        }
        DB::q("INSERT INTO news_posts (channel_id,title,body,media_url,status,scheduled_at,admin_id,created_at)
               VALUES (?,?,?,?,?,?,?,?)",
            [$channel ?: null, str_limit((string)($in['title'] ?? ''), 198), $body,
             str_limit((string)($in['media_url'] ?? ''), 250) ?: null, $status, $when, $in['admin_id'] ?? null, time()]);
        return ['ok' => true, 'id' => (int)DB::pdo()->lastInsertId(), 'status' => $status];
    }

    /** تبدیل ورودی زمان (timestamp، رشته تاریخ یا خالی) به timestamp */
    public static function parseWhen(mixed $v): int
    {
        if (is_int($v) || is_float($v)) {
            return (int)$v;
        }
        $v = trim((string)$v);
        if ($v === '') {
            return 0;
        }
        $v = strtr($v, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                         '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '،' => ' ']);
        if (preg_match('~^\d{10,}$~', $v)) {
            return (int)$v;
        }
        $ts = strtotime(str_replace('/', '-', $v));
        return $ts !== false ? (int)$ts : 0;
    }

    /** ارسال فوری یک خبر */
    public static function send(int $id): array
    {
        $n = DB::one("SELECT * FROM news_posts WHERE id=?", [$id]);
        if (!$n) {
            return ['ok' => false, 'error' => 'خبر پیدا نشد.'];
        }
        $channelId = (int)($n['channel_id'] ?? 0);
        $targets = $channelId > 0
            ? DB::all("SELECT * FROM channels WHERE chat_id=?", [$channelId])
            : DB::all("SELECT * FROM channels WHERE is_active=1");
        if (!$targets) {
            return ['ok' => false, 'error' => 'کانال مقصدی وجود ندارد؛ اول کانال ثبت کن.'];
        }
        $text = ($n['title'] ? '📰 <b>' . h((string)$n['title']) . "</b>\n\n" : '') . (string)$n['body'];
        $ok = 0;
        $err = '';
        foreach ($targets as $c) {
            $body = $text;
            $sig = trim((string)($c['signature'] ?? ''));
            if ($sig !== '') {
                $body .= "\n\n" . $sig;
            }
            $media = trim((string)($n['media_url'] ?? ''));
            $res = $media !== ''
                ? BaleApi::sendPhoto((int)$c['chat_id'], $media, $body)
                : BaleApi::sendMessage((int)$c['chat_id'], $body, null, null, 'HTML');
            if ($res !== null) {
                $ok++;
                DB::q("UPDATE channels SET post_count=post_count+1 WHERE id=?", [(int)$c['id']]);
            } else {
                $err = (string)(BaleApi::$lastError ?? 'ارسال ناموفق');
            }
        }
        if ($ok === 0) {
            DB::q("UPDATE news_posts SET status='failed', error=? WHERE id=?", [str_limit($err, 250), $id]);
            return ['ok' => false, 'error' => $err !== '' ? $err : 'ارسال ناموفق'];
        }
        DB::q("UPDATE news_posts SET status='sent', sent_at=?, error=NULL WHERE id=?", [time(), $id]);
        Logger::audit(null, 'panel', 'news_sent', 'news#' . $id, 'کانال‌ها: ' . $ok);
        return ['ok' => true, 'sent' => $ok];
    }

    public static function delete(int $id): bool
    {
        DB::q("DELETE FROM news_posts WHERE id=?", [$id]);
        return true;
    }

    public static function cron(): void
    {
        $due = DB::all("SELECT id FROM news_posts WHERE status='scheduled' AND scheduled_at <= ? ORDER BY scheduled_at LIMIT 5", [time()]);
        foreach ($due as $row) {
            self::send((int)$row['id']);
        }
    }

    public static function panelData(): ?array
    {
        return [
            'news'     => DB::all("SELECT * FROM news_posts ORDER BY id DESC LIMIT 50"),
            'channels' => DB::all("SELECT * FROM channels WHERE is_active=1 ORDER BY title"),
            'stats'    => [
                'scheduled' => (int)DB::val("SELECT COUNT(*) FROM news_posts WHERE status='scheduled'", [], 0),
                'sent'      => (int)DB::val("SELECT COUNT(*) FROM news_posts WHERE status='sent'", [], 0),
                'failed'    => (int)DB::val("SELECT COUNT(*) FROM news_posts WHERE status='failed'", [], 0),
            ],
        ];
    }

    public static function handleAction(string $action, array $admin): ?string
    {
        switch ($action) {
            case 'news_save':
                $r = self::save($_POST + ['admin_id' => (int)$admin['id']]);
                if (!$r['ok']) {
                    return self::fail((string)($r['error'] ?? 'ذخیره خبر ناموفق بود.'));
                }
                return $r['status'] === 'scheduled' ? 'خبر ذخیره و زمان‌بندی شد.' : 'خبر به‌صورت پیش‌نویس ذخیره شد.';
            case 'news_send':
                $r = self::send((int)input('id', 0));
                return $r['ok']
                    ? 'خبر ارسال شد (' . digits_fa((int)($r['sent'] ?? 0)) . ' کانال).'
                    : self::fail((string)($r['error'] ?? 'ارسال ناموفق.'));
            case 'news_delete':
                self::delete((int)input('id', 0));
                return 'خبر حذف شد.';
        }
        return null;
    }
}
