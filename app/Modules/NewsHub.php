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
use str_limit;

/**
 * ماژول «کانال خبر» (۱.۵)
 *
 * جریان کار:
 *   ۱) چند «کانال منبع» در تنظیمات ثبت می‌کنی (ربات باید در آن‌ها ادمین باشد)
 *   ۲) برای هر «دسته» یک فهرست کلیدواژه و یک «کانال مقصد» تعیین می‌کنی
 *   ۳) هر پست تازه در کانال‌های منبع → خودکار دسته‌بندی (بر اساس کلیدواژه‌ها)
 *      → به کانال مقصد همان دسته (یا مقصد پیش‌فرض) فرستاده می‌شود
 *
 * اگر بله آپدیت `channel_post` نفرستد، از فرم «ارسال دستی خبر» در پنل
 * (یا ارسال متن به ربات با دستور `خبر_دستی`) استفاده می‌شود؛ همان دسته‌بندی
 * و همان مقصدها عمل می‌کنند.
 */
final class NewsHub extends Module
{
    public static function key(): string
    {
        return 'newshub';
    }

    public static function title(): string
    {
        return 'کانال خبر';
    }

    public static function description(): string
    {
        return 'چند کانال منبع → دسته‌بندی خودکار با کلیدواژه → ارسال به کانال مقصد هر دسته';
    }

    public static function icon(): string
    {
        return '📡';
    }

    public static function badge(): string
    {
        $src = (int)DB::val("SELECT COUNT(*) FROM news_sources WHERE is_active=1", [], 0);
        $cat = (int)DB::val("SELECT COUNT(*) FROM news_categories WHERE is_active=1", [], 0);
        $sent = (int)DB::val("SELECT COUNT(*) FROM news_inbox WHERE status='sent'", [], 0);
        return digits_fa($src) . ' منبع · ' . digits_fa($cat) . ' دسته · ' . digits_fa($sent) . ' ارسال';
    }

    public static function commands(): array
    {
        return [
            'منابع_خبر'  => 'cmdSummary',
            'کانال_خبر'  => 'cmdSummary',
            'خبر_دستی'   => 'cmdManual',
        ];
    }

    // ---------------- تنظیمات ----------------

    public static function autoSend(): bool
    {
        $v = Schema::getSetting('newshub_auto', '1');
        return $v === null ? true : (string)$v === '1';
    }

    public static function withHeader(): bool
    {
        $v = Schema::getSetting('newshub_header', '1');
        return $v === null ? true : (string)$v === '1';
    }

    public static function defaultTarget(): int
    {
        return (int)(Schema::getSetting('newshub_default_target', '0') ?: 0);
    }

    // ---------------- منابع ----------------

    public static function sources(bool $onlyActive = false): array
    {
        $w = $onlyActive ? 'WHERE is_active=1' : '';
        return DB::all("SELECT * FROM news_sources $w ORDER BY id");
    }

    public static function addSource(array $in): array
    {
        $raw   = trim((string)($in['chat_id'] ?? ''));
        $title = trim((string)($in['title'] ?? ''));
        if ($raw === '') {
            return ['ok' => false, 'error' => 'آیدی عددی یا @یوزرنیم کانال منبع را وارد کن.'];
        }
        $chatId = 0;
        if (preg_match('~^-?\d+$~', $raw)) {
            $chatId = (int)$raw;
        } elseif (preg_match('~^@?[A-Za-z0-9_]{4,}$~', $raw)) {
            $info   = BaleApi::getChat('@' . ltrim($raw, '@'));
            $chatId = (int)($info['id'] ?? 0);
            if ($chatId === 0) {
                return ['ok' => false, 'error' => 'کانال با این یوزرنیم پیدا نشد (ربات باید ادمین باشد).'];
            }
            if ($title === '') {
                $title = (string)($info['title'] ?? ltrim($raw, '@'));
            }
        } else {
            return ['ok' => false, 'error' => 'قالب آیدی نامعتبر است.'];
        }
        if ($chatId === 0) {
            return ['ok' => false, 'error' => 'آیدی کانال معتبر نیست.'];
        }
        if ($title === '') {
            $title = (string)$chatId;
        }
        $exists = DB::one("SELECT * FROM news_sources WHERE chat_id=?", [$chatId]);
        if ($exists) {
            DB::q("UPDATE news_sources SET title=?, is_active=1 WHERE id=?", [str_limit($title, 156), (int)$exists['id']]);
            return ['ok' => true, 'id' => (int)$exists['id'], 'updated' => true];
        }
        DB::q("INSERT INTO news_sources (chat_id,title,is_active,created_at) VALUES (?,?,1,?)",
            [$chatId, str_limit($title, 156), time()]);
        $id = (int)DB::pdo()->lastInsertId();
        Logger::audit(null, 'panel', 'newshub_source_add', (string)$chatId, $title);
        return ['ok' => true, 'id' => $id];
    }

