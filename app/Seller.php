<?php
declare(strict_types=1);

/**
 * فروشندگان (چندفروشندگی با تایید ادمین)
 */
final class Seller
{
    public const STATUSES = [
        'pending'   => 'در انتظار تایید',
        'approved'  => 'تاییدشده',
        'suspended' => 'تعلیق‌شده',
    ];

    public static function forUser(int $userId): ?array
    {
        $s = DB::one("SELECT * FROM sellers WHERE user_id=?", [$userId]);
        return $s ?: null;
    }

    public static function approved(int $userId): ?array
    {
        $s = self::forUser($userId);
        return ($s && $s['status'] === 'approved') ? $s : null;
    }

    /** درخواست فروشندگی (ثبت یا ویرایش درخواست در حالت pending) */
    public static function request(int $userId, string $shopName, string $phone = ''): array
    {
        $shopName = trim($shopName);
        if (mb_strlen($shopName) < 3) {
            return ['ok' => false, 'error' => 'نام فروشگاه حداقل ۳ کاراکتر باشد.'];
        }
        $exists = self::forUser($userId);
        if ($exists) {
            if ($exists['status'] === 'approved') {
                DB::q("UPDATE sellers SET shop_name=?, phone=? WHERE user_id=?", [$shopName, $phone ?: null, $userId]);
                return ['ok' => true, 'status' => 'approved', 'note' => 'اطلاعات فروشگاه به‌روزرسانی شد.'];
            }
            DB::q("UPDATE sellers SET shop_name=?, phone=?, status='pending', created_at=? WHERE user_id=?",
                [$shopName, $phone ?: null, time(), $userId]);
            return ['ok' => true, 'status' => 'pending', 'note' => 'درخواست دوباره ثبت شد و در انتظار تایید است.'];
        }
        DB::q("INSERT INTO sellers (user_id,shop_name,phone,status,created_at) VALUES (?,?,?, 'pending', ?)",
            [$userId, $shopName, $phone ?: null, time()]);
        $admins = DB::all("SELECT bale_user_id FROM panel_admins WHERE is_active=1 AND bale_user_id IS NOT NULL");
        foreach ($admins as $a) {
            BaleApi::sendMessage((int)$a['bale_user_id'],
                "🏪 درخواست فروشندگی جدید\nفروشگاه: <b>" . h($shopName) . "</b>\n"
                . "کاربر: <code>" . $userId . "</code>\n"
                . url('panel/index.php', ['p' => 'store', 'tab' => 'sellers']), null, null, 'HTML');
        }
        Logger::user(null, $userId, null, 'seller_request', $shopName);
        return ['ok' => true, 'status' => 'pending'];
    }

    public static function setStatus(int $sellerId, string $status, ?int $adminId = null, string $note = ''): bool
    {
        if (!isset(self::STATUSES[$status])) {
            return false;
        }
        $s = DB::one("SELECT * FROM sellers WHERE id=?", [$sellerId]);
        if (!$s) {
            return false;
        }
        DB::q("UPDATE sellers SET status=?, note=?, approved_at=?, approved_by=? WHERE id=?",
            [$status, str_limit($note, 250) ?: null, time(), $adminId, $sellerId]);
        // اگر فروشنده تعلیق شد، کالاهایش از دسترس خارج شوند
        if ($status !== 'approved') {
            DB::q("UPDATE products SET status='draft', updated_at=? WHERE seller_id=? AND status='active'", [time(), $sellerId]);
        }
        Logger::audit($adminId, 'panel', 'seller_status', 'seller#' . $sellerId, $s['status'] . ' → ' . $status . ' ' . $note);
        if (BaleApi::hasToken()) {
            $msg = match ($status) {
                'approved'  => "🎉 فروشندگی شما تایید شد!\nحالا از پنل فروشگاه می‌توانی کالا اضافه کنی:\n" . url('shop/index.php', ['p' => 'product']),
                'suspended' => "⛔ فروشندگی شما موقتاً تعلیق شد." . ($note !== '' ? "\nدلیل: " . h($note) : ''),
                default     => "🏪 وضعیت فروشندگی شما: " . self::STATUSES[$status],
            };
            BaleApi::sendMessage((int)$s['user_id'], $msg, null, null, 'HTML');
        }
        return true;
    }

    public static function list(string $status = '', int $limit = 50): array
    {
        $w = isset(self::STATUSES[$status]) ? 'WHERE s.status=?' : '';
        $args = $w ? [$status] : [];
        return DB::all("SELECT s.*, u.first_name, u.username, u.last_name,
                          (SELECT COUNT(*) FROM products p WHERE p.seller_id=s.id) AS products_count,
                          (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.seller_id=s.id AND o.payment_status='verified') AS revenue
                        FROM sellers s LEFT JOIN users u ON u.user_id=s.user_id
                        $w ORDER BY s.id DESC LIMIT " . max(1, min(200, $limit)), $args);
    }

    public static function stats(): array
    {
        return [
            'total'    => (int)DB::val("SELECT COUNT(*) FROM sellers", [], 0),
            'approved' => (int)DB::val("SELECT COUNT(*) FROM sellers WHERE status='approved'", [], 0),
            'pending'  => (int)DB::val("SELECT COUNT(*) FROM sellers WHERE status='pending'", [], 0),
        ];
    }

    /** فروشنده فعال یک کالا */
    public static function ofProduct(array $product): ?array
    {
        $id = (int)($product['seller_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $s = DB::one("SELECT * FROM sellers WHERE id=?", [$id]);
        return $s ?: null;
    }
}
