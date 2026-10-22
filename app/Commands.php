<?php
declare(strict_types=1);

/**
 * دستورات متنی فارسی (بدون اسلش) + معادل انگلیسی
 * نکته: «اولین توکن پیام» با دستور مقایسه می‌شود و برای دستورهای حساس
 * (بن/سکوت/…) حتماً باید کاربر هدف مشخص شود تا پیام معمولی اشتباه اجرا نشود.
 */
final class Commands
{
    /** دستورهایی که فقط ادمین گروه/ربات می‌تواند اجرا کند */
    public const ADMIN_ONLY = [
        'ban','unban','kick','mute','unmute','warn','unwarn','purge','warns_list',
        'lock','unlock','locks_list','rules_set','welcome_toggle','welcome_set','welcome_buttons',
        'goodbye_toggle','goodbye_set','antilink','antispam','badwords','antibot','antiforward',
        'antiuser','captcha','spam_tune','warn_tune','mute_time','bans_list','mutes_list',
        'reports_list','log_set','log_off','settings_view','group_lock','group_unlock',
        'pin','unpin','reset','welcome_test','broadcast','chart','backup',
    ];

    /** نگاشت نام‌های فارسی/انگلیسی به دستور کانونیکال */
    public const MAP = [
        // عمومی
        'راهنما' => 'help', 'help' => 'help', 'دستورات' => 'help', 'منو' => 'help',
        'پنل' => 'help', 'پنل_ادمین' => 'help', 'adminpanel' => 'help',
        'قوانین' => 'rules_view', 'rules' => 'rules_view',
        'گزارش' => 'report', 'report' => 'report',
        'من' => 'me', 'آیدی' => 'me', 'ایدی' => 'me', 'id' => 'me', 'اطلاعات_من' => 'me', 'myid' => 'me',
        'ادمینها' => 'admins', 'ادمین‌ها' => 'admins', 'لیست_ادمین' => 'admins', 'admins' => 'admins',
        'امار' => 'stats', 'آمار' => 'stats', 'وضعیت' => 'stats', 'stats' => 'stats', 'status' => 'stats',
        'آیدی_گروه' => 'chat_id', 'chatid' => 'chat_id',

        // مدیریت اعضا
        'بن' => 'ban', 'ban' => 'ban', 'مسدود' => 'ban',
        'حذف_بن' => 'unban', 'انبن' => 'unban', 'آنبن' => 'unban', 'unban' => 'unban',
        'کیک' => 'kick', 'اخراج' => 'kick', 'kick' => 'kick',
        'سکوت' => 'mute', 'میوت' => 'mute', 'mute' => 'mute',
        'حذف_سکوت' => 'unmute', 'انمیوت' => 'unmute', 'آنمیوت' => 'unmute', 'unmute' => 'unmute',
        'هشدار' => 'warn', 'اخطار' => 'warn', 'warn' => 'warn',
        'حذف_هشدار' => 'unwarn', 'حذف_اخطار' => 'unwarn', 'unwarn' => 'unwarn',
        'اخطارها' => 'warns_list', 'لیست_اخطار' => 'warns_list', 'warns' => 'warns_list',
        'پاکسازی' => 'purge', 'پاک_پیام' => 'purge', 'حذف_پیام' => 'purge', 'purge' => 'purge',
        'اطلاعات' => 'info', 'سابقه' => 'info', 'info' => 'info',
        'لیست_بن' => 'bans_list', 'bans' => 'bans_list',
        'لیست_سکوت' => 'mutes_list', 'mutes' => 'mutes_list',
        'لیست_گزارش' => 'reports_list', 'reports' => 'reports_list',
        'پین' => 'pin', 'سنجاق' => 'pin', 'pin' => 'pin',
        'حذف_پین' => 'unpin', 'unpin' => 'unpin',

        // قفل‌ها
        'قفل' => 'lock', 'lock' => 'lock',
        'باز_کردن' => 'unlock', 'بازکردن' => 'unlock', 'آنلاک' => 'unlock', 'unlock' => 'unlock',
        'لیست_قفل' => 'locks_list', 'locks' => 'locks_list',
        'قفل_گروه' => 'group_lock', 'سکوت_همگانی' => 'group_lock',
        'باز_کردن_گروه' => 'group_unlock',

        // سوییچ‌ها
        'ضد_لینک' => 'antilink', 'antilink' => 'antilink',
        'ضد_اسپم' => 'antispam', 'ضد_فلود' => 'antispam', 'antispam' => 'antispam',
        'ضد_فحش' => 'badwords', 'ضد_کلمات' => 'badwords', 'badwords' => 'badwords',
        'ضد_بات' => 'antibot', 'antibot' => 'antibot',
        'ضد_فوروارد' => 'antiforward', 'antiforward' => 'antiforward',
        'ضد_یوزرنیم' => 'antiuser', 'antiuser' => 'antiuser',
        'کپچا' => 'captcha', 'captcha' => 'captcha',

        // خوش‌آمد
        'خوش_آمدگویی' => 'welcome_toggle', 'خوشامد' => 'welcome_toggle', 'welcome' => 'welcome_toggle',
        'تنظیم_خوش_آمدگویی' => 'welcome_set', 'setwelcome' => 'welcome_set',
        'تست_خوش_آمدگویی' => 'welcome_test',
        'دکمه' => 'welcome_buttons', 'دکمه‌ها' => 'welcome_buttons', 'buttons' => 'welcome_buttons',
        'خداحافظی' => 'goodbye_toggle', 'goodbye' => 'goodbye_toggle',
        'تنظیم_خداحافظی' => 'goodbye_set', 'setgoodbye' => 'goodbye_set',

        // تنظیمات
        'تنظیم_قوانین' => 'rules_set', 'setrules' => 'rules_set',
        'تنظیم_اسپم' => 'spam_tune', 'تنظیم_فلود' => 'spam_tune',
        'تنظیم_اخطار' => 'warn_tune',
        'تنظیم_زمان_سکوت' => 'mute_time',
        'تنظیم_لاگ' => 'log_set', 'لاگ' => 'log_set', 'حذف_لاگ' => 'log_off',
        'تنظیمات' => 'settings_view', 'settings' => 'settings_view',
        'ریست' => 'reset', 'reset' => 'reset',
        'ارسال_همگانی' => 'broadcast',
        'دعوت' => 'invite', 'لینک_دعوت' => 'invite', 'invite' => 'invite',
        'زیرمجموعه' => 'referrals_me', 'زیرمجموعه‌ها' => 'referrals_me', 'referrals' => 'referrals_me',
        'تاپ_دعوت' => 'top_inviters', 'تاپ' => 'top_inviters', 'top' => 'top_inviters',
        'گزارش_نمودار' => 'chart', 'نمودار' => 'chart', 'chart' => 'chart',
        'بکاپ' => 'backup', 'پشتیبان' => 'backup', 'backup' => 'backup',
        'کالاها' => 'products', 'فروشگاه' => 'shop', 'shop' => 'shop',
        'خرید' => 'buy', 'buy' => 'buy', 'سفارشها' => 'my_orders', 'سفارش‌ها' => 'my_orders',
        'کیف_پول' => 'wallet', 'کیفپول' => 'wallet', 'موجودی' => 'wallet', 'wallet' => 'wallet',
        'فروشنده' => 'sell', 'فروشندگی' => 'sell',
    ];

    public static function names(): array
    {
        return array_keys(self::MAP);
    }