    public static function toggleSource(int $id, bool $on): bool
    {
        DB::q("UPDATE news_sources SET is_active=? WHERE id=?", [$on ? 1 : 0, $id]);
        return true;
    }

    public static function deleteSource(int $id): bool
    {
        DB::q("DELETE FROM news_sources WHERE id=?", [$id]);
        return true;
    }

    public static function isSource(int $chatId): bool
    {
        return DB::one("SELECT id FROM news_sources WHERE chat_id=? AND is_active=1", [$chatId]) !== null;
    }

    // ---------------- دسته‌ها و دسته‌بندی ----------------

    public static function categories(bool $onlyActive = false): array
    {
        $w = $onlyActive ? 'WHERE is_active=1' : '';
        return DB::all("SELECT * FROM news_categories $w ORDER BY sort, id");
    }

    public static function keywordsOf(array $cat): array
    {
        $out = [];
        foreach (preg_split('~[,،|\n]+~u', (string)$cat['keywords']) ?: [] as $kw) {
            $kw = trim($kw);
            if ($kw !== '') {
                $out[] = $kw;
            }
        }
        return $out;
    }

    public static function saveCategory(array $in): array
    {
        $id   = (int)($in['id'] ?? 0);
        $name = trim((string)($in['name'] ?? ''));
        if (mb_strlen($name) < 2) {
            return ['ok' => false, 'error' => 'نام دسته حداقل ۲ کاراکتر باشد.'];
        }
        $keywords = trim((string)($in['keywords'] ?? ''));
        if ($keywords === '') {
            return ['ok' => false, 'error' => 'حداقل یک کلیدواژه لازم است (با کاما جدا کن).'];
        }
        $target = trim((string)($in['target_chat'] ?? ''));
        $targetId = 0;
        if ($target !== '') {
            if (preg_match('~^-?\d+$~', $target)) {
                $targetId = (int)$target;
            } elseif (preg_match('~^@?[A-Za-z0-9_]{4,}$~', $target)) {
                $info = BaleApi::getChat('@' . ltrim($target, '@'));
                $targetId = (int)($info['id'] ?? 0);
                if ($targetId === 0) {
                    return ['ok' => false, 'error' => 'کانال مقصد با این یوزرنیم پیدا نشد.'];
                }
            } else {
                return ['ok' => false, 'error' => 'آیدی کانال مقصد نامعتبر است.'];
            }
        }
        $sort = (int)($in['sort'] ?? 0);
        if ($id > 0) {
            DB::q("UPDATE news_categories SET name=?, keywords=?, target_chat=?, is_active=?, sort=? WHERE id=?",
                [str_limit($name, 76), str_limit($keywords, 590), $targetId ?: null,
                 !empty($in['is_active']) || !isset($in['is_active']) ? 1 : 0, $sort, $id]);
            return ['ok' => true, 'id' => $id];
        }
        DB::q("INSERT INTO news_categories (name,keywords,target_chat,is_active,sort,created_at)
               VALUES (?,?,?,?,?,?)",
            [str_limit($name, 76), str_limit($keywords, 590), $targetId ?: null,
             !empty($in['is_active']) || !isset($in['is_active']) ? 1 : 0, $sort, time()]);
        $newId = (int)DB::pdo()->lastInsertId();
        Logger::audit(null, 'panel', 'newshub_category_save', 'cat#' . $newId, $name);
        return ['ok' => true, 'id' => $newId];
    }

    public static function deleteCategory(int $id): bool
    {
        DB::q("DELETE FROM news_categories WHERE id=?", [$id]);
        return true;
    }

