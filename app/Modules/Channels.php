<?php
declare(strict_types=1);

namespace Modules;

use BaleApi;
use DB;
use Logger;
use Module;
use Schema;
use Broadcast;
use h;
use digits_fa;
use dt;

/**
 * ماژول «مدیریت کانال»
 * - ثبت کانال/گروه‌های مقصد (با آیدی عددی یا یوزرنیم)
 * - امضای پیش‌فرض و ارسال پست به کانال‌ها با موتور صف (Broadcast)
 * - آمار پست‌های ارسال‌شده
 */
final class Channels extends Module
{
    public static function key(): string
    {
        return 'channels';
    }

    public static function title(): string
    {
        return 'مدیریت کانال';
    }

    public static function description(): string
    {
        return 'ثبت کانال‌ها، امضای پست و ارسال پست/اعلان به کانال‌ها';
    }

    public static function icon(): string
    {
        return '📣';
    }

    public static function badge(): string
    {
        $n = (int)DB::val("SELECT COUNT(*) FROM channels WHERE is_active=1", [], 0);
        $p = (int)DB::val("SELECT COALESCE(SUM(post_count),0) FROM channels", [], 0);
        return digits_fa($n) . ' کانال · ' . digits_fa($p) . ' پست';
    }

    public static function commands(): array
    {
        return ['کانالها' => 'listChannels'];
    }

    public static function listChannels(array $ctx): void
    {
        $rows = self::channels();
        if (!$rows) {
            self::say($ctx, "کانالی ثبت نشده است.");
            return;
        }
        $lines = ["📣 <b>کانال‌های ما</b>"];
        foreach ($rows as $c) {
            $lines[] = '• <b>' . h((string)$c['title']) . '</b>'
                . ($c['username'] ? ' (@' . h((string)$c['username']) . ')' : '');
        }
        self::say($ctx, implode("\n", $lines));
    }

    /** @return array<int,array<string,mixed>> */
    public static function channels(bool $onlyActive = false): array
    {
        $w = $onlyActive ? 'WHERE is_active=1' : '';
        return DB::all("SELECT * FROM channels $w ORDER BY id DESC");
    }

    public static function add(array $in): array
    {
        $chat = trim((string)($in['chat_id'] ?? ''));
        $title = trim((string)($in['title'] ?? ''));
        if ($chat === '') {
            return ['ok' => false, 'error' => 'آیدی عددی یا @یوزرنیم کانال را وارد کن.'];
        }
        $chatId = 0;
        if (preg_match('~^-?\d+$~', $chat)) {
            $chatId = (int)$chat;
        } elseif (preg_match('~^@?[A-Za-z0-9_]{4,}$~', $chat)) {
            $info = BaleApi::getChat('@' . ltrim($chat, '@'));
            $chatId = (int)($info['id'] ?? 0);
            if ($chatId === 0) {
                return ['ok' => false, 'error' => 'کانال با این یوزرنیم پیدا نشد (ربات باید ادمین کانال باشد).'];
            }
            if ($title === '') {
                $title = (string)($info['title'] ?? $chat);
            }
        } else {
            return ['ok' => false, 'error' => 'قالب آیدی نامعتبر است.'];
        }
        if ($title === '') {
            return ['ok' => false, 'error' => 'عنوان کانال را وارد کن.'];
        }
        // یوزرنیم فقط وقتی ورودی متنی (@name) بوده معنا دارد
        $username = preg_match('~^-?\d+$~', $chat) ? null : ltrim($chat, '@');
        $exists = DB::one("SELECT * FROM channels WHERE chat_id=?", [$chatId]);
        if ($exists) {
            // امضا فقط وقتی پاک/تغییر می‌کند که در فرم آمده باشد
            $sig = array_key_exists('signature', $in)
                ? (str_limit(trim((string)$in['signature']), 250) ?: null)
                : ($exists['signature'] ?? null);
            DB::q("UPDATE channels SET title=?, username=?, kind=?, signature=?, is_active=1 WHERE id=?",
                [$title, $username === '' ? null : $username, (string)($in['kind'] ?? $exists['kind'] ?? 'channel'),
                 $sig, (int)$exists['id']]);
            return ['ok' => true, 'id' => (int)$exists['id'], 'updated' => true];
        }
        DB::q("INSERT INTO channels (chat_id,title,username,kind,signature,is_active,created_at)
               VALUES (?,?,?,?,?,1,?)",
            [$chatId, $title, $username === '' ? null : $username, (string)($in['kind'] ?? 'channel'),
             str_limit((string)($in['signature'] ?? ''), 250) ?: null, time()]);
        $newId = (int)DB::pdo()->lastInsertId();
        Logger::audit(null, 'panel', 'channel_add', (string)$chatId, $title);
        return ['ok' => true, 'id' => $newId];
    }

