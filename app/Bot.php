<?php
declare(strict_types=1);

/**
 * هسته ربات: دریافت آپدیت، تشخیص دستور، پایپ‌لاین ضدتخلف
 *
 * تفاوت‌های کلیدی با نسخه پایتون (که در گزارش تحلیل آمده):
 *  - idempotency: هر update_id فقط یک‌بار پردازش می‌شود (تکرار پیام بعد از ری‌استارت/وب‌هوک تکراری)
 *  - هیچ خطایی حلقه را نمی‌خواباند؛ خطا لاگ می‌شود و آپدیت بعدی پردازش می‌شود
 *  - ادمین‌ها کش می‌شوند و برای هر پیام ۲ درخواست API اضافه زده نمی‌شود
 *  - ترتیب بررسی‌ها درست است (اسپم/لینک/قفل) و ادمین‌ها مستثنی هستند
 *  - پشتیبانی از کپچا، فوروارد، یوزرنیم، گزارش، لاگ کانال، دکمه‌های خوش‌آمد و ...
 */
final class Bot
{
    public static function run(array $update): void
    {
        try {
            if (!self::once($update)) {
                return;
            }
            if (isset($update['message'])) {
                self::handleMessage($update['message']);
            } elseif (isset($update['edited_message'])) {
                self::handleEdited($update['edited_message']);
            } elseif (isset($update['callback_query'])) {
                self::handleCallback($update['callback_query']);
            } elseif (isset($update['my_chat_member'])) {
                self::handleBotMembership($update['my_chat_member']);
            } elseif (isset($update['channel_post'])) {
                // فعلاً نادیده
            }
        } catch (Throwable $e) {
            Logger::error('bot', get_class($e) . ': ' . $e->getMessage(), [
                'file' => basename($e->getFile()) . ':' . $e->getLine(),
            ]);
        }
    }

    /** جلوگیری از پردازش تکراری آپدیت */
    private static function once(array $update): bool
    {
        $id = (int)($update['update_id'] ?? 0);
        if ($id <= 0) {
            return true;
        }
        try {
            $st = DB::q("INSERT IGNORE INTO bot_updates (update_id, handled_at) VALUES (?,?)", [$id, time()]);
            if ($st->rowCount() === 0) {
                return false;
            }
            // تمیزکاری دوره‌ای
            if (random_int(1, 500) === 1) {
                DB::q("DELETE FROM bot_updates WHERE handled_at < ?", [time() - 3 * 86400]);
            }
            return true;
        } catch (Throwable $e) {
            Logger::warn('bot', 'dedupe failed: ' . $e->getMessage());
            return true;
        }
    }

    private static function handleBotMembership(array $u): void
    {
        $chat   = $u['chat'] ?? [];
        $chatId = (int)($chat['id'] ?? 0);
        if (!$chatId) {
            return;
        }
        $status = (string)($u['new_chat_member']['status'] ?? '');
        if (in_array($status, ['member', 'administrator'], true)) {
            Settings::ensure($chatId, (string)($chat['title'] ?? ''), (string)($chat['type'] ?? ''));
            Settings::set($chatId, 'is_active', 1);
            BaleApi::sendMessage($chatId,
                "🤖 ربات مدیر گروه فعال شد.\n" .
                "برای دیدن دستورات بنویس: <code>راهنما</code>\n" .
                "برای اینکه ربات بتواند پیام حذف/سکوت کند، آن را <b>ادمین</b> کنید.");
        } elseif (in_array($status, ['left', 'kicked'], true)) {
            Settings::set($chatId, 'is_active', 0);
        }
    }