    /** @return array<string,mixed>|null */
    public static function parse(array $message, int $chatId, array $from, int $userId, int $msgId, bool $isAdmin): ?array
    {
        $raw = Moderation::textOf($message);
        if ($raw === '' || str_starts_with($raw, '/')) {
            $raw = ltrim($raw, '/');
        }
        $found = Text::looksLikeCommand($raw, self::names());
        if ($found === null) {
            return null;
        }
        $canonical = self::MAP[$found['cmd']] ?? null;
        if ($canonical === null) {
            return null;
        }
        return [
            'chat_id'  => $chatId,
            'chat'     => (array)($message['chat'] ?? []),
            'from'     => $from,
            'user_id'  => $userId,
            'msg_id'   => $msgId,
            'message'  => $message,
            'cmd_name' => $found['cmd'],
            'cmd'      => $canonical,
            'rest'     => $found['rest'],
            'is_admin' => $isAdmin,
            'prefixed' => (bool)preg_match('~^[/!#]~u', (string)Moderation::textOf($message)),
        ];
    }

    public static function dispatch(array $ctx): bool
    {
        $cmd = (string)$ctx['cmd'];
        if (in_array($cmd, self::ADMIN_ONLY, true) && !$ctx['is_admin']) {
            if (!empty($ctx['prefixed'])) {
                self::say($ctx, "⛔ این دستور فقط برای ادمین‌های گروه است.");
            }
            return true;
        }
        $method = 'do_' . $cmd;
        if (method_exists(self::class, $method)) {
            self::$method($ctx);
            return true;
        }
        return false;
    }

    // ---------------- ابزار ----------------
    private static function say(array $ctx, string $text, ?array $markup = null, bool $reply = true): void
    {
        BaleApi::sendMessage($ctx['chat_id'], $text, $reply ? $ctx['msg_id'] : null, $markup, 'HTML');
    }

    private static function settingLine(bool $on): string
    {
        return $on ? '✅ روشن' : '❌ خاموش';
    }

    /** @return array{0:?int,1:string,2:string} [targetId, tail, error] */
    private static function target(array $ctx): array
    {
        $rest  = Text::normalize((string)$ctx['rest']);
        $parts = $rest === '' ? [] : explode(' ', $rest, 2);
        $who   = $parts[0] ?? '';
        $tail  = $parts[1] ?? '';

        $rep = $ctx['message']['reply_to_message'] ?? null;
        if (!$rep && isset($ctx['message']['reply_to']) && is_array($ctx['message']['reply_to'])) {
            $rep = $ctx['message']['reply_to'];
        }
        if ($rep && !empty($rep['from']['id'])) {
            // اگر کاربر هدف را صریح (آیدی/یوزرنیم) نوشته، همان اولویت دارد
            if ($who !== '' && preg_match('~^(\d{5,}|@?[a-zA-Z0-9_]{4,})$~', $who)) {
                $explicit = preg_match('~^\d{5,}$~', $who)
                    ? (int)$who
                    : (int)DB::val("SELECT user_id FROM users WHERE username=? ORDER BY last_seen_at DESC LIMIT 1", [ltrim($who, '@')], 0);
                if ($explicit > 0) {
                    return [$explicit, $tail, ''];
                }
            }
            // وگرنه کل متن بعد از دستور، «دلیل» است (نه آیدی)
            return [(int)$rep['from']['id'], trim($rest), ''];
        }
        // اگر «who» خودش عدد/یوزرنیم نیست، احتمالاً دلیل است و کاربر ریپلای نکرده
        if ($who === '') {
            return [null, $tail, "روی پیام کاربر ریپلای کن، یا آیدی عددی/@یوزرنیم بنویس."];
        }
        if (preg_match('~^\d{5,}$~', $who)) {
            return [(int)$who, $tail, ''];
        }
        if (preg_match('~^@?([a-zA-Z0-9_]{4,})$~', $who, $m)) {
            $u = DB::one("SELECT user_id FROM users WHERE username=? ORDER BY last_seen_at DESC LIMIT 1", [$m[1]]);
            if ($u) {
                return [(int)$u['user_id'], $tail, ''];
            }
            return [null, $tail, "کاربری با یوزرنیم @{$m[1]} در دیتابیس پیدا نشد.\nبهترین راه: روی پیام خودش ریپلای کن، یا آیدی عددی بده."];
        }
        return [null, trim($rest), "کاربر هدف مشخص نیست. ریپلای کن یا آیدی عددی بده."];
    }

    private static function canActOn(array $ctx, int $targetId): ?string
    {
        if ($targetId === (int)($ctx['user_id'] ?? 0)) {
            return "روی خودت که نمیشه 🙂";
        }
        $botId = (int)(Schema::getSetting('bot_id', '0') ?: 0);
        if ($botId && $targetId === $botId) {
            return "من؟! 😅";
        }
        if (Moderation::isGroupAdmin((int)$ctx['chat_id'], $targetId, true)) {
            return "این کاربر ادمین است و نمی‌تونم اقدامی روش انجام بدم.";
        }
        return null;
    }

    private static function nameOf(int $userId): string
    {
        $u = DB::one("SELECT first_name,last_name,username FROM users WHERE user_id=?", [$userId]);
        if (!$u) {
            return "<code>{$userId}</code>";
        }
        $n = trim((string)$u['first_name'] . ' ' . (string)$u['last_name']);
        $n = $n !== '' ? $n : (string)($u['username'] ?? $userId);
        return '<b>' . h($n) . '</b>' . (!empty($u['username']) ? ' (@' . h($u['username']) . ')' : '') . " <code>{$userId}</code>";
    }

    // ---------------- عمومی ----------------
    private static function do_invite(array $ctx): void
    {
        if (!Referral::isEnabled()) {
            self::say($ctx, "ℹ️ سامانه دعوت غیرفعال است.");
            return;
        }
        $st = Referral::stats((int)$ctx['user_id']);
        self::say($ctx, "🔗 <b>لینک دعوت شما</b>\n<code>" . h($st['link']) . "</code>\n\n"
            . "👥 تاییدشده: " . digits_fa($st['verified']) . " | ⏳ در انتظار: " . digits_fa($st['pending'])
            . "\n🌱 سطح ۲: " . digits_fa($st['level2']));
    }

    private static function do_referrals_me(array $ctx): void
    {
        $rows = Referral::children((int)$ctx['user_id'], 15);
        if (!$rows) {
            self::say($ctx, "هنوز زیرمجموعه‌ای نداری.\nبفرست: <code>دعوت</code> تا لینکت را بگیری.");
            return;
        }
        $lines = ["👥 <b>زیرمجموعه‌های شما</b>"];
        foreach ($rows as $r) {
            $name = trim(((string)($r['first_name'] ?? '')) . ' ' . ((string)($r['last_name'] ?? '')));
            $name = $name !== '' ? $name : (string)($r['invitee_id'] ?? '');
            $st   = $r['status'] === 'verified' ? '✅' : ($r['status'] === 'pending' ? '⏳' : '✖️');
            $lines[] = "{$st} " . h($name) . " — " . dt((int)$r['created_at'], 'Y/m/d');
        }
        self::say($ctx, implode("\n", $lines));
    }

    private static function do_top_inviters(array $ctx): void
    {
        $rows = Referral::top(10, 0, (int)$ctx['chat_id']);
        if (!$rows) {
            self::say($ctx, "هنوز دعوتی تایید نشده است.");
            return;
        }
        $lines = ["🏆 <b>بهترین دعوت‌کننده‌ها</b>"];
        $i = 1;
        foreach ($rows as $r) {
            $name = trim(((string)($r['first_name'] ?? '')) . ' ' . ((string)($r['last_name'] ?? '')));
            $lines[] = digits_fa($i++) . ". " . h($name !== '' ? $name : (string)$r['inviter_id'])
                . " — " . digits_fa((int)$r['c']) . " دعوت";
        }
        self::say($ctx, implode("\n", $lines));
    }

