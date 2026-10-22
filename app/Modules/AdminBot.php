<?php
declare(strict_types=1);

namespace Modules;

use BaleApi;
use Cache;
use DB;
use Events;
use Logger;
use Login;
use Module;
use Recovery;
use Schema;
use Store;
use digits_fa;
use dt;
use h;
use money;
use str_limit;

/**
 * ماژول «مدیریت از طریق ربات» (۱.۶)
 *
 * هدف: ادمین پنل بدون باز کردن مرورگر، از داخل بله:
 *   • گزارش‌گیری از سامانه (کاربر، گروه، فروش، سفارش، صف ارسال، امنیت)
 *   • دیدن موجودی انبار و کالاهای کم‌موجود
 *   • ثبت کالای تازه، تغییر موجودی و حذف (بایگانی) کالا
 *   • دریافت هشدار فوری امنیتی/حمله و هشدار کمبود موجودی
 *
 * امنیت (سه لایه):
 *   ۱) اتصال: فقط با «کد اتصال» یک‌بارمصرفی که در پنل ساخته می‌شود
 *   ۲) نشست: هر فرمان حساس فقط با کد OTP شش‌رقمی بله باز می‌شود (اعتبار کوتاه)
 *   ۳) فقط چت خصوصی؛ فرمان‌ها در گروه اجرا نمی‌شوند
 */
final class AdminBot extends Module
{
    public static function key(): string
    {
        return 'adminbot';
    }

    public static function title(): string
    {
        return 'مدیریت با ربات';
    }

    public static function description(): string
    {
        return 'گزارش سیستم، انبار و ثبت/حذف کالا از داخل بله با کد یک‌بارمصرف + هشدار فوری امنیتی و کمبود موجودی';
    }

    public static function icon(): string
    {
        return '🤖';
    }

    public static function badge(): string
    {
        $links  = (int)DB::val("SELECT COUNT(*) FROM bot_links", [], 0);
        $alerts = (int)DB::val("SELECT COUNT(*) FROM event_log WHERE severity IN ('warn','critical') AND created_at>?", [time() - 86400], 0);
        $low    = count(self::lowStockList(99));
        return digits_fa($links) . ' اتصال · ' . digits_fa($alerts) . ' هشدار ۲۴ساعته · '
            . digits_fa($low) . ' کم‌موجود';
    }

    public static function commands(): array
    {
        return [
            'اتصال'          => 'cmdPair',
            'تایید'          => 'cmdOtp',
            'کد_ورود'        => 'cmdOtp',
            'گزارش'          => 'cmdReport',
            'امنیت'          => 'cmdSecurity',
            'انبار'          => 'cmdInventory',
            'موجودی'         => 'cmdStock',
            'افزودن_کالا'    => 'cmdAddProduct',
            'ویرایش_موجودی'  => 'cmdSetStock',
            'حذف_کالا'       => 'cmdDeleteProduct',
            'قفل'            => 'cmdLock',
            'ورود_پنل'       => 'cmdPanelLink',
        ];
    }

    // ================= اتصال (pairing) =================

    /** کد اتصال یک‌بارمصرف برای ادمین (از پنل ساخته می‌شود) */
    public static function pairCode(int $adminId, int $ttl = 600): array
    {
        $admin = DB::one("SELECT * FROM panel_admins WHERE id=? AND is_active=1", [$adminId]);
        if (!$admin) {
            return ['ok' => false, 'error' => 'ادمین فعال پیدا نشد.'];
        }
        DB::q("UPDATE bot_pairs SET used_at=? WHERE admin_id=? AND used_at IS NULL", [time(), $adminId]);
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        DB::q("INSERT INTO bot_pairs (code,admin_id,expires_at,created_at) VALUES (?,?,?,?)",
            [$code, $adminId, time() + max(60, $ttl), time()]);
        Logger::audit($adminId, (string)$admin['username'], 'adminbot_pair_code', 'admin#' . $adminId, $code);
        return ['ok' => true, 'code' => $code, 'expires_at' => time() + max(60, $ttl),
                'admin' => (string)$admin['username']];
    }