    private static function handleCallback(array $cb): void
    {
        $data = (string)($cb['data'] ?? '');
        $from = $cb['from'] ?? [];
        $uid  = (int)($from['id'] ?? 0);
        $msg  = $cb['message'] ?? [];
        $chat = $msg['chat'] ?? [];
        $chatId = (int)($chat['id'] ?? 0);
        try {
            if (str_starts_with($data, 'cap:')) {
                // cap:{chatId}:{userId}:{answer}
                $p = explode(':', $data);
                if (count($p) >= 4) {
                    $targetChat = (int)$p[1];
                    $targetUser = (int)$p[2];
                    $answer     = (int)$p[3];
                    if ($uid !== $targetUser) {
                        BaleApi::answerCallback((string)$cb['id'], 'این دکمه برای شما نیست 🙂', true);
                        return;
                    }
                    [$ok, $why] = Moderation::verifyCaptcha($targetChat, $targetUser, $answer);
                    if ($ok) {
                        BaleApi::answerCallback((string)$cb['id'], 'تایید شد ✅ خوش آمدی!');
                        if (!empty($msg['message_id'])) {
                            BaleApi::deleteMessage($chatId, (int)$msg['message_id']);
                        }
                        Moderation::sendWelcome($chatId, $chat, $from);
                    } elseif ($why === 'expired') {
                        BaleApi::answerCallback((string)$cb['id'], 'زمان تمام شد؛ دوباره امتحان کن.', true);
                    } else {
                        BaleApi::answerCallback((string)$cb['id'], 'جواب درست نیست ❌', true);
                    }
                    return;
                }
            }
            if (str_starts_with($data, 'ra:')) {
                // ra:{op}:{chatId}:{userId}:{reportId}
                $p2 = explode(':', $data);
                if (count($p2) >= 4) {
                    $op       = $p2[1];
                    $targetChat = (int)$p2[2];
                    $targetUser = (int)$p2[3];
                    $reportId   = (int)($p2[4] ?? 0);
                    if (!Moderation::isGroupAdmin($targetChat, $uid, true)) {
                        BaleApi::answerCallback((string)$cb['id'], '⛔ فقط ادمین‌های گروه می‌توانند.', true);
                        return;
                    }
                    if ($op === 'mute') {
                        Moderation::mute($targetChat, $targetUser, 3600, 'از دکمه گزارش', $uid);
                        BaleApi::answerCallback((string)$cb['id'], 'کاربر ۱ ساعت سکوت شد ✅');
                    } elseif ($op === 'ban') {
                        Moderation::ban($targetChat, $targetUser, 0, 'از دکمه گزارش', $uid);
                        BaleApi::answerCallback((string)$cb['id'], 'کاربر بن شد ⛔');
                    }
                    if ($reportId) {
                        DB::q("UPDATE reports SET status='closed', admin_user_id=? WHERE id=?", [$uid, $reportId]);
                    }
                    if (!empty($chatId) && !empty($msg['message_id'])) {
                        BaleApi::editMessage($chatId, (int)$msg['message_id'], ($op === 'ban' ? '⛔' : '🔇') . " گزارش #{$reportId} توسط ادمین رسیدگی شد.");
                    }
                    Logger::audit(null, 'bale:' . $uid, 'report_action_' . $op, "{$targetChat}/{$targetUser}");
                    return;
                }
            }
            BaleApi::answerCallback((string)$cb['id'], '');
        } catch (Throwable $e) {
            Logger::warn('callback', $e->getMessage());
            BaleApi::answerCallback((string)$cb['id'], '');
        }
    }

    private static function handleEdited(array $m): void
    {
        // اگر پیام ویرایش شد و اکنون تخلف دارد، همان چرخه اجرا می‌شود
        $m['_edited'] = true;
        self::handleMessage($m);
    }

