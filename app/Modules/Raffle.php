<?php
declare(strict_types=1);

namespace Modules;

use BaleApi;
use DB;
use Logger;
use Module;
use digits_fa;
use dt;
use h;

/**
 * ماژول «قرعه‌کشی» (۱.۵)
 *
 * - ساخت قرعه‌کشی با جایزه، مهلت شرکت و تعداد برنده
 * - شرکت با دکمه شیشه‌ای یا دستور `شرکت CODE` (هر کاربر یک‌بار)
 * - شرط اختیاری «عضویت در کانال» پیش از شرکت
 * - قرعه‌کشی دستی از پنل یا خودکار در زمان پایان (کرون) + اعلام برنده‌ها
 */
final class Raffle extends Module
{
    public static function key(): string
    {
        return 'raffle';
    }

    public static function title(): string
    {
        return 'قرعه‌کشی';
    }

    public static function description(): string
    {
        return 'قرعه‌کشی با جایزه، شرط عضویت، شرکت با دکمه و انتخاب تصادفی برنده‌ها';
    }

    public static function icon(): string
    {
        return '🎁';
    }

    public static function badge(): string
    {
        $act = (int)DB::val("SELECT COUNT(*) FROM raffles WHERE status='active'", [], 0);
        $ent = (int)DB::val("SELECT COUNT(*) FROM raffle_entries", [], 0);
        return digits_fa($act) . ' فعال · ' . digits_fa($ent) . ' شرکت‌کننده';
    }

    public static function commands(): array
    {
        return [
            'قرعه‌کشی'        => 'listRaffles',
            'قرعه‌کشی‌ها'     => 'listRaffles',
            'شرکت'            => 'cmdJoin',
            'برنده'           => 'cmdWinners',
            'قرعه‌کشی_ساز'    => 'cmdCreate',
        ];
    }

    // ---------------- هسته ----------------

    public static function byCode(string $code): ?array
    {
        $code = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', $code) ?? '');
        if ($code === '') {
            return null;
        }
        return DB::one("SELECT * FROM raffles WHERE code=?", [$code]);
    }

    public static function create(array $in): array
    {
        $title = trim((string)($in['title'] ?? ''));
        $prize = trim((string)($in['prize'] ?? ''));
        if (mb_strlen($title) < 3 || mb_strlen($prize) < 2) {
            return ['ok' => false, 'error' => 'عنوان و جایزه را کامل وارد کن.'];
        }
        $ends = $in['ends_at'] ?? 0;
        if (is_string($ends)) {
            $ends = trim($ends) === '' ? 0
                : (ctype_digit(trim($ends)) ? (int)trim($ends)
                    : (int)strtotime(str_replace('/', '-', trim($ends))));
        }
        $ends = (int)$ends;
        if ($ends > 0 && $ends <= time()) {
            return ['ok' => false, 'error' => 'زمان پایان باید در آینده باشد.'];
        }
        $winners = max(1, min(20, (int)($in['winners_count'] ?? 1)));
        $now  = time();
        $code = self::genCode();
        DB::q("INSERT INTO raffles (code,title,prize,description,chat_id,require_chat,ends_at,winners_count,status,admin_id,created_at)
               VALUES (?,?,?,?,?,?,?,?, 'active', ?, ?)",
            [
                $code, str_limit($title, 196), str_limit($prize, 196),
                str_limit((string)($in['description'] ?? ''), 590) ?: null,
                (int)($in['chat_id'] ?? 0) ?: null,
                (int)($in['require_chat'] ?? 0) ?: null,
                $ends, $winners, $in['admin_id'] ?? null, $now,
            ]);
        $id = (int)DB::pdo()->lastInsertId();
        Logger::audit(null, 'panel', 'raffle_create', 'raffle#' . $id, $code . ' — ' . $title);
        return ['ok' => true, 'id' => $id, 'code' => $code];
    }

