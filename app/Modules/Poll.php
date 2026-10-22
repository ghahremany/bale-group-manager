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
 * ماژول «نظرسنجی» (۱.۵)
 *
 * - ساخت نظرسنجی با ۲ تا ۱۰ گزینه از پنل یا با دستور ربات
 * - انتشار در گروه/کانال با دکمه‌های شیشه‌ای (callback) + رأی متنی (`رای CODE 2`)
 * - هر کاربر یک رأی (یا چندگانه در حالت multi)، نمایش درصد و نوارِ نتیجه
 * - بستن دستی یا خودکار با انقضا (کرون)
 */
final class Poll extends Module
{
    public static function key(): string
    {
        return 'poll';
    }

    public static function title(): string
    {
        return 'نظرسنجی';
    }

    public static function description(): string
    {
        return 'ساخت نظرسنجی با گزینه‌های شیشه‌ای، رأی‌گیری، نتیجه زنده و بستن خودکار';
    }

    public static function icon(): string
    {
        return '📊';
    }

    public static function badge(): string
    {
        $act = (int)DB::val("SELECT COUNT(*) FROM polls WHERE status='active'", [], 0);
        $votes = (int)DB::val("SELECT COUNT(*) FROM poll_votes", [], 0);
        return digits_fa($act) . ' فعال · ' . digits_fa($votes) . ' رأی';
    }

    public static function migrations(): array
    {
        return [];
    }

    public static function commands(): array
    {
        return [
            'نظرسنجی'      => 'listPolls',
            'نظرسنجی‌ها'   => 'listPolls',
            'رای'          => 'cmdVote',
            'رأی'          => 'cmdVote',
            'نتیجه'        => 'cmdResult',
            'نظرسنجی_ساز'  => 'cmdCreate',
        ];
    }

    // ---------------- هسته ----------------

    public static function byCode(string $code): ?array
    {
        $code = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', $code) ?? '');
        if ($code === '') {
            return null;
        }
        return DB::one("SELECT * FROM polls WHERE code=?", [$code]);
    }

    public static function options(int $pollId): array
    {
        return DB::all("SELECT * FROM poll_options WHERE poll_id=? ORDER BY idx", [$pollId]);
    }

    /** ساخت نظرسنجی؛ گزینه‌ها با | جدا می‌شوند (یا آرایه) */
    public static function create(array $in): array
    {
        $question = trim((string)($in['question'] ?? ''));
        if (mb_strlen($question) < 3) {
            return ['ok' => false, 'error' => 'متن سؤال حداقل ۳ کاراکتر باشد.'];
        }
        $raw = $in['options'] ?? '';
        if (is_string($raw)) {
            $opts = array_map('trim', preg_split('~[|،\n]+~u', $raw) ?: []);
        } else {
            $opts = array_map(static fn($v) => trim((string)$v), (array)$raw);
        }
        $opts = array_values(array_filter($opts, static fn($o) => $o !== ''));
        if (count($opts) < 2) {
            return ['ok' => false, 'error' => 'حداقل دو گزینه لازم است (با | جدا کن).'];
        }
        if (count($opts) > 10) {
            $opts = array_slice($opts, 0, 10);
        }
        $expires = $in['expires_at'] ?? 0;
        if (is_string($expires)) {
            $expires = trim($expires) === '' ? 0
                : (ctype_digit(trim($expires)) ? (int)trim($expires)
                    : (int)strtotime(str_replace('/', '-', trim($expires))));
        }
        $expires = (int)$expires;
        $now = time();
        $code = self::genCode();
        DB::q("INSERT INTO polls (code,chat_id,question,description,multi,anonymous,status,expires_at,admin_id,created_at)
               VALUES (?,?,?,?,?,?, 'draft', ?,?,?)",
            [
                $code, isset($in['chat_id']) && (int)$in['chat_id'] !== 0 ? (int)$in['chat_id'] : null,
                str_limit($question, 296),
                str_limit((string)($in['description'] ?? ''), 590) ?: null,
                !empty($in['multi']) ? 1 : 0,
                !empty($in['anonymous']) ? 1 : 0,
                $expires > 0 ? $expires : null,
                $in['admin_id'] ?? null,
                $now,
            ]);
        $pollId = (int)DB::pdo()->lastInsertId();
        foreach ($opts as $i => $label) {
            DB::q("INSERT INTO poll_options (poll_id,idx,label,votes) VALUES (?,?,?,0)",
                [$pollId, $i + 1, str_limit($label, 116)]);
        }
        Logger::audit(null, 'panel', 'poll_create', 'poll#' . $pollId, $code);
        return ['ok' => true, 'id' => $pollId, 'code' => $code, 'options' => count($opts)];
    }