    /** اتصال چت بله به یک ادمین پنل با کد اتصال */
    public static function pair(int $baleUserId, string $code): array
    {
        $code = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', $code) ?? '');
        if ($baleUserId <= 0 || $code === '') {
            return ['ok' => false, 'error' => 'کد اتصال نامعتبر است.'];
        }
        $row = DB::one("SELECT * FROM bot_pairs WHERE code=? AND used_at IS NULL ORDER BY id DESC LIMIT 1", [$code]);
        if (!$row) {
            Events::emit('security.bot_pair_failed', ['detail' => 'code not found', 'actor_id' => $baleUserId], 'warn');
            return ['ok' => false, 'error' => 'کد اتصال پیدا نشد یا قبلاً استفاده شده است.'];
        }
        if ((int)$row['expires_at'] < time()) {
            return ['ok' => false, 'error' => 'کد اتصال منقضی شده؛ از پنل کد تازه بگیر.'];
        }
        $admin = DB::one("SELECT * FROM panel_admins WHERE id=? AND is_active=1", [(int)$row['admin_id']]);
        if (!$admin) {
            return ['ok' => false, 'error' => 'حساب ادمین غیرفعال است.'];
        }
        DB::q("UPDATE bot_pairs SET used_at=?, used_by=? WHERE id=?", [time(), $baleUserId, (int)$row['id']]);
        // هر چت بله فقط به یک ادمین و هر ادمین فقط به یک چت وصل می‌شود
        DB::q("DELETE FROM bot_links WHERE bale_user_id=? OR admin_id=?", [$baleUserId, (int)$admin['id']]);
        DB::q("INSERT INTO bot_links (bale_user_id,admin_id,verified_at,otp_ok_until,alert_opt,created_at)
               VALUES (?,?,?,0,1,?)",
            [$baleUserId, (int)$admin['id'], time(), time()]);
        DB::q("UPDATE panel_admins SET bale_user_id=? WHERE id=?", [$baleUserId, (int)$admin['id']]);
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'adminbot_pair', 'user#' . $baleUserId, 'اتصال ربات');
        return ['ok' => true, 'admin' => $admin];
    }

    /** اطلاعات اتصال یک چت بله */
    public static function linkFor(int $baleUserId): ?array
    {
        if ($baleUserId <= 0) {
            return null;
        }
        $row = DB::one("SELECT l.*, a.username, a.role, a.full_name, a.is_active AS admin_active
                        FROM bot_links l JOIN panel_admins a ON a.id=l.admin_id
                        WHERE l.bale_user_id=? LIMIT 1", [$baleUserId]);
        if (!$row || (int)$row['admin_active'] !== 1) {
            return null;
        }
        return $row;
    }

    public static function unlink(int $id): bool
    {
        DB::q("DELETE FROM bot_links WHERE id=?", [$id]);
        return true;
    }

    public static function unlinkAdmin(int $adminId): bool
    {
        DB::q("DELETE FROM bot_links WHERE admin_id=?", [$adminId]);
        return true;
    }

    public static function links(): array
    {
        return DB::all("SELECT l.*, a.username, a.role FROM bot_links l
                        JOIN panel_admins a ON a.id=l.admin_id ORDER BY l.id DESC");
    }

    public static function setAlertOpt(int $linkId, bool $on): bool
    {
        DB::q("UPDATE bot_links SET alert_opt=? WHERE id=?", [$on ? 1 : 0, $linkId]);
        return true;
    }

    // ================= OTP =================

    private static function hmac(string $v): string
    {
        return hash_hmac('sha256', $v, Login::secret());
    }

    public static function otpTtl(): int
    {
        $v = (int)(Schema::getSetting('adminbot_otp_ttl', '') ?: 0);
        return $v > 0 ? $v : 180;
    }

    public static function sessionTtl(): int
    {
        $v = (int)(Schema::getSetting('adminbot_session_ttl', '') ?: 0);
        return $v > 0 ? $v : 900;
    }

    /** ارسال کد یک‌بارمصرف به چت اتصال‌یافته */
    public static function issueOtp(int $baleUserId): array
    {
        $link = self::linkFor($baleUserId);
        if ($link === null) {
            return ['ok' => false, 'error' => 'این چت به هیچ ادمین پنل وصل نیست. ابتدا از پنل «کد اتصال» بگیر و بفرست: اتصال CODE'];
        }
        $hourly = (int)DB::val("SELECT COUNT(*) FROM bot_otps WHERE bale_user_id=? AND created_at>?",
            [$baleUserId, time() - 3600], 0);
        if ($hourly >= 10) {
            Events::emit('security.bot_otp_rate', ['detail' => 'hourly limit', 'actor_id' => $baleUserId], 'warn');
            return ['ok' => false, 'error' => 'تعداد درخواست کد زیاد است؛ یک ساعت دیگر تلاش کن.'];
        }
        DB::q("UPDATE bot_otps SET used_at=? WHERE bale_user_id=? AND used_at IS NULL", [time(), $baleUserId]);
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl  = self::otpTtl();
        DB::q("INSERT INTO bot_otps (bale_user_id,code_hash,expires_at,attempts,created_at) VALUES (?,?,?,0,?)",
            [$baleUserId, self::hmac($code), time() + $ttl, time()]);
        DB::q("DELETE FROM bot_otps WHERE expires_at < ?", [time() - 86400]);

        $text = "🔐 <b>کد تایید مدیریت</b>\n\n<code>{$code}</code>\n\n"
            . "⏱ اعتبار: " . digits_fa((int)round($ttl / 60)) . " دقیقه\n"
            . "پس از تایید، " . digits_fa((int)round(self::sessionTtl() / 60)) . " دقیقه دسترسی به گزارش/انبار باز است.\n"
            . "اگر درخواست ندادی، همین کد را نادیده بگیر.";
        $sent = BaleApi::sendMessage($baleUserId, $text, null, null, 'HTML') !== null;
        Recovery::log((int)$link['admin_id'], 'bot_otp', 'bale', (string)$baleUserId, $sent ? 'sent' : 'send_failed');
        if (!$sent) {
            return ['ok' => false, 'error' => 'ارسال کد در چت ناموفق بود.'];
        }
        return ['ok' => true, 'code' => $code, 'ttl' => $ttl];
    }

    /** بررسی کد؛ در صورت درستی نشست کوتاه باز می‌شود */
    public static function verifyOtp(int $baleUserId, string $code): array
    {
        $link = self::linkFor($baleUserId);
        if ($link === null) {
            return ['ok' => false, 'error' => 'ابتدا چت را با کد اتصال به پنل وصل کن.'];
        }
        $code = preg_replace('~\D~', '', $code) ?? '';
        $row  = DB::one("SELECT * FROM bot_otps WHERE bale_user_id=? AND used_at IS NULL ORDER BY id DESC LIMIT 1",
            [$baleUserId]);
        if (!$row) {
            return ['ok' => false, 'error' => 'کدی فعال نیست؛ دستور «تایید» را بفرست تا کد تازه بگیری.'];
        }
        if ((int)$row['expires_at'] < time()) {
            return ['ok' => false, 'error' => 'کد منقضی شده؛ دوباره «تایید» بفرست.'];
        }
        if ((int)$row['attempts'] >= 5) {
            Events::emit('security.bot_otp_failed', ['detail' => 'attempts limit', 'actor_id' => $baleUserId], 'critical');
            return ['ok' => false, 'error' => 'تلاش زیاد؛ کد تازه بگیر.'];
        }
        DB::q("UPDATE bot_otps SET attempts=attempts+1 WHERE id=?", [(int)$row['id']]);
        if (!hash_equals((string)$row['code_hash'], self::hmac($code))) {
            Events::emit('security.bot_otp_failed', ['detail' => 'wrong code', 'actor_id' => $baleUserId], 'warn');
            return ['ok' => false, 'error' => 'کد اشتباه است.'];
        }
        DB::q("UPDATE bot_otps SET used_at=? WHERE id=?", [time(), (int)$row['id']]);
        $until = time() + self::sessionTtl();
        DB::q("UPDATE bot_links SET otp_ok_until=?, otp_fail_count=0, last_seen_at=? WHERE id=?",
            [$until, time(), (int)$link['id']]);
        Recovery::log((int)$link['admin_id'], 'bot_otp', 'bale', (string)$baleUserId, 'success');
        Logger::audit((int)$link['admin_id'], (string)$link['username'], 'adminbot_login', 'user#' . $baleUserId, 'تایید OTP ربات');
        return ['ok' => true, 'until' => $until, 'admin' => $link];
    }

    public static function sessionOk(?array $link): bool
    {
        return $link !== null && (int)$link['otp_ok_until'] > time();
    }

    public static function endSession(int $baleUserId): void
    {
        DB::q("UPDATE bot_links SET otp_ok_until=0 WHERE bale_user_id=?", [$baleUserId]);
    }

    // ================= گزارش‌ها =================

    public static function report(int $days = 7): string
    {
        $days = max(1, min(90, $days));
        $since = time() - $days * 86400;
        $users      = (int)DB::val("SELECT COUNT(*) FROM users", [], 0);
        $newUsers   = (int)DB::val("SELECT COUNT(*) FROM users WHERE first_seen_at>?", [$since], 0);
        $groups     = (int)DB::val("SELECT COUNT(*) FROM chat_settings WHERE is_active=1", [], 0);
        $orders     = (int)DB::val("SELECT COUNT(*) FROM orders WHERE created_at>?", [$since], 0);
        $revenue    = (int)DB::val("SELECT COALESCE(SUM(total),0) FROM orders WHERE created_at>? AND status IN ('paid','preparing','shipped','delivered')", [$since], 0);
        $pendingPay = (int)DB::val("SELECT COUNT(*) FROM payments WHERE status='pending'", [], 0);
        $pendingWd  = (int)DB::val("SELECT COUNT(*) FROM withdraw_requests WHERE status='pending'", [], 0);
        $low        = count(self::lowStockList(99));
        $queue      = (int)DB::val("SELECT COUNT(*) FROM broadcast_queue WHERE status='pending'", [], 0);
        $alerts24   = Events::countSince('', 86400, 'critical');
        $warns24    = Events::countSince('', 86400, 'warn');
        $storeStats = Store::stats();
        $m          = \Metrics::summary(min($days, 30));

        $lines = [
            '📊 <b>گزارش سامانه</b> — ' . digits_fa($days) . ' روز اخیر',
            '',
            '👤 کاربران: <b>' . digits_fa($users) . '</b> (+' . digits_fa($newUsers) . ' تازه)',
            '🏘 گروه‌های فعال: <b>' . digits_fa($groups) . '</b>',
            '💬 پیام‌ها: ' . digits_fa((int)$m['messages']) . ' · تخلف/اقدام: ' . digits_fa((int)$m['violations']),
            '👥 ورود: ' . digits_fa((int)$m['joins']) . ' · خروج: ' . digits_fa((int)$m['lefts']),
            '',
            '🛒 سفارش‌ها: <b>' . digits_fa($orders) . '</b> · فروش: <b>' . money($revenue) . '</b>',
            '• کالای فعال: ' . digits_fa((int)$storeStats['active']) . ' — در انتظار تایید: ' . digits_fa((int)$storeStats['pending']),
            '• پرداخت در انتظار تایید: ' . digits_fa($pendingPay) . ' · برداشت در انتظار: ' . digits_fa($pendingWd),
            '',
            '📦 کم‌موجود: <b>' . digits_fa($low) . '</b> · 📡 صف ارسال: ' . digits_fa($queue),
            '🔐 امنیت ۲۴ساعته: ' . digits_fa($alerts24) . ' بحرانی · ' . digits_fa($warns24) . ' هشدار',
            '',
            '⏱ ' . dt(time(), 'Y/m/d H:i'),
        ];
        return implode("\n", $lines);
    }

    public static function securitySummary(int $limit = 8): string
    {
        $rows = Events::recent($limit);
        $lines = ['🔐 <b>وضعیت امنیتی</b>', ''];
        $c24 = Events::countSince('', 86400, 'critical');
        $w24 = Events::countSince('', 86400, 'warn');
        $lines[] = '۲۴ ساعت اخیر: ' . digits_fa($c24) . ' بحرانی · ' . digits_fa($w24) . ' هشدار';
        $lines[] = '';
        if (!$rows) {
            $lines[] = 'رویدادی ثبت نشده است. ✅';
        }
        foreach ($rows as $r) {
            $icon = $r['severity'] === 'critical' ? '🚨' : ($r['severity'] === 'warn' ? '⚠️' : 'ℹ️');
            $lines[] = $icon . ' ' . h(self::eventLabel((string)$r['etype'])) . ' — ' . dt((int)$r['created_at'], 'm/d H:i')
                . ((string)$r['ip'] !== '' ? ' · <code>' . h((string)$r['ip']) . '</code>' : '')
                . ((string)($r['detail'] ?? '') !== '' ? "\n   " . h(str_limit((string)$r['detail'], 90)) : '');
        }
        $lines[] = '';
        $lines[] = 'جزئیات بیشتر در پنل → 🧩 ماژول‌ها → مدیریت با ربات';
        return implode("\n", $lines);
    }

    public static function eventLabel(string $type): string
    {
        return [
            'security.login_failed'   => 'ورود ناموفق به پنل',
            'security.login_locked'   => 'قفل شدن حساب پس از تلاش‌های ناموفق',
            'security.otp_failed'     => 'کد یک‌بارمصرف اشتباه (پنل)',
            'security.csrf_fail'      => 'درخواست بدون توکن امنیتی (CSRF)',
            'security.webhook_forged' => 'درخواست جعلی به وب‌هوک ربات',
            'security.webhook_flood'  => 'بمباران درخواست روی وب‌هوک',
            'security.panel_flood'    => 'ترافیک غیرعادی روی پنل',
            'security.bot_otp_failed' => 'کد تایید اشتباه در ربات',
            'security.bot_otp_rate'   => 'درخواست زیاد کد تایید در ربات',
            'security.bot_pair_failed'=> 'تلاش برای اتصال با کد نامعتبر',
            'adminbot.paired'         => 'اتصال موفق چت به پنل',
            'store.low_stock'         => 'کمبود موجودی کالا',
            'store.product_added'     => 'ثبت کالای تازه از ربات',
            'store.product_removed'   => 'حذف (بایگانی) کالا از ربات',
            'store.stock_changed'     => 'تغییر موجودی از ربات',
        ][$type] ?? $type;
    }

    // ================= انبار =================

    public static function lowStockList(int $limit = 15): array
    {
        $limit = max(1, min(200, $limit));
        return DB::all("SELECT p.id,p.title,p.code,p.stock,p.reserved,p.min_stock,p.unit,
                               (p.stock - p.reserved) AS avail
                        FROM products p
                        WHERE p.status='active' AND (p.stock - p.reserved) <= p.min_stock
                        ORDER BY (p.stock - p.reserved) ASC, p.id DESC LIMIT {$limit}");
    }

    public static function inventorySummary(): string
    {
        $stats  = Store::stats();
        $low    = self::lowStockList(10);
        $total  = count(self::lowStockList(200));
        $value  = (int)DB::val("SELECT COALESCE(SUM(price*GREATEST(stock-reserved,0)),0) FROM products WHERE status='active'", [], 0);
        $out    = (int)DB::val("SELECT COUNT(*) FROM products WHERE status='active' AND (stock-reserved)<=0", [], 0);

        $lines = [
            '📦 <b>انبار</b>',
            '• کالای فعال: <b>' . digits_fa((int)$stats['active']) . '</b>'
                . ' · در انتظار تایید: ' . digits_fa((int)$stats['pending']),
            '• ارزش موجودی: <b>' . money($value) . '</b>',
            '• ناموجود: ' . digits_fa($out) . ' · کم‌موجود: <b>' . digits_fa($total) . '</b>',
        ];
        if ($low) {
            $lines[] = '';
            $lines[] = '⚠️ <b>کالاهای کم‌موجود</b>';
            foreach ($low as $p) {
                $lines[] = '• <code>' . h((string)$p['code']) . '</code> ' . h(str_limit((string)$p['title'], 40))
                    . ' — ' . digits_fa((int)$p['avail']) . ' ' . h((string)$p['unit'])
                    . ' (حد ' . digits_fa((int)$p['min_stock']) . ')';
            }
        }
        $lines[] = '';
        $lines[] = 'دستورها: <code>موجودی CODE</code> · <code>افزودن_کالا عنوان | قیمت | موجودی</code>'
            . ' · <code>ویرایش_موجودی CODE 10</code> · <code>حذف_کالا CODE</code>';
        return implode("\n", $lines);
    }

    public static function productInfo(string $code): array
    {
        $p = Store::byCode($code);
        if (!$p) {
            return ['ok' => false, 'error' => 'کالایی با این کد پیدا نشد.'];
        }
        $avail = (int)$p['stock'] - (int)$p['reserved'];
        $text = '📦 <b>' . h((string)$p['title']) . "</b>\n"
            . 'کد: <code>' . h((string)$p['code']) . "</code>\n"
            . 'قیمت: <b>' . money(Store::price($p)) . "</b>\n"
            . 'موجودی: <b>' . digits_fa((int)$p['stock']) . '</b> ' . h((string)$p['unit'])
            . ' · رزرو: ' . digits_fa((int)$p['reserved'])
            . ' · قابل فروش: <b>' . digits_fa($avail) . "</b>\n"
            . 'حد هشدار: ' . digits_fa((int)$p['min_stock']) . ' · وضعیت: ' . h((string)$p['status'])
            . "\nفروش: " . digits_fa((int)$p['sold_count']);
        if ($avail <= (int)$p['min_stock']) {
            $text .= "\n\n⚠️ این کالا کم‌موجود است.";
        }
        return ['ok' => true, 'product' => $p, 'text' => $text];
    }

    public static function addProduct(array $in, ?int $adminId = null): array
    {
        $title = trim((string)($in['title'] ?? ''));
        if (mb_strlen($title) < 3) {
            return ['ok' => false, 'error' => 'عنوان کالا حداقل ۳ کاراکتر باشد.'];
        }
        $price = (int)($in['price'] ?? 0);
        if ($price <= 0) {
            return ['ok' => false, 'error' => 'قیمت باید بزرگ‌تر از صفر باشد.'];
        }
        $res = Store::save([
            'title'      => $title,
            'price'      => $price,
            'stock'      => max(0, (int)($in['stock'] ?? 0)),
            'min_stock'  => max(0, (int)($in['min_stock'] ?? 3)),
            'unit'       => (string)($in['unit'] ?? 'عدد'),
            'description'=> (string)($in['description'] ?? ''),
            'status'     => 'active',
            'actor'      => 'adminbot',
        ], null, $adminId);
        if ($res['ok']) {
            Events::emit('store.product_added', [
                'detail' => 'p#' . (int)$res['id'] . ' ' . str_limit($title, 60) . ' price=' . $price,
                'actor_id' => $adminId,
            ], 'info');
        }
        return $res;
    }

    public static function setStock(string $code, int $newStock, ?int $adminId = null): array
    {
        $p = Store::byCode($code);
        if (!$p) {
            return ['ok' => false, 'error' => 'کالایی با این کد پیدا نشد.'];
        }
        $newStock = max(0, $newStock);
        $delta = $newStock - (int)$p['stock'];
        if ($delta === 0) {
            return ['ok' => true, 'unchanged' => true, 'stock' => $newStock];
        }
        Store::adjustStock((int)$p['id'], $delta, 'adjust', [], $adminId, 'ویرایش موجودی از ربات');
        Events::emit('store.stock_changed', [
            'detail' => (string)$p['code'] . ' ' . (int)$p['stock'] . '→' . $newStock,
            'actor_id' => $adminId,
        ], 'info');
        return ['ok' => true, 'stock' => $newStock, 'delta' => $delta];
    }

    public static function removeProduct(string $code, ?int $adminId = null): array
    {
        $p = Store::byCode($code);
        if (!$p) {
            return ['ok' => false, 'error' => 'کالایی با این کد پیدا نشد.'];
        }
        Store::setStatus((int)$p['id'], 'archived', $adminId, 'حذف از ربات');
        Events::emit('store.product_removed', [
            'detail' => (string)$p['code'] . ' ' . str_limit((string)$p['title'], 60),
            'actor_id' => $adminId,
        ], 'info');
        return ['ok' => true, 'id' => (int)$p['id'], 'title' => (string)$p['title']];
    }

    // ================= هشدار =================

    /** چت‌هایی که هشدار می‌گیرند */
    public static function recipients(): array
    {
        return DB::all("SELECT l.bale_user_id, l.admin_id, a.username
                        FROM bot_links l JOIN panel_admins a ON a.id=l.admin_id
                        WHERE l.alert_opt=1 AND a.is_active=1 ORDER BY l.id");
    }

    /** ارسال متن به همه ادمین‌های متصل (+ چت پشتیبان) */
    public static function notify(string $text, string $etype = ''): int
    {
        $sent = 0;
        foreach (self::recipients() as $r) {
            if (BaleApi::sendMessage((int)$r['bale_user_id'], $text, null, null, 'HTML') !== null) {
                $sent++;
            }
        }
        $fallback = (int)(Schema::getSetting('adminbot_alert_chat', '0') ?: 0);
        if ($fallback !== 0 && BaleApi::sendMessage($fallback, $text, null, null, 'HTML') !== null) {
            $sent++;
        }
        if ($sent > 0 && $etype !== '') {
            Logger::info('adminbot', 'alert sent: ' . $etype, ['to' => $sent]);
        }
        return $sent;
    }

    public static function alertLevel(): string
    {
        $v = (string)(Schema::getSetting('adminbot_alert_level', 'warn') ?: 'warn');
        return isset(Events::LEVELS[$v]) ? $v : 'warn';
    }

    public static function alertCooldown(): int
    {
        $v = Schema::getSetting('adminbot_alert_cooldown', null);
        return $v === null ? 300 : max(0, (int)$v);
    }

    /**
     * تصمیم‌گیری و ارسال هشدار برای یک رویداد
     * @param array<string,mixed> $e ردیف event_log یا payload Events
     */
    public static function processEvent(array $e): bool
    {
        $type = (string)($e['type'] ?? $e['etype'] ?? 'event');
        $sev  = (string)($e['severity'] ?? 'warn');
        $id   = (int)($e['id'] ?? 0);
        if (Events::levelValue($sev) < Events::levelValue(self::alertLevel())) {
            if ($id > 0) {
                Events::markNotified($id);
            }
            return false;
        }
        $ip   = (string)($e['ip'] ?? '');
        $cool = self::alertCooldown();
        $key  = 'ab:alert:' . md5($type . '|' . $ip);
        $last = (int)Cache::get($key, 0);
        if ($cool > 0 && $last > 0 && time() - $last < $cool) {
            if ($id > 0) {
                Events::markNotified($id);
            }
            return false;
        }
        $burst = Events::countSince($type, 600);
        $icon  = $sev === 'critical' ? '🚨' : '⚠️';
        $lines = [
            $icon . ' <b>هشدار امنیتی سامانه</b>',
            '',
            'نوع: <b>' . h(self::eventLabel($type)) . '</b>',
            'شدت: ' . h(Events::severityFa($sev)),
        ];
        if ($ip !== '') {
            $lines[] = 'IP: <code>' . h($ip) . '</code>';
        }
        if (!empty($e['detail'])) {
            $lines[] = 'جزئیات: ' . h(str_limit((string)$e['detail'], 150));
        }
        if ($burst > 1) {
            $lines[] = 'تکرار در ۱۰ دقیقه: <b>' . digits_fa($burst) . '</b> بار'
                . ($burst >= 10 ? ' — احتمال حمله/اسکن جدی است.' : '');
        }
        $lines[] = 'زمان: ' . dt(time(), 'Y/m/d H:i:s');
        $lines[] = '';
        $lines[] = 'پنل → 🧩 مدیریت با ربات · <code>امنیت</code> برای لیست';

        $sent = self::notify(implode("\n", $lines), $type);
        if ($sent > 0) {
            Cache::set($key, time(), max(60, $cool + 60));
        }
        if ($id > 0) {
            Events::markNotified($id);
        }
        return $sent > 0;
    }

    public static function onEvent(string $type, array $data): void
    {
        try {
            self::processEvent($data + ['type' => $type]);
        } catch (\Throwable $e) {
            Logger::warn('adminbot', 'onEvent: ' . $e->getMessage());
        }
    }

    public static function stockCooldown(): int
    {
        $v = Schema::getSetting('adminbot_stock_cooldown', null);
        return $v === null ? 21600 : max(0, (int)$v);
    }

    public static function stockAlertsOn(): bool
    {
        $v = Schema::getSetting('adminbot_stock_enabled', null);
        return $v === null ? true : (string)$v === '1';
    }

    /** بررسی کمبود موجودی و اطلاع‌رسانی (کرون / دکمه پنل) */
    public static function checkStock(bool $force = false): array
    {
        if (!self::stockAlertsOn() && !$force) {
            return ['ok' => true, 'alerts' => 0, 'products' => 0, 'skipped' => true];
        }
        $low = self::lowStockList(50);
        $fresh = [];
        $cool = self::stockCooldown();
        foreach ($low as $p) {
            $row = DB::one("SELECT * FROM stock_alerts WHERE product_id=?", [(int)$p['id']]);
            $lastAt = $row ? (int)$row['last_alert_at'] : 0;
            if (!$force && $cool > 0 && $lastAt > 0 && time() - $lastAt < $cool) {
                continue;
            }
            $fresh[] = $p;
            if ($row) {
                DB::q("UPDATE stock_alerts SET last_stock=?, last_alert_at=?, alert_count=alert_count+1 WHERE product_id=?",
                    [(int)$p['avail'], time(), (int)$p['id']]);
            } else {
                DB::q("INSERT INTO stock_alerts (product_id,last_stock,last_alert_at,alert_count,created_at)
                       VALUES (?,?,?,1,?)", [(int)$p['id'], (int)$p['avail'], time(), time()]);
            }
        }
        if (!$fresh) {
            return ['ok' => true, 'alerts' => 0, 'products' => count($low)];
        }
        $lines = ['📦 <b>هشدار موجودی انبار</b>', ''];
        foreach ($fresh as $p) {
            $lines[] = '• <code>' . h((string)$p['code']) . '</code> ' . h(str_limit((string)$p['title'], 40))
                . ' — <b>' . digits_fa((int)$p['avail']) . '</b> ' . h((string)$p['unit'])
                . ' (حد ' . digits_fa((int)$p['min_stock']) . ')';
        }
        $lines[] = '';
        $lines[] = 'جمع کالاهای کم‌موجود: <b>' . digits_fa(count($low)) . '</b>';
        $lines[] = 'برای شارژ موجودی: <code>ویرایش_موجودی CODE تعداد</code>';
        $text = implode("\n", $lines);
        Events::emit('store.low_stock', ['detail' => 'count=' . count($fresh)], 'warn');
        $sent = self::notify($text, 'store.low_stock');
        return ['ok' => true, 'alerts' => count($fresh), 'products' => count($low), 'sent' => $sent];
    }

    // ================= دستورهای ربات =================

    /** @return array<string,mixed>|null اتصال معتبر یا قفل */
    private static function guard(array $ctx, string $need = 'session'): ?array
    {
        $uid = (int)($ctx['user_id'] ?? 0);
        $chatId = (int)($ctx['chat_id'] ?? 0);
        if ($chatId !== $uid) {
            self::say($ctx, '🔒 فرمان‌های مدیریتی فقط در <b>چت خصوصی ربات</b> اجرا می‌شوند.');
            return null;
        }
        $link = self::linkFor($uid);
        if ($link === null) {
            self::say($ctx, "🔗 این چت به پنل وصل نیست.\n"
                . "۱) در پنل → 🧩 ماژول‌ها → مدیریت با ربات، «کد اتصال» بساز\n"
                . "۲) همان کد را اینجا بفرست: <code>اتصال CODE</code>");
            return null;
        }
        DB::q("UPDATE bot_links SET last_seen_at=? WHERE id=?", [time(), (int)$link['id']]);
        if ($need === 'session' && !self::sessionOk($link)) {
            self::say($ctx, "🔐 نشست مدیریتی باز نیست.\n"
                . "دستور <code>تایید</code> را بفرست تا کد شش‌رقمی بیاید، سپس <code>تایید کد</code>.");
            return null;
        }
        return $link;
    }

    public static function cmdPair(array $ctx): void
    {
        $rest = trim((string)($ctx['rest'] ?? ''));
        if ($rest === '') {
            self::say($ctx, "🔗 <b>اتصال چت به پنل</b>\n\n"
                . "۱) در پنل → 🧩 ماژول‌ها → مدیریت با ربات، برای حساب خودت «کد اتصال» بساز (۱۰ دقیقه اعتبار)\n"
                . "۲) بفرست: <code>اتصال ABC123</code>\n\n"
                . "پس از اتصال، برای هر نشست مدیریتی یک کد یک‌بارمصرف لازم است: <code>تایید</code>");
            return;
        }
        $r = self::pair((int)($ctx['user_id'] ?? 0), $rest);
        if (!$r['ok']) {
            self::say($ctx, '⚠️ ' . h((string)$r['error']));
            return;
        }
        Events::emit('adminbot.paired', ['detail' => 'admin=' . (int)$r['admin']['id'], 'actor_id' => (int)$ctx['user_id']], 'info');
        self::say($ctx, '✅ چت به حساب <b>' . h((string)$r['admin']['username']) . "</b> وصل شد.\n"
            . "برای شروع، دستور <code>تایید</code> را بفرست.");
    }

    public static function cmdOtp(array $ctx): void
    {
        $uid  = (int)($ctx['user_id'] ?? 0);
        $rest = trim((string)($ctx['rest'] ?? ''));
        if ((int)($ctx['chat_id'] ?? 0) !== $uid) {
            self::say($ctx, '🔒 این دستور فقط در چت خصوصی کار می‌کند.');
            return;
        }
        if ($rest === '') {
            $r = self::issueOtp($uid);
            if (!$r['ok']) {
                self::say($ctx, '⚠️ ' . h((string)$r['error']));
                return;
            }
            self::say($ctx, '📬 کد تایید در همین چت فرستاده شد. آن را این‌گونه بفرست: <code>تایید ۱۲۳۴۵۶</code>');
            return;
        }
        $r = self::verifyOtp($uid, $rest);
        if (!$r['ok']) {
            self::say($ctx, '⚠️ ' . h((string)$r['error']));
            return;
        }
        $mins = (int)round(max(1, (int)$r['until'] - time()) / 60);
        self::say($ctx, '✅ تایید شد. دسترسی مدیریتی برای ' . digits_fa($mins) . " دقیقه باز است.\n"
            . "دستورها: <code>گزارش</code> · <code>انبار</code> · <code>امنیت</code> · <code>افزودن_کالا</code> · <code>قفل</code>");
    }

    public static function cmdLock(array $ctx): void
    {
        $uid  = (int)($ctx['user_id'] ?? 0);
        self::endSession($uid);
        Logger::audit(null, 'bot', 'adminbot_lock', 'user#' . $uid, 'پایان نشست مدیریتی');
        self::say($ctx, '🔒 نشست مدیریتی بسته شد. برای ادامه دوباره <code>تایید</code> بفرست.');
    }

    public static function cmdPanelLink(array $ctx): void
    {
        $g = self::guard($ctx);
        if ($g === null) {
            return;
        }
        $link = self::loginLink((int)$g['admin_id']);
        if ($link === null) {
            self::say($ctx, '⚠️ ساخت لینک ورود ناموفق بود.');
            return;
        }
        self::say($ctx, "🔑 <b>لینک ورود یک‌بارمصرف به پنل</b>\n{$link}\n\n"
            . 'اعتبار: چند دقیقه · فقط یک‌بار قابل استفاده');
    }

    public static function loginLink(int $adminId): ?string
    {
        try {
            return Login::createMagicLink($adminId, 180);
        } catch (\Throwable $e) {
            Logger::warn('adminbot', 'magic link: ' . $e->getMessage());
            return null;
        }
    }

    public static function cmdReport(array $ctx): void
    {
        $g = self::guard($ctx);
        if ($g === null) {
            return;
        }
        $days = (int)preg_replace('~\D~', '', (string)($ctx['rest'] ?? ''));
        self::say($ctx, self::report($days > 0 ? $days : 7));
    }

    public static function cmdSecurity(array $ctx): void
    {
        $g = self::guard($ctx);
        if ($g === null) {
            return;
        }
        $n = (int)preg_replace('~\D~', '', (string)($ctx['rest'] ?? ''));
        self::say($ctx, self::securitySummary($n > 0 ? min(30, $n) : 8));
    }

    public static function cmdInventory(array $ctx): void
    {
        $g = self::guard($ctx);
        if ($g === null) {
            return;
        }
        self::say($ctx, self::inventorySummary());
    }

    public static function cmdStock(array $ctx): void
    {
        $g = self::guard($ctx);
        if ($g === null) {
            return;
        }
        $rest = trim((string)($ctx['rest'] ?? ''));
        if ($rest === '') {
            self::say($ctx, 'شکل درست: <code>موجودی CODE</code> (کد کالا در دستور <code>انبار</code> دیده می‌شود)');
            return;
        }
        $r = self::productInfo($rest);
        self::say($ctx, $r['ok'] ? (string)$r['text'] : '⚠️ ' . h((string)$r['error']));
    }

    public static function cmdAddProduct(array $ctx): void
    {
        $g = self::guard($ctx);
        if ($g === null) {
            return;
        }
        $raw = trim((string)($ctx['rest'] ?? ''));
        if ($raw === '') {
            self::say($ctx, "➕ شکل درست:\n<code>افزودن_کالا عنوان | قیمت | موجودی | حد هشدار</code>\n"
                . "مثال: <code>افزودن_کالا پاوربانک ۲۰هزار | 450000 | 10 | 3</code>");
            return;
        }
        $parts = array_map('trim', preg_split('~\s*\|\s*~u', $raw) ?: []);
        $title = (string)($parts[0] ?? '');
        $price = (int)preg_replace('~\D~', '', (string)($parts[1] ?? ''));
        $stock = isset($parts[2]) ? (int)preg_replace('~\D~', '', (string)$parts[2]) : 0;
        $min   = isset($parts[3]) ? (int)preg_replace('~\D~', '', (string)$parts[3]) : 3;
        $r = self::addProduct([
            'title' => $title, 'price' => $price, 'stock' => $stock, 'min_stock' => $min,
        ], (int)$g['admin_id']);
        if (!$r['ok']) {
            self::say($ctx, '⚠️ ' . h((string)($r['error'] ?? 'ثبت کالا ناموفق بود.')));
            return;
        }
        $p = Store::product((int)$r['id']);
        self::say($ctx, "✅ کالا ثبت شد.\n"
            . 'کد: <code>' . h((string)($r['code'] ?? ($p['code'] ?? ''))) . "</code>\n"
            . 'عنوان: <b>' . h($title) . '</b> · قیمت: ' . money($price)
            . ' · موجودی: ' . digits_fa($stock));
    }

    public static function cmdSetStock(array $ctx): void
    {
        $g = self::guard($ctx);
        if ($g === null) {
            return;
        }
        if (!preg_match('~^(\S+)\s+(\d+)$~u', trim((string)($ctx['rest'] ?? '')), $m)) {
            self::say($ctx, 'شکل درست: <code>ویرایش_موجودی CODE 10</code> (موجودی روی عدد داده‌شده تنظیم می‌شود)');
            return;
        }
        $r = self::setStock($m[1], (int)$m[2], (int)$g['admin_id']);
        if (!$r['ok']) {
            self::say($ctx, '⚠️ ' . h((string)$r['error']));
            return;
        }
        $p = Store::byCode($m[1]);
        self::say($ctx, '✅ موجودی <code>' . h((string)$p['code']) . '</code> روی <b>'
            . digits_fa((int)$r['stock']) . '</b> ' . h((string)$p['unit']) . ' تنظیم شد.'
            . (!empty($r['unchanged']) ? ' (بدون تغییر)' : ''));
    }

    public static function cmdDeleteProduct(array $ctx): void
    {
        $g = self::guard($ctx);
        if ($g === null) {
            return;
        }
        $code = trim((string)($ctx['rest'] ?? ''));
        if ($code === '') {
            self::say($ctx, 'شکل درست: <code>حذف_کالا CODE</code> — کالا بایگانی می‌شود و از فروشگاه/ربات برداشته می‌شود.');
            return;
        }
        $r = self::removeProduct($code, (int)$g['admin_id']);
        if (!$r['ok']) {
            self::say($ctx, '⚠️ ' . h((string)$r['error']));
            return;
        }
        self::say($ctx, '🗑 کالای <b>' . h((string)$r['title']) . '</b> بایگانی شد و از فروشگاه برداشته شد.');
    }

    // ================= کرون =================

    public static function cron(): void
    {
        // ۱) هشدار موجودی
        try {
            self::checkStock();
        } catch (\Throwable $e) {
            Logger::warn('adminbot', 'stock check: ' . $e->getMessage());
        }
        // ۲) رویدادهایی که هنوز هشدار نگرفته‌اند (اگر ارسال لحظه‌ای از دست رفته باشد)
        try {
            foreach (Events::pending(20, 86400) as $row) {
                self::processEvent([
                    'id' => (int)$row['id'], 'type' => (string)$row['etype'],
                    'severity' => (string)$row['severity'], 'ip' => (string)$row['ip'],
                    'detail' => (string)($row['detail'] ?? ''),
                ]);
            }
        } catch (\Throwable $e) {
            Logger::warn('adminbot', 'events sweep: ' . $e->getMessage());
        }
        // ۳) پاک‌سازی
        try {
            DB::q("DELETE FROM bot_otps WHERE created_at < ?", [time() - 7 * 86400]);
            DB::q("DELETE FROM bot_pairs WHERE expires_at < ?", [time() - 86400]);
        } catch (\Throwable $e) {
            Logger::warn('adminbot', 'cleanup: ' . $e->getMessage());
        }
    }

    // ================= پنل =================

    public static function panelData(): ?array
    {
        return [
            'links'    => self::links(),
            'admins'   => DB::all("SELECT id,username,role,bale_user_id FROM panel_admins WHERE is_active=1 ORDER BY id"),
            'pairs'    => DB::all("SELECT p.*, a.username FROM bot_pairs p JOIN panel_admins a ON a.id=p.admin_id
                                   WHERE p.used_at IS NULL AND p.expires_at > ? ORDER BY p.id DESC LIMIT 20", [time()]),
            'events'   => Events::recent(40),
            'low'      => self::lowStockList(20),
            'settings' => [
                'level'      => self::alertLevel(),
                'cooldown'   => self::alertCooldown(),
                'stock_cool' => self::stockCooldown(),
                'stock_on'   => self::stockAlertsOn(),
                'alert_chat' => (int)(Schema::getSetting('adminbot_alert_chat', '0') ?: 0),
                'otp_ttl'    => self::otpTtl(),
                'sess_ttl'   => self::sessionTtl(),
            ],
            'stats' => [
                'links'   => count(self::links()),
                'events24' => Events::countSince('', 86400),
                'critical24' => Events::countSince('', 86400, 'critical'),
                'low'     => count(self::lowStockList(200)),
                'otps'    => (int)DB::val("SELECT COUNT(*) FROM bot_otps WHERE created_at>?", [time() - 86400], 0),
            ],
        ];
    }

    public static function handleAction(string $action, array $admin): ?string
    {
        switch ($action) {
            case 'adminbot_settings':
                $level = (string)input('alert_level', 'warn');
                Schema::setSetting('adminbot_alert_level', isset(Events::LEVELS[$level]) ? $level : 'warn');
                Schema::setSetting('adminbot_alert_cooldown', (string)max(0, (int)input('alert_cooldown', 300)));
                Schema::setSetting('adminbot_stock_cooldown', (string)max(0, (int)input('stock_cooldown', 21600)));
                Schema::setSetting('adminbot_stock_enabled', (string)input('stock_enabled', '0') === '1' ? '1' : '0');
                Schema::setSetting('adminbot_alert_chat', (string)(int)preg_replace('~\D~', '', (string)input('alert_chat', '0')));
                Schema::setSetting('adminbot_otp_ttl', (string)max(60, (int)input('otp_ttl', 180)));
                Schema::setSetting('adminbot_session_ttl', (string)max(120, (int)input('session_ttl', 900)));
                Logger::audit((int)($admin['id'] ?? 0), (string)($admin['username'] ?? 'panel'), 'adminbot_settings', 'adminbot', 'تنظیمات هشدار');
                return 'تنظیمات مدیریت با ربات ذخیره شد.';
            case 'adminbot_pair':
                $r = self::pairCode((int)input('admin_id', 0));
                return $r['ok']
                    ? 'کد اتصال: ' . $r['code'] . ' — در چت خصوصی ربات بفرست: اتصال ' . $r['code']
                    : self::fail((string)($r['error'] ?? 'ساخت کد اتصال ناموفق بود.'));
            case 'adminbot_unlink':
                self::unlink((int)input('id', 0));
                return 'اتصال چت حذف شد.';
            case 'adminbot_alert_opt':
                self::setAlertOpt((int)input('id', 0), (string)input('on', '0') === '1');
                return 'وضعیت دریافت هشدار تغییر کرد.';
            case 'adminbot_test':
                $n = self::notify("🧪 <b>پیام آزمایشی</b>\nهمه‌چیز درست است؛ هشدارها به همین چت می‌رسند.\n" . dt(time()), 'test');
                return $n > 0
                    ? 'پیام آزمایشی به ' . digits_fa($n) . ' چت ارسال شد.'
                    : self::fail('هیچ چت متصلی برای ارسال وجود ندارد؛ ابتدا اتصال بساز.');
            case 'adminbot_stock_now':
                $r = self::checkStock(true);
                return 'بررسی موجودی انجام شد — ' . digits_fa((int)$r['alerts']) . ' هشدار ارسال شد.';
            case 'adminbot_clear_events':
                DB::q("DELETE FROM event_log WHERE created_at < ?", [time() - 7 * 86400]);
                return 'رویدادهای قدیمی‌تر از ۷ روز پاک شد.';
        }
        return null;
    }
}