    public static function genCode(int $len = 5): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($try = 0; $try < 10; $try++) {
            $code = '';
            for ($i = 0; $i < $len; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            if (!DB::one("SELECT id FROM raffles WHERE code=?", [$code])) {
                return $code;
            }
        }
        return 'R' . substr((string)time(), -4);
    }

    public static function entrants(int $raffleId): int
    {
        return (int)DB::val("SELECT COUNT(*) FROM raffle_entries WHERE raffle_id=?", [$raffleId], 0);
    }

    /** متن قرعه‌کشی برای ارسال */
    public static function render(array $r): array
    {
        $n = self::entrants((int)$r['id']);
        $lines = [
            '🎁 <b>' . h((string)$r['title']) . '</b>',
            '🏆 جایزه: <b>' . h((string)$r['prize']) . '</b>',
        ];
        if (!empty($r['description'])) {
            $lines[] = h((string)$r['description']);
        }
        $lines[] = '👥 شرکت‌کننده: <b>' . digits_fa($n) . '</b>'
            . ' · 🥇 برنده: ' . digits_fa((int)$r['winners_count']);
        if ((int)$r['ends_at'] > 0) {
            $lines[] = '⏳ مهلت: ' . dt((int)$r['ends_at'], 'Y/m/d H:i');
        }
        if (!empty($r['require_chat'])) {
            $lines[] = '🔒 شرط: عضویت در کانال <code>' . h((string)$r['require_chat']) . '</code>';
        }
        $markup = null;
        if ((string)$r['status'] === 'active') {
            $markup = ['inline_keyboard' => [[[
                'text'          => '🎲 شرکت در قرعه‌کشی',
                'callback_data' => 'rf:j:' . (int)$r['id'],
            ]]]];
            $lines[] = '';
            $lines[] = 'شرکت با دکمه یا دستور <code>شرکت ' . h((string)$r['code']) . '</code>';
        } else {
            $lines[] = '🔒 این قرعه‌کشی بسته شده است.';
        }
        return ['text' => implode("\n", $lines), 'markup' => $markup];
    }

    public static function publish(int $raffleId, int|string|null $chatId = null): array
    {
        $r = DB::one("SELECT * FROM raffles WHERE id=?", [$raffleId]);
        if (!$r) {
            return ['ok' => false, 'error' => 'قرعه‌کشی پیدا نشد.'];
        }
        $target = $chatId !== null && (string)$chatId !== '' && (string)$chatId !== '0'
            ? $chatId : ($r['chat_id'] ?: null);
        if ($target === null) {
            return ['ok' => false, 'error' => 'چت مقصد را انتخاب کن.'];
        }
        DB::q("UPDATE raffles SET chat_id=? WHERE id=?", [(int)$target, $raffleId]);
        $r['chat_id'] = (int)$target;
        $view = self::render($r);
        $mid  = BaleApi::sendMessage($target, $view['text'], null, $view['markup'], 'HTML');
        if ($mid === null) {
            return ['ok' => false, 'error' => 'ارسال ناموفق (ربات ادمین آن چت هست؟)'];
        }
        DB::q("UPDATE raffles SET message_id=? WHERE id=?", [$mid, $raffleId]);
        return ['ok' => true, 'message_id' => (int)$mid];
    }

    /** شرط عضویت (اگر تنظیم شده باشد) */
    public static function memberOk(array $r, int $userId): bool
    {
        $need = (int)($r['require_chat'] ?? 0);
        if ($need === 0) {
            return true;
        }
        $m = BaleApi::getChatMember($need, $userId);
        $st = (string)($m['status'] ?? '');
        return in_array($st, ['creator', 'administrator', 'member'], true);
    }

