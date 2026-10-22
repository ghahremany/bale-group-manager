<?php
declare(strict_types=1);

/**
 * زیرمجموعه‌گیری (Referral) داخل گروه‌ها
 *
 * جریان دومرحله‌ای ضد تقلب (الهام‌گرفته از bale-referral-bot):
 *   ۱) کاربر در چت خصوصی ربات /start ref_CODE می‌زند → ثبت «pending»
 *   ۲) اولین حضور او در یکی از گروه‌های ربات → «verified»
 * این کار از ثبت دعوت‌های الکی (اکانت‌های بدون فعالیت) جلوگیری می‌کند.
 *
 * لینک دعوت: https://ble.ir/<bot_username>?start=ref_<code>
 */
final class Referral
{
    public static function botUsername(): string
    {
        $u = (string)(Schema::getSetting('bot_username', (string)config('bot.username', '')) ?: '');
        if ($u === '' && BaleApi::hasToken()) {
            $me = BaleApi::getMe();
            if (is_array($me) && !empty($me['username'])) {
                $u = (string)$me['username'];
                Schema::setSetting('bot_username', $u);
                if (!empty($me['id'])) {
                    Schema::setSetting('bot_id', (string)(int)$me['id']);
                }
            }
        }
        return ltrim($u, '@');
    }

    public static function isEnabled(): bool
    {
        return (string)Schema::getSetting('referral_enabled', '1') === '1';
    }

    /** کد یکتا برای کاربر (تنبل ساخته می‌شود) */
    public static function ensureCode(int $userId): string
    {
        $row = DB::one("SELECT referral_code FROM users WHERE user_id=?", [$userId]);
        if ($row && !empty($row['referral_code'])) {
            return (string)$row['referral_code'];
        }
        for ($i = 0; $i < 6; $i++) {
            $code = self::makeCode($userId);
            $dup  = DB::val("SELECT user_id FROM users WHERE referral_code=?", [$code]);
            if (!$dup) {
                DB::q("UPDATE users SET referral_code=? WHERE user_id=?", [$code, $userId]);
                return $code;
            }
        }
        return 'u' . $userId;
    }

    private static function makeCode(int $userId): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $rand = '';
        for ($i = 0; $i < 4; $i++) {
            $rand .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return 'r' . base_convert((string)$userId, 10, 36) . $rand;
    }

    public static function link(int $userId): string
    {
        $code = self::ensureCode($userId);
        $bot  = self::botUsername() ?: 'your_bale_bot';
        return "https://ble.ir/{$bot}?start=ref_{$code}";
    }

    /** یافتن معرف از پارامتر start */
    public static function resolveInviter(string $payload): ?array
    {
        $payload = trim($payload);
        if ($payload === '') {
            return null;
        }
        if (str_starts_with($payload, 'ref_')) {
            $payload = substr($payload, 4);
        }
        $payload = preg_replace('/[^A-Za-z0-9]/', '', $payload) ?? '';
        if ($payload === '') {
            return null;
        }
        $u = DB::one("SELECT user_id FROM users WHERE referral_code=?", [$payload]);
        if ($u) {
            return $u;
        }
        if (preg_match('~^\d{5,}$~', $payload)) {
            $u = DB::one("SELECT user_id FROM users WHERE user_id=?", [(int)$payload]);
            return $u ?: null;
        }
        return null;
    }