    public static function genCode(int $len = 5): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($try = 0; $try < 10; $try++) {
            $code = '';
            for ($i = 0; $i < $len; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            if (!DB::one("SELECT id FROM polls WHERE code=?", [$code])) {
                return $code;
            }
        }
        return 'P' . substr((string)time(), -4);
    }

    /** متن نظرسنجی + دکمه‌ها */
    public static function render(array $poll, bool $withResults = false): array
    {
        $opts = self::options((int)$poll['id']);
        $total = array_sum(array_map(static fn($o) => (int)$o['votes'], $opts));
        $lines = ['📊 <b>' . h((string)$poll['question']) . '</b>'];
        if (!empty($poll['description'])) {
            $lines[] = h((string)$poll['description']);
        }
        $lines[] = '';
        foreach ($opts as $o) {
            $line = '<b>' . digits_fa((int)$o['idx']) . ')</b> ' . h((string)$o['label']);
            if ($withResults) {
                $pct = $total > 0 ? (int)round((int)$o['votes'] * 100 / $total) : 0;
                $bar = str_repeat('█', (int)round($pct / 10)) . str_repeat('░', 10 - (int)round($pct / 10));
                $line .= "\n   <code>" . $bar . '</code> ' . digits_fa($pct) . '٪ (' . digits_fa((int)$o['votes']) . ')';
            }
            $lines[] = $line;
        }
        $lines[] = '';
        $lines[] = '🗳 رأی‌ها: <b>' . digits_fa($total) . '</b>' . ((int)$poll['multi'] ? ' · چندگزینه‌ای' : '');
        if (!empty($poll['expires_at'])) {
            $lines[] = '⏳ تا ' . dt((int)$poll['expires_at'], 'Y/m/d H:i');
        }
        if ((string)$poll['status'] === 'closed') {
            $lines[] = '🔒 <b>بسته شده</b>';
        } else {
            $lines[] = 'رأی: دکمه‌ها یا <code>رای ' . h((string)$poll['code']) . ' شماره</code>';
        }

        $markup = null;
        if ((string)$poll['status'] === 'active') {
            $rows = [];
            foreach ($opts as $o) {
                $rows[] = [[
                    'text'          => digits_fa((int)$o['idx']) . ') ' . mb_strimwidth((string)$o['label'], 0, 28, '…'),
                    'callback_data' => 'pl:v:' . (int)$poll['id'] . ':' . (int)$o['idx'],
                ]];
            }
            $markup = ['inline_keyboard' => $rows];
        }
        return ['text' => implode("\n", $lines), 'markup' => $markup];
    }

    /** انتشار نظرسنجی در یک چت */
    public static function publish(int $pollId, int|string|null $chatId = null): array
    {
        $poll = DB::one("SELECT * FROM polls WHERE id=?", [$pollId]);
        if (!$poll) {
            return ['ok' => false, 'error' => 'نظرسنجی پیدا نشد.'];
        }
        $target = $chatId !== null && (string)$chatId !== '' && (string)$chatId !== '0'
            ? $chatId
            : ($poll['chat_id'] ?: null);
        if ($target === null) {
            return ['ok' => false, 'error' => 'چت مقصد را انتخاب کن.'];
        }
        DB::q("UPDATE polls SET status='active', chat_id=? WHERE id=?", [(int)$target, $pollId]);
        $poll['status'] = 'active';
        $poll['chat_id'] = (int)$target;
        $view = self::render($poll);
        $mid  = BaleApi::sendMessage($target, $view['text'], null, $view['markup'], 'HTML');
        if ($mid === null) {
            return ['ok' => false, 'error' => 'ارسال ناموفق (ربات ادمین آن چت هست؟)'];
        }
        DB::q("UPDATE polls SET message_id=? WHERE id=?", [$mid, $pollId]);
        Logger::audit(null, 'panel', 'poll_publish', 'poll#' . $pollId, (string)$target);
        return ['ok' => true, 'message_id' => (int)$mid];
    }

