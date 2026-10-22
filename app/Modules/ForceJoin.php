<?php
declare(strict_types=1);

namespace Modules;

use BaleApi;
use DB;
use Logger;
use Module;
use Schema;
use digits_fa;

/**
 * ماژول «اجبار عضویت + بازی»
 *
 * مکانیزم: کاربر برای استفاده از خدمات ربات (فروشگاه، دعوت، سفارش) اول باید
 * در کانال/گروه(های) تعیین‌شده عضو شود. برای اینکه ربات‌های تقلبی هم عبور نکنند،
 * یک «سؤال بازی» ساده هم پرسیده می‌شود (مثلاً جمع دو عدد یا انتخاب گزینه درست).
 *
 * دستور ربات `شروع_بازی` / `عضو_شدم` روند را اجرا می‌کند.
 * بررسی عضویت با getChatMember انجام می‌شود (کاربر باید واقعاً عضو باشد).
 */
final class ForceJoin extends Module
{
    public static function key(): string
    {
        return 'forcejoin';
    }

    public static function title(): string
    {
        return 'اجبار عضویت و بازی';
    }

    public static function description(): string
    {
        return 'کاربر تا در کانال‌های تعیین‌شده عضو نشود و سؤال بازی را جواب ندهد، به بخش‌های قفل‌شده دسترسی ندارد';
    }

    public static function icon(): string
    {
        return '🎮';
    }

    public static function defaultEnabled(): bool
    {
        return false;   // پیش‌فرض خاموش تا مزاحم کاربران فعلی نشود
    }

    public static function badge(): string
    {
        $passed = (int)DB::val("SELECT COUNT(*) FROM forcejoin_state WHERE status='passed'", [], 0);
        $target = count(self::requiredChats());
        return digits_fa($target) . ' مقصد · ' . digits_fa($passed) . ' عبورکرده';
    }

    public static function migrations(): array
    {
        return [];
    }