    private static function do_products(array $ctx): void
    {
        $rows = Store::active(10);
        if (!$rows) {
            self::say($ctx, "🛒 در حال حاضر کالای موجودی ثبت نشده است.");
            return;
        }
        $lines = ["🛒 <b>کالاهای موجود</b>"];
        foreach ($rows as $p) {
            $lines[] = "• <b>" . h((string)$p['title']) . "</b> — " . money(Store::price($p))
                . " (موجودی " . digits_fa(Store::available($p)) . ")"
                . "\n  کد خرید: <code>" . h((string)$p['code']) . "</code>";
        }
        $lines[] = "\nبرای خرید بنویس: <code>خرید CODE</code>\nیا وارد فروشگاه شو: " . url('shop/');
        self::say($ctx, implode("\n", $lines));
    }

    private static function do_shop(array $ctx): void
    {
        $uid = (int)$ctx['user_id'];
        $r   = ShopAuth::sendLoginLink($uid);
        if (!$r['ok']) {
            self::say($ctx, "❌ " . h((string)($r['error'] ?? 'خطا')));
            return;
        }
        self::say($ctx, "🛍 <b>ورود به فروشگاه</b>\nلینک یک‌بارمصرف (۱۰ دقیقه) در همین چت ارسال شد؟ "
            . "اگر پیام ورود را ندیدی، دوباره <code>فروشگاه</code> بفرست.\n\n"
            . "در فروشگاه می‌توانی خرید کنی، سفارش‌ها را ببینی و (با تایید ادمین) کالا اضافه کنی.");
    }

    private static function do_buy(array $ctx): void
    {
        $code = trim((string)$ctx['rest']);
        $p    = $code !== '' ? Store::byCode($code) : null;
        if (!$p || $p['status'] !== 'active') {
            self::say($ctx, "کد کالا را درست بفرست: <code>خرید AB12CD34</code>\nلیست کالاها: <code>کالاها</code>");
            return;
        }
        $avail = Store::available($p);
        if ($avail <= 0) {
            self::say($ctx, "❌ این کالا فعلاً ناموجود است.");
            return;
        }
        $res = Orders::create([
            'user_id'    => (int)$ctx['user_id'],
            'product_id' => (int)$p['id'],
            'qty'        => 1,
            'buyer_name' => Moderation::fullName($ctx['from'] ?? []),
            'notify'     => true,
        ]);
        if (!$res['ok']) {
            self::say($ctx, "❌ " . h((string)$res['error']));
            return;
        }
        $card = (string)(Schema::getSetting('store_card_number', '') ?: '');
        self::say($ctx, "✅ سفارش <code>" . h((string)$res['code']) . "</code> ثبت شد.\n"
            . "کالا: " . h((string)$p['title']) . "\n"
            . "مبلغ: " . money((int)$res['total']) . "\n"
            . ($card !== '' ? "\n💳 کارت: <code>" . h($card) . "</code>\n" : '')
            . "\nرسید را از اینجا آپلود کن:\n" . url('shop/index.php', ['p' => 'order', 'code' => (string)$res['code']]));
    }

    private static function do_my_orders(array $ctx): void
    {
        $rows = Orders::forUser((int)$ctx['user_id'], 8);
        if (!$rows) {
            self::say($ctx, "سفارشی نداری. لیست کالاها: <code>کالاها</code>");
            return;
        }
        $lines = ["🧾 <b>سفارش‌های شما</b>"];
        foreach ($rows as $o) {
            $lines[] = "• <code>" . h((string)$o['code']) . "</code> — " . h(mb_strimwidth((string)($o['item_title'] ?? ''), 0, 30, '…'))
                . "\n  " . h(Orders::STATUSES[$o['status']] ?? (string)$o['status'])
                . " · " . h(Orders::PAYMENTS[$o['payment_status']] ?? (string)$o['payment_status'])
                . " · " . money((int)$o['total']);
        }
        $lines[] = "\nجزئیات و آپلود رسید: " . url('shop/index.php', ['p' => 'orders']);
        self::say($ctx, implode("\n", $lines));
    }

    private static function do_wallet(array $ctx): void
    {
        $uid = (int)$ctx['user_id'];
        $bal = Wallet::balance($uid);
        $aff = Referral::stats($uid);
        self::say($ctx, "💰 <b>کیف پول شما</b>\n"
            . "موجودی: " . money($bal) . "\n"
            . "دعوت تاییدشده: " . digits_fa((int)$aff['verified']) . " (سطح ۲: " . digits_fa((int)$aff['level2']) . ")\n"
            . "نرخ کمیسیون: " . digits_fa((float)Orders::commissionRate()) . "٪ از هر سفارش، بعد از تحویل\n\n"
            . "لینک دعوت: <code>" . h((string)$aff['link']) . "</code>\n"
            . "برداشت و گردش حساب: " . url('shop/index.php', ['p' => 'wallet']));
    }

    private static function do_sell(array $ctx): void
    {
        $uid = (int)$ctx['user_id'];
        $s   = Seller::forUser($uid);
        if ($s && $s['status'] === 'approved') {
            self::say($ctx, "🏪 فروشگاه شما فعال است: <b>" . h((string)$s['shop_name']) . "</b>\n"
                . "افزودن کالا: " . url('shop/index.php', ['p' => 'product']));
            return;
        }
        if ($s && $s['status'] === 'pending') {
            self::say($ctx, "⏳ درخواست فروشندگی شما در انتظار تایید ادمین است.");
            return;
        }
        self::say($ctx, "🏪 <b>فروشنده شو</b>\nبا تایید ادمین می‌توانی کالا به انبار اضافه کنی و در گروه‌ها تبلیغ کنی.\n"
            . "درخواست: " . url('shop/index.php', ['p' => 'seller']));
    }

    private static function do_chart(array $ctx): void
    {
        $days = (int)Text::normalize((string)$ctx['rest']);
        $days = $days > 0 ? min(60, max(3, $days)) : 7;
        $s    = Metrics::summary($days);
        $max  = max(1, $s['messages'], $s['violations']);
        $blk  = static function (int $v, int $max): string {
            $n = (int)round(($v / max(1, $max)) * 10);
            return str_repeat('▇', $n) . str_repeat('▁', max(0, 10 - $n));
        };
        self::say($ctx, "📊 <b>نمودار {$days} روز اخیر</b>\n"
            . "پیام‌ها: " . digits_fa($s['messages']) . "\n<code>" . $blk($s['messages'], $max) . "</code>\n"
            . "تخلف/اقدام: " . digits_fa($s['violations']) . "\n<code>" . $blk($s['violations'], $max) . "</code>\n"
            . "ورود: " . digits_fa($s['joins']) . " | خروج: " . digits_fa($s['lefts']) . "\n"
            . "دستورها: " . digits_fa($s['commands']) . "\n\n"
            . "نمودار کامل: " . url('panel/index.php', ['p' => 'analytics']));
    }

    private static function do_backup(array $ctx): void
    {
        $res = Backup::create('manual', (int)$ctx['user_id'], 'از دستور ربات');
        if (!$res['ok']) {
            self::say($ctx, "❌ بکاپ ناموفق: " . h((string)($res['error'] ?? '')));
            return;
        }
        self::say($ctx, "🗄 نسخه پشتیبان ساخته شد.\nفایل: <code>" . h((string)$res['file']) . "</code>\n"
            . "حجم: " . Backup::humanSize((int)$res['size']) . "\n"
            . "جدول: " . digits_fa((int)$res['tables']) . " | ردیف: " . digits_fa((int)$res['rows']));
    }