    public static function join(int $raffleId, int $userId): array
    {
        $r = DB::one("SELECT * FROM raffles WHERE id=?", [$raffleId]);
        if (!$r) {
            return ['ok' => false, 'error' => 'قرعه‌کشی پیدا نشد.'];
        }
        if ((string)$r['status'] !== 'active') {
            return ['ok' => false, 'error' => 'این قرعه‌کشی بسته شده است.'];
        }
        if ((int)$r['ends_at'] > 0 && (int)$r['ends_at'] < time()) {
            return ['ok' => false, 'error' => 'مهلت شرکت تمام شده است.'];
        }
        if (!self::memberOk($r, $userId)) {
            return ['ok' => false, 'error' => 'اول در کانال عضو شو، بعد شرکت کن.'];
        }
        $ins = DB::q("INSERT IGNORE INTO raffle_entries (raffle_id,user_id,created_at) VALUES (?,?,?)",
            [$raffleId, $userId, time()]);
        if ((int)$ins->rowCount() === 0) {
            return ['ok' => false, 'error' => 'قبلاً شرکت کرده‌ای؛ شانست را دوباره امتحان نکن 😉'];
        }
        DB::q("UPDATE raffles SET entrants=entrants+1 WHERE id=?", [$raffleId]);
        Logger::user(null, $userId, null, 'raffle_join', 'raffle#' . $raffleId);
        return ['ok' => true, 'entrants' => self::entrants($raffleId)];
    }

    /** انتخاب برنده(ها) و اعلام */
    public static function draw(int $raffleId, ?int $adminId = null): array
    {
        $r = DB::one("SELECT * FROM raffles WHERE id=?", [$raffleId]);
        if (!$r) {
            return ['ok' => false, 'error' => 'قرعه‌کشی پیدا نشد.'];
        }
        if ((string)$r['status'] === 'drawn') {
            return ['ok' => false, 'error' => 'قبلاً قرعه‌کشی شده است.'];
        }
        $rows = DB::all("SELECT user_id FROM raffle_entries WHERE raffle_id=?", [$raffleId]);
        if (!$rows) {
            return ['ok' => false, 'error' => 'هیچ شرکت‌کننده‌ای وجود ندارد.'];
        }
        $ids = array_map(static fn($x) => (int)$x['user_id'], $rows);
        shuffle($ids);
        $winners = array_slice($ids, 0, min((int)$r['winners_count'], count($ids)));
        DB::q("UPDATE raffles SET status='drawn', winner_ids=?, drawn_at=? WHERE id=?",
            [implode(',', $winners), time(), $raffleId]);
        Logger::audit($adminId, 'panel', 'raffle_draw', 'raffle#' . $raffleId, implode(',', $winners));

        $names = [];
        foreach ($winners as $w) {
            $u = DB::one("SELECT first_name,last_name,username FROM users WHERE user_id=?", [$w]);
            $label = $u ? trim((string)($u['first_name'] ?? '') . ' ' . (string)($u['last_name'] ?? '')) : '';
            if ($label === '' && $u && !empty($u['username'])) {
                $label = '@' . $u['username'];
            }
            $names[] = ['user_id' => $w, 'name' => $label !== '' ? $label : 'کاربر ' . $w];
        }

        // اعلام در همان چت (اگر تنظیم شده)
        $announce = (int)($r['chat_id'] ?? 0);
        if ($announce !== 0) {
            $lines = ['🎉 <b>نتیجه قرعه‌کشی</b>', '🎁 ' . h((string)$r['title']), '🏆 ' . h((string)$r['prize']), ''];
            foreach ($names as $i => $n) {
                $lines[] = '🥇 برنده ' . digits_fa($i + 1) . ': <b>' . h($n['name']) . '</b>';
            }
            $lines[] = '';
            $lines[] = '🎲 از میان ' . digits_fa(count($ids)) . ' شرکت‌کننده انتخاب شد.';
            self::sayTo($announce, implode("\n", $lines));
        }
        return ['ok' => true, 'winners' => $names, 'total' => count($ids)];
    }

    public static function delete(int $raffleId): bool
    {
        DB::q("DELETE FROM raffle_entries WHERE raffle_id=?", [$raffleId]);
        DB::q("DELETE FROM raffles WHERE id=?", [$raffleId]);
        return true;
    }