    private static function handleMessage(array $message): void
    {
        $chat    = $message['chat'] ?? [];
        $chatId  = (int)($chat['id'] ?? 0);
        $type    = (string)($chat['type'] ?? '');
        $from    = $message['from'] ?? [];
        $userId  = (int)($from['id'] ?? 0);
        $msgId   = (int)($message['message_id'] ?? 0);
        if (!$chatId) {
            return;
        }

        // ---- چت خصوصی: منو/ورود به پنل ----
        if ($type === 'private') {
            self::handlePrivate($message, $from, $userId);
            return;
        }
        if ($type !== 'group' && $type !== 'supergroup') {
            return;
        }

        $s = Settings::ensure($chatId, (string)($chat['title'] ?? ''), $type);
        if ((int)$s['is_active'] === 0) {
            return;
        }

        // ---- پیام‌های سرویسی ----
        if (!empty($message['new_chat_members'])) {
            self::onJoin($message, $chat, $chatId, $userId, $s);
            return;
        }
        if (!empty($message['left_chat_member'])) {
            $left = (array)$message['left_chat_member'];
            Logger::user($chatId, (int)($left['id'] ?? 0), null, 'left', Moderation::fullName($left));
            Moderation::sendGoodbye($chatId, $chat, $left);
            return;
        }

        if ($userId && !empty($from['is_bot'])) {
            return; // به پیام ربات‌ها کاری نداریم (جلوگیری از حلقه)
        }

        self::touchUser($from);
        Metrics::bump($chatId, 'messages');
        if ($userId) {
            // اگر از قبل دعوت‌شده در حالت pending است، اولین حضور در گروه = تایید
            try {
                Referral::verify($userId, $chatId);
            } catch (Throwable $e) {
                Logger::warn('referral', 'verify on message: ' . $e->getMessage());
            }
        }

        // ---- کپچای در انتظار ----
        if ($userId) {
            $pending = Moderation::pendingCaptcha($chatId, $userId);
            if ($pending) {
                $txt = Text::normalize(Moderation::textOf($message));
                $digits = preg_replace('/[^0-9]/', '', $txt);
                if ($digits !== '' && (int)$digits === (int)$pending['answer']) {
                    Moderation::verifyCaptcha($chatId, $userId, (int)$digits);
                    Moderation::safeDelete($chatId, $msgId);
                    Moderation::sendWelcome($chatId, $chat, $from);
                    return;
                }
                Moderation::safeDelete($chatId, $msgId);
                $left = max(0, (int)$pending['expires_at'] - time());
                BaleApi::sendMessage($chatId, "❌ جواب درست نیست. {$left} ثانیه فرصت داری.", $msgId);
                return;
            }
        }

        $isAdmin = $userId ? Moderation::isGroupAdmin($chatId, $userId) : false;

        // ---- کاربر سکوت‌شده: پیامش حذف می‌شود و به دستورها هم دسترسی ندارد ----
        if ($userId && !$isAdmin && Moderation::isMuted($chatId, $userId)) {
            Moderation::safeDelete($chatId, $msgId);
            return;
        }

        // ---- دستورات ----
        $text = Moderation::textOf($message);
        if ($text !== '' && $userId) {
            $ctx = Commands::parse($message, $chatId, $from, $userId, $msgId, $isAdmin);
            if ($ctx !== null && Commands::dispatch($ctx)) {
                Metrics::bump($chatId, 'commands');
                Logger::user($chatId, $userId, null, 'command', (string)$ctx['cmd_name']);
                return;
            }
        }

        if ($isAdmin) {
            return; // ادمین‌ها از همه فیلترها معاف هستند
        }

        // ---- ضدبات / ضد فوروارد ----
        if ((int)$s['anti_bot'] === 1 && $userId && !empty($from['is_bot'])) {
            Moderation::ban($chatId, $userId, 0, 'anti_bot', null);
            return;
        }
        if ((int)$s['lock_bot'] === 1 && $userId && !empty($from['is_bot'])) {
            Moderation::safeDelete($chatId, $msgId);
            return;
        }
        if ((int)$s['anti_forward'] === 1 && (isset($message['forward_from']) || isset($message['forward_origin']))) {
            Moderation::safeDelete($chatId, $msgId);
            BaleApi::sendMessage($chatId, "📵 ارسال پیام فوروارد در این گروه مجاز نیست.", $msgId);
            return;
        }

        // ---- قفل‌ها ----
        $locked = Moderation::lockedContentType($message, $s);
        if ($locked !== null) {
            Moderation::safeDelete($chatId, $msgId);
            BaleApi::sendMessage($chatId, "🔒 ارسال «{$locked}» در این گروه قفل است.", $msgId);
            return;
        }

        // ---- ضدلینک ----
        if ((int)$s['anti_link'] === 1 && $text !== '' && Text::hasLink($text)) {
            self::autoViolation($chatId, $userId, $msgId, 'ارسال لینک/آیدی/شماره', $s);
            return;
        }
        if ((int)$s['anti_username'] === 1 && $text !== '' && preg_match('~(?:^|\s)@[a-zA-Z0-9_]{4,}~u', $text)
            && (int)$s['anti_link'] === 0) {
            self::autoViolation($chatId, $userId, $msgId, 'ارسال یوزرنیم', $s);
            return;
        }

        // ---- فحش ----
        if ((int)$s['bad_words'] === 1 && $text !== '') {
            $hit = Text::hasBadWord($text, Settings::badWords($chatId));
            if ($hit !== null) {
                self::autoViolation($chatId, $userId, $msgId, 'کلمه نامناسب', $s);
                return;
            }
        }

        // ---- ضداسپم ----
        if ((int)$s['anti_spam'] === 1 && $userId && self::isSpam($chatId, $userId, $text, $message, $s)) {
            $action = (string)$s['anti_flood_action'];
            $sec    = max(30, (int)$s['flood_mute_sec']);
            if ($action === 'delete') {
                Moderation::safeDelete($chatId, $msgId);
                BaleApi::sendMessage($chatId, "🚫 اسپم تشخیص داده شد.", $msgId);
            } elseif ($action === 'warn') {
                self::autoViolation($chatId, $userId, $msgId, 'اسپم/فلود', $s);
            } else {
                Moderation::safeDelete($chatId, $msgId);
                Moderation::mute($chatId, $userId, $sec, 'اسپم/فلود', null);
                BaleApi::sendMessage($chatId, "🔇 " . Moderation::mention($from) . " به دلیل اسپم " . duration_human($sec) . " سکوت شد.");
            }
            return;
        }
    }