    private static function do_help(array $ctx): void
    {
        $t = "📋 <b>دستورات ربات مدیر گروه</b>\n\n"
            . "<b>عمومی</b>\n"
            . "راهنما — همین پیام\n"
            . "قوانین — نمایش قوانین گروه\n"
            . "گزارش [دلیل] (ریپلای) — گزارش تخلف به ادمین‌ها\n"
            . "من — آیدی عددی خودت\n"
            . "ادمینها — لیست ادمین‌های گروه\n"
            . "آمار — آمار گروه\n\n"
            . "<b>مدیریت اعضا</b> (ریپلای یا آیدی)\n"
            . "بن [دلیل] | حذف_بن\n"
            . "کیک [زمان] — پیش‌فرض ۶۰ ثانیه\n"
            . "سکوت [زمان مثل 10m/2h/1d] | حذف_سکوت\n"
            . "هشدار [دلیل] | حذف_هشدار | اخطارها\n"
            . "پاکسازی [تعداد] — حذف پیام‌های اخیر (ریپلای)\n"
            . "اطلاعات — سابقه کاربر\n"
            . "لیست_بن | لیست_سکوت | لیست_گزارش\n"
            . "پین | حذف_پین (ریپلای)\n\n"
            . "<b>قفل‌ها</b>\n"
            . "قفل/باز_کردن + یکی از: لینک، عکس، ویدیو، گیف، استیکر، ویس، فایل، فوروارد، مخاطب، نظرسنجی، موقعیت\n"
            . "لیست_قفل | قفل_گروه | باز_کردن_گروه\n\n"
            . "<b>فیلترها</b>\n"
            . "ضد_لینک | ضد_اسپم | ضد_فحش | ضد_بات | ضد_فوروارد | ضد_یوزرنیم | کپچا\n"
            . "همه با «روشن» / «خاموش» — بدون آرگومان، وضعیت را نشان می‌دهد\n\n"
            . "<b>خوش‌آمدگویی</b>\n"
            . "خوش_آمدگویی روشن/خاموش\n"
            . "تنظیم_خوش_آمدگویی متن با {name} و {group}\n"
            . "دکمه متن | لینک — افزودن دکمه شیشه‌ای\n"
            . "خداحافظی | تنظیم_خداحافظی\n\n"
            . "<b>تنظیمات</b>\n"
            . "تنظیم_قوانین متن\n"
            . "تنظیم_اسپم [ثانیه] [تعداد]\n"
            . "تنظیم_اخطار [تعداد] [mute|kick|ban]\n"
            . "تنظیم_زمان_سکوت [زمان]\n"
            . "تنظیم_لاگ [آیدی کانال] — ارسال گزارش‌ها به کانال\n"
            . "تنظیمات — خلاصه همه تنظیمات\n"
            . "ریست — بازگشت به پیش‌فرض\n\n"
            . "<b>فروشگاه</b>\n"
            . "کالاها — لیست کالاهای موجود\n"
            . "خرید CODE — ثبت سفارش سریع\n"
            . "سفارشها — وضعیت سفارش‌های من\n"
            . "فروشگاه — لینک ورود به پنل فروشگاه\n"
            . "کیف_پول — موجودی و کمیسیون\n"
            . "فروشنده — درخواست فروشندگی\n\n"
            . "<b>دعوت و آمار</b>\n"
            . "دعوت — لینک دعوت اختصاصی\n"
            . "زیرمجموعه‌ها — لیست دعوت‌شده‌ها\n"
            . "تاپ_دعوت — بهترین دعوت‌کننده‌ها\n"
            . "نمودار [روز] — خلاصه آماری (ادمین)\n"
            . "بکاپ — گرفتن نسخه پشتیبان (ادمین)\n\n"
            . "🔐 پنل مدیریت: " . url('panel/');
        self::say($ctx, $t);
    }

    private static function do_rules_view(array $ctx): void
    {
        $rules = trim((string)Settings::get((int)$ctx['chat_id'])['rules']);
        self::say($ctx, $rules !== '' ? "📜 <b>قوانین گروه</b>\n\n" . h($rules) : "📜 هنوز قانونی ثبت نشده.\nادمین با دستور «تنظیم_قوانین متن...» اضافه کند.");
    }

    private static function do_me(array $ctx): void
    {
        $u = $ctx['from'];
        self::say($ctx, "👤 نام: " . h(Moderation::fullName($u)) . "\n🆔 آیدی عددی: <code>" . (int)$ctx['user_id'] . "</code>\n"
            . (!empty($u['username']) ? "🔗 یوزرنیم: @" . h((string)$u['username']) . "\n" : '')
            . "👑 ادمین گروه: " . ($ctx['is_admin'] ? 'بله' : 'خیر'));
    }

    private static function do_chat_id(array $ctx): void
    {
        self::say($ctx, "🆔 آیدی این گروه: <code>" . (int)$ctx['chat_id'] . "</code>");
    }

    private static function do_report(array $ctx): void
    {
        [$targetId, $tail, $err] = self::target($ctx);
        if (!$targetId) {
            self::say($ctx, "برای گزارش، روی پیام متخلف ریپلای کن و بنویس: گزارش [دلیل]");
            return;
        }
        $reason = trim($tail) !== '' ? trim($tail) : trim((string)$ctx['rest']);
        if (preg_match('~^@?[a-zA-Z0-9_]{4,}$~', $reason) || preg_match('~^\d{5,}$~', $reason)) {
            $reason = '';
        }
        // جلوگیری از گزارش تکراری/بمباران
        if (RateLimit::hit("report:{$ctx['chat_id']}:{$ctx['user_id']}", 3, 600)) {
            self::say($ctx, "⏳ تعداد گزارش‌های شما زیاد است؛ چند دقیقه بعد امتحان کن.");
            return;
        }
        $id = Moderation::createReport((int)$ctx['chat_id'], $targetId, (int)$ctx['user_id'],
            (int)($ctx['message']['reply_to_message']['message_id'] ?? 0), $reason);
        self::say($ctx, "✅ گزارش #{$id} ثبت شد. ادمین‌ها بررسی می‌کنند.");
        $admins = BaleApi::getChatAdministrators((int)$ctx['chat_id']);
        if ($admins) {
            $txt = "🚨 گزارش #{$id}\nهدف: " . self::nameOf($targetId) . "\nدلیل: " . h($reason ?: '-')
                . "\nگزارش‌دهنده: " . self::nameOf((int)$ctx['user_id']);
            $markup = ['inline_keyboard' => [[
                ['text' => '🔇 سکوت ۱ ساعت', 'callback_data' => "ra:mute:{$ctx['chat_id']}:{$targetId}:{$id}"],
                ['text' => '⛔ بن', 'callback_data' => "ra:ban:{$ctx['chat_id']}:{$targetId}:{$id}"],
            ]]];
            foreach ($admins as $a) {
                $auid = (int)($a['user']['id'] ?? 0);
                if ($auid && !empty($a['user']['id'])) {
                    BaleApi::sendMessage($auid, $txt, null, $markup);
                }
            }
        }
    }

    private static function do_admins(array $ctx): void
    {
        $admins = BaleApi::getChatAdministrators((int)$ctx['chat_id']);
        if (!$admins) {
            self::say($ctx, "❌ لیست ادمین‌ها از API دریافت نشد (ممکن است ربات ادمین نباشد).");
            return;
        }
        $lines = ["👑 <b>ادمین‌های گروه</b>"];
        foreach ($admins as $a) {
            $u = (array)($a['user'] ?? []);
            $role = ((string)($a['status'] ?? '')) === 'creator' ? 'مالک' : 'ادمین';
            $lines[] = "• " . h(Moderation::fullName($u)) . (!empty($u['username']) ? ' (@' . h((string)$u['username']) . ')' : '') . " — {$role}";
        }
        self::say($ctx, implode("\n", $lines));
    }