    public static function winners(array $r): array
    {
        $ids = array_filter(array_map('intval', explode(',', (string)($r['winner_ids'] ?? ''))));
        $out = [];
        foreach ($ids as $id) {
            $u = DB::one("SELECT first_name,last_name,username FROM users WHERE user_id=?", [$id]);
            $name = $u ? trim((string)($u['first_name'] ?? '') . ' ' . (string)($u['last_name'] ?? '')) : '';
            $out[] = ['user_id' => $id, 'name' => $name !== '' ? $name : ('کاربر ' . $id)];
        }
        return $out;
    }

    // ---------------- دستورهای ربات ----------------

    public static function listRaffles(array $ctx): void
    {
        $rows = DB::all("SELECT * FROM raffles WHERE status='active' ORDER BY id DESC LIMIT 8");
        if (!$rows) {
            self::say($ctx, '🎁 قرعه‌کشی فعالی وجود ندارد.');
            return;
        }
        $lines = ['🎁 <b>قرعه‌کشی‌های فعال</b>'];
        foreach ($rows as $r) {
            $lines[] = '• <code>' . h((string)$r['code']) . '</code> — ' . h((string)$r['title'])
                . ' · 🏆 ' . h((string)$r['prize'])
                . ((int)$r['ends_at'] > 0 ? ' · تا ' . dt((int)$r['ends_at'], 'Y/m/d') : '');
        }
        $lines[] = '';
        $lines[] = 'شرکت: <code>شرکت CODE</code>';
        self::say($ctx, implode("\n", $lines));
    }

    public static function cmdJoin(array $ctx): void
    {
        $rest = trim((string)($ctx['rest'] ?? ''));
        if ($rest === '' && empty($ctx['prefixed'])) {
            // «شرکت» ممکن است واژه معمولی گفتگو باشد؛ بدون کد و پیشوند ساکت بمان
            return;
        }
        $r = self::byCode($rest);
        if (!$r) {
            self::say($ctx, '🎁 شکل درست: <code>شرکت CODE</code> — کد را از <code>قرعه‌کشی</code> بگیر.');
            return;
        }
        $res = self::join((int)$r['id'], (int)$ctx['user_id']);
        if (!$res['ok']) {
            self::say($ctx, '⚠️ ' . h((string)$res['error']));
            return;
        }
        self::say($ctx, '✅ شرکت کردی! 🎲'
            . "\n🎁 " . h((string)$r['title']) . ' — 🏆 ' . h((string)$r['prize'])
            . "\n👥 تعداد شرکت‌کننده‌ها: <b>" . digits_fa((int)$res['entrants']) . '</b>'
            . ((int)$r['ends_at'] > 0 ? "\n⏳ مهلت: " . dt((int)$r['ends_at'], 'Y/m/d H:i') : ''));
    }

    public static function cmdWinners(array $ctx): void
    {
        $r = self::byCode(trim((string)($ctx['rest'] ?? '')));
        if (!$r) {
            self::say($ctx, 'شکل درست: <code>برنده CODE</code>');
            return;
        }
        if ((string)$r['status'] !== 'drawn') {
            self::say($ctx, '⏳ هنوز قرعه‌کشی انجام نشده است.');
            return;
        }
        $w = self::winners($r);
        $lines = ['🎉 <b>برندگان</b> ' . h((string)$r['title'])];
        foreach ($w as $i => $x) {
            $lines[] = '🥇 ' . digits_fa($i + 1) . '. ' . h($x['name']);
        }
        self::say($ctx, implode("\n", $lines));
    }

    public static function cmdCreate(array $ctx): void
    {
        if (!self::isAdmin($ctx)) {
            self::say($ctx, '⛔ فقط ادمین گروه می‌تواند قرعه‌کشی بسازد.');
            return;
        }
        $parts = array_map('trim', explode('|', trim((string)($ctx['rest'] ?? ''))));
        $res = self::create([
            'title' => $parts[0] ?? '',
            'prize' => $parts[1] ?? '',
            'description' => $parts[2] ?? '',
            'chat_id' => (int)$ctx['chat_id'],
            'winners_count' => (int)($parts[3] ?? 1),
        ]);
        if (!$res['ok']) {
            self::say($ctx, '⚠️ ' . h((string)$res['error'])
                . "\nمثال: <code>قرعه‌کشی_ساز عنوان | جایزه | توضیح | تعداد برنده</code>");
            return;
        }
        $pub = self::publish((int)$res['id'], (int)$ctx['chat_id']);
        if (!$pub['ok']) {
            self::say($ctx, 'ساخته شد ولی ارسال نشد: ' . h((string)$pub['error'])
                . ' — کد: <code>' . h((string)$res['code']) . '</code>');
        }
    }

