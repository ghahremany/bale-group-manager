<?php
declare(strict_types=1);

/**
 * منطق مدیریتی گروه: قفل‌ها، اخطار، سکوت، بن، پاک‌سازی، گزارش، کپچا، خوش‌آمد
 */
final class Moderation
{
    // ---------------- شناسایی نقش‌ها ----------------
    public static function isSuperAdmin(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }
        $ids = (array)config('bot.super_admins', []);
        if (in_array($userId, array_map('intval', $ids), true)) {
            return true;
        }
        try {
            $found = DB::val("SELECT id FROM panel_admins WHERE bale_user_id=? AND is_active=1 LIMIT 1", [$userId]);
            return (bool)$found;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function isGroupAdmin(int $chatId, ?int $userId, bool $fresh = false): bool
    {
        if (!$userId) {
            return false;
        }
        if (self::isSuperAdmin($userId)) {
            return true;
        }
        return BaleApi::isAdmin($chatId, $userId, !$fresh);
    }

    // ---------------- قفل محتوا ----------------
    public static function lockedContentType(array $message, array $s): ?string
    {
        $txt = self::textOf($message);
        if ((int)$s['lock_link'] === 1 && $txt !== '' && Text::hasLink($txt)) {
            return 'لینک';
        }
        if ((int)$s['lock_photo'] === 1 && isset($message['photo'])) {
            return 'عکس';
        }
        if ((int)$s['lock_video'] === 1 && (isset($message['video']) || isset($message['video_note']))) {
            return 'ویدیو';
        }
        if ((int)$s['lock_gif'] === 1 && isset($message['animation'])) {
            return 'گیف';
        }
        if ((int)$s['lock_voice'] === 1 && (isset($message['voice']) || isset($message['audio']))) {
            return 'صدا';
        }
        if ((int)$s['lock_document'] === 1 && isset($message['document'])) {
            return 'فایل';
        }
        if ((int)$s['lock_sticker'] === 1 && isset($message['sticker'])) {
            return 'استیکر';
        }
        if ((int)$s['lock_contact'] === 1 && isset($message['contact'])) {
            return 'مخاطب';
        }
        if ((int)$s['lock_poll'] === 1 && isset($message['poll'])) {
            return 'نظرسنجی';
        }
        if ((int)$s['lock_location'] === 1 && (isset($message['location']) || isset($message['venue']))) {
            return 'موقعیت مکانی';
        }
        if ((int)$s['lock_forward'] === 1 && (isset($message['forward_from']) || isset($message['forward_origin']) || isset($message['forward_from_chat']))) {
            return 'فوروارد';
        }
        return null;
    }

    public static function textOf(array $message): string
    {
        return (string)($message['text'] ?? $message['caption'] ?? '');
    }

    public static function fullName(array $user): string
    {
        $n = trim(((string)($user['first_name'] ?? '')) . ' ' . ((string)($user['last_name'] ?? '')));
        return $n !== '' ? $n : ('کاربر ' . ($user['id'] ?? '?'));
    }

    public static function mention(array $user): string
    {
        $name = self::fullName($user);
        if (!empty($user['username'])) {
            return '@' . $user['username'];
        }
        return $name . ' (ID: ' . ($user['id'] ?? '?') . ')';
    }

    // ---------------- اقدامات ----------------
    public static function warn(int $chatId, int $userId, string $reason, ?int $actorId = null, ?int $messageId = null): int
    {
        DB::q("INSERT INTO warns (chat_id,user_id,count,updated_at) VALUES (?,?,1,?)
               ON DUPLICATE KEY UPDATE count=count+1, updated_at=VALUES(updated_at)",
            [$chatId, $userId, time()]);
        $count = (int)DB::val("SELECT count FROM warns WHERE chat_id=? AND user_id=?", [$chatId, $userId], 0);
        DB::q("UPDATE users SET violations_count=violations_count+1 WHERE user_id=?", [$userId]);
        Logger::user($chatId, $actorId, $userId, 'warn', $reason . " ({$count})");

        $s     = Settings::get($chatId);
        $limit = max(1, (int)$s['warn_limit']);
        if ($count >= $limit) {
            self::applyWarnPunishment($chatId, $userId, $s, $actorId);
            DB::q("DELETE FROM warns WHERE chat_id=? AND user_id=?", [$chatId, $userId]);
            return 0; // reset after punishment
        }
        if ($messageId) {
            self::safeDelete($chatId, $messageId);
        }
        return $count;
    }

    private static function applyWarnPunishment(int $chatId, int $userId, array $s, ?int $actorId): void
    {
        $action = (string)($s['warn_action'] ?: 'mute');
        $limit  = (int)$s['warn_limit'];
        if ($action === 'ban') {
            self::ban($chatId, $userId, 0, "رسیدن به {$limit} اخطار", $actorId);
            BaleApi::sendMessage($chatId, "⛔ کاربر به دلیل رسیدن به {$limit} اخطار، بن شد.");
        } elseif ($action === 'kick') {
            self::kick($chatId, $userId, 60, $actorId);
            BaleApi::sendMessage($chatId, "👢 کاربر به دلیل رسیدن به {$limit} اخطار، از گروه اخراج شد.");
        } else {
            $sec = max(60, (int)$s['warn_mute_sec']);
            self::mute($chatId, $userId, $sec, "رسیدن به {$limit} اخطار", $actorId);
            BaleApi::sendMessage($chatId, "🔇 کاربر به دلیل رسیدن به {$limit} اخطار، " . duration_human($sec) . " سکوت شد.");
        }
        self::logToChannel($chatId, "⚖️ اخطار کاربر `$userId` به سقف رسید → $action");
    }

    /** آیا کاربر در این گروه سکوت است؟ (until_ts = 0 یعنی سکوت نامحدود) */
    public static function isMuted(int $chatId, int $userId): bool
    {
        $row = DB::one("SELECT until_ts FROM mutes WHERE chat_id=? AND user_id=?", [$chatId, $userId]);
        if (!$row) {
            return false;
        }
        $until = (int)$row['until_ts'];
        return $until === 0 || $until > time();
    }

    public static function mute(int $chatId, int $userId, int $seconds, string $reason = '', ?int $actorId = null): bool
    {
        $ok = BaleApi::restrict($chatId, $userId, false, $seconds > 0 ? time() + max(30, $seconds) : null);
        DB::q("INSERT INTO mutes (chat_id,user_id,until_ts,reason,by_admin_id,created_at) VALUES (?,?,?,?,?,?)
               ON DUPLICATE KEY UPDATE until_ts=VALUES(until_ts), reason=VALUES(reason), by_admin_id=VALUES(by_admin_id), created_at=VALUES(created_at)",
            [$chatId, $userId, $seconds > 0 ? time() + $seconds : 0, str_limit($reason, 240), $actorId, time()]);
        if ($seconds > 0) {
            DB::q("INSERT INTO scheduled_actions (chat_id,user_id,action,amount,run_at,done,created_at) VALUES (?,?,'unmute',0,?,0,?)",
                [$chatId, $userId, time() + $seconds, time()]);
        }
        Logger::user($chatId, $actorId, $userId, 'mute', $reason . " {$seconds}s");
        return $ok;
    }

    public static function unmute(int $chatId, int $userId, ?int $actorId = null): bool
    {
        $ok = BaleApi::restrict($chatId, $userId, true);
        DB::q("DELETE FROM mutes WHERE chat_id=? AND user_id=?", [$chatId, $userId]);
        DB::q("UPDATE scheduled_actions SET done=1 WHERE chat_id=? AND user_id=? AND action='unmute'", [$chatId, $userId]);
        Logger::user($chatId, $actorId, $userId, 'unmute');
        return $ok;
    }

    public static function ban(int $chatId, int $userId, int $seconds = 0, string $reason = '', ?int $actorId = null): bool
    {
        $until   = $seconds > 0 ? time() + $seconds : 0;
        $payload = ['chat_id' => $chatId, 'user_id' => $userId];
        if ($until) {
            $payload['until_date'] = $until;
        }
        $ok = BaleApi::call('banChatMember', $payload, 15) !== null;
        if (!$ok) {
            $ok = BaleApi::call('kickChatMember', $payload, 15) !== null;
        }
        DB::q("INSERT INTO bans (chat_id,user_id,by_admin_id,reason,ts,until_ts) VALUES (?,?,?,?,?,?)
               ON DUPLICATE KEY UPDATE by_admin_id=VALUES(by_admin_id), reason=VALUES(reason), ts=VALUES(ts), until_ts=VALUES(until_ts)",
            [$chatId, $userId, $actorId, str_limit($reason, 240), time(), $until]);
        DB::q("DELETE FROM mutes WHERE chat_id=? AND user_id=?", [$chatId, $userId]);
        if ($until) {
            DB::q("INSERT INTO scheduled_actions (chat_id,user_id,action,amount,run_at,done,created_at) VALUES (?,?,'unban',0,?,0,?)",
                [$chatId, $userId, $until, time()]);
        }
        Logger::user($chatId, $actorId, $userId, $until ? 'tempban' : 'ban', $reason);
        return $ok;
    }

    public static function unban(int $chatId, int $userId, ?int $actorId = null): bool
    {
        $ok = BaleApi::call('unbanChatMember', ['chat_id' => $chatId, 'user_id' => $userId], 15) !== null;
        DB::q("DELETE FROM bans WHERE chat_id=? AND user_id=?", [$chatId, $userId]);
        DB::q("DELETE FROM mutes WHERE chat_id=? AND user_id=?", [$chatId, $userId]);
        DB::q("UPDATE scheduled_actions SET done=1 WHERE chat_id=? AND user_id=? AND action IN ('unban')", [$chatId, $userId]);
        Logger::user($chatId, $actorId, $userId, 'unban');
        return $ok;
    }

    public static function kick(int $chatId, int $userId, int $seconds = 60, ?int $actorId = null): bool
    {
        // کیک واقعی = بن کوتاه + آنبن خودکار
        $ok = self::ban($chatId, $userId, max(30, $seconds), 'kick', $actorId);
        Logger::user($chatId, $actorId, $userId, 'kick', (string)$seconds);
        return $ok;
    }

    public static function purge(int $chatId, int $baseMessageId, int $count): int
    {
        $deleted = 0;
        for ($i = 0; $i < $count; $i++) {
            $mid = $baseMessageId - $i;
            if ($mid <= 0) {
                break;
            }
            if (BaleApi::deleteMessage($chatId, $mid)) {
                $deleted++;
            }
            usleep(150000); // فاصله برای رعایت محدودیت API
        }
        Logger::user($chatId, null, null, 'purge', "{$deleted}/{$count} از {$baseMessageId}");
        return $deleted;
    }

    /** حذف امن + تلاش برای حذف پیام اختیاری */
    public static function safeDelete(int $chatId, ?int $messageId): bool
    {
        if (!$messageId) {
            return false;
        }
        return BaleApi::deleteMessage($chatId, $messageId);
    }

    /** ارسال گزارش به کانال لاگ گروه (اگر تنظیم شده باشد) */
    public static function logToChannel(int $chatId, string $text): void
    {
        $s       = Settings::get($chatId);
        $logChat = (int)($s['log_channel_id'] ?: 0);
        if ($logChat === 0) {
            $logChat = (int)(Schema::getSetting('default_log_channel', '0') ?: 0);
        }
        if ($logChat !== 0) {
            BaleApi::sendMessage($logChat, $text);
        }
    }

    // ---------------- کپچا ----------------
    public static function startCaptcha(int $chatId, array $user, array $s): void
    {
        $userId = (int)$user['id'];
        $a      = random_int(2, 9);
        $b      = random_int(2, 9);
        $answer = $a + $b;
        DB::q("INSERT INTO captcha_pending (chat_id,user_id,message_id,answer,expires_at) VALUES (?,?,NULL,?,?)
               ON DUPLICATE KEY UPDATE answer=VALUES(answer), expires_at=VALUES(expires_at), message_id=NULL",
            [$chatId, $userId, $answer, time() + max(30, (int)$s['captcha_timeout_sec'])]);
        // فقط می‌تواند دکمه بزند/پیام ندهد
        BaleApi::restrict($chatId, $userId, false, time() + max(60, (int)$s['captcha_timeout_sec'] + 30));

        $options = [$answer, $answer + random_int(1, 4), max(1, $answer - random_int(1, 5)), $answer + random_int(5, 9)];
        $options = array_values(array_unique($options));
        shuffle($options);
        $buttons = [];
        foreach ($options as $opt) {
            $buttons[] = ['text' => digits_fa($opt), 'callback_data' => "cap:{$chatId}:{$userId}:{$opt}"];
        }
        $rows = array_chunk($buttons, 2);
        $mid  = BaleApi::sendMessage(
            $chatId,
            "👋 " . self::mention($user) . "\nبرای اینکه بتونی پیام بفرستی، جواب این جمع رو بزن:\n\n<b>{$a} + {$b} = ?</b>\n\n⏳ " . digits_fa((int)$s['captcha_timeout_sec']) . " ثانیه فرصت داری.",
            null,
            ['inline_keyboard' => $rows],
            'HTML'
        );
        DB::q("UPDATE captcha_pending SET message_id=? WHERE chat_id=? AND user_id=?", [$mid, $chatId, $userId]);
    }

    /** @return array{0:bool,1:string} */
    public static function verifyCaptcha(int $chatId, int $userId, int $answer): array
    {
        $row = DB::one("SELECT * FROM captcha_pending WHERE chat_id=? AND user_id=?", [$chatId, $userId]);
        if (!$row) {
            return [true, 'no-captcha'];
        }
        if ((int)$row['expires_at'] < time()) {
            return [false, 'expired'];
        }
        if ((int)$row['answer'] !== $answer) {
            return [false, 'wrong'];
        }
        DB::q("DELETE FROM captcha_pending WHERE chat_id=? AND user_id=?", [$chatId, $userId]);
        self::unmute($chatId, $userId, null);
        if (!empty($row['message_id'])) {
            BaleApi::deleteMessage($chatId, (int)$row['message_id']);
        }
        return [true, 'ok'];
    }

    public static function pendingCaptcha(int $chatId, int $userId): ?array
    {
        $row = DB::one("SELECT * FROM captcha_pending WHERE chat_id=? AND user_id=?", [$chatId, $userId]);
        if ($row && (int)$row['expires_at'] >= time()) {
            return $row;
        }
        return null;
    }

    // ---------------- خوش‌آمد / خداحافظ ----------------
    public static function renderTemplate(string $tpl, array $user, array $chat, array $s): string
    {
        $map = [
            '{name}'    => self::fullName($user),
            '{username}' => !empty($user['username']) ? '@' . $user['username'] : self::fullName($user),
            '{id}'      => (string)($user['id'] ?? ''),
            '{group}'   => (string)($chat['title'] ?? ''),
            '{count}'   => (string)($s['_member_count'] ?? ''),
            '{rules}'   => (string)($s['rules'] ?? ''),
        ];
        return str_replace(array_keys($map), array_values($map), $tpl);
    }

    public static function sendWelcome(int $chatId, array $chat, array $user): void
    {
        $s = Settings::get($chatId);
        if (!(int)$s['welcome_on'] || !empty($user['is_bot'])) {
            return;
        }
        $cnt = BaleApi::getChatMemberCount($chatId);
        if ($cnt !== null) {
            $s['_member_count'] = (string)$cnt;
        }
        $text = trim((string)($s['welcome_text'] ?: Settings::DEFAULTS['welcome_text']));
        $text = self::renderTemplate($text, $user, $chat, $s);
        $markup = null;
        $btns   = DB::all("SELECT label,url FROM welcome_buttons WHERE chat_id=? ORDER BY sort,id", [$chatId]);
        if ($btns) {
            $rows = [];
            foreach ($btns as $b) {
                $rows[] = [['text' => (string)$b['label'], 'url' => (string)$b['url']]];
            }
            $markup = ['inline_keyboard' => $rows];
        }
        BaleApi::sendMessage($chatId, $text, null, $markup, 'HTML');
    }

    public static function sendGoodbye(int $chatId, array $chat, array $user): void
    {
        $s = Settings::get($chatId);
        if (!(int)$s['goodbye_on']) {
            return;
        }
        $text = self::renderTemplate((string)($s['goodbye_text'] ?: Settings::DEFAULTS['goodbye_text']), $user, $chat, $s);
        BaleApi::sendMessage($chatId, $text, null, null, 'HTML');
    }

    // ---------------- گزارش کاربر ----------------
    public static function createReport(int $chatId, int $targetId, ?int $reporterId, ?int $messageId, string $reason): int
    {
        $id = DB::insert("INSERT INTO reports (chat_id,target_user_id,reporter_user_id,admin_user_id,message_id,reason,status,ts)
                          VALUES (?,?,?,NULL,?,?,'open',?)",
            [$chatId, $targetId, $reporterId, $messageId, str_limit($reason, 480), time()]);
        $s = Settings::get($chatId);
        $ch = (int)($s['report_channel_id'] ?: 0);
        if ($ch !== 0) {
            BaleApi::sendMessage($ch, "🚨 گزارش جدید #<code>{$id}</code>\nگروه: " . h((string)($s['title'] ?? $chatId)) .
                "\nگزارش‌دهنده: <code>{$reporterId}</code>\nهدف: <code>{$targetId}</code>\nدلیل: " . h($reason ?: '-'), null, null, 'HTML');
        }
        return $id;
    }

    // ---------------- اقدامات زمان‌بندی‌شده ----------------
    public static function processScheduled(): int
    {
        $rows = DB::all("SELECT * FROM scheduled_actions WHERE done=0 AND run_at<=? ORDER BY run_at LIMIT 50", [time()]);
        $n    = 0;
        foreach ($rows as $r) {
            $chatId = (int)$r['chat_id'];
            $userId = (int)$r['user_id'];
            try {
                switch ((string)$r['action']) {
                    case 'unmute':
                        self::unmute($chatId, $userId, null);
                        BaleApi::sendMessage($chatId, "🔊 سکوت کاربر <code>{$userId}</code> به پایان رسید.", null, null, 'HTML');
                        break;
                    case 'unban':
                        self::unban($chatId, $userId, null);
                        break;
                    case 'unwarn':
                        DB::q("UPDATE warns SET count=GREATEST(count-1,0) WHERE chat_id=? AND user_id=?", [$chatId, $userId]);
                        break;
                }
            } catch (Throwable $e) {
                Logger::error('cron', 'scheduled action failed: ' . $e->getMessage(), ['row' => $r]);
            }
            DB::q("UPDATE scheduled_actions SET done=1 WHERE id=?", [(int)$r['id']]);
            $n++;
        }
        return $n;
    }
}