    /** نرمال‌سازی متن فارسی برای تطبیق کلیدواژه */
    public static function norm(string $text): string
    {
        $text = strtr($text, ['ي' => 'ی', 'ك' => 'ک', 'ۀ' => 'ه', 'ة' => 'ه', 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا']);
        $text = str_replace(["\u{200c}", "\u{200f}", "\u{200e}"], ' ', $text); // نیم‌فاصله و کنترل‌ها
        $text = preg_replace('~[\x{064B}-\x{065F}]~u', '', $text) ?? $text;   // اعراب
        $text = mb_strtolower($text);
        return preg_replace('~\s+~u', ' ', $text) ?? $text;
    }

    /**
     * تشخیص دسته یک متن بر اساس کلیدواژه‌ها (بیشترین تطبیق برنده است)
     * @return array{0:?array,1:int} [دسته, امتیاز]
     */
    public static function classify(string $text): array
    {
        $t = self::norm($text);
        if ($t === '') {
            return [null, 0];
        }
        $best = null;
        $bestScore = 0;
        foreach (self::categories(true) as $cat) {
            $score = 0;
            foreach (self::keywordsOf($cat) as $kw) {
                $k = self::norm($kw);
                if ($k !== '' && mb_strpos($t, $k) !== false) {
                    // کلیدواژه طولانی‌تر وزن بیشتری دارد
                    $score += max(1, mb_strlen($k));
                }
            }
            if ($score > $bestScore) {
                $best = $cat;
                $bestScore = $score;
            }
        }
        return [$best, $bestScore];
    }

    /** مقصد نهایی یک خبر: مقصد دسته، وگرنه مقصد پیش‌فرض */
    public static function targetFor(?array $category): int
    {
        $t = (int)($category['target_chat'] ?? 0);
        return $t !== 0 ? $t : self::defaultTarget();
    }

    // ---------------- دریافت و ارسال ----------------

    /**
     * پردازش پست کانال منبع
     * @param array<string,mixed> $post
     */
    public static function handleChannelPost(array $post): bool
    {
        $chatId = (int)($post['chat']['id'] ?? 0);
        if ($chatId === 0 || !self::isSource($chatId)) {
            return false;
        }
        $text = trim((string)($post['text'] ?? $post['caption'] ?? ''));
        if ($text === '') {
            return false; // پست بدون متن (فقط عکس/ویدیو) — قابل دسته‌بندی نیست
        }
        $item = self::ingest($text, $chatId, (int)($post['message_id'] ?? 0), null, $post);
        return $item !== null;
    }

    /**
     * ثبت یک خبر در صندوق ورودی (و ارسال در صورت فعال بودن ارسال خودکار)
     * @param array<string,mixed>|null $raw پست اصلی برای فوروارد
     */
    public static function ingest(string $text, int $sourceChat = 0, int $sourceMsg = 0, ?int $forcedCategory = null, ?array $raw = null): ?array
    {
        [$cat, $score] = self::classify($text);
        if ($forcedCategory !== null) {
            $cat = DB::one("SELECT * FROM news_categories WHERE id=?", [$forcedCategory]);
        }
        $target = self::targetFor($cat);
        DB::q("INSERT INTO news_inbox (source_chat,source_msg,category_id,body,status,target_chat,created_at)
               VALUES (?,?,?,?, 'new', ?,?)",
            [$sourceChat, $sourceMsg ?: null, $cat ? (int)$cat['id'] : null,
             str_limit($text, 4000), $target ?: null, time()]);
        $item = DB::one("SELECT * FROM news_inbox WHERE id=?", [(int)DB::pdo()->lastInsertId()]);

        if (!self::autoSend() || $target === 0) {
            if ($target === 0) {
                DB::q("UPDATE news_inbox SET status='skipped', error=? WHERE id=?",
                    ['مقصدی تعیین نشده', (int)$item['id']]);
            }
            return DB::one("SELECT * FROM news_inbox WHERE id=?", [(int)$item['id']]);
        }
        self::deliver($item, $cat, $target, $sourceChat, $sourceMsg);
        return DB::one("SELECT * FROM news_inbox WHERE id=?", [(int)$item['id']]);
    }

    /** ارسال یک ردیف صندوق ورودی به مقصد */
    public static function deliver(array $item, ?array $cat = null, ?int $target = null, int $fromChat = 0, int $fromMsg = 0): array
    {
        $cat    = $cat ?? ($item['category_id'] ? DB::one("SELECT * FROM news_categories WHERE id=?", [(int)$item['category_id']]) : null);
        $target = $target ?? (int)($item['target_chat'] ?? 0) ?: self::targetFor($cat);
        if ($target === 0) {
            DB::q("UPDATE news_inbox SET status='skipped', error=? WHERE id=?", ['مقصدی تعیین نشده', (int)$item['id']]);
            return ['ok' => false, 'error' => 'مقصدی تعیین نشده است.'];
        }
        $fromChat = $fromChat ?: (int)$item['source_chat'];
        $fromMsg  = $fromMsg ?: (int)($item['source_msg'] ?? 0);

        $msgId = 0;
        // فوروارد اگر پست اصلی از کانال آمده باشد (رسانه حفظ می‌شود)
        if ($fromChat !== 0 && $fromMsg !== 0) {
            $fwd = BaleApi::forwardMessage($target, $fromChat, $fromMsg);
            if ($fwd !== null) {
                $msgId = (int)($fwd['message_id'] ?? 0);
            }
        }
        if ($msgId === 0) {
            $header = self::withHeader() && $cat ? '📌 <b>' . h((string)$cat['name']) . "</b>\n" : '';
            $res = BaleApi::sendMessage($target, $header . str_limit((string)$item['body'], 3900), null, null, 'HTML');
            if ($res === null) {
                DB::q("UPDATE news_inbox SET status='failed', error=? WHERE id=?",
                    [str_limit((string)(BaleApi::$lastError ?? 'ارسال ناموفق'), 250), (int)$item['id']]);
                return ['ok' => false, 'error' => 'ارسال ناموفق'];
            }
            $msgId = (int)$res;
        }
        DB::q("UPDATE news_inbox SET status='sent', target_chat=?, sent_msg=?, error=NULL WHERE id=?",
            [$target, $msgId ?: null, (int)$item['id']]);
        Logger::audit(null, 'module', 'newshub_sent', 'inbox#' . (int)$item['id'],
            ($cat['name'] ?? 'بدون دسته') . ' → ' . $target);
        return ['ok' => true, 'message_id' => $msgId, 'target' => $target];
    }

    public static function deleteItem(int $id): bool
    {
        DB::q("DELETE FROM news_inbox WHERE id=?", [$id]);
        return true;
    }

    // ---------------- دستورهای ربات ----------------

    public static function cmdSummary(array $ctx): void
    {
        $src = self::sources(true);
        $cats = self::categories(true);
        $lines = ['📡 <b>کانال خبر</b>'];
        $lines[] = 'منابع فعال: <b>' . digits_fa(count($src)) . '</b>';
        foreach ($src as $s) {
            $lines[] = '• ' . h((string)$s['title']) . ' (<code>' . h((string)$s['chat_id']) . '</code>)';
        }
        $lines[] = '';
        $lines[] = 'دسته‌ها:';
        foreach ($cats as $c) {
            $t = (int)($c['target_chat'] ?? 0) ?: self::defaultTarget();
            $lines[] = '• <b>' . h((string)$c['name']) . '</b> → ' . ($t ? '<code>' . h((string)$t) . '</code>' : 'مقصد ندارد');
        }
        if (!$src && !$cats) {
            $lines[] = 'هنوز منبع/دسته‌ای تنظیم نشده؛ از پنل → 🧩 ماژول‌ها → کانال خبر اضافه کن.';
        }
        self::say($ctx, implode("\n", $lines));
    }

    public static function cmdManual(array $ctx): void
    {
        if (!self::isAdmin($ctx) && (int)($ctx['chat_id'] ?? 0) === (int)($ctx['user_id'] ?? -1)) {
            // در چت خصوصی، ادمین پنل هم می‌تواند؛ در گروه فقط ادمین گروه
            self::say($ctx, '⛔ این دستور برای ادمین‌هاست.');
            return;
        }
        $text = trim((string)($ctx['rest'] ?? ''));
        if (mb_strlen($text) < 5) {
            self::say($ctx, 'شکل درست: <code>خبر_دستی متن خبر …</code>');
            return;
        }
        $item = self::ingest($text, 0, 0);
        if ($item === null) {
            self::say($ctx, '⚠️ ثبت خبر ناموفق بود.');
            return;
        }
        $cat = $item['category_id'] ? DB::one("SELECT name FROM news_categories WHERE id=?", [(int)$item['category_id']]) : null;
        self::say($ctx, '📡 خبر ثبت شد.' . "\nدسته: <b>" . h((string)($cat['name'] ?? 'بدون دسته')) . '</b>'
            . "\nوضعیت: " . h((string)$item['status']));
    }

    // ---------------- پنل ----------------

    public static function panelData(): ?array
    {
        $test = null;
        $tt   = trim((string)($_GET['try'] ?? ''));
        if ($tt !== '') {
            [$c, $score] = self::classify($tt);
            $test = [
                'text'   => $tt,
                'cat'    => $c,
                'score'  => $score,
                'target' => self::targetFor($c),
            ];
        }
        return [
            'sources'    => self::sources(),
            'categories' => self::categories(),
            'inbox'      => DB::all("SELECT i.*, c.name AS category_name FROM news_inbox i
                                     LEFT JOIN news_categories c ON c.id=i.category_id
                                     ORDER BY i.id DESC LIMIT 40"),
            'auto'       => self::autoSend(),
            'header'     => self::withHeader(),
            'default_target' => self::defaultTarget(),
            'test'       => $test,
            'stats'      => [
                'sources' => count(self::sources(true)),
                'cats'    => count(self::categories(true)),
                'sent'    => (int)DB::val("SELECT COUNT(*) FROM news_inbox WHERE status='sent'", [], 0),
                'waiting' => (int)DB::val("SELECT COUNT(*) FROM news_inbox WHERE status='new'", [], 0),
            ],
            'groups' => DB::all("SELECT chat_id,title FROM chat_settings ORDER BY title LIMIT 300"),
            'channels' => DB::all("SELECT chat_id,title FROM channels WHERE is_active=1 ORDER BY title"),
        ];
    }

    public static function handleAction(string $action, array $admin): ?string
    {
        switch ($action) {
            case 'newshub_settings':
                Schema::setSetting('newshub_auto', !empty($_POST['newshub_auto']) ? '1' : '0');
                Schema::setSetting('newshub_header', !empty($_POST['newshub_header']) ? '1' : '0');
                $dt = trim((string)input('newshub_default_target', ''));
                $targetId = 0;
                if ($dt !== '') {
                    if (preg_match('~^-?\d+$~', $dt)) {
                        $targetId = (int)$dt;
                    } else {
                        $info = BaleApi::getChat('@' . ltrim($dt, '@'));
                        $targetId = (int)($info['id'] ?? 0);
                    }
                }
                Schema::setSetting('newshub_default_target', (string)$targetId);
                return 'تنظیمات کانال خبر ذخیره شد.';
            case 'newshub_source_add':
                $r = self::addSource(['chat_id' => input('chat_id', ''), 'title' => input('title', '')]);
                return $r['ok'] ? 'کانال منبع ثبت شد.' : self::fail((string)($r['error'] ?? 'ثبت منبع ناموفق.'));
            case 'newshub_source_toggle':
                self::toggleSource((int)input('id', 0), (string)input('on', '0') === '1');
                return 'وضعیت منبع تغییر کرد.';
            case 'newshub_source_delete':
                self::deleteSource((int)input('id', 0));
                return 'کانال منبع حذف شد.';
            case 'newshub_category_save':
                $r = self::saveCategory([
                    'id' => (int)input('id', 0), 'name' => input('name', ''),
                    'keywords' => input('keywords', ''), 'target_chat' => input('target_chat', ''),
                    'sort' => (int)input('sort', 0), 'is_active' => input('is_active', '1'),
                ]);
                return $r['ok'] ? 'دسته ذخیره شد.' : self::fail((string)($r['error'] ?? 'ذخیره دسته ناموفق.'));
            case 'newshub_category_delete':
                self::deleteCategory((int)input('id', 0));
                return 'دسته حذف شد.';
            case 'newshub_send_manual':
                $text = trim((string)input('body', ''));
                if (mb_strlen($text) < 5) {
                    return self::fail('متن خبر حداقل ۵ کاراکتر باشد.');
                }
                $forced = (int)input('category_id', 0);
                $item = self::ingest($text, 0, 0, $forced > 0 ? $forced : null);
                if ($item === null) {
                    return self::fail('ثبت خبر ناموفق بود.');
                }
                return 'خبر پردازش شد — وضعیت: ' . self::faStatus((string)$item['status']);
            case 'newshub_send_item':
                $item = DB::one("SELECT * FROM news_inbox WHERE id=?", [(int)input('id', 0)]);
                if (!$item) {
                    return self::fail('خبر پیدا نشد.');
                }
                $r = self::deliver($item);
                return $r['ok']
                    ? 'خبر به مقصد ارسال شد (' . digits_fa((int)($r['target'] ?? 0)) . ').'
                    : self::fail((string)($r['error'] ?? 'ارسال ناموفق.'));
            case 'newshub_delete_item':
                self::deleteItem((int)input('id', 0));
                return 'خبر از صندوق حذف شد.';
        }
        return null;
    }

    public static function faStatus(string $status): string
    {
        return [
            'new' => 'در انتظار', 'sent' => 'ارسال‌شده',
            'skipped' => 'رد‌شده (بدون مقصد)', 'failed' => 'ناموفق',
        ][$status] ?? $status;
    }
}