    public static function handleCallback(string $data, array $cb): bool
    {
        if (!str_starts_with($data, 'rf:')) {
            return false;
        }
        $p = explode(':', $data);
        if (count($p) < 3 || $p[1] !== 'j') {
            return false;
        }
        $raffleId = (int)$p[2];
        $uid = (int)($cb['from']['id'] ?? 0);
        if ($uid <= 0) {
            return true;
        }
        $res = self::join($raffleId, $uid);
        BaleApi::answerCallback((string)($cb['id'] ?? ''),
            $res['ok'] ? '🎲 شرکت کردی! موفق باشی' : (string)$res['error'], !$res['ok']);
        return true;
    }

    public static function cron(): void
    {
        $due = DB::all("SELECT id FROM raffles WHERE status='active' AND ends_at > 0 AND ends_at < ?", [time()]);
        foreach ($due as $r) {
            self::draw((int)$r['id'], null);
        }
    }

    // ---------------- پنل ----------------

    public static function panelData(): ?array
    {
        $rows = DB::all("SELECT * FROM raffles ORDER BY id DESC LIMIT 50");
        foreach ($rows as &$r) {
            $r['winners_list'] = (string)$r['status'] === 'drawn' ? self::winners($r) : [];
            $r['entrant_count'] = self::entrants((int)$r['id']);
        }
        unset($r);
        return [
            'raffles' => $rows,
            'groups'  => DB::all("SELECT chat_id,title FROM chat_settings ORDER BY title LIMIT 300"),
            'stats'   => [
                'total'  => (int)DB::val("SELECT COUNT(*) FROM raffles", [], 0),
                'active' => (int)DB::val("SELECT COUNT(*) FROM raffles WHERE status='active'", [], 0),
                'drawn'  => (int)DB::val("SELECT COUNT(*) FROM raffles WHERE status='drawn'", [], 0),
                'entries' => (int)DB::val("SELECT COUNT(*) FROM raffle_entries", [], 0),
            ],
        ];
    }

    public static function handleAction(string $action, array $admin): ?string
    {
        switch ($action) {
            case 'raffle_create':
                $r = self::create($_POST + ['admin_id' => (int)$admin['id']]);
                if (!$r['ok']) {
                    return self::fail((string)($r['error'] ?? 'ساخت قرعه‌کشی ناموفق بود.'));
                }
                if (!empty($_POST['publish_now']) && !empty($_POST['chat_id'])) {
                    self::publish((int)$r['id'], (string)$_POST['chat_id']);
                }
                return 'قرعه‌کشی ساخته شد — کد: ' . $r['code'];
            case 'raffle_publish':
                $r = self::publish((int)input('id', 0), (string)input('chat_id', ''));
                return $r['ok'] ? 'قرعه‌کشی منتشر شد.' : self::fail((string)($r['error'] ?? 'ارسال ناموفق.'));
            case 'raffle_draw':
                $r = self::draw((int)input('id', 0), (int)($admin['id'] ?? 0));
                if (!$r['ok']) {
                    return self::fail((string)($r['error'] ?? 'قرعه‌کشی انجام نشد.'));
                }
                $names = array_map(static fn($w) => (string)$w['name'], (array)$r['winners']);
                return 'برنده‌ها: ' . implode(' ، ', $names);
            case 'raffle_delete':
                self::delete((int)input('id', 0));
                return 'قرعه‌کشی حذف شد.';
        }
        return null;
    }
}