    public static function toggle(int $id, bool $active): bool
    {
        DB::q("UPDATE channels SET is_active=? WHERE id=?", [$active ? 1 : 0, $id]);
        return true;
    }

    public static function delete(int $id): bool
    {
        DB::q("DELETE FROM channels WHERE id=?", [$id]);
        return true;
    }

    /**
     * ارسال پست به کانال‌ها: اگر یک کانال انتخاب شود مستقیم، وگرنه از صف همگانی
     * @return array{ok:bool,queued?:int,error?:string}
     */
    public static function publish(int $channelId, string $text, bool $useQueue = false): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'error' => 'متن پست خالی است.'];
        }
        $channels = $channelId > 0
            ? DB::all("SELECT * FROM channels WHERE id=? AND is_active=1", [$channelId])
            : self::channels(true);
        if (!$channels) {
            return ['ok' => false, 'error' => 'کانال فعالی انتخاب نشده است.'];
        }
        $sent = 0;
        $fail = 0;
        foreach ($channels as $c) {
            $body = $text;
            $sig = trim((string)($c['signature'] ?? ''));
            if ($sig !== '' && !str_contains($body, $sig)) {
                $body .= "\n\n" . $sig;
            }
            if ($useQueue) {
                $res = Broadcast::enqueue([
                    'text'     => $body,
                    'title'    => 'کانال: ' . mb_strimwidth((string)$c['title'], 0, 40, '…'),
                    'chats'    => [(int)$c['chat_id']],
                    'rate'     => 5,
                ]);
                if ($res['ok']) {
                    $sent++;
                } else {
                    $fail++;
                }
                continue;
            }
            if (BaleApi::hasToken() && BaleApi::sendMessage((int)$c['chat_id'], $body, null, null, 'HTML') !== null) {
                $sent++;
                DB::q("UPDATE channels SET post_count=post_count+1 WHERE id=?", [(int)$c['id']]);
            } else {
                $fail++;
            }
        }
        Logger::audit(null, 'panel', 'channel_publish', 'channels', "sent=$sent fail=$fail");
        if ($sent === 0) {
            return ['ok' => false, 'error' => 'هیچ پستی ارسال نشد (ربات ادمین کانال است؟).'];
        }
        return ['ok' => true, 'queued' => $sent, 'fail' => $fail];
    }

    public static function panelData(): ?array
    {
        return [
            'channels' => self::channels(),
            'stats'    => [
                'total' => (int)DB::val("SELECT COUNT(*) FROM channels", [], 0),
                'active' => (int)DB::val("SELECT COUNT(*) FROM channels WHERE is_active=1", [], 0),
                'posts' => (int)DB::val("SELECT COALESCE(SUM(post_count),0) FROM channels", [], 0),
            ],
            'recent' => DB::all("SELECT * FROM news_posts ORDER BY id DESC LIMIT 5"),
        ];
    }

    public static function handleAction(string $action, array $admin): ?string
    {
        switch ($action) {
            case 'channel_add':
                $r = self::add($_POST + [
                    'chat_id'   => input('chat_id', ''),
                    'title'     => input('title', ''),
                    'signature' => input('signature', ''),
                    'kind'      => input('kind', 'channel'),
                ]);
                return $r['ok'] ? 'کانال ثبت شد.' : self::fail((string)($r['error'] ?? 'خطا در ثبت کانال.'));
            case 'channel_toggle':
                $on = array_key_exists('on', $_POST) ? $_POST['on'] : input('active', '0');
                self::toggle((int)input('id', 0), (string)$on === '1');
                return 'وضعیت کانال تغییر کرد.';
            case 'channel_delete':
                self::delete((int)input('id', 0));
                return 'کانال حذف شد.';
            case 'channel_publish':
                $q = array_key_exists('queue', $_POST) ? $_POST['queue'] : input('via_queue', '0');
                $r = self::publish((int)input('channel_id', 0), (string)input('text', ''), (string)$q === '1');
                return $r['ok']
                    ? 'پست ارسال شد (' . digits_fa((int)($r['queued'] ?? 0)) . ' مورد).'
                    : self::fail((string)($r['error'] ?? 'ارسال ناموفق.'));
        }
        return null;
    }
}