    private static function do_stats(array $ctx): void
    {
        $chatId = (int)$ctx['chat_id'];
        $s      = Settings::get($chatId);
        $warns  = (int)DB::val("SELECT COALESCE(SUM(count),0) FROM warns WHERE chat_id=?", [$chatId], 0);
        $bans   = (int)DB::val("SELECT COUNT(*) FROM bans WHERE chat_id=?", [$chatId], 0);
        $mutes  = (int)DB::val("SELECT COUNT(*) FROM mutes WHERE chat_id=? AND until_ts>?", [$chatId, time()], 0);
        $reps   = (int)DB::val("SELECT COUNT(*) FROM reports WHERE chat_id=? AND status='open'", [$chatId], 0);
        $msgs   = (int)DB::val("SELECT COUNT(*) FROM logs WHERE chat_id=? AND ts>?", [$chatId, time() - 86400], 0);
        $members = BaleApi::getChatMemberCount($chatId);
        $filters = array_filter([
            'ضدلینک' => $s['anti_link'], 'ضداسپم' => $s['anti_spam'], 'ضدفحش' => $s['bad_words'],
            'ضدبات' => $s['anti_bot'], 'ضدفوروارد' => $s['anti_forward'], 'کپچا' => $s['captcha_on'],
        ]);
        $act = [];
        foreach ($filters as $k => $v) {
            $act[] = $k . ':' . ((int)$v ? '✅' : '❌');
        }
        self::say($ctx, "📊 <b>آمار گروه</b>\n"
            . "👥 اعضا: " . ($members !== null ? digits_fa($members) : '—') . "\n"
            . "📝 رخدادهای ۲۴ ساعت: " . digits_fa($msgs) . "\n"
            . "⚠️ مجموع اخطارها: " . digits_fa($warns) . "\n"
            . "🔇 در سکوت: " . digits_fa($mutes) . "\n"
            . "⛔ بن‌شده‌ها: " . digits_fa($bans) . "\n"
            . "🚨 گزارش‌های باز: " . digits_fa($reps) . "\n"
            . "🧰 فیلترهای فعال: " . implode(' | ', $act) . "\n"
            . "🆔 آیدی گروه: <code>{$chatId}</code>");
    }

    // ---------------- اقدامات ----------------
    private static function do_ban(array $ctx): void
    {
        [$tid, $tail, $err] = self::target($ctx);
        if (!$tid) {
            self::say($ctx, "⛔ " . $err);
            return;
        }
        if ($w = self::canActOn($ctx, $tid)) {
            self::say($ctx, $w);
            return;
        }
        $dur    = parse_duration($tail);
        $reason = $dur > 0 ? trim(preg_replace('~^\S+~', '', $tail)) : trim($tail);
        if ($dur > 0) {
            Moderation::ban((int)$ctx['chat_id'], $tid, $dur, $reason, (int)$ctx['user_id']);
            self::say($ctx, "⛔ کاربر " . self::nameOf($tid) . " برای " . duration_human($dur) . " بن شد." . ($reason ? "\nدلیل: " . h($reason) : ''));
        } else {
            Moderation::ban((int)$ctx['chat_id'], $tid, 0, $reason, (int)$ctx['user_id']);
            self::say($ctx, "⛔ کاربر " . self::nameOf($tid) . " بن شد." . ($reason ? "\nدلیل: " . h($reason) : ''));
        }
    }

    private static function do_unban(array $ctx): void
    {
        [$tid, , $err] = self::target($ctx);
        if (!$tid) {
            self::say($ctx, "⛔ " . $err);
            return;
        }
        Moderation::unban((int)$ctx['chat_id'], $tid, (int)$ctx['user_id']);
        self::say($ctx, "✅ بن/سکوت کاربر " . self::nameOf($tid) . " برداشته شد.");
    }

    private static function do_kick(array $ctx): void
    {
        [$tid, $tail, $err] = self::target($ctx);
        if (!$tid) {
            self::say($ctx, "⛔ " . $err);
            return;
        }
        if ($w = self::canActOn($ctx, $tid)) {
            self::say($ctx, $w);
            return;
        }
        $dur = parse_duration($tail) ?: 60;
        Moderation::kick((int)$ctx['chat_id'], $tid, $dur, (int)$ctx['user_id']);
        self::say($ctx, "👢 کاربر " . self::nameOf($tid) . " اخراج شد (امکان بازگشت بعد از " . duration_human($dur) . ").");
    }

    private static function do_mute(array $ctx): void
    {
        [$tid, $tail, $err] = self::target($ctx);
        if (!$tid) {
            self::say($ctx, "🔇 " . $err);
            return;
        }
        if ($w = self::canActOn($ctx, $tid)) {
            self::say($ctx, $w);
            return;
        }
        $dur    = parse_duration($tail);
        $reason = $dur > 0 ? trim(preg_replace('~^\S+~', '', $tail)) : trim($tail);
        if ($dur === 0) {
            $dur = 600; // پیش‌فرض ۱۰ دقیقه (در نسخه قبلی هم بود اما متن پیام گمراه‌کننده بود)
        }
        Moderation::mute((int)$ctx['chat_id'], $tid, $dur, $reason, (int)$ctx['user_id']);
        self::say($ctx, "🔇 کاربر " . self::nameOf($tid) . " برای " . duration_human($dur) . " سکوت شد." . ($reason ? "\nدلیل: " . h($reason) : ''));
    }

    private static function do_unmute(array $ctx): void
    {
        [$tid, , $err] = self::target($ctx);
        if (!$tid) {
            self::say($ctx, "🔊 " . $err);
            return;
        }
        Moderation::unmute((int)$ctx['chat_id'], $tid, (int)$ctx['user_id']);
        self::say($ctx, "🔊 سکوت " . self::nameOf($tid) . " برداشته شد.");
    }

    private static function do_warn(array $ctx): void
    {
        [$tid, $tail, $err] = self::target($ctx);
        if (!$tid) {
            self::say($ctx, "⚠️ " . $err);
            return;
        }
        if ($w = self::canActOn($ctx, $tid)) {
            self::say($ctx, $w);
            return;
        }
        $reason = trim($tail) ?: 'تخلف';
        $count  = Moderation::warn((int)$ctx['chat_id'], $tid, $reason, (int)$ctx['user_id'],
            (int)($ctx['message']['reply_to_message']['message_id'] ?? 0));
        $s = Settings::get((int)$ctx['chat_id']);
        if ($count > 0) {
            self::say($ctx, "⚠️ اخطار " . digits_fa($count) . "/" . digits_fa((int)$s['warn_limit']) . " برای " . self::nameOf($tid) . "\nدلیل: " . h($reason));
        }
    }

    private static function do_unwarn(array $ctx): void
    {
        [$tid, $tail] = self::target($ctx);
        if (!$tid) {
            self::say($ctx, "کاربر هدف مشخص نیست.");
            return;
        }
        $n = max(1, (int)($tail ?: 1));
        DB::q("UPDATE warns SET count=GREATEST(count-?,0), updated_at=? WHERE chat_id=? AND user_id=?",
            [$n, time(), (int)$ctx['chat_id'], $tid]);
        $cur = (int)DB::val("SELECT count FROM warns WHERE chat_id=? AND user_id=?", [(int)$ctx['chat_id'], $tid], 0);
        self::say($ctx, "✅ " . digits_fa($n) . " اخطار کم شد. اخطار فعلی: " . digits_fa($cur));
    }

    private static function do_warns_list(array $ctx): void
    {
        $rows = DB::all("SELECT user_id,count,updated_at FROM warns WHERE chat_id=? AND count>0 ORDER BY count DESC LIMIT 30", [(int)$ctx['chat_id']]);
        if (!$rows) {
            self::say($ctx, "✅ هیچ کاربری اخطار فعال ندارد.");
            return;
        }
        $lines = ["⚠️ <b>اخطارهای فعال</b>"];
        foreach ($rows as $r) {
            $lines[] = "• " . self::nameOf((int)$r['user_id']) . " — " . digits_fa((int)$r['count']) . " اخطار | " . dt((int)$r['updated_at'], 'Y/m/d');
        }
        self::say($ctx, implode("\n", $lines));
    }

