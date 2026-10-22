<?php
declare(strict_types=1);

/**
 * نظرات و امتیاز خریداران (v1.4)
 *
 * - هر خریدار برای هر کالا در هر سفارش فقط یک نظر می‌تواند ثبت کند
 * - فقط سفارش «تحویل‌شده» اجازه ثبت نظر می‌دهد (خرید تاییدشده)
 * - پیش‌فرض: نمایش خودکار (auto-approve) با امکان تغییر به حالت بازبینی در تنظیمات
 * - میانگین امتیاز روی صفحه کالا نمایش داده می‌شود
 */
final class Reviews
{
    public const MAX_COMMENT = 600;

    /** آیا نمایش خودکار باشد؟ (store_reviews_auto=1) */
    public static function autoApprove(): bool
    {
        $v = Schema::getSetting('store_reviews_auto', '1');
        return $v === null ? true : (string)$v === '1';
    }

    /** آیا کاربر می‌تواند برای این سفارش نظر بدهد؟ */
    public static function canReview(int $orderId, int $userId): array
    {
        $o = Orders::order($orderId);
        if (!$o || (int)$o['user_id'] !== $userId) {
            return ['ok' => false, 'error' => 'سفارش پیدا نشد.'];
        }
        if ($o['status'] !== 'delivered') {
            return ['ok' => false, 'error' => 'ثبت نظر فقط بعد از تحویل سفارش ممکن است.'];
        }
        $productId = (int)($o['items'][0]['product_id'] ?? 0);
        if ($productId <= 0) {
            return ['ok' => false, 'error' => 'کالای سفارش مشخص نیست.'];
        }
        $exists = DB::one("SELECT id FROM product_reviews WHERE order_id=? AND user_id=?", [$orderId, $userId]);
        if ($exists) {
            return ['ok' => false, 'error' => 'برای این سفارش قبلاً نظر ثبت کرده‌اید.'];
        }
        return ['ok' => true, 'product_id' => $productId];
    }

    /**
     * ثبت نظر
     * @return array{ok:bool,id?:int,status?:string,error?:string}
     */
    public static function submit(int $orderId, int $userId, int $rating, string $comment): array
    {
        $check = self::canReview($orderId, $userId);
        if (!$check['ok']) {
            return $check;
        }
        $rating  = max(1, min(5, $rating));
        $comment = trim($comment);
        if (mb_strlen($comment) > self::MAX_COMMENT) {
            $comment = mb_substr($comment, 0, self::MAX_COMMENT);
        }
        $status = self::autoApprove() ? 'approved' : 'pending';
        $productId = (int)$check['product_id'];
        DB::q("INSERT INTO product_reviews (product_id,user_id,order_id,rating,comment,status,created_at)
               VALUES (?,?,?,?,?,?,?)",
            [$productId, $userId, $orderId, $rating, $comment !== '' ? $comment : null, $status, time()]);
        $id = (int)DB::pdo()->lastInsertId();
        DB::q("UPDATE orders SET reviewed=1 WHERE id=?", [$orderId]);
        Logger::user(null, $userId, null, 'review_submit', 'product#' . $productId . ' · ' . $rating . '★');
        // میانگین امتیاز را روی کالا نگه می‌داریم (برای مرتب‌سازی/نمایش سریع)
        self::refreshProductRating($productId);
        return ['ok' => true, 'id' => $id, 'status' => $status];
    }