    /** ثبت رأی */
    public static function vote(int $pollId, int $userId, int $optIdx): array
    {
        $poll = DB::one("SELECT * FROM polls WHERE id=?", [$pollId]);
        if (!$poll) {
            return ['ok' => false, 'error' => 'نظرسنجی پیدا نشد.'];
        }
        if ((string)$poll['status'] !== 'active') {
            return ['ok' => false, 'error' => 'این نظرسنجی بسته شده است.'];
        }
        if (!empty($poll['expires_at']) && (int)$poll['expires_at'] < time()) {
            self::close($pollId);
            return ['ok' => false, 'error' => 'زمان نظرسنجی تمام شده است.'];
        }
        $opt = DB::one("SELECT * FROM poll_options WHERE poll_id=? AND idx=?", [$pollId, $optIdx]);
        if (!$opt) {
            return ['ok' => false, 'error' => 'گزینه نامعتبر است.'];
        }
        $ins = DB::q("INSERT IGNORE INTO poll_votes (poll_id,option_idx,user_id,created_at) VALUES (?,?,?,?)",
            [$pollId, $optIdx, $userId, time()]);
        if ((int)$ins->rowCount() === 0) {
            return ['ok' => false, 'error' => 'قبلاً همین گزینه را انتخاب کرده‌ای.'];
        }
        if ((int)$poll['multi'] === 0) {
            // تک‌گزینه‌ای: رأی‌های قبلی همان کاربر حذف می‌شوند
            $old = DB::all("SELECT * FROM poll_votes WHERE poll_id=? AND user_id=? AND option_idx<>?",
                [$pollId, $userId, $optIdx]);
            foreach ($old as $v) {
                DB::q("DELETE FROM poll_votes WHERE id=?", [(int)$v['id']]);
                DB::q("UPDATE poll_options SET votes=GREATEST(votes-1,0) WHERE poll_id=? AND idx=?",
                    [$pollId, (int)$v['option_idx']]);
            }
        }
        DB::q("UPDATE poll_options SET votes=votes+1 WHERE id=?", [(int)$opt['id']]);
        return ['ok' => true, 'option' => (string)$opt['label'], 'index' => $optIdx];
    }

    public static function close(int $pollId): bool
    {
        DB::q("UPDATE polls SET status='closed' WHERE id=?", [$pollId]);
        return true;
    }

    public static function delete(int $pollId): bool
    {
        DB::q("DELETE FROM poll_options WHERE poll_id=?", [$pollId]);
        DB::q("DELETE FROM poll_votes WHERE poll_id=?", [$pollId]);
        DB::q("DELETE FROM polls WHERE id=?", [$pollId]);
        return true;
    }

    /** نتایج برای نمایش/پنل */
    public static function results(int $pollId): array
    {
        $opts  = self::options($pollId);
        $total = array_sum(array_map(static fn($o) => (int)$o['votes'], $opts));
        $out = [];
        foreach ($opts as $o) {
            $out[] = [
                'idx'    => (int)$o['idx'],
                'label'  => (string)$o['label'],
                'votes'  => (int)$o['votes'],
                'percent' => $total > 0 ? (int)round((int)$o['votes'] * 100 / $total) : 0,
            ];
        }
        return ['total' => $total, 'options' => $out, 'voters' => (int)DB::val(
            "SELECT COUNT(DISTINCT user_id) FROM poll_votes WHERE poll_id=?", [$pollId], 0)];
    }

    // ---------------- دستورهای ربات ----------------

    public static function listPolls(array $ctx): void
    {
        $rows = DB::all("SELECT * FROM polls WHERE status='active' ORDER BY id DESC LIMIT 8");
        if (!$rows) {
            self::say($ctx, '📊 نظرسنجی فعالی وجود ندارد.');
            return;
        }
        $lines = ['📊 <b>نظرسنجی‌های فعال</b>'];
        foreach ($rows as $p) {
            $lines[] = '• <code>' . h((string)$p['code']) . '</code> — ' . h((string)$p['question']);
        }
        $lines[] = '';
        $lines[] = 'رأی: <code>رای CODE شماره</code> · نتیجه: <code>نتیجه CODE</code>';
        self::say($ctx, implode("\n", $lines));
    }

    public static function cmdVote(array $ctx): void
    {
        $rest = trim((string)($ctx['rest'] ?? ''));
        if ($rest === '' && empty($ctx['prefixed'])) {
            return;
        }
        if (!preg_match('~^([A-Za-z0-9]{4,12})\s+(\d{1,2})$~', $rest, $m)) {
            self::say($ctx, "🗳 شکل درست: <code>رای CODE 1</code>");
            return;
        }
        $poll = self::byCode($m[1]);
        if (!$poll) {
            self::say($ctx, '❌ نظرسنجی با این کد پیدا نشد.');
            return;
        }
        $r = self::vote((int)$poll['id'], (int)$ctx['user_id'], (int)$m[2]);
        if (!$r['ok']) {
            self::say($ctx, '⚠️ ' . h((string)$r['error']));
            return;
        }
        $poll = DB::one("SELECT * FROM polls WHERE id=?", [(int)$poll['id']]);
        $view = self::render($poll, true);
        self::say($ctx, '✅ رأی تو ثبت شد: <b>' . h((string)$r['option']) . "</b>\n\n" . $view['text']);
    }

    public static function cmdResult(array $ctx): void
    {
        $poll = self::byCode(trim((string)($ctx['rest'] ?? '')));
        if (!$poll) {
            self::say($ctx, '❌ نظرسنجی پیدا نشد. شکل درست: <code>نتیجه CODE</code>');
            return;
        }
        $view = self::render($poll, true);
        self::say($ctx, $view['text']);
    }