    private static function do_purge(array $ctx): void
    {
        $rep = $ctx['message']['reply_to_message'] ?? null;
        if (!$rep) {
            self::say($ctx, "روی یکی از پیام‌های موردنظر ریپلای کن و بنویس: پاکسازی ۱۰");
            return;
        }
        $n = (int)trim((string)$ctx['rest']);
        $n = $n > 0 ? min($n, 50) : 1;
        $deleted = Moderation::purge((int)$ctx['chat_id'], (int)$rep['message_id'], $n);
        self::say($ctx, "🧹 " . digits_fa($deleted) . " پیام حذف شد.");
    }

    private static function do_info(array $ctx): void
    {
        [$tid, , $err] = self::target($ctx);
        if (!$tid) {
            self::say($ctx, "ℹ️ " . $err);
            return;
        }
        $chatId = (int)$ctx['chat_id'];
        $u      = DB::one("SELECT * FROM users WHERE user_id=?", [$tid]);
        $warns  = (int)DB::val("SELECT count FROM warns WHERE chat_id=? AND user_id=?", [$chatId, $tid], 0);
        $mute   = DB::one("SELECT until_ts,reason FROM mutes WHERE chat_id=? AND user_id=?", [$chatId, $tid]);
        $ban    = DB::one("SELECT until_ts,reason,ts FROM bans WHERE chat_id=? AND user_id=?", [$chatId, $tid]);
        $reps   = (int)DB::val("SELECT COUNT(*) FROM reports WHERE chat_id=? AND target_user_id=?", [$chatId, $tid], 0);
        $member = BaleApi::getChatMember($chatId, $tid);
        $t = "ℹ️ <b>اطلاعات کاربر</b>\n" . self::nameOf($tid) . "\n";
        if ($u) {
            $t .= "📝 پیام‌ها (کل): " . digits_fa((int)$u['messages_count']) . "\n";
            $t .= "🚩 تخلف‌ها: " . digits_fa((int)$u['violations_count']) . "\n";
            $t .= "📅 اولین‌بار: " . dt((int)$u['first_seen_at'], 'Y/m/d') . "\n";
            $t .= "👀 آخرین‌بار: " . dt((int)$u['last_seen_at'], 'Y/m/d H:i') . "\n";
        }
        $t .= "⚠️ اخطار فعال: " . digits_fa($warns) . "\n";
        $t .= $mute && (int)$mute['until_ts'] > time()
            ? "🔇 سکوت تا: " . dt((int)$mute['until_ts']) . "\n"
            : "";
        $t .= $ban
            ? "⛔ بن: " . ((int)$ban['until_ts'] ? 'تا ' . dt((int)$ban['until_ts']) : 'دائمی') . "\n"
            : "";
        $t .= "🚨 گزارش‌های ثبت‌شده: " . digits_fa($reps) . "\n";
        $t .= "👑 نقش در گروه: " . h((string)($member['status'] ?? 'نامشخص'));
        self::say($ctx, $t);
    }

    private static function do_bans_list(array $ctx): void
    {
        $rows = DB::all("SELECT * FROM bans WHERE chat_id=? ORDER BY ts DESC LIMIT 30", [(int)$ctx['chat_id']]);
        if (!$rows) {
            self::say($ctx, "لیست بن خالی است.");
            return;
        }
        $lines = ["⛔ <b>لیست بن</b>"];
        foreach ($rows as $r) {
            $until = (int)$r['until_ts'] ? dt((int)$r['until_ts']) : 'دائمی';
            $lines[] = "• " . self::nameOf((int)$r['user_id']) . " | تا: {$until}" . ((string)$r['reason'] !== '' ? ' | ' . h((string)$r['reason']) : '');
        }
        self::say($ctx, implode("\n", $lines));
    }

    private static function do_mutes_list(array $ctx): void
    {
        $rows = DB::all("SELECT * FROM mutes WHERE chat_id=? AND until_ts>? ORDER BY until_ts DESC LIMIT 30", [(int)$ctx['chat_id'], time()]);
        if (!$rows) {
            self::say($ctx, "لیست سکوت خالی است.");
            return;
        }
        $lines = ["🔇 <b>لیست سکوت</b>"];
        foreach ($rows as $r) {
            $lines[] = "• " . self::nameOf((int)$r['user_id']) . " | باقی‌مانده: " . duration_human((int)$r['until_ts'] - time());
        }
        self::say($ctx, implode("\n", $lines));
    }

    private static function do_reports_list(array $ctx): void
    {
        $rows = DB::all("SELECT * FROM reports WHERE chat_id=? ORDER BY ts DESC LIMIT 20", [(int)$ctx['chat_id']]);
        if (!$rows) {
            self::say($ctx, "هیچ گزارشی ثبت نشده.");
            return;
        }
        $lines = ["🚨 <b>آخرین گزارش‌ها</b>"];
        foreach ($rows as $r) {
            $lines[] = "#" . (int)$r['id'] . " | " . self::nameOf((int)$r['target_user_id']) . " | " . h((string)$r['reason']) . " | " . dt((int)$r['ts'], 'Y/m/d H:i') . " | " . h((string)$r['status']);
            DB::q("UPDATE reports SET status='seen' WHERE id=? AND status='open'", [(int)$r['id']]);
        }
        self::say($ctx, implode("\n", $lines));
    }

    private static function do_pin(array $ctx): void
    {
        $rep = $ctx['message']['reply_to_message'] ?? null;
        if (!$rep) {
            self::say($ctx, "روی پیامی که می‌خواهی سنجاق شود ریپلای کن.");
            return;
        }
        $r = BaleApi::call('pinChatMessage', ['chat_id' => (int)$ctx['chat_id'], 'message_id' => (int)$rep['message_id']], 12);
        self::say($ctx, $r ? "📌 سنجاق شد." : "❌ سنجاق نشد (دسترسی ربات را بررسی کن).");
    }

    private static function do_unpin(array $ctx): void
    {
        $r = BaleApi::call('unpinChatMessage', ['chat_id' => (int)$ctx['chat_id']], 12);
        self::say($ctx, $r ? "📌 سنجاق برداشته شد." : "❌ انجام نشد.");
    }

    // ---------------- قفل‌ها ----------------
    private const LOCK_ALIASES = [
        'لینک' => 'lock_link', 'link' => 'lock_link',
        'عکس' => 'lock_photo', 'تصویر' => 'lock_photo', 'photo' => 'lock_photo',
        'ویدیو' => 'lock_video', 'فیلم' => 'lock_video', 'video' => 'lock_video',
        'گیف' => 'lock_gif', 'gif' => 'lock_gif',
        'استیکر' => 'lock_sticker', 'sticker' => 'lock_sticker',
        'ویس' => 'lock_voice', 'صدا' => 'lock_voice', 'voice' => 'lock_voice',
        'فایل' => 'lock_document', 'سند' => 'lock_document', 'document' => 'lock_document',
        'فوروارد' => 'lock_forward', 'forward' => 'lock_forward',
        'مخاطب' => 'lock_contact', 'contact' => 'lock_contact',
        'نظرسنجی' => 'lock_poll', 'poll' => 'lock_poll',
        'موقعیت' => 'lock_location', 'location' => 'lock_location',
        'بات' => 'lock_bot', 'bot' => 'lock_bot',
    ];

    private const LOCK_LABELS = [
        'lock_link' => 'لینک', 'lock_photo' => 'عکس', 'lock_video' => 'ویدیو', 'lock_gif' => 'گیف',
        'lock_sticker' => 'استیکر', 'lock_voice' => 'ویس', 'lock_document' => 'فایل',
        'lock_forward' => 'فوروارد', 'lock_contact' => 'مخاطب', 'lock_poll' => 'نظرسنجی',
        'lock_location' => 'موقعیت', 'lock_bot' => 'بات',
    ];

    private static function do_lock(array $ctx): void
    {
        self::applyLock($ctx, true);
    }

    private static function do_unlock(array $ctx): void
    {
        self::applyLock($ctx, false);
    }