    /** تخلف خودکار: اخطار + حذف + اطلاع‌رسانی */
    private static function autoViolation(int $chatId, int $userId, int $msgId, string $reason, array $s): void
    {
        Moderation::safeDelete($chatId, $msgId);
        $count = Moderation::warn($chatId, $userId, $reason, null, null);
        if ($count > 0) {
            BaleApi::sendMessage($chatId, "⚠️ اخطار {$count}/" . (int)$s['warn_limit'] . "\nدلیل: {$reason}");
        }
        Moderation::logToChannel($chatId, "🛡 تخلف: <code>{$userId}</code> | {$reason}");
    }

    /** تشخیص اسپم چندسیگنالی (نرخ + تکرار متن + تکراری پشت‌سرهم) */
    private static function isSpam(int $chatId, int $userId, string $text, array $message, array $s): bool
    {
        $window = max(2, (int)$s['spam_window_sec']);
        $max    = max(2, (int)$s['spam_max_msgs']);
        $rate   = RateLimit::count("msg:$chatId:$userId", $window);
        if ($rate >= $max) {
            return true;
        }
        if ($text === '') {
            return false;
        }
        // پیام یکسان پشت‌سرهم
        $fp   = Text::fingerprint($text);
        $last = (string)Cache::get("lastmsg:$chatId:$userId", '');
        Cache::set("lastmsg:$chatId:$userId", $fp, 120);
        if ($fp === $last && mb_strlen($text) > 3) {
            $sameCount = Cache::incr("same:$chatId:$userId:$fp", 120);
            if ($sameCount >= 3) {
                Cache::delete("same:$chatId:$userId:$fp");
                return true;
            }
        }
        // پیام طولانی تکرارشده در گروه (۳ نفر)
        if (mb_strlen($text) > 60) {
            $groupFp = Cache::incr("fp:$chatId:$fp", 300);
            if ($groupFp >= 3) {
                return true;
            }
        }
        return false;
    }

