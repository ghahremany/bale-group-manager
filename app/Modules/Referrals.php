<?php
declare(strict_types=1);

namespace Modules;

use DB;
use Logger;
use Module;
use Referral;
use Schema;
use digits_fa;
use h;

/**
 * ماژول «زیرمجموعه‌گیری» (Referral)
 *
 * منطق اصلی در Referral.php هست (ضد تقلب دومرحله‌ای، سطح ۲، تاپ معرف‌ها).
 * این ماژول همان قابلیت را به‌صورت پلاگین در می‌آورد:
 *  - روشن/خاموش شدن از صفحه «ماژول‌ها» (و همگام با تنظیم referral_enabled)
 *  - صفحه پنل اختصاصی: تایید/رد دعوت‌ها، تنظیمات، تاپ معرف‌ها
 *  - دستورهای ربات: لینک دعوت من، دعوت‌های من، برترین‌ها
 */
final class Referrals extends Module
{
    public static function key(): string
    {
        return 'referrals';
    }

    public static function title(): string
    {
        return 'زیرمجموعه‌گیری';
    }

    public static function description(): string
    {
        return 'لینک دعوت اختصاصی، تایید دومرحله‌ای ضد تقلب، سطح ۲ و جدول برترین معرف‌ها';
    }

    public static function icon(): string
    {
        return '🎁';
    }

    /** همگام‌سازی با تنظیم قدیمی referral_enabled */
    public static function isEnabled(): bool
    {
        $v = Schema::getSetting('module_on_referrals', null);
        if ($v === null) {
            return (string)Schema::getSetting('referral_enabled', '1') === '1';
        }
        return (string)$v === '1';
    }

    public static function setEnabled(bool $on): void
    {
        parent::setEnabled($on);
        Schema::setSetting('referral_enabled', $on ? '1' : '0');
    }

    public static function badge(): string
    {
        $v = (int)DB::val("SELECT COUNT(*) FROM referrals WHERE status='verified'", [], 0);
        $p = (int)DB::val("SELECT COUNT(*) FROM referrals WHERE status='pending'", [], 0);
        return digits_fa($v) . ' تاییدشده · ' . digits_fa($p) . ' در انتظار';
    }

    public static function commands(): array
    {
        return [
            'لینک_دعوت_من' => 'myLink',
            'دعوتها'       => 'myInvites',
            'دعوت‌ها'      => 'myInvites',
            'برترینها'     => 'topList',
        ];
    }

    // ---------- دستورهای ربات ----------

    public static function myLink(array $ctx): void
    {
        $uid  = (int)($ctx['user_id'] ?? 0);
        $link = Referral::link($uid);
        $code = Referral::ensureCode($uid);
        self::say($ctx, "🎁 <b>لینک دعوت اختصاصی تو</b>\n<code>" . h($link) . "</code>\n\n"
            . "کد: <code>" . h($code) . "</code>\n"
            . "هر نفر که با این لینک بیاید و اولین بار در گروه پیام بدهد، به عنوان زیرمجموعه تایید می‌شود.");
    }

    public static function myInvites(array $ctx): void
    {
        $uid   = (int)($ctx['user_id'] ?? 0);
        $stats = Referral::stats($uid);
        $rows  = Referral::children($uid, 10);
        $out   = ["🎁 <b>وضعیت زیرمجموعه‌های تو</b>",
            'تاییدشده: <b>' . digits_fa((int)$stats['verified']) . '</b>'
            . ' · در انتظار: <b>' . digits_fa((int)$stats['pending']) . '</b>'
            . ' · سطح ۲: <b>' . digits_fa((int)$stats['level2']) . '</b>'];
        if ($rows) {
            $out[] = '';
            foreach ($rows as $r) {
                $flag = $r['status'] === 'verified' ? '✅' : ($r['status'] === 'rejected' ? '⛔' : '⏳');
                $name = trim((string)($r['first_name'] ?? '') . ' ' . (string)($r['last_name'] ?? ''));
                $out[] = $flag . ' ' . h($name !== '' ? $name : 'کاربر ' . (int)$r['invitee_id']);
            }
        }
        self::say($ctx, implode("\n", $out));
    }

    public static function topList(array $ctx): void
    {
        $rows = Referral::top(10, 0);
        if (!$rows) {
            self::say($ctx, "هنوز زیرمجموعه‌ای تایید نشده است.");
            return;
        }
        $out = ["🏆 <b>برترین معرف‌ها</b>"];
        $i = 0;
        foreach ($rows as $r) {
            $i++;
            $name = trim((string)($r['first_name'] ?? '') . ' ' . (string)($r['last_name'] ?? ''));
            if ($name === '') {
                $name = 'کاربر ' . (int)$r['inviter_id'];
            }
            $medal = $i === 1 ? '🥇' : ($i === 2 ? '🥈' : ($i === 3 ? '🥉' : digits_fa($i) . '.'));
            $out[] = $medal . ' ' . h($name) . ' — <b>' . digits_fa((int)$r['c']) . '</b> دعوت';
        }
        self::say($ctx, implode("\n", $out));
    }

    // ---------- پنل ----------

    public static function panelData(): ?array
    {
        [$rows, $total] = Referral::paginate((string)($_GET['f'] ?? ''), 50, 0);
        return [
            'rows'  => $rows,
            'total' => $total,
            'top'   => Referral::top(10, 0),
            'stats' => [
                'verified' => (int)DB::val("SELECT COUNT(*) FROM referrals WHERE status='verified'", [], 0),
                'pending'  => (int)DB::val("SELECT COUNT(*) FROM referrals WHERE status='pending'", [], 0),
                'rejected' => (int)DB::val("SELECT COUNT(*) FROM referrals WHERE status='rejected'", [], 0),
                'inviters' => (int)DB::val("SELECT COUNT(DISTINCT inviter_id) FROM referrals", [], 0),
            ],
            'settings' => [
                'notify' => (string)Schema::getSetting('referral_notify', '1'),
            ],
        ];
    }

    public static function handleAction(string $action, array $admin): ?string
    {
        switch ($action) {
            case 'referral_set_status':
                $id = (int)input('id', 0);
                $st = (string)input('status', '');
                if ($id && in_array($st, ['verified', 'rejected', 'pending'], true)) {
                    Referral::setStatus($id, $st, (int)($admin['id'] ?? 0), (string)input('note', ''));
                    return 'وضعیت دعوت به «' . self::faStatus($st) . '» تغییر کرد.';
                }
                return self::fail('دعوت یا وضعیت نامعتبر است.');
            case 'referral_save':
                Schema::setSetting('referral_notify', !empty($_POST['referral_notify']) ? '1' : '0');
                return 'تنظیمات زیرمجموعه‌گیری ذخیره شد.';
        }
        return null;
    }

    public static function faStatus(string $status): string
    {
        return ['verified' => 'تاییدشده', 'rejected' => 'رد‌شده', 'pending' => 'در انتظار'][$status] ?? $status;
    }
}
