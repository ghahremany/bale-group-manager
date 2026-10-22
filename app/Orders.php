<?php
declare(strict_types=1);

/**
 * سفارش، پرداخت کارت‌به‌کارت و کمیسیون زیرمجموعه فروش
 *
 * جریان:
 *   ثبت سفارش → رزرو موجودی (reserved) → awaiting_payment
 *   → آپلود رسید → pending_review → تایید ادمین → paid (stock کم می‌شود)
 *   → preparing → shipped → delivered (کمیسیون آزاد می‌شود)
 *   → در هر مرحله: اعلان در چت خصوصی بله خریدار
 */
final class Orders
{
    public const STATUSES = [
        'awaiting_payment' => 'در انتظار پرداخت',
        'paid'             => 'پرداخت شد',
        'preparing'        => 'در حال آماده‌سازی',
        'shipped'          => 'ارسال شد',
        'delivered'        => 'تحویل شد',
        'canceled'         => 'لغو شد',
        'returned'         => 'مرجوع شد',
    ];

    public const PAYMENTS = [
        'unpaid'         => 'پرداخت‌نشده',
        'pending_review' => 'در انتظار بررسی رسید',
        'verified'       => 'پرداخت تاییدشده',
        'rejected'       => 'رسید رد شد',
    ];

    public static function commissionRate(): float
    {
        $r = (float)(Schema::getSetting('store_commission_rate', '10') ?: 10);
        return max(0.0, min(50.0, $r));
    }

    public static function shipping(): int
    {
        return max(0, (int)(Schema::getSetting('store_shipping', '0') ?: 0));
    }