    private static function onJoin(array $message, array $chat, int $chatId, int $actorId, array $s): void
    {
        foreach ((array)$message['new_chat_members'] as $u) {
            $u = (array)$u;
            $uid = (int)($u['id'] ?? 0);
            if (!$uid) {
                continue;
            }
            self::touchUser($u);
            Logger::user($chatId, $uid, null, 'join', Moderation::fullName($u));
            try {
                Referral::verify($uid, $chatId);
            } catch (Throwable $e) {
                Logger::warn('referral', 'verify on join: ' . $e->getMessage());
            }
            if (!empty($u['is_bot']) && (int)$s['anti_bot'] === 1 && !Moderation::isGroupAdmin($chatId, $uid, true)) {
                Moderation::ban($chatId, $uid, 0, 'anti_bot (ورود)', $actorId);
                continue;
            }
            if ((int)$s['captcha_on'] === 1 && !Moderation::isGroupAdmin($chatId, $uid, true)) {
                Moderation::startCaptcha($chatId, $u, $s);
                continue;
            }
            Moderation::sendWelcome($chatId, $chat, $u);
        }
    }

    private static function touchUser(array $u): void
    {
        $uid = (int)($u['id'] ?? 0);
        if (!$uid) {
            return;
        }
        try {
            DB::q("INSERT INTO users (user_id,username,first_name,last_name,messages_count,first_seen_at,last_seen_at)
                   VALUES (?,?,?,?,1,?,?)
                   ON DUPLICATE KEY UPDATE username=VALUES(username), first_name=VALUES(first_name),
                     last_name=VALUES(last_name), messages_count=messages_count+1, last_seen_at=VALUES(last_seen_at)",
                [$uid, isset($u['username']) ? str_limit((string)$u['username'], 60) : null,
                 str_limit((string)($u['first_name'] ?? ''), 100), str_limit((string)($u['last_name'] ?? ''), 100),
                 time(), time()]);
        } catch (Throwable $e) {
            Logger::warn('db', 'touchUser: ' . $e->getMessage());
        }
    }

