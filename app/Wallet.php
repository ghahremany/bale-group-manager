<?php
declare(strict_types=1);

/**
 * کیف پول و کمیسیون زیرمجموعه فروش
 * - هر تغییر با یک ردیف در wallet_tx ثبت می‌شود (دفتر کل)
 * - کمیسیون فقط بعد از «تحویل» سفارش آزاد می‌شود (ضد تقلب/مرجوعی)
 */
final class Wallet
{
    public static function balance(int $userId): int
    {
        return (int)DB::val("SELECT balance FROM wallets WHERE user_id=?", [$userId], 0);
    }

    private static function ensure(int $userId): void
    {
        $row = DB::val("SELECT id FROM wallets WHERE user_id=?", [$userId]);
        if (!$row) {
            try {
                DB::q("INSERT INTO wallets (user_id, balance, updated_at) VALUES (?,0,?)", [$userId, time()]);
            } catch (Throwable $e) {
                // اگر هم‌زمان ساخته شد، مشکلی نیست
            }
        }
    }

    /** افزایش موجودی (کمیسیون، شارژ، بازگشت پول) */
    public static function credit(int $userId, int $amount, string $kind, ?string $ref = null, string $note = '', ?int $adminId = null): int
    {
        $amount = (int)$amount;
        if ($amount === 0) {
            return self::balance($userId);
        }
        self::ensure($userId);
        DB::q("UPDATE wallets SET balance=balance+?, updated_at=? WHERE user_id=?", [$amount, time(), $userId]);
        DB::q("INSERT INTO wallet_tx (user_id,amount,kind,ref,note,admin_id,created_at) VALUES (?,?,?,?,?,?,?)",
            [$userId, $amount, str_limit($kind, 18), str_limit((string)$ref, 38) ?: null, str_limit($note, 250) ?: null, $adminId, time()]);
        return self::balance($userId);
    }

    /** کاهش موجودی؛ اگر کافی نباشد false */
    public static function debit(int $userId, int $amount, string $kind, ?string $ref = null, string $note = '', ?int $adminId = null): bool
    {
        $amount = (int)$amount;
        if ($amount <= 0) {
            return false;
        }
        if (self::balance($userId) < $amount) {
            return false;
        }
        DB::q("UPDATE wallets SET balance=balance-?, updated_at=? WHERE user_id=?", [$amount, time(), $userId]);
        DB::q("INSERT INTO wallet_tx (user_id,amount,kind,ref,note,admin_id,created_at) VALUES (?,?,?,?,?,?,?)",
            [$userId, -$amount, str_limit($kind, 18), str_limit((string)$ref, 38) ?: null, str_limit($note, 250) ?: null, $adminId, time()]);
        return true;
    }

    public static function tx(int $userId, int $limit = 30): array
    {
        return DB::all("SELECT * FROM wallet_tx WHERE user_id=? ORDER BY id DESC LIMIT " . max(1, min(200, $limit)), [$userId]);
    }

    /** درخواست برداشت (موجودی بلوکه می‌شود تا تصمیم ادمین) */
    public static function requestWithdraw(int $userId, int $amount, string $card): array
    {
        $min = (int)(Schema::getSetting('store_min_withdraw', '50000') ?: 50000);
        $amount = (int)$amount;
        $card = preg_replace('/\D/', '', $card) ?? '';
        if ($amount < $min) {
            return ['ok' => false, 'error' => 'حداقل مبلغ برداشت ' . money($min) . ' است.'];
        }
        if (strlen($card) < 16) {
            return ['ok' => false, 'error' => 'شماره کارت باید ۱۶ رقم باشد.'];
        }
        $pending = (int)DB::val("SELECT COUNT(*) FROM withdraw_requests WHERE user_id=? AND status='pending'", [$userId], 0);
        if ($pending > 0) {
            return ['ok' => false, 'error' => 'یک درخواست برداشت باز داری؛ تا بررسی آن صبر کن.'];
        }
        if (!self::debit($userId, $amount, 'withdraw', null, 'درخواست برداشت')) {
            return ['ok' => false, 'error' => 'موجودی کافی نیست.'];
        }
        DB::q("INSERT INTO withdraw_requests (user_id,amount,dest_card,status,created_at) VALUES (?,?,?, 'pending', ?)",
            [$userId, $amount, $card, time()]);
        return ['ok' => true, 'id' => (int)DB::pdo()->lastInsertId()];
    }

    public static function withdrawals(string $status = '', int $limit = 50): array
    {
        $w = in_array($status, ['pending', 'paid', 'rejected'], true) ? 'WHERE r.status=?' : '';
        $args = $w ? [$status] : [];
        return DB::all("SELECT r.*, u.first_name, u.username FROM withdraw_requests r
                        LEFT JOIN users u ON u.user_id=r.user_id $w ORDER BY r.id DESC LIMIT " . max(1, min(200, $limit)), $args);
    }

    /** تصمیم ادمین روی درخواست برداشت */
    public static function decideWithdraw(int $id, bool $approve, ?int $adminId = null, string $note = ''): bool
    {
        $r = DB::one("SELECT * FROM withdraw_requests WHERE id=? AND status='pending'", [$id]);
        if (!$r) {
            return false;
        }
        DB::q("UPDATE withdraw_requests SET status=?, admin_id=?, note=?, paid_at=? WHERE id=?",
            [$approve ? 'paid' : 'rejected', $adminId, str_limit($note, 250) ?: null,
             $approve ? time() : null, $id]);
        if (!$approve) {
            // بازگشت مبلغ به کیف پول
            self::credit((int)$r['user_id'], (int)$r['amount'], 'refund', 'w#' . $id, 'برداشت رد شد', $adminId);
        }
        Logger::audit($adminId, 'panel', $approve ? 'withdraw_paid' : 'withdraw_rejected', 'w#' . $id, money((int)$r['amount']));
        if (BaleApi::hasToken()) {
            BaleApi::sendMessage((int)$r['user_id'], $approve
                ? "✅ درخواست برداشت شما تایید و پرداخت شد.\nمبلغ: " . money((int)$r['amount']) . "\nکارت: <code>" . h((string)$r['dest_card']) . "</code>"
                : "❌ درخواست برداشت شما رد شد و مبلغ به کیف پول بازگشت.\nمبلغ: " . money((int)$r['amount'])
                  . ($note !== '' ? "\nدلیل: " . h($note) : ''), null, null, 'HTML');
        }
        return true;
    }

    public static function stats(): array
    {
        return [
            'pending'  => (int)DB::val("SELECT COUNT(*) FROM withdraw_requests WHERE status='pending'", [], 0),
            'paid_sum' => (int)DB::val("SELECT COALESCE(SUM(amount),0) FROM withdraw_requests WHERE status='paid'", [], 0),
            'wallets'  => (int)DB::val("SELECT COALESCE(SUM(balance),0) FROM wallets", [], 0),
            'comm_sum' => (int)DB::val("SELECT COALESCE(SUM(amount),0) FROM wallet_tx WHERE kind='commission'", [], 0),
        ];
    }
}