    private static function applyLock(array $ctx, bool $on): void
    {
        $typ = Text::normalize((string)$ctx['rest']);
        if ($typ === '' || $typ === 'همه') {
            foreach (array_keys(self::LOCK_LABELS) as $f) {
                Settings::set((int)$ctx['chat_id'], $f, $on ? 1 : 0);
            }
            self::say($ctx, $on ? "🔒 همه انواع پیام قفل شد." : "🔓 همه قفل‌ها باز شد.");
            return;
        }
        if (!isset(self::LOCK_ALIASES[$typ])) {
            self::say($ctx, "نوع قفل نامعتبر: <code>" . h($typ) . "</code>\nانواع مجاز: " . implode('، ', array_keys(self::LOCK_ALIASES)));
            return;
        }
        $field = self::LOCK_ALIASES[$typ];
        Settings::set((int)$ctx['chat_id'], $field, $on ? 1 : 0);
        self::say($ctx, ($on ? "🔒 قفل " : "🔓 باز شد: ") . self::LOCK_LABELS[$field]);
    }

    private static function do_locks_list(array $ctx): void
    {
        $s = Settings::get((int)$ctx['chat_id']);
        $lines = ["🔐 <b>وضعیت قفل‌ها</b>"];
        foreach (self::LOCK_LABELS as $f => $label) {
            $lines[] = ($s[$f] ? '🔒' : '🔓') . ' ' . $label;
        }
        self::say($ctx, implode("\n", $lines));
    }

    private static function do_group_lock(array $ctx): void
    {
        $r = BaleApi::call('setChatPermissions', [
            'chat_id'     => (int)$ctx['chat_id'],
            'permissions' => ['can_send_messages' => false, 'can_send_media_messages' => false,
                'can_send_other_messages' => false, 'can_add_web_page_previews' => false],
        ], 15);
        self::say($ctx, $r ? "🔒 گروه قفل شد (فقط ادمین‌ها می‌نویسند)." : "❌ این متد توسط API بله پشتیبانی نشد یا ربات دسترسی ندارد.");
    }

    private static function do_group_unlock(array $ctx): void
    {
        $r = BaleApi::call('setChatPermissions', [
            'chat_id'     => (int)$ctx['chat_id'],
            'permissions' => ['can_send_messages' => true, 'can_send_media_messages' => true,
                'can_send_other_messages' => true, 'can_add_web_page_previews' => true],
        ], 15);
        self::say($ctx, $r ? "🔓 گروه باز شد." : "❌ انجام نشد.");
    }

    // ---------------- سوییچ‌ها ----------------
    private static function toggleCmd(array $ctx, string $field, string $label): void
    {
        $arg = Text::normalize((string)$ctx['rest']);
        $s   = Settings::get((int)$ctx['chat_id']);
        if (in_array($arg, ['روشن', 'on', '1', 'فعال'], true)) {
            Settings::set((int)$ctx['chat_id'], $field, 1);
        } elseif (in_array($arg, ['خاموش', 'off', '0', 'غیرفعال'], true)) {
            Settings::set((int)$ctx['chat_id'], $field, 0);
        }
        $now = (int)Settings::get((int)$ctx['chat_id'])[$field];
        self::say($ctx, "{$label}: " . self::settingLine((bool)$now)
            . (in_array($arg, ['روشن', 'خاموش'], true) ? '' : "\nبرای تغییر بنویس: <code>" . $ctx['cmd_name'] . " روشن</code> یا <code>خاموش</code>"));
    }

    private static function do_antilink(array $ctx): void
    {
        self::toggleCmd($ctx, 'anti_link', '🔗 ضد لینک');
    }

    private static function do_antispam(array $ctx): void
    {
        self::toggleCmd($ctx, 'anti_spam', '🌀 ضد اسپم/فلود');
    }

    private static function do_badwords(array $ctx): void
    {
        self::toggleCmd($ctx, 'bad_words', '🤬 ضد فحش');
    }

    private static function do_antibot(array $ctx): void
    {
        self::toggleCmd($ctx, 'anti_bot', '🤖 ضد بات');
    }

    private static function do_antiforward(array $ctx): void
    {
        self::toggleCmd($ctx, 'anti_forward', '↪️ ضد فوروارد');
    }

    private static function do_antiuser(array $ctx): void
    {
        self::toggleCmd($ctx, 'anti_username', '👤 ضد یوزرنیم');
    }

    private static function do_captcha(array $ctx): void
    {
        self::toggleCmd($ctx, 'captcha_on', '🧩 کپچای ورود');
    }

    // ---------------- خوش‌آمد / خداحافظ ----------------
    private static function do_welcome_toggle(array $ctx): void
    {
        self::toggleCmd($ctx, 'welcome_on', '👋 خوش‌آمدگویی');
    }

    private static function do_welcome_set(array $ctx): void
    {
        $txt = trim((string)$ctx['rest']);
        if ($txt === '') {
            self::say($ctx, "متن را بعد از دستور بنویس. مثال:\n<code>تنظیم_خوش_آمدگویی سلام {name}، به {group} خوش اومدی 👋</code>\n\nمتغیرها: {name} {username} {id} {group} {count} {rules}");
            return;
        }
        Settings::set((int)$ctx['chat_id'], 'welcome_text', $txt);
        self::say($ctx, "✅ متن خوش‌آمدگویی ذخیره شد.\nپیش‌نمایش:\n" . Moderation::renderTemplate($txt, $ctx['from'], $ctx['chat'], Settings::get((int)$ctx['chat_id'])));
    }

    private static function do_welcome_test(array $ctx): void
    {
        Moderation::sendWelcome((int)$ctx['chat_id'], $ctx['chat'], $ctx['from']);
    }

    private static function do_welcome_buttons(array $ctx): void
    {
        $rest = trim((string)$ctx['rest']);
        if ($rest === '' || Text::normalize($rest) === 'لیست') {
            $btns = DB::all("SELECT * FROM welcome_buttons WHERE chat_id=? ORDER BY sort,id", [(int)$ctx['chat_id']]);
            $lines = ["🔘 <b>دکمه‌های خوش‌آمدگویی</b>"];
            if (!$btns) {
                $lines[] = 'هیچ دکمه‌ای ثبت نشده.';
            }
            foreach ($btns as $b) {
                $lines[] = "#" . (int)$b['id'] . " — " . h((string)$b['label']) . " → " . h((string)$b['url']);
            }
            $lines[] = "\nافزودن: <code>دکمه متن | لینک</code>\nحذف: <code>دکمه حذف شناسه</code>";
            self::say($ctx, implode("\n", $lines));
            return;
        }
        if (preg_match('~^حذف\s+(\d+)$~u', $rest, $m)) {
            DB::q("DELETE FROM welcome_buttons WHERE chat_id=? AND id=?", [(int)$ctx['chat_id'], (int)$m[1]]);
            self::say($ctx, "🗑 دکمه حذف شد.");
            return;
        }
        $parts = preg_split('~\s*\|\s*~u', $rest);
        if (count($parts) < 2 || !preg_match('~^https?://~i', trim($parts[1]))) {
            self::say($ctx, "فرمت: <code>دکمه متن | https://link</code>");
            return;
        }
        DB::q("INSERT INTO welcome_buttons (chat_id,label,url,sort) VALUES (?,?,?,?)",
            [(int)$ctx['chat_id'], str_limit(trim($parts[0]), 70), str_limit(trim($parts[1]), 480), 0]);
        self::say($ctx, "✅ دکمه «" . h(trim($parts[0])) . "» اضافه شد.");
    }

    private static function do_goodbye_toggle(array $ctx): void
    {
        self::toggleCmd($ctx, 'goodbye_on', '👋 خداحافظی');
    }