    private static function handlePrivate(array $message, array $from, int $userId): void
    {
        $text = Text::normalize(Moderation::textOf($message));
        $text = preg_replace('~^[/!#]~u', '', $text);
        $cmd  = explode(' ', $text, 2)[0];
        $rest = trim((string)explode(' ', $text, 2)[1]);
        $ctxP = [
            'chat_id' => $userId, 'chat' => ['id' => $userId, 'type' => 'private'],
            'from' => $from, 'user_id' => $userId, 'msg_id' => (int)($message['message_id'] ?? 0),
            'message' => $message, 'cmd_name' => $cmd, 'cmd' => $cmd, 'rest' => $rest,
            'is_admin' => false, 'prefixed' => false,
        ];

        // گیت عضویت/بازی (ماژول forcejoin) — پیش از دسترسی به بخش‌های فروشگاه و دعوت
        if (class_exists('Modules\ForceJoin') && \Modules\ForceJoin::isEnabled()) {
            $gated = ['کالاها', 'محصولات', 'products', 'خرید', 'buy', 'سفارشها', 'سفارش‌ها', 'orders',
                      'فروشگاه', 'shop', 'کیف_پول', 'کیفپول', 'موجودی', 'wallet', 'فروشنده', 'فروشندگی', 'sell'];
            if (in_array($cmd, $gated, true) && !\Modules\ForceJoin::gate($ctxP)) {
                return;
            }
        }

        // دستورهای ماژول‌ها در چت خصوصی (مثل عضو_شدم / اخبار / لینک_دعوت_من)
        if ($cmd !== '' && !in_array($cmd, ['start', 'شروع', 'سلام'], true)
            && ModuleManager::dispatchCommand($cmd, $ctxP)) {
            return;
        }

        if ($cmd === '' || in_array($cmd, ['start', 'شروع', 'سلام'], true)) {
            // پارامتر دعوت: /start ref_xxx
            $payload = trim((string)preg_replace('~^(/)?(start|شروع|سلام)\s*~u', '', Text::normalize(Moderation::textOf($message)), 1));
            // لینک خرید: /start buy_CODE
            if (str_starts_with($payload, 'buy_')) {
                $code = substr($payload, 4);
                $p    = Store::byCode($code);
                if ($p && $p['status'] === 'active') {
                    DB::q("INSERT IGNORE INTO users (user_id, first_name, username, messages_count, first_seen_at, last_seen_at)
                           VALUES (?,?,?,0,?,?)
                           ON DUPLICATE KEY UPDATE last_seen_at=VALUES(last_seen_at)",
                        [$userId, (string)($from['first_name'] ?? ''), (string)($from['username'] ?? ''), time(), time()]);
                    $link = url('shop/index.php', ['p' => 'product_view', 'id' => (int)$p['id']]);
                    BaleApi::sendMessage($userId,
                        "🛍 <b>" . h((string)$p['title']) . "</b>\n\n"
                        . mb_strimwidth((string)($p['description'] ?? ''), 0, 600, '…') . "\n\n"
                        . "💰 قیمت: " . money(Store::price($p)) . "\n"
                        . "📦 موجودی: " . digits_fa(Store::available($p)) . " " . h((string)$p['unit']) . "\n\n"
                        . "برای ثبت سفارش و پرداخت: " . $link . "\n"
                        . "خرید سریع: <code>خرید " . h((string)$p['code']) . "</code>",
                        null, null, 'HTML');
                    return;
                }
                BaleApi::sendMessage($userId, "❌ این کالا موجود یا فعال نیست.");
                return;
            }
            if ($payload !== '' && Referral::isEnabled()) {
                $r = Referral::attachPending($userId, $payload, null);
                if ($r['ok']) {
                    BaleApi::sendMessage($userId, "✅ دعوت ثبت شد. بعد از اولین حضورت در گروه، برای معرفت تایید می‌شود.");
                }
            }
            $panel = url('panel/');
            BaleApi::sendMessage($userId,
                "🤖 <b>ربات مدیر گروه بله</b>\n\n" .
                "من را به گروه اضافه کن و <b>ادمین</b> بده، بعد این دستورها را بفرست:\n" .
                "<code>راهنما</code> — لیست کامل دستورات\n" .
                "<code>قوانین</code> — نمایش قوانین\n" .
                "<code>گزارش</code> (ریپلای) — گزارش تخلف\n\n" .
                "🛍 فروشگاه و خرید: " . url('shop/') . "\n" .
                "🔐 ورود به پنل مدیریت: {$panel}",
                null, null, 'HTML');
            return;
        }
        if (in_array($cmd, ['دعوت', 'لینک_دعوت', 'زیرمجموعه', 'زیرمجموعه‌ها', 'referral', 'invite'], true)) {
            if (!Referral::isEnabled()) {
                BaleApi::sendMessage($userId, "ℹ️ سامانه دعوت در حال حاضر غیرفعال است.");
                return;
            }
            $st = Referral::stats($userId);
            BaleApi::sendMessage($userId,
                "🔗 <b>لینک دعوت اختصاصی شما</b>\n"
                . "<code>" . h($st['link']) . "</code>\n\n"
                . "👥 دعوت تاییدشده: " . digits_fa($st['verified']) . "\n"
                . "⏳ در انتظار: " . digits_fa($st['pending']) . "\n"
                . "🌱 سطح ۲: " . digits_fa($st['level2']) . "\n\n"
                . "هر کسی با این لینک وارد شود و در یکی از گروه‌های ربات فعالیت کند، به عنوان زیرمجموعه تو ثبت می‌شود.",
                null, null, 'HTML');
            return;
        }
        if (in_array($cmd, ['کالاها', 'محصولات', 'products'], true)) {
            Commands::dispatch(['cmd' => 'products', 'cmd_name' => 'products', 'chat_id' => $userId,
                'rest' => '', 'from' => $from, 'user_id' => $userId, 'msg_id' => 0, 'is_admin' => false, 'prefixed' => false]);
            return;
        }
        if (in_array($cmd, ['خرید', 'buy'], true)) {
            $rest = trim((string)preg_replace('~^(خرید|buy)\s*~u', '', $text, 1));
            Commands::dispatch(['cmd' => 'buy', 'cmd_name' => 'buy', 'chat_id' => $userId,
                'rest' => $rest, 'from' => $from, 'user_id' => $userId, 'msg_id' => 0, 'is_admin' => false, 'prefixed' => false]);
            return;
        }
        if (in_array($cmd, ['سفارشها', 'سفارش‌ها', 'orders'], true)) {
            Commands::dispatch(['cmd' => 'my_orders', 'cmd_name' => 'my_orders', 'chat_id' => $userId,
                'rest' => '', 'from' => $from, 'user_id' => $userId, 'msg_id' => 0, 'is_admin' => false, 'prefixed' => false]);
            return;
        }
        if (in_array($cmd, ['فروشگاه', 'shop'], true)) {
            Commands::dispatch(['cmd' => 'shop', 'cmd_name' => 'shop', 'chat_id' => $userId,
                'rest' => '', 'from' => $from, 'user_id' => $userId, 'msg_id' => 0, 'is_admin' => false, 'prefixed' => false]);
            return;
        }
        if (in_array($cmd, ['کیف_پول', 'کیفپول', 'موجودی', 'wallet'], true)) {
            Commands::dispatch(['cmd' => 'wallet', 'cmd_name' => 'wallet', 'chat_id' => $userId,
                'rest' => '', 'from' => $from, 'user_id' => $userId, 'msg_id' => 0, 'is_admin' => false, 'prefixed' => false]);
            return;
        }
        if (in_array($cmd, ['فروشنده', 'فروشندگی', 'sell'], true)) {
            Commands::dispatch(['cmd' => 'sell', 'cmd_name' => 'sell', 'chat_id' => $userId,
                'rest' => '', 'from' => $from, 'user_id' => $userId, 'msg_id' => 0, 'is_admin' => false, 'prefixed' => false]);
            return;
        }
        if (in_array($cmd, ['ورود', 'login', 'پنل'], true)) {
            $link = Login::issueLinkForBaleUser($userId);
            if ($link === null) {
                BaleApi::sendMessage($userId, "❌ حساب بله شما به هیچ ادمینِ پنل متصل نیست.\nاز داخل پنل، آیدی عددی بله خود را در بخش «حساب من» ثبت کنید.");
                return;
            }
            BaleApi::sendMessage($userId, "🔐 لینک ورود یک‌بارمصرف (۳ دقیقه اعتبار):\n" . $link . "\n\nاگر درخواست ندادی، نادیده بگیر.", null, null, 'HTML');
            return;
        }
        // سایر دستورهای عمومی (مثل «کدتخفیف») در چت خصوصی
        $ctxC = Commands::parse($message, $userId, $from, $userId, (int)($message['message_id'] ?? 0), false);
        if ($ctxC !== null && !in_array((string)$ctxC['cmd'], Commands::ADMIN_ONLY, true)
            && Commands::dispatch($ctxC)) {
            return;
        }
        BaleApi::sendMessage($userId, "برای شروع بنویس: <code>start</code>", null, null, 'HTML');
    }

    public static function registerWebhook(): array
    {
        $base   = rtrim((string)config('app.url', ''), '/');
        $secret = (string)config('bot.webhook_secret', '');
        if ($base === '') {
            return ['ok' => false, 'error' => 'app.url در تنظیمات خالی است'];
        }
        $hook = $base . '/webhook.php';
        if ($secret !== '') {
            $hook .= '?s=' . urlencode($secret);
        }
        $res = BaleApi::setWebhook($hook, $secret);
        if ($res === null) {
            return ['ok' => false, 'error' => BaleApi::$lastError ?: 'unknown'];
        }
        Schema::setSetting('webhook_url', $hook);
        Schema::setSetting('mode', 'webhook');
        return ['ok' => (bool)($res['ok'] ?? false), 'url' => $hook];
    }

    public static function removeWebhook(): array
    {
        $res = BaleApi::deleteWebhook();
        Schema::setSetting('webhook_url', '');
        Schema::setSetting('mode', 'polling');
        return ['ok' => $res !== null];
    }
}
