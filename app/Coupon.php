<?php
declare(strict_types=1);

/**
 * کد تخفیف (v1.4)
 *
 * - درصدی یا مبلغ ثابت
 * - حداقل مبلغ سبد، سقف تعداد استفاده کلی، سقف استفاده هر کاربر
 * - محدود به فروشنده یا یک کالای مشخص (اختیاری)
 * - تاریخ انقضا
 *
 * چرخه: validate() → ثبت سفارش (رزرو با recordUse) → تایید پرداخت (commit)
 *        یا لغو سفارش (release)
 */
final class Coupon
{
    /** پاک‌سازی کد واردشده کاربر (حروف بزرگ، بدون فاصله) */
    public static function normalize(string $code): string
    {
        $code = trim($code);
        $code = strtr($code, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                              '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
        $code = preg_replace('~[^A-Za-z0-9_-]~', '', $code) ?? '';
        return strtoupper(substr($code, 0, 32));
    }

    public static function find(string $code): ?array
    {
        $code = self::normalize($code);
        if ($code === '') {
            return null;
        }
        return DB::one("SELECT * FROM coupons WHERE code=?", [$code]);
    }

    /**
     * اعتبارسنجی کد برای یک سبد
     * @param array{user_id:int,subtotal:int,product_id?:int,seller_id?:int,qty?:int} $cart
     * @return array{ok:bool,discount?:int,coupon?:array,error?:string}
     */
    public static function validate(string $code, array $cart): array
    {
        $c = self::find($code);
        if (!$c) {
            return ['ok' => false, 'error' => 'کد تخفیف پیدا نشد.'];
        }
        if ((int)$c['is_active'] !== 1) {
            return ['ok' => false, 'error' => 'این کد غیرفعال شده است.'];
        }
        $now = time();
        if (!empty($c['expires_at']) && (int)$c['expires_at'] > 0 && (int)$c['expires_at'] < $now) {
            return ['ok' => false, 'error' => 'این کد منقضی شده است.'];
        }
        $userId    = (int)($cart['user_id'] ?? 0);
        $subtotal  = (int)($cart['subtotal'] ?? 0);
        $productId = (int)($cart['product_id'] ?? 0);
        $sellerId  = (int)($cart['seller_id'] ?? 0);

        if ((int)$c['product_id'] > 0 && (int)$c['product_id'] !== $productId) {
            return ['ok' => false, 'error' => 'این کد فقط برای کالای خاصی معتبر است.'];
        }
        if ((int)$c['seller_id'] > 0 && (int)$c['seller_id'] !== $sellerId) {
            return ['ok' => false, 'error' => 'این کد فقط در فروشگاه فروشنده مشخصی معتبر است.'];
        }
        if ((int)$c['min_total'] > 0 && $subtotal < (int)$c['min_total']) {
            return ['ok' => false, 'error' => 'حداقل مبلغ سبد برای این کد ' . money((int)$c['min_total']) . ' است.'];
        }
        if ((int)$c['max_uses'] > 0 && (int)$c['used_count'] >= (int)$c['max_uses']) {
            return ['ok' => false, 'error' => 'ظرفیت استفاده از این کد تکمیل شده است.'];
        }
        $perUser = max(1, (int)$c['per_user']);
        if ($userId > 0) {
            $mine = (int)DB::val("SELECT COUNT(*) FROM coupon_uses WHERE coupon_id=? AND user_id=?", [(int)$c['id'], $userId], 0);
            if ($mine >= $perUser) {
                return ['ok' => false, 'error' => 'شما قبلاً از این کد استفاده کرده‌اید.'];
            }
        }
        $discount = self::discountFor($c, $subtotal);
        if ($discount <= 0) {
            return ['ok' => false, 'error' => 'مبلغ تخفیف صفر است.'];
        }
        return ['ok' => true, 'discount' => $discount, 'coupon' => $c];
    }

    /** محاسبه مبلغ تخفیف روی یک سبد */
    public static function discountFor(array $c, int $subtotal): int
    {
        $value = (int)$c['value'];
        $discount = ($c['type'] ?? 'percent') === 'percent'
            ? (int)floor($subtotal * min(100, $value) / 100)
            : $value;
        $discount = min($discount, $subtotal);
        return max(0, $discount);
    }

    /** ثبت استفاده (در زمان ساخت سفارش؛ ذخیره‌شده با شناسه سفارش) */
    public static function recordUse(int $couponId, int $userId, int $orderId, int $amount): void
    {
        if ($couponId <= 0) {
            return;
        }
        DB::q("INSERT INTO coupon_uses (coupon_id,user_id,order_id,amount,created_at) VALUES (?,?,?,?,?)",
            [$couponId, $userId, $orderId, $amount, time()]);
        DB::q("UPDATE coupons SET used_count=used_count+1 WHERE id=?", [$couponId]);
    }

    /** آزادسازی استفاده هنگام لغو سفارش */
    public static function release(int $orderId): void
    {
        $rows = DB::all("SELECT * FROM coupon_uses WHERE order_id=?", [$orderId]);
        foreach ($rows as $r) {
            DB::q("DELETE FROM coupon_uses WHERE id=?", [(int)$r['id']]);
            DB::q("UPDATE coupons SET used_count=GREATEST(used_count-1,0) WHERE id=?", [(int)$r['coupon_id']]);
        }
    }

    // ---------------- مدیریت (پنل) ----------------

    public static function save(array $in, ?int $adminId = null): array
    {
        $id     = (int)($in['id'] ?? 0);
        $code   = self::normalize((string)($in['code'] ?? ''));
        $type   = (string)($in['type'] ?? 'percent');
        $value  = max(0, (int)($in['value'] ?? 0));
        if (!in_array($type, ['percent', 'fixed'], true)) {
            $type = 'percent';
        }
        if ($type === 'percent' && $value > 100) {
            $value = 100;
        }
        if ($id === 0 && $code === '') {
            $code = self::generateCode();
        }
        if ($code === '') {
            return ['ok' => false, 'error' => 'کد تخفیف را وارد کن (حروف/عدد).'];
        }
        if ($value <= 0) {
            return ['ok' => false, 'error' => 'مقدار تخفیف باید بزرگ‌تر از صفر باشد.'];
        }
        $dup = DB::one("SELECT id FROM coupons WHERE code=? AND id<>?", [$code, $id]);
        if ($dup) {
            return ['ok' => false, 'error' => 'این کد قبلاً ثبت شده است.'];
        }
        $expires = (int)($in['expires_at'] ?? 0);
        if (is_string($in['expires_at'] ?? null)) {
            $expires = trim((string)$in['expires_at']) === '' ? 0 : (int)strtotime((string)$in['expires_at']);
        }
        $data = [
            $code, $type, $value,
            max(0, (int)($in['min_total'] ?? 0)),
            max(0, (int)($in['max_uses'] ?? 0)),
            max(1, (int)($in['per_user'] ?? 1)),
            (int)($in['seller_id'] ?? 0) ?: null,
            (int)($in['product_id'] ?? 0) ?: null,
            $expires > 0 ? $expires : null,
            !empty($in['is_active']) || !isset($in['is_active']) ? 1 : 0,
        ];
        if ($id > 0) {
            DB::q("UPDATE coupons SET code=?, type=?, value=?, min_total=?, max_uses=?, per_user=?,
                     seller_id=?, product_id=?, expires_at=?, is_active=? WHERE id=?", array_merge($data, [$id]));
            Logger::audit($adminId, 'panel', 'coupon_update', 'coupon#' . $id, $code);
            return ['ok' => true, 'id' => $id, 'code' => $code];
        }
        DB::q("INSERT INTO coupons (code,type,value,min_total,max_uses,per_user,seller_id,product_id,expires_at,is_active,created_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)", array_merge($data, [time()]));
        $newId = (int)DB::pdo()->lastInsertId();
        Logger::audit($adminId, 'panel', 'coupon_create', 'coupon#' . $newId, $code);
        return ['ok' => true, 'id' => $newId, 'code' => $code];
    }

    public static function toggle(int $id, bool $active, ?int $adminId = null): bool
    {
        DB::q("UPDATE coupons SET is_active=? WHERE id=?", [$active ? 1 : 0, $id]);
        Logger::audit($adminId, 'panel', $active ? 'coupon_on' : 'coupon_off', 'coupon#' . $id);
        return true;
    }

    public static function delete(int $id, ?int $adminId = null): bool
    {
        DB::q("DELETE FROM coupon_uses WHERE coupon_id=?", [$id]);
        DB::q("DELETE FROM coupons WHERE id=?", [$id]);
        Logger::audit($adminId, 'panel', 'coupon_delete', 'coupon#' . $id);
        return true;
    }

    public static function generateCode(int $len = 6): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($try = 0; $try < 10; $try++) {
            $code = '';
            for ($i = 0; $i < $len; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            if (!DB::one("SELECT id FROM coupons WHERE code=?", [$code])) {
                return $code;
            }
        }
        return 'C' . substr((string)time(), -6);
    }

    /** @return array<int,array<string,mixed>> */
    public static function list(string $q = '', int $limit = 100): array
    {
        $w = '';
        $a = [];
        if (trim($q) !== '') {
            $w = 'WHERE c.code LIKE ?';
            $a[] = '%' . trim($q) . '%';
        }
        return DB::all("SELECT c.*, p.title AS product_title, s.shop_name
                        FROM coupons c
                        LEFT JOIN products p ON p.id=c.product_id
                        LEFT JOIN sellers s ON s.id=c.seller_id
                        $w ORDER BY c.id DESC LIMIT " . max(1, min(500, $limit)), $a);
    }

    public static function stats(): array
    {
        return [
            'total'   => (int)DB::val("SELECT COUNT(*) FROM coupons", [], 0),
            'active'  => (int)DB::val("SELECT COUNT(*) FROM coupons WHERE is_active=1", [], 0),
            'uses'    => (int)DB::val("SELECT COUNT(*) FROM coupon_uses", [], 0),
            'saved'   => (int)DB::val("SELECT COALESCE(SUM(amount),0) FROM coupon_uses", [], 0),
        ];
    }
}