    public static function cmdCreate(array $ctx): void
    {
        if (!self::isAdmin($ctx)) {
            self::say($ctx, '⛔ فقط ادمین گروه می‌تواند نظرسنجی بسازد.');
            return;
        }
        $rest = trim((string)($ctx['rest'] ?? ''));
        $parts = array_map('trim', explode('|', $rest));
        $question = array_shift($parts) ?? '';
        $r = self::create([
            'question' => $question, 'options' => $parts,
            'chat_id' => (int)$ctx['chat_id'], 'admin_id' => null,
        ]);
        if (!$r['ok']) {
            self::say($ctx, '⚠️ ' . h((string)$r['error'])
                . "\nمثال: <code>نظرسنجی_ساز بهترین کالا کدام است؟ | الف | ب | ج</code>");
            return;
        }
        $pub = self::publish((int)$r['id'], (int)$ctx['chat_id']);
        if (!$pub['ok']) {
            self::say($ctx, 'نظرسنجی ساخته شد ولی ارسال نشد: ' . h((string)$pub['error']) . "\nکد: <code>" . h((string)$r['code']) . '</code>');
        }
    }

    // ---------------- دکمه‌های شیشه‌ای ----------------

    public static function handleCallback(string $data, array $cb): bool
    {
        if (!str_starts_with($data, 'pl:')) {
            return false;
        }
        $p = explode(':', $data);
        if (count($p) < 4 || $p[1] !== 'v') {
            return false;
        }
        $pollId = (int)$p[2];
        $optIdx = (int)$p[3];
        $uid    = (int)($cb['from']['id'] ?? 0);
        if ($uid <= 0) {
            return true;
        }
        $r = self::vote($pollId, $uid, $optIdx);
        BaleApi::answerCallback((string)($cb['id'] ?? ''), $r['ok'] ? '✅ رأی ثبت شد' : (string)$r['error'], !$r['ok']);

        // به‌روزرسانی پیام نظرسنجی با نتایج
        $poll = DB::one("SELECT * FROM polls WHERE id=?", [$pollId]);
        $msg  = $cb['message'] ?? [];
        if ($poll && !empty($msg['message_id']) && !empty($msg['chat']['id'])) {
            $view = self::render($poll, true);
            BaleApi::editMessage((int)$msg['chat']['id'], (int)$msg['message_id'], $view['text'], $view['markup']);
        }
        return true;
    }

    // ---------------- کرون ----------------

    public static function cron(): void
    {
        $due = DB::all("SELECT id FROM polls WHERE status='active' AND expires_at IS NOT NULL AND expires_at < ?", [time()]);
        foreach ($due as $p) {
            self::close((int)$p['id']);
        }
    }

    // ---------------- پنل ----------------

    public static function panelData(): ?array
    {
        $polls = DB::all("SELECT * FROM polls ORDER BY id DESC LIMIT 50");
        foreach ($polls as &$p) {
            $p['results'] = self::results((int)$p['id']);
        }
        unset($p);
        return [
            'polls' => $polls,
            'groups' => DB::all("SELECT chat_id,title FROM chat_settings ORDER BY title LIMIT 300"),
            'stats' => [
                'total'   => (int)DB::val("SELECT COUNT(*) FROM polls", [], 0),
                'active'  => (int)DB::val("SELECT COUNT(*) FROM polls WHERE status='active'", [], 0),
                'votes'   => (int)DB::val("SELECT COUNT(*) FROM poll_votes", [], 0),
                'voters'  => (int)DB::val("SELECT COUNT(DISTINCT user_id) FROM poll_votes", [], 0),
            ],
        ];
    }

    public static function handleAction(string $action, array $admin): ?string
    {
        switch ($action) {
            case 'poll_create':
                $r = self::create($_POST + ['admin_id' => (int)$admin['id']]);
                if (!$r['ok']) {
                    return self::fail((string)($r['error'] ?? 'ساخت نظرسنجی ناموفق بود.'));
                }
                if (!empty($_POST['publish_now']) && !empty($_POST['chat_id'])) {
                    $pub = self::publish((int)$r['id'], (string)$_POST['chat_id']);
                    if (!$pub['ok']) {
                        return 'نظرسنجی ساخته شد (کد ' . $r['code'] . ') ولی ارسال نشد: ' . (string)$pub['error'];
                    }
                }
                return 'نظرسنجی ساخته شد — کد: ' . $r['code'];
            case 'poll_publish':
                $r = self::publish((int)input('id', 0), (string)input('chat_id', ''));
                return $r['ok'] ? 'نظرسنجی منتشر شد.' : self::fail((string)($r['error'] ?? 'ارسال ناموفق.'));
            case 'poll_close':
                self::close((int)input('id', 0));
                return 'نظرسنجی بسته شد.';
            case 'poll_delete':
                self::delete((int)input('id', 0));
                return 'نظرسنجی حذف شد.';
        }
        return null;
    }
}