    public static function newCode(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $code = 'BGM-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
            if (!DB::val("SELECT id FROM orders WHERE code=?", [$code])) {
                return $code;
            }
        }
        return 'BGM-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * ثبت سفارش
     * @param array{user_id:int,product_id:int,qty?:int,buyer_name?:string,buyer_phone?:string,address?:string,note?:string,ref_code?:string,notify?:bool} $in
     */
    public static function create(array $in): array
    {
        $userId    = (int)($in['user_id'] ?? 0);
        $productId = (int)($in['product_id'] ?? 0);
        $qty       = max(1, min(50, (int)($in['qty'] ?? 1)));
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'کاربر نامعتبر است.'];
        }
        $p = Store::product($productId);
        if (!$p || $p['status'] !== 'active') {
            return ['ok' => false, 'error' => 'کالا موجود یا فعال نیست.'];
        }
        if (Store::available($p) < $qty) {
            return ['ok' => false, 'error' => 'موجودی کافی نیست (موجود: ' . digits_fa(Store::available($p)) . ').'];
        }
        $price    = Store::price($p);
        $subtotal = $price * $qty;
        $shipping = self::shipping();

        // کد تخفیف (اختیاری)
        $discount   = 0;
        $coupon     = null;
        $couponCode = Coupon::normalize((string)($in['coupon'] ?? ''));
        if ($couponCode !== '') {
            $check = Coupon::validate($couponCode, [
                'user_id'    => $userId,
                'subtotal'   => $subtotal,
                'product_id' => $productId,
                'seller_id'  => (int)$p['seller_id'],
            ]);
            if (!$check['ok']) {
                return ['ok' => false, 'error' => (string)($check['error'] ?? 'کد تخفیف نامعتبر است.')];
            }
            $coupon   = $check['coupon'];
            $discount = (int)$check['discount'];
        }
        $total    = max(0, $subtotal - $discount) + $shipping;
        $rate     = self::commissionRate();
        $commission = (int)round($total * $rate / 100);

        // معرف (زیرمجموعه فروش)
        $affiliate = null;
        $refCode   = trim((string)($in['ref_code'] ?? ''));
        if ($refCode !== '') {
            $owner = DB::one("SELECT user_id FROM users WHERE referral_code=?", [preg_replace('/[^A-Za-z0-9]/', '', $refCode)]);
            if ($owner && (int)$owner['user_id'] !== $userId) {
                $affiliate = (int)$owner['user_id'];
            }
        }

        $now  = time();
        $code = self::newCode();
        DB::q("INSERT INTO orders (code,user_id,seller_id,ref_code,affiliate_user_id,status,payment_status,
                 subtotal,discount,shipping,total,commission_rate,commission_amount,commission_released,
                 coupon_id,coupon_code,buyer_name,buyer_phone,address,note,created_at,updated_at)
               VALUES (?,?,?,?,?, 'awaiting_payment', 'unpaid', ?,?,?,?,?,?,0, ?,?,?,?,?,?,?,?)",
            [
                $code, $userId, (int)$p['seller_id'] ?: null, $refCode ?: null, $affiliate,
                $subtotal, $discount, $shipping, $total, $rate, $commission,
                $coupon ? (int)$coupon['id'] : null, $couponCode !== '' ? $couponCode : null,
                str_limit((string)($in['buyer_name'] ?? ''), 118) ?: null,
                str_limit((string)($in['buyer_phone'] ?? ''), 30) ?: null,
                str_limit((string)($in['address'] ?? ''), 900) ?: null,
                str_limit((string)($in['note'] ?? ''), 250) ?: null,
                $now, $now,
            ]);
        $orderId = (int)DB::pdo()->lastInsertId();

        DB::q("INSERT INTO order_items (order_id,product_id,title_snapshot,price_snapshot,qty,total) VALUES (?,?,?,?,?,?)",
            [$orderId, $productId, (string)$p['title'], $price, $qty, $subtotal]);
        // ثبت استفاده از کد تخفیف (تا لغو سفارش)
        if ($coupon !== null && $discount > 0) {
            Coupon::recordUse((int)$coupon['id'], $userId, $orderId, $discount);
        }
        // رزرو موجودی
        DB::q("UPDATE products SET reserved=reserved+? WHERE id=?", [$qty, $productId]);
        DB::q("INSERT INTO payments (order_id,method,amount,status,created_at) VALUES (?, 'card', ?, 'pending', ?)",
            [$orderId, $total, $now]);

        Logger::user(null, $userId, null, 'order_create', $code . ' · ' . $p['title'] . ' × ' . $qty
            . ($discount > 0 ? ' · تخفیف ' . $discount . ' (' . $couponCode . ')' : ''));
        if (!empty($in['notify'])) {
            self::notify($orderId, 'create');
        }
        return [
            'ok' => true, 'id' => $orderId, 'code' => $code, 'total' => $total, 'qty' => $qty,
            'discount' => $discount, 'coupon' => $couponCode !== '' ? $couponCode : null,
        ];
    }

    public static function order(int $id): ?array
    {
        $o = DB::one("SELECT o.*, u.first_name, u.username FROM orders o LEFT JOIN users u ON u.user_id=o.user_id WHERE o.id=?", [$id]);
        if (!$o) {
            return null;
        }
        $o['items'] = DB::all("SELECT * FROM order_items WHERE order_id=? ORDER BY id", [$id]);
        $o['payment'] = DB::one("SELECT * FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1", [$id]);
        return $o;
    }

    public static function byCode(string $code): ?array
    {
        $id = (int)DB::val("SELECT id FROM orders WHERE code=?", [trim($code)], 0);
        return $id ? self::order($id) : null;
    }

    /** @return array{0:array<int,array<string,mixed>>,1:int} */
    public static function paginate(array $f, int $limit = 30, int $off = 0): array
    {
        $where = [];
        $args  = [];
        foreach (['status' => self::STATUSES, 'payment_status' => self::PAYMENTS] as $field => $map) {
            if (!empty($f[$field]) && isset($map[(string)$f[$field]])) {
                $where[] = "o.$field=?";
                $args[]  = (string)$f[$field];
            }
        }
        if (!empty($f['user_id'])) {
            $where[] = 'o.user_id=?';
            $args[]  = (int)$f['user_id'];
        }
        if (!empty($f['seller_id'])) {
            $where[] = 'o.seller_id=?';
            $args[]  = (int)$f['seller_id'];
        }
        if (!empty($f['q'])) {
            $where[] = '(o.code LIKE ? OR o.buyer_phone LIKE ? OR o.buyer_name LIKE ?)';
            $q = '%' . (string)$f['q'] . '%';
            array_push($args, $q, $q, $q);
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $rows = DB::all("SELECT o.*, u.first_name, u.username,
                           (SELECT title_snapshot FROM order_items i WHERE i.order_id=o.id LIMIT 1) AS item_title,
                           (SELECT SUM(qty) FROM order_items i WHERE i.order_id=o.id) AS qty
                         FROM orders o LEFT JOIN users u ON u.user_id=o.user_id
                         $w ORDER BY o.id DESC LIMIT $limit OFFSET $off", $args);
        $total = (int)DB::val("SELECT COUNT(*) FROM orders o $w", $args, 0);
        return [$rows, $total];
    }

    public static function forUser(int $userId, int $limit = 20): array
    {
        return DB::all("SELECT o.*, (SELECT title_snapshot FROM order_items i WHERE i.order_id=o.id LIMIT 1) AS item_title
                        FROM orders o WHERE o.user_id=? ORDER BY o.id DESC LIMIT " . max(1, min(100, $limit)), [$userId]);
    }

    /** آپلود/ثبت رسید پرداخت */
    public static function attachReceipt(int $orderId, string $path, int $amount = 0): array
    {
        $o = self::order($orderId);
        if (!$o) {
            return ['ok' => false, 'error' => 'سفارش پیدا نشد.'];
        }
        if (in_array($o['status'], ['canceled', 'returned'], true)) {
            return ['ok' => false, 'error' => 'این سفارش بسته شده است.'];
        }
        if ($o['payment_status'] === 'verified') {
            return ['ok' => false, 'error' => 'پرداخت این سفارش قبلاً تایید شده.'];
        }
        $amount = $amount > 0 ? $amount : (int)$o['total'];
        DB::q("UPDATE payments SET receipt_file=?, amount=?, status='pending', created_at=? WHERE order_id=? AND status<>'verified'",
            [str_limit($path, 250), $amount, time(), $orderId]);
        DB::q("UPDATE orders SET payment_status='pending_review', updated_at=? WHERE id=?", [time(), $orderId]);
        $admins = DB::all("SELECT bale_user_id FROM panel_admins WHERE is_active=1 AND bale_user_id IS NOT NULL");
        foreach ($admins as $a) {
            BaleApi::sendMessage((int)$a['bale_user_id'],
                "🧾 رسید جدید برای بررسی\nسفارش: <code>" . h((string)$o['code']) . "</code>\n"
                . "مبلغ: " . money($amount) . "\nپنل: " . url('panel/index.php', ['p' => 'orders']), null, null, 'HTML');
        }
        Logger::user(null, (int)$o['user_id'], null, 'order_receipt', (string)$o['code']);
        return ['ok' => true];
    }

    /**
     * تایید/رد پرداخت (ادمین)
     */
    public static function verifyPayment(int $orderId, bool $approve, ?int $adminId = null, string $note = ''): array
    {
        $o = self::order($orderId);
        if (!$o) {
            return ['ok' => false, 'error' => 'سفارش پیدا نشد.'];
        }
        if ($o['payment_status'] === 'verified' && $approve) {
            return ['ok' => false, 'error' => 'قبلاً تایید شده است.'];
        }
        $now = time();
        $paymentId = (int)($o['payment']['id'] ?? 0);
        if ($approve) {
            DB::q("UPDATE payments SET status='verified', verified_by=?, verified_at=?, note=? WHERE id=?",
                [$adminId, $now, str_limit($note, 250) ?: null, $paymentId]);
            DB::q("UPDATE orders SET payment_status='verified', status='paid', verified_by=?, verified_at=?, updated_at=? WHERE id=?",
                [$adminId, $now, $now, $orderId]);
            // کم کردن موجودی و رزرو + شمارنده فروش
            foreach ($o['items'] as $it) {
                $qty = (int)$it['qty'];
                DB::q("UPDATE products SET stock=GREATEST(stock-?,0), reserved=GREATEST(reserved-?,0), sold_count=sold_count+? WHERE id=?",
                    [$qty, $qty, $qty, (int)$it['product_id']]);
                DB::q("INSERT INTO stock_moves (product_id,delta,reason,ref_type,ref_id,admin_id,note,created_at)
                       VALUES (?,?, 'buy', 'order', ?, ?, ?, ?)",
                    [(int)$it['product_id'], -$qty, $orderId, $adminId, 'تایید پرداخت ' . $o['code'], $now]);
            }
        } else {
            DB::q("UPDATE payments SET status='rejected', verified_by=?, verified_at=?, note=? WHERE id=?",
                [$adminId, $now, str_limit($note, 250) ?: null, $paymentId]);
            DB::q("UPDATE orders SET payment_status='rejected', updated_at=? WHERE id=?", [$now, $orderId]);
        }
        Logger::audit($adminId, 'panel', $approve ? 'payment_verified' : 'payment_rejected',
            'order#' . $orderId, $o['code'] . ' · ' . money((int)$o['total']));
        self::notify($orderId, $approve ? 'paid' : 'payment_rejected');
        return ['ok' => true];
    }

    /**
     * تغییر وضعیت سفارش؛ در «تحویل شد» کمیسیون آزاد می‌شود
     */
    public static function setStatus(int $orderId, string $status, ?int $adminId = null, string $note = ''): array
    {
        if (!isset(self::STATUSES[$status])) {
            return ['ok' => false, 'error' => 'وضعیت نامعتبر.'];
        }
        $o = self::order($orderId);
        if (!$o) {
            return ['ok' => false, 'error' => 'سفارش پیدا نشد.'];
        }
        if ($status === $o['status']) {
            return ['ok' => true, 'unchanged' => true];
        }
        $now = time();
        DB::q("UPDATE orders SET status=?, updated_at=? WHERE id=?", [$status, $now, $orderId]);

        // آزادسازی کد تخفیف در لغو/مرجوعی
        if (in_array($status, ['canceled', 'returned'], true)) {
            Coupon::release($orderId);
        }

        // آزادسازی رزرو در لغو/مرجوعی
        if (in_array($status, ['canceled', 'returned'], true)) {
            foreach ($o['items'] as $it) {
                $qty = (int)$it['qty'];
                if ($o['payment_status'] === 'verified') {
                    // کالا برگشته به انبار
                    DB::q("UPDATE products SET stock=stock+?, sold_count=GREATEST(sold_count-?,0) WHERE id=?",
                        [$qty, $qty, (int)$it['product_id']]);
                    DB::q("INSERT INTO stock_moves (product_id,delta,reason,ref_type,ref_id,admin_id,note,created_at)
                           VALUES (?,?, 'return', 'order', ?, ?, ?, ?)",
                        [(int)$it['product_id'], $qty, $orderId, $adminId, $status, $now]);
                } else {
                    DB::q("UPDATE products SET reserved=GREATEST(reserved-?,0) WHERE id=?", [$qty, (int)$it['product_id']]);
                }
            }
        }

        // کمیسیون: فقط یک‌بار و فقط بعد از تحویل
        $commissionNote = '';
        if ($status === 'delivered' && (int)$o['commission_released'] === 0) {
            $aff = (int)($o['affiliate_user_id'] ?? 0);
            $amount = (int)$o['commission_amount'];
            if ($aff > 0 && $amount > 0) {
                Wallet::credit($aff, $amount, 'commission', (string)$o['code'],
                    'کمیسیون سفارش ' . $o['code'] . ' (' . digits_fa((float)$o['commission_rate']) . '٪)', $adminId);
                DB::q("UPDATE orders SET commission_released=1 WHERE id=?", [$orderId]);
                $commissionNote = ' · کمیسیون ' . money($amount) . ' به معرف واریز شد';
                if (BaleApi::hasToken()) {
                    BaleApi::sendMessage($aff, "🎉 <b>کمیسیون فروش</b>\nسفارش: <code>" . h((string)$o['code'])
                        . "</code>\nمبلغ: " . money($amount) . "\nموجودی کیف پول: " . money(Wallet::balance($aff))
                        . "\n" . url('shop/index.php', ['p' => 'wallet']), null, null, 'HTML');
                }
            }
        }
        if ($status === 'delivered' && $o['payment_status'] !== 'verified') {
            $commissionNote .= ' · توجه: پرداخت تایید نشده بود';
        }
        Logger::audit($adminId, 'panel', 'order_status', 'order#' . $orderId, $o['status'] . ' → ' . $status . ' ' . $note);
        self::notify($orderId, 'status', $status);
        if ($status === 'delivered' && (int)($o['reviewed'] ?? 0) === 0) {
            self::notify($orderId, 'review');
        }
        return ['ok' => true, 'commission' => $commissionNote];
    }

    /** اعلان وضعیت به خریدار در بله */
    public static function notify(int $orderId, string $kind, string $status = ''): void
    {
        if (!BaleApi::hasToken()) {
            return;
        }
        $o = self::order($orderId);
        if (!$o) {
            return;
        }
        $item = $o['items'][0]['title_snapshot'] ?? '';
        $link = url('shop/index.php', ['p' => 'order', 'code' => $o['code']]);
        $card = (string)(Schema::getSetting('store_card_number', '') ?: '');
        $name = (string)(Schema::getSetting('store_card_name', '') ?: '');
        switch ($kind) {
            case 'create':
                $text = "🛒 <b>سفارش ثبت شد</b>\nکد: <code>" . h((string)$o['code']) . "</code>\n"
                    . "کالا: " . h((string)$item) . " × " . digits_fa((int)($o['items'][0]['qty'] ?? 1)) . "\n"
                    . ((int)($o['discount'] ?? 0) > 0
                        ? "تخفیف (" . h((string)($o['coupon_code'] ?? '')) . "): -" . money((int)$o['discount']) . "\n" : '')
                    . "مبلغ: " . money((int)$o['total']) . "\n\n"
                    . ($card !== '' ? "💳 پرداخت کارت‌به‌کارت:\n<code>" . h($card) . "</code>\n" . ($name !== '' ? h($name) . "\n" : '') . "\n" : '')
                    . "رسید را از پنل آپلود کن:\n" . $link;
                break;
            case 'paid':
                $text = "✅ پرداخت سفارش <code>" . h((string)$o['code']) . "</code> تایید شد. کالا در حال آماده‌سازی است.";
                break;
            case 'review':
                $pid = (int)($o['items'][0]['product_id'] ?? 0);
                $text = "⭐ <b>نظرت برای ما ارزشمند است</b>\n"
                    . "سفارش <code>" . h((string)$o['code']) . "</code> تحویل شد.\n"
                    . "با ثبت امتیاز (۱ تا ۵ ستاره) به بقیه خریدارها کمک کن:\n"
                    . url('shop/index.php', ['p' => 'review', 'code' => (string)$o['code'], 'id' => $pid]);
                break;
            case 'payment_rejected':
                $text = "❌ رسید سفارش <code>" . h((string)$o['code']) . "</code> تایید نشد. لطفاً رسید درست را آپلود کن یا با پشتیبانی تماس بگیر.";
                break;
            default:
                $text = "📦 وضعیت سفارش <code>" . h((string)$o['code']) . "</code>:\n<b>"
                    . h(self::STATUSES[$status] ?? $status) . "</b>";
        }
        BaleApi::sendMessage((int)$o['user_id'], $text, null, null, 'HTML');
    }

    public static function stats(?int $sellerId = null, int $days = 30): array
    {
        $w = $sellerId ? 'AND seller_id=' . (int)$sellerId : '';
        $from = time() - $days * 86400;
        return [
            'today_count' => (int)DB::val("SELECT COUNT(*) FROM orders WHERE created_at >= ? $w", [strtotime('today')], 0),
            'today_sum'   => (int)DB::val("SELECT COALESCE(SUM(total),0) FROM orders WHERE created_at >= ? AND payment_status='verified' $w", [strtotime('today')], 0),
            'period_sum'  => (int)DB::val("SELECT COALESCE(SUM(total),0) FROM orders WHERE created_at >= ? AND payment_status='verified' $w", [$from], 0),
            'period_cnt'  => (int)DB::val("SELECT COUNT(*) FROM orders WHERE created_at >= ? $w", [$from], 0),
            'pending'     => (int)DB::val("SELECT COUNT(*) FROM orders WHERE payment_status='pending_review'", [], 0),
            'unpaid'      => (int)DB::val("SELECT COUNT(*) FROM orders WHERE status='awaiting_payment' $w", [], 0),
            'to_ship'     => (int)DB::val("SELECT COUNT(*) FROM orders WHERE status IN ('paid','preparing') $w", [], 0),
            'delivered'   => (int)DB::val("SELECT COUNT(*) FROM orders WHERE status='delivered' $w", [], 0),
        ];
    }

    /** نمودار فروش روزانه */
    public static function salesSeries(int $days = 14, ?int $sellerId = null): array
    {
        $labels = [];
        $amount = [];
        $count  = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime('-' . $i . ' days'));
            $labels[] = date('m/d', strtotime($d));
            $row = DB::one("SELECT COUNT(*) c, COALESCE(SUM(CASE WHEN payment_status='verified' THEN total ELSE 0 END),0) s
                            FROM orders WHERE DATE(FROM_UNIXTIME(created_at))=?" . ($sellerId ? ' AND seller_id=' . (int)$sellerId : ''),
                [$d]);
            $amount[] = (int)($row['s'] ?? 0);
            $count[]  = (int)($row['c'] ?? 0);
        }
        return ['labels' => $labels, 'amount' => $amount, 'count' => $count];
    }

    public static function topProducts(int $limit = 5): array
    {
        return DB::all("SELECT p.title, p.code, p.sold_count, COALESCE(SUM(i.total),0) revenue
                        FROM order_items i JOIN orders o ON o.id=i.order_id
                        JOIN products p ON p.id=i.product_id
                        WHERE o.payment_status='verified'
                        GROUP BY p.id ORDER BY revenue DESC LIMIT " . max(1, min(50, $limit)));
    }
}