    private static function do_goodbye_set(array $ctx): void
    {
        $txt = trim((string)$ctx['rest']);
        if ($txt === '') {
            self::say($ctx, "متن خداحافظی را بنویس. مثال: <code>تنظیم_خداحافظی خداحافظ {name} 👋</code>");
            return;
        }
        Settings::set((int)$ctx['chat_id'], 'goodbye_text', $txt);
        self::say($ctx, "✅ متن خداحافظی ذخیره شد.");
    }

    // ---------------- تنظیمات ----------------
    private static function do_rules_set(array $ctx): void
    {
        $txt = trim((string)$ctx['rest']);
        if ($txt === '') {
            self::say($ctx, "متن قوانین را بعد از دستور بنویس.");
            return;
        }
        Settings::set((int)$ctx['chat_id'], 'rules', $txt);
        self::say($ctx, "✅ قوانین ذخیره شد.");
    }

    private static function do_spam_tune(array $ctx): void
    {
        $p = preg_split('~\s+~u', Text::normalize((string)$ctx['rest']));
        if (count($p) < 2) {
            $s = Settings::get((int)$ctx['chat_id']);
            self::say($ctx, "⚙️ تنظیم اسپم فعلی: پنجره " . digits_fa((int)$s['spam_window_sec']) . " ثانیه، حداکثر " . digits_fa((int)$s['spam_max_msgs']) . " پیام\nتغییر: <code>تنظیم_اسپم 8 6</code>");
            return;
        }
        $win = max(2, min(120, (int)$p[0]));
        $max = max(2, min(60, (int)$p[1]));
        Settings::set((int)$ctx['chat_id'], 'spam_window_sec', $win);
        Settings::set((int)$ctx['chat_id'], 'spam_max_msgs', $max);
        self::say($ctx, "✅ شد: در هر " . digits_fa($win) . " ثانیه، حداکثر " . digits_fa($max) . " پیام.");
    }

    private static function do_warn_tune(array $ctx): void
    {
        $p = preg_split('~\s+~u', Text::normalize((string)$ctx['rest']));
        $s = Settings::get((int)$ctx['chat_id']);
        if (!$p[0]) {
            self::say($ctx, "⚙️ اخطار: سقف " . digits_fa((int)$s['warn_limit']) . " | اقدام: " . h((string)$s['warn_action'])
                . "\nتغییر: <code>تنظیم_اخطار 3 mute</code> (mute/kick/ban)");
            return;
        }
        $lim = max(1, min(20, (int)$p[0]));
        Settings::set((int)$ctx['chat_id'], 'warn_limit', $lim);
        if (isset($p[1]) && in_array($p[1], ['mute', 'kick', 'ban'], true)) {
            Settings::set((int)$ctx['chat_id'], 'warn_action', $p[1]);
        }
        $s = Settings::get((int)$ctx['chat_id']);
        self::say($ctx, "✅ سقف اخطار: " . digits_fa((int)$s['warn_limit']) . " | اقدام: " . h((string)$s['warn_action']));
    }

    private static function do_mute_time(array $ctx): void
    {
        $sec = parse_duration((string)$ctx['rest']);
        if ($sec <= 0) {
            $s = Settings::get((int)$ctx['chat_id']);
            self::say($ctx, "⏱ زمان سکوت پیش‌فرض/اسپم: " . duration_human((int)$s['warn_mute_sec']) . "\nتغییر: <code>تنظیم_زمان_سکوت 2h</code>");
            return;
        }
        Settings::set((int)$ctx['chat_id'], 'warn_mute_sec', $sec);
        Settings::set((int)$ctx['chat_id'], 'flood_mute_sec', $sec);
        self::say($ctx, "✅ زمان سکوت شد: " . duration_human($sec));
    }

    private static function do_log_set(array $ctx): void
    {
        $arg = Text::normalize((string)$ctx['rest']);
        $chatId = 0;
        if (preg_match('~^-?\d{5,}$~', $arg)) {
            $chatId = (int)$arg;
        } elseif ($arg === '' || $arg === 'همینجا' || $arg === 'همین_گروه') {
            $chatId = (int)$ctx['chat_id'];
        }
        if (!$chatId) {
            self::say($ctx, "آیدی کانال/گروه لاگ را بده (مثل <code>-100123456789</code>) یا بنویس «تنظیم_لاگ همینجا».\nربات باید در آن کانال ادمین باشد.");
            return;
        }
        Settings::set((int)$ctx['chat_id'], 'log_channel_id', $chatId);
        $test = BaleApi::sendMessage($chatId, "✅ کانال لاگ برای گروه «" . h((string)($ctx['chat']['title'] ?? $ctx['chat_id'])) . "» تنظیم شد.");
        self::say($ctx, $test ? "✅ لاگ فعال شد." : "⚠️ ذخیره شد ولی ارسال تست ناموفق بود؛ ربات را در کانال ادمین کن.");
    }

    private static function do_log_off(array $ctx): void
    {
        Settings::set((int)$ctx['chat_id'], 'log_channel_id', 0);
        self::say($ctx, "✅ لاگ غیرفعال شد.");
    }

    private static function do_settings_view(array $ctx): void
    {
        $s   = Settings::get((int)$ctx['chat_id']);
        $yn  = static fn($v) => (int)$v ? '✅' : '❌';
        $locks = [];
        foreach (self::LOCK_LABELS as $f => $l) {
            if ((int)$s[$f]) {
                $locks[] = $l;
            }
        }
        self::say($ctx, "⚙️ <b>تنظیمات گروه</b>\n"
            . "ضدلینک " . $yn($s['anti_link']) . " | ضداسپم " . $yn($s['anti_spam']) . " | ضدفحش " . $yn($s['bad_words']) . "\n"
            . "ضدبات " . $yn($s['anti_bot']) . " | ضدفوروارد " . $yn($s['anti_forward']) . " | ضدیوزرنیم " . $yn($s['anti_username']) . "\n"
            . "کپچا " . $yn($s['captcha_on']) . " | خوش‌آمد " . $yn($s['welcome_on']) . " | خداحافظی " . $yn($s['goodbye_on']) . "\n"
            . "اسپم: " . digits_fa((int)$s['spam_max_msgs']) . " پیام / " . digits_fa((int)$s['spam_window_sec']) . " ثانیه | اقدام: " . h((string)$s['anti_flood_action']) . "\n"
            . "اخطار: سقف " . digits_fa((int)$s['warn_limit']) . " | اقدام: " . h((string)$s['warn_action']) . "\n"
            . "زمان سکوت: " . duration_human((int)$s['warn_mute_sec']) . "\n"
            . "قفل‌ها: " . ($locks ? implode('، ', $locks) : 'هیچ') . "\n"
            . "کانال لاگ: " . ((int)$s['log_channel_id'] ? '<code>' . (int)$s['log_channel_id'] . '</code>' : '—'));
    }

    private static function do_reset(array $ctx): void
    {
        $chatId = (int)$ctx['chat_id'];
        foreach (Settings::DEFAULTS as $f => $v) {
            Settings::set($chatId, $f, $v);
        }
        DB::q("DELETE FROM welcome_buttons WHERE chat_id=?", [$chatId]);
        self::say($ctx, "♻️ تنظیمات گروه به حالت پیش‌فرض برگشت.");
        Logger::audit(null, 'bot:' . $ctx['user_id'], 'settings_reset', (string)$chatId);
    }

    private static function do_broadcast(array $ctx): void
    {
        if (!Moderation::isSuperAdmin((int)$ctx['user_id'])) {
            return;
        }
        $txt = trim((string)$ctx['rest']);
        if ($txt === '') {
            self::say($ctx, "متن پیام همگانی را بنویس.");
            return;
        }
        $n = 0;
        foreach (Settings::allActive() as $g) {
            if (BaleApi::sendMessage((int)$g['chat_id'], "📢 " . $txt)) {
                $n++;
            }
        }
        self::say($ctx, "✅ ارسال شد به {$n} گروه.");
    }
}