    /** کانال/گروه‌های الزامی از تنظیمات */
    public static function requiredChats(): array
    {
        $raw = (string)(Schema::getSetting('forcejoin_chats', '') ?: '');
        $out = [];
        foreach (preg_split('~[\s,]+~', $raw) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('~^-?\d+$~', $part)) {
                $out[] = ['chat_id' => (int)$part, 'username' => null, 'label' => $part];
            } elseif (preg_match('~^@?([A-Za-z0-9_]{4,})$~', $part, $m)) {
                $out[] = ['chat_id' => 0, 'username' => $m[1], 'label' => '@' . $m[1]];
            }
        }
        return $out;
    }

    public static function quizEnabled(): bool
    {
        $v = Schema::getSetting('forcejoin_quiz', '1');
        return $v === null ? true : (string)$v === '1';
    }

    /** سؤال بازی تصادفی */
    public static function makeQuiz(): array
    {
        $a = random_int(2, 9);
        $b = random_int(2, 9);
        return ['question' => "{$a} + {$b} = ?", 'answer' => (string)($a + $b)];
    }

    /** وضعیت کاربر */
    public static function state(int $userId): array
    {
        $row = DB::one("SELECT * FROM forcejoin_state WHERE user_id=?", [$userId]);
        if (!$row) {
            DB::q("INSERT INTO forcejoin_state (user_id,status,quiz_ok,attempts,created_at) VALUES (?, 'pending',0,0,?)",
                [$userId, time()]);
            $row = DB::one("SELECT * FROM forcejoin_state WHERE user_id=?", [$userId]);
        }
        return $row ?: ['user_id' => $userId, 'status' => 'pending', 'quiz_ok' => 0, 'attempts' => 0];
    }

    /** آیا کاربر از گیت گذشته است؟ */
    public static function passed(int $userId): bool
    {
        if (!self::isEnabled()) {
            return true;
        }
        $row = DB::one("SELECT status FROM forcejoin_state WHERE user_id=?", [$userId]);
        return $row !== null && $row['status'] === 'passed';
    }

    /** آیا در همه مقصدها عضو است؟ */
    public static function memberOfAll(int $userId): bool
    {
        $chats = self::requiredChats();
        if (!$chats) {
            return true;
        }
        foreach ($chats as $c) {
            // اگر آیدی عددی داریم همان، وگرنه @یوزرنیم (اگر قابل تبدیل نبود، با یوزرنیم بررسی می‌کنیم)
            $ref = (int)$c['chat_id'];
            if ($ref === 0 && !empty($c['username'])) {
                $info = BaleApi::getChat('@' . $c['username']);
                $resolved = (int)($info['id'] ?? 0);
                $ref = $resolved > 0 ? $resolved : '@' . $c['username'];
            }
            if ($ref === 0 || $ref === '') {
                return false;
            }
            $m = BaleApi::getChatMember($ref, $userId);
            $status = (string)($m['status'] ?? '');
            if (!in_array($status, ['creator', 'administrator', 'member'], true)) {
                return false;
            }
        }
        return true;
    }

    /** اجرای گیت: پیام راهنما می‌فرستد؛ true = عبور کرد */
    public static function gate(array $ctx): bool
    {
        $userId = (int)($ctx['user_id'] ?? 0);
        if (!$userId || !self::isEnabled()) {
            return true;
        }
        if (self::passed($userId)) {
            return true;
        }
        $state = self::state($userId);
        $chats = self::requiredChats();
        $lines = ["🎮 <b>یک قدم مانده!</b>\nبرای استفاده از امکانات، اول در این مقصدها عضو شو:"];
        foreach ($chats as $c) {
            $link = $c['username'] !== null
                ? 'https://ble.ir/' . $c['username']
                : 'https://ble.ir/' . $c['chat_id'];
            $lines[] = '• ' . $link;
        }
        $lines[] = "\nبعد از عضویت بنویس: <code>عضو_شدم</code>";
        if (self::quizEnabled() && (int)$state['quiz_ok'] === 0) {
            $q = self::makeQuiz();
            Schema::setSetting('forcejoin_quiz_' . $userId, json_encode($q, JSON_UNESCAPED_UNICODE));
            $lines[] = "\n🧠 برای اینکه مطمئن شویم ربات نیستی: <b>" . $q['question'] . "</b>";
            $lines[] = "جواب را همراه دستور بنویس، مثل: <code>عضو_شدم " . $q['answer'] . "</code>";
        }
        BaleApi::sendMessage((int)$ctx['chat_id'], implode("\n", $lines), null, null, 'HTML');
        return false;
    }

    /** بررسی و ثبت عبور */
    public static function verify(array $ctx, string $answer = ''): array
    {
        $userId = (int)($ctx['user_id'] ?? 0);
        if (!$userId) {
            return ['ok' => false, 'error' => 'کاربر نامعتبر'];
        }
        $state = self::state($userId);
        DB::q("UPDATE forcejoin_state SET attempts=attempts+1 WHERE user_id=?", [$userId]);

        $quizOk = (int)$state['quiz_ok'] === 1;
        if (!$quizOk && self::quizEnabled()) {
            $saved = json_decode((string)(Schema::getSetting('forcejoin_quiz_' . $userId, '') ?: ''), true);
            if (is_array($saved) && isset($saved['answer'])) {
                if (trim($answer) !== (string)$saved['answer']) {
                    BaleApi::sendMessage((int)$ctx['chat_id'],
                        "❌ جواب بازی درست نیست. دوباره بنویس <code>عضو_شدم</code> تا سؤال تازه بگیری.",
                        null, null, 'HTML');
                    if ((int)$state['attempts'] >= 5) {
                        DB::q("UPDATE forcejoin_state SET status='failed' WHERE user_id=?", [$userId]);
                    }
                    return ['ok' => false, 'error' => 'quiz'];
                }
                DB::q("UPDATE forcejoin_state SET quiz_ok=1 WHERE user_id=?", [$userId]);
                $quizOk = true;
            }
        }
        if (!$quizOk) {
            return ['ok' => false, 'error' => 'quiz_required'];
        }
        if (!self::memberOfAll($userId)) {
            BaleApi::sendMessage((int)$ctx['chat_id'],
                "❌ هنوز عضویتت در همه مقصدها تایید نشد. عضو شو و بعد دوباره <code>عضو_شدم</code> بفرست.",
                null, null, 'HTML');
            return ['ok' => false, 'error' => 'not_member'];
        }
        DB::q("UPDATE forcejoin_state SET status='passed', verified_at=? WHERE user_id=?", [time(), $userId]);
        Schema::setSetting('forcejoin_quiz_' . $userId, '');
        Logger::user(null, $userId, null, 'forcejoin_passed', 'عبور از گیت عضویت');
        BaleApi::sendMessage((int)$ctx['chat_id'],
            "✅ تایید شد! حالا به همه امکانات دسترسی داری.\n" . url('shop/'), null, null, 'HTML');
        return ['ok' => true];
    }

    public static function commands(): array
    {
        return ['عضو_شدم' => 'cmdVerify', 'شروع_بازی' => 'cmdVerify', 'عضو شدم' => 'cmdVerify'];
    }

    public static function cmdVerify(array $ctx): void
    {
        self::verify($ctx, trim((string)($ctx['rest'] ?? '')));
    }

    public static function panelData(): ?array
    {
        return [
            'chats'  => self::requiredChats(),
            'quiz'   => self::quizEnabled(),
            'raw'    => (string)(Schema::getSetting('forcejoin_chats', '') ?: ''),
            'stats'  => [
                'passed'  => (int)DB::val("SELECT COUNT(*) FROM forcejoin_state WHERE status='passed'", [], 0),
                'pending' => (int)DB::val("SELECT COUNT(*) FROM forcejoin_state WHERE status='pending'", [], 0),
                'failed'  => (int)DB::val("SELECT COUNT(*) FROM forcejoin_state WHERE status='failed'", [], 0),
            ],
            'recent' => DB::all("SELECT f.*, u.first_name FROM forcejoin_state f
                                 LEFT JOIN users u ON u.user_id=f.user_id
                                 ORDER BY f.id DESC LIMIT 20"),
        ];
    }

    public static function handleAction(string $action, array $admin): ?string
    {
        if ($action === 'forcejoin_save') {
            Schema::setSetting('forcejoin_chats', trim((string)input('forcejoin_chats', '')));
            Schema::setSetting('forcejoin_quiz', !empty($_POST['forcejoin_quiz']) ? '1' : '0');
            return 'تنظیمات گیت عضویت ذخیره شد.';
        }
        if ($action === 'forcejoin_reset') {
            $uid = (int)input('user_id', 0);
            if ($uid) {
                DB::q("DELETE FROM forcejoin_state WHERE user_id=?", [$uid]);
                return 'وضعیت کاربر بازنشانی شد.';
            }
        }
        return null;
    }
}