    /** میانگین و تعداد امتیازهای تاییدشده یک کالا */
    public static function summary(int $productId): array
    {
        $row = DB::one("SELECT AVG(rating) avg_rating, COUNT(*) cnt FROM product_reviews
                        WHERE product_id=? AND status='approved'", [$productId]);
        return [
            'avg'   => round((float)($row['avg_rating'] ?? 0), 1),
            'count' => (int)($row['cnt'] ?? 0),
        ];
    }

    public static function refreshProductRating(int $productId): void
    {
        $s = self::summary($productId);
        Schema::setSetting('rating_' . $productId, (string)$s['avg']);
        Schema::setSetting('rating_count_' . $productId, (string)$s['count']);
    }

    /** @return array<int,array<string,mixed>> نظرات تاییدشده کالا */
    public static function forProduct(int $productId, int $limit = 20): array
    {
        return DB::all("SELECT r.*, u.first_name, u.last_name, u.username
                        FROM product_reviews r LEFT JOIN users u ON u.user_id=r.user_id
                        WHERE r.product_id=? AND r.status='approved'
                        ORDER BY r.id DESC LIMIT " . max(1, min(100, $limit)), [$productId]);
    }

    /** @return array<int,array<string,mixed>> برای پنل/فروشنده */
    public static function list(string $status = '', int $limit = 50, int $off = 0, ?int $sellerId = null): array
    {
        $w = [];
        $a = [];
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $w[] = 'r.status=?';
            $a[] = $status;
        }
        if ($sellerId !== null) {
            $w[] = 'p.seller_id=?';
            $a[] = $sellerId;
        }
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        return DB::all("SELECT r.*, p.title AS product_title, p.code AS product_code,
                               u.first_name, u.last_name, u.username, o.code AS order_code
                        FROM product_reviews r
                        LEFT JOIN products p ON p.id=r.product_id
                        LEFT JOIN users u ON u.user_id=r.user_id
                        LEFT JOIN orders o ON o.id=r.order_id
                        $where ORDER BY r.id DESC LIMIT " . max(1, min(200, $limit)) . ' OFFSET ' . max(0, $off), $a);
    }

    public static function count(string $status = '', ?int $sellerId = null): int
    {
        $w = [];
        $a = [];
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $w[] = 'r.status=?';
            $a[] = $status;
        }
        if ($sellerId !== null) {
            $w[] = 'p.seller_id=?';
            $a[] = $sellerId;
        }
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        return (int)DB::val("SELECT COUNT(*) FROM product_reviews r LEFT JOIN products p ON p.id=r.product_id $where", $a, 0);
    }

    public static function moderate(int $id, string $status, ?int $adminId = null): bool
    {
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            return false;
        }
        $r = DB::one("SELECT * FROM product_reviews WHERE id=?", [$id]);
        if (!$r) {
            return false;
        }
        DB::q("UPDATE product_reviews SET status=?, admin_id=? WHERE id=?", [$status, $adminId, $id]);
        self::refreshProductRating((int)$r['product_id']);
        Logger::audit($adminId, 'panel', 'review_' . $status, 'review#' . $id, 'product#' . (int)$r['product_id']);
        return true;
    }

    public static function delete(int $id, ?int $adminId = null): bool
    {
        $r = DB::one("SELECT * FROM product_reviews WHERE id=?", [$id]);
        if (!$r) {
            return false;
        }
        DB::q("DELETE FROM product_reviews WHERE id=?", [$id]);
        self::refreshProductRating((int)$r['product_id']);
        Logger::audit($adminId, 'panel', 'review_delete', 'review#' . $id);
        return true;
    }

    /** ستاره‌ها برای نمایش متنی/HTML (میانگین به نزدیک‌ترین ستاره گرد می‌شود) */
    public static function stars(float $avg): string
    {
        $full = max(0, min(5, (int)round($avg)));
        return str_repeat('★', $full) . str_repeat('☆', 5 - $full);
    }

    /** خلاصه برای داشبورد/پنل */
    public static function stats(): array
    {
        $row = DB::one("SELECT AVG(rating) a, COUNT(*) c FROM product_reviews WHERE status='approved'");
        return [
            'total'    => (int)DB::val("SELECT COUNT(*) FROM product_reviews", [], 0),
            'pending'  => (int)DB::val("SELECT COUNT(*) FROM product_reviews WHERE status='pending'", [], 0),
            'approved' => (int)($row['c'] ?? 0),
            'avg'      => round((float)($row['a'] ?? 0), 1),
        ];
    }
}