    /**
     * ثبت دعوت در حالت pending
     * @return array{ok:bool,reason?:string,inviter?:int}
     */
    public static function attachPending(int $inviteeId, string $startPayload, ?int $chatId = null): array
    {
        if (!self::isEnabled()) {
            return ['ok' => false, 'reason' => 'disabled'];
        }
        $inviter = self::resolveInviter($startPayload);
        if (!$inviter) {
            return ['ok' => false, 'reason' => 'inviter_not_found'];
        }
        $inviterId = (int)$inviter['user_id'];
        if ($inviterId === $inviteeId) {
            return ['ok' => false, 'reason' => 'self'];
        }
        $existing = DB::one("SELECT id FROM referrals WHERE invitee_id=?", [$inviteeId]);
        if ($existing) {
            return ['ok' => false, 'reason' => 'already'];
        }
        // جلوگیری از حلقه: معرفِ معرف نمی‌تواند خودِ معرف باشد
        $inviterRow = DB::one("SELECT referred_by FROM users WHERE user_id=?", [$inviterId]);
        if ($inviterRow && (int)$inviterRow['referred_by'] === $inviteeId) {
            return ['ok' => false, 'reason' => 'circular'];
        }
        try {
            DB::q("INSERT INTO referrals (inviter_id, invitee_id, chat_id, status, created_at) VALUES (?,?,?,'pending',?)",
                [$inviterId, $inviteeId, $chatId, time()]);
            DB::q("UPDATE users SET referred_by=? WHERE user_id=? AND (referred_by IS NULL OR referred_by=0)", [$inviterId, $inviteeId]);
            Logger::user($chatId, $inviterId, $inviteeId, 'referral_pending', 'دعوت ثبت شد');
            return ['ok' => true, 'inviter' => $inviterId];
        } catch (Throwable $e) {
            Logger::warn('referral', 'attach failed: ' . $e->getMessage());
            return ['ok' => false, 'reason' => 'db'];
        }
    }

    /** تایید دعوت وقتی کاربر در گروهی از ربات دیده شد */
    public static function verify(int $inviteeId, ?int $chatId = null): bool
    {
        if (!self::isEnabled()) {
            return false;
        }
        $row = DB::one("SELECT * FROM referrals WHERE invitee_id=? AND status='pending'", [$inviteeId]);
        if (!$row) {
            return false;
        }
        DB::q("UPDATE referrals SET status='verified', verified_at=?, chat_id=COALESCE(?, chat_id) WHERE id=?",
            [time(), $chatId, (int)$row['id']]);
        DB::q("UPDATE users SET invites_count=invites_count+1 WHERE user_id=?", [(int)$row['inviter_id']]);
        Logger::user($chatId, (int)$row['inviter_id'], $inviteeId, 'referral_verified', 'دعوت تایید شد');
        // اطلاع به معرف در چت خصوصی
        $notify = (string)Schema::getSetting('referral_notify', '1') === '1';
        if ($notify) {
            $stats = self::stats((int)$row['inviter_id']);
            BaleApi::sendMessage((int)$row['inviter_id'],
                "🎉 یکی از دوستانت با لینک دعوت تو عضو شد!\n"
                . "👥 مجموع دعوت‌های تاییدشده: " . digits_fa($stats['verified']) . "\n"
                . ($chatId ? "🏠 گروه: <code>{$chatId}</code>" : ''), null, null, 'HTML');
        }
        return true;
    }

    /** آمار یک کاربر: مستقیم، غیرمستقیم (سطح ۲)، در انتظار */
    public static function stats(int $userId): array
    {
        $verified = (int)DB::val("SELECT COUNT(*) FROM referrals WHERE inviter_id=? AND status='verified'", [$userId], 0);
        $pending  = (int)DB::val("SELECT COUNT(*) FROM referrals WHERE inviter_id=? AND status='pending'", [$userId], 0);
        $level2   = (int)DB::val("SELECT COUNT(*) FROM referrals r2
                                  JOIN users u ON u.user_id = r2.inviter_id
                                  WHERE u.referred_by = ? AND r2.status='verified'", [$userId], 0);
        $code     = self::ensureCode($userId);
        return [
            'verified' => $verified,
            'pending'  => $pending,
            'level2'   => $level2,
            'total'    => $verified + $pending,
            'code'     => $code,
            'link'     => self::link($userId),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function children(int $userId, int $limit = 50): array
    {
        return DB::all("SELECT r.*, u.first_name, u.last_name, u.username
                        FROM referrals r LEFT JOIN users u ON u.user_id=r.invitee_id
                        WHERE r.inviter_id=? ORDER BY r.id DESC LIMIT " . max(1, min(500, $limit)), [$userId]);
    }

    /** تاپ معرف‌ها */
    public static function top(int $limit = 10, int $days = 0, ?int $chatId = null): array
    {
        $where = ["r.status='verified'"];
        $args  = [];
        if ($days > 0) {
            $where[] = 'r.verified_at >= ?';
            $args[]  = time() - $days * 86400;
        }
        if ($chatId) {
            $where[] = 'r.chat_id = ?';
            $args[]  = $chatId;
        }
        $sql = "SELECT r.inviter_id, COUNT(*) c, u.first_name, u.last_name, u.username
                FROM referrals r LEFT JOIN users u ON u.user_id=r.inviter_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY r.inviter_id ORDER BY c DESC LIMIT " . max(1, min(100, $limit));
        return DB::all($sql, $args);
    }

    /** لیست برای پنل */
    public static function paginate(string $status = '', int $limit = 50, int $off = 0): array
    {
        $where = '';
        $args  = [];
        if (in_array($status, ['pending', 'verified', 'rejected'], true)) {
            $where = 'WHERE r.status=?';
            $args[] = $status;
        }
        $rows  = DB::all("SELECT r.*, i.first_name inviter_name, i.username inviter_username,
                                 e.first_name invitee_name, e.username invitee_username
                          FROM referrals r
                          LEFT JOIN users i ON i.user_id = r.inviter_id
                          LEFT JOIN users e ON e.user_id = r.invitee_id
                          $where ORDER BY r.id DESC LIMIT $limit OFFSET $off", $args);
        $total = (int)DB::val("SELECT COUNT(*) FROM referrals r $where", $args, 0);
        return [$rows, $total];
    }

    public static function setStatus(int $id, string $status, ?int $adminId = null, string $note = ''): void
    {
        if (!in_array($status, ['pending', 'verified', 'rejected'], true)) {
            return;
        }
        $row = DB::one("SELECT * FROM referrals WHERE id=?", [$id]);
        if (!$row) {
            return;
        }
        DB::q("UPDATE referrals SET status=?, note=?, verified_at=? WHERE id=?",
            [$status, str_limit($note, 250), $status === 'verified' ? time() : null, $id]);
        if ($status === 'verified' && $row['status'] !== 'verified') {
            DB::q("UPDATE users SET invites_count=invites_count+1 WHERE user_id=?", [(int)$row['inviter_id']]);
        } elseif ($status !== 'verified' && $row['status'] === 'verified') {
            DB::q("UPDATE users SET invites_count=GREATEST(invites_count-1,0) WHERE user_id=?", [(int)$row['inviter_id']]);
        }
        Logger::audit($adminId, 'panel', 'referral_' . $status, '#' . $id, $note);
    }

    /** خلاصه کل سیستم برای داشبورد/نمودار */
    public static function overview(int $days = 14): array
    {
        $labels = [];
        $rows   = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime('-' . $i . ' days'));
            $labels[] = date('m/d', strtotime($d));
            $rows[$d] = 0;
        }
        $data = DB::all("SELECT DATE(FROM_UNIXTIME(COALESCE(verified_at, created_at))) day, COUNT(*) c
                         FROM referrals WHERE COALESCE(verified_at, created_at) >= ?
                         GROUP BY day", [strtotime('-' . ($days - 1) . ' days')]);
        foreach ($data as $r) {
            if (isset($rows[(string)$r['day']])) {
                $rows[(string)$r['day']] = (int)$r['c'];
            }
        }
        return [
            'labels'   => $labels,
            'series'   => array_values($rows),
            'verified' => (int)DB::val("SELECT COUNT(*) FROM referrals WHERE status='verified'", [], 0),
            'pending'  => (int)DB::val("SELECT COUNT(*) FROM referrals WHERE status='pending'", [], 0),
            'rejected' => (int)DB::val("SELECT COUNT(*) FROM referrals WHERE status='rejected'", [], 0),
            'inviters' => (int)DB::val("SELECT COUNT(DISTINCT inviter_id) FROM referrals", [], 0),
        ];
    }
}
