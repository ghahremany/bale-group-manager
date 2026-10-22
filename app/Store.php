<?php
declare(strict_types=1);

/**
 * انبار و کالا (فروشگاه نسخه ۱.۳)
 *
 * - کالا با موجودی، قیمت، تخفیف و کد کوتاه برای لینک خرید
 * - هر تغییر موجودی در stock_moves ثبت می‌شود (قابل حسابرسی)
 * - کمپین تبلیغ کالا با موتور صف موجود (Broadcast) اجرا می‌شود
 */
final class Store
{
    public const STATUSES = [
        'draft'    => 'پیش‌نویس',
        'pending'  => 'در انتظار تایید',
        'active'   => 'فعال',
        'rejected' => 'ردشده',
        'archived' => 'بایگانی',
    ];

    // ---------------- دسته‌بندی ----------------

    /** @return array<int,array<string,mixed>> */
    public static function categories(bool $onlyActive = false): array
    {
        $where = $onlyActive ? 'WHERE is_active=1' : '';
        return DB::all("SELECT * FROM categories $where ORDER BY sort, id");
    }

    public static function saveCategory(array $in): array
    {
        $name = trim((string)($in['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'error' => 'نام دسته خالی است.'];
        }
        $id   = (int)($in['id'] ?? 0);
        $slug = trim((string)($in['slug'] ?? ''));
        if ($slug === '') {
            $slug = self::slugify($name);
        }
        $slug = self::uniqueSlug('categories', $slug, $id);
        if ($id > 0) {
            DB::q("UPDATE categories SET name=?, slug=?, parent_id=?, sort=?, is_active=? WHERE id=?",
                [$name, $slug, (int)($in['parent_id'] ?? 0) ?: null, (int)($in['sort'] ?? 0),
                 !empty($in['is_active']) ? 1 : 0, $id]);
            return ['ok' => true, 'id' => $id];
        }
        DB::q("INSERT INTO categories (name,slug,parent_id,sort,is_active) VALUES (?,?,?,?,?)",
            [$name, $slug, (int)($in['parent_id'] ?? 0) ?: null, (int)($in['sort'] ?? 0), !empty($in['is_active']) ? 1 : 0]);
        return ['ok' => true, 'id' => (int)DB::pdo()->lastInsertId()];
    }

    // ---------------- کالا ----------------

    public static function price(array $p): int
    {
        $d = (int)($p['discount_price'] ?? 0);
        $p0 = (int)($p['price'] ?? 0);
        return ($d > 0 && $d < $p0) ? $d : $p0;
    }

    public static function available(array $p): int
    {
        return max(0, (int)$p['stock'] - (int)$p['reserved']);
    }

    public static function byCode(string $code): ?array
    {
        $p = DB::one("SELECT * FROM products WHERE code=?", [strtoupper(trim($code))]);
        return $p ?: null;
    }

    public static function product(int $id): ?array
    {
        $p = DB::one("SELECT p.*, s.shop_name, s.user_id AS seller_user, c.name AS category_name
                      FROM products p LEFT JOIN sellers s ON s.id=p.seller_id
                      LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=?", [$id]);
        return $p ?: null;
    }

    /**
     * ثبت/ویرایش کالا
     * @param array<string,mixed> $in
     */
    public static function save(array $in, ?int $sellerId = null, ?int $adminId = null): array
    {
        $title = trim((string)($in['title'] ?? ''));
        if (mb_strlen($title) < 3) {
            return ['ok' => false, 'error' => 'عنوان کالا حداقل ۳ کاراکتر باشد.'];
        }
        $price = max(0, (int)($in['price'] ?? 0));
        $disc  = max(0, (int)($in['discount_price'] ?? 0));
        if ($disc > 0 && $disc >= $price) {
            return ['ok' => false, 'error' => 'قیمت با تخفیف باید کمتر از قیمت اصلی باشد.'];
        }
        $stock = (int)($in['stock'] ?? 0);
        $id    = (int)($in['id'] ?? 0);
        $now   = time();

        $status = (string)($in['status'] ?? '');
        if (!isset(self::STATUSES[$status])) {
            $status = $adminId ? 'active' : 'pending';
        }
        // فروشنده فقط می‌تواند کالای خودش را ویرایش کند
        if ($id > 0) {
            $old = DB::one("SELECT * FROM products WHERE id=?", [$id]);
            if (!$old) {
                return ['ok' => false, 'error' => 'کالا پیدا نشد.'];
            }
            if ($sellerId !== null && (int)$old['seller_id'] !== $sellerId) {
                return ['ok' => false, 'error' => 'این کالا متعلق به شما نیست.'];
            }
            if ($sellerId !== null && !$adminId) {
                $status = $old['status'] === 'active' ? 'active' : 'pending';
            }
            DB::q("UPDATE products SET category_id=?, sku=?, title=?, description=?, price=?, discount_price=?,
                     min_stock=?, unit=?, image=?, status=?, updated_at=? WHERE id=?",
                [
                    (int)($in['category_id'] ?? 0) ?: null, str_limit((string)($in['sku'] ?? ''), 38) ?: null,
                    $title, (string)($in['description'] ?? ''), $price, $disc ?: null,
                    (int)($in['min_stock'] ?? 3), str_limit((string)($in['unit'] ?? 'عدد'), 18),
                    str_limit((string)($in['image'] ?? ''), 250) ?: null, $status, $now, $id,
                ]);
            $delta = $stock - (int)$old['stock'];
            if ($delta !== 0) {
                self::adjustStock($id, $delta, 'restock', [], $adminId, 'ویرایش کالا');
            }
            Logger::audit($adminId, (string)($in['actor'] ?? 'seller'), 'product_update', 'p#' . $id, $title);
            return ['ok' => true, 'id' => $id];
        }

        $slug = self::uniqueSlug('products', self::slugify($title), 0);
        DB::q("INSERT INTO products (seller_id,category_id,sku,title,slug,description,price,discount_price,
                 stock,reserved,min_stock,unit,image,code,status,created_at,updated_at)
               VALUES (?,?,?,?,?,?,?,?,0,0,?,?,?,?,?,?,?)",
            [
                $sellerId, (int)($in['category_id'] ?? 0) ?: null, str_limit((string)($in['sku'] ?? ''), 38) ?: null,
                $title, $slug, (string)($in['description'] ?? ''), $price, $disc ?: null,
                (int)($in['min_stock'] ?? 3), str_limit((string)($in['unit'] ?? 'عدد'), 18),
                str_limit((string)($in['image'] ?? ''), 250) ?: null,
                self::genCode(), $status, $now, $now,
            ]);
        $newId = (int)DB::pdo()->lastInsertId();
        if ($stock !== 0) {
            self::adjustStock($newId, $stock, 'initial', [], $adminId, 'موجودی اولیه');
        }
        Logger::audit($adminId, (string)($in['actor'] ?? 'seller'), 'product_create', 'p#' . $newId, $title);
        return ['ok' => true, 'id' => $newId, 'code' => (string)DB::val("SELECT code FROM products WHERE id=?", [$newId])];
    }

    public static function setStatus(int $id, string $status, ?int $adminId = null, string $note = ''): bool
    {
        if (!isset(self::STATUSES[$status])) {
            return false;
        }
        $p = DB::one("SELECT title, status FROM products WHERE id=?", [$id]);
        if (!$p) {
            return false;
        }
        DB::q("UPDATE products SET status=?, updated_at=? WHERE id=?", [$status, time(), $id]);
        Logger::audit($adminId, 'panel', 'product_status', 'p#' . $id, $p['status'] . ' → ' . $status . ' ' . $note);
        return true;
    }

    /** تغییر موجودی + ثبت گردش */
    public static function adjustStock(int $productId, int $delta, string $reason, array $ref = [], ?int $adminId = null, string $note = ''): bool
    {
        if ($delta === 0) {
            return true;
        }
        $p = DB::one("SELECT id,stock FROM products WHERE id=?", [$productId]);
        if (!$p) {
            return false;
        }
        $new = (int)$p['stock'] + $delta;
        if ($new < 0) {
            return false;
        }
        DB::q("UPDATE products SET stock=?, updated_at=? WHERE id=?", [$new, time(), $productId]);
        DB::q("INSERT INTO stock_moves (product_id,delta,reason,ref_type,ref_id,admin_id,note,created_at)
               VALUES (?,?,?,?,?,?,?,?)",
            [$productId, $delta, str_limit($reason, 22), str_limit((string)($ref['type'] ?? ''), 14) ?: null,
             isset($ref['id']) ? (int)$ref['id'] : null, $adminId, str_limit($note, 250) ?: null, time()]);
        return true;
    }

    /**
     * @param array{status?:string,seller_id?:int,category_id?:int,q?:string,low?:bool} $f
     * @return array{0:array<int,array<string,mixed>>,1:int}
     */
    public static function paginate(array $f, int $limit = 30, int $off = 0): array
    {
        $where = [];
        $args  = [];
        if (!empty($f['status']) && isset(self::STATUSES[(string)$f['status']])) {
            $where[] = 'p.status=?';
            $args[]  = (string)$f['status'];
        }
        if (!empty($f['seller_id'])) {
            $where[] = 'p.seller_id=?';
            $args[]  = (int)$f['seller_id'];
        }
        if (!empty($f['category_id'])) {
            $where[] = 'p.category_id=?';
            $args[]  = (int)$f['category_id'];
        }
        if (!empty($f['q'])) {
            $where[] = '(p.title LIKE ? OR p.sku LIKE ? OR p.code LIKE ?)';
            $q = '%' . (string)$f['q'] . '%';
            array_push($args, $q, $q, $q);
        }
        if (!empty($f['low'])) {
            $where[] = '(p.stock - p.reserved) <= p.min_stock';
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $rows = DB::all("SELECT p.*, s.shop_name, c.name AS category_name
                         FROM products p LEFT JOIN sellers s ON s.id=p.seller_id
                         LEFT JOIN categories c ON c.id=p.category_id
                         $w ORDER BY p.id DESC LIMIT $limit OFFSET $off", $args);
        $total = (int)DB::val("SELECT COUNT(*) FROM products p $w", $args, 0);
        return [$rows, $total];
    }

    public static function lowStock(int $limit = 20): array
    {
        return DB::all("SELECT p.*, s.shop_name FROM products p LEFT JOIN sellers s ON s.id=p.seller_id
                        WHERE p.status='active' AND (p.stock - p.reserved) <= p.min_stock
                        ORDER BY (p.stock - p.reserved) ASC LIMIT " . max(1, min(200, $limit)));
    }

    /** کالاهای قابل نمایش در ربات */
    public static function active(int $limit = 10): array
    {
        return DB::all("SELECT * FROM products WHERE status='active' AND (stock - reserved) > 0
                        ORDER BY sold_count DESC, id DESC LIMIT " . max(1, min(50, $limit)));
    }

    public static function stats(?int $sellerId = null): array
    {
        $w    = $sellerId ? 'WHERE seller_id=' . (int)$sellerId : '';
        $wAnd = $sellerId ? 'AND seller_id=' . (int)$sellerId : '';
        return [
            'total'     => (int)DB::val("SELECT COUNT(*) FROM products $w", [], 0),
            'active'    => (int)DB::val("SELECT COUNT(*) FROM products WHERE status='active' $wAnd", [], 0),
            'pending'   => (int)DB::val("SELECT COUNT(*) FROM products WHERE status='pending' $wAnd", [], 0),
            'out'       => (int)DB::val("SELECT COUNT(*) FROM products WHERE (stock-reserved) <= 0 $wAnd", [], 0),
            'low'       => (int)DB::val("SELECT COUNT(*) FROM products WHERE status='active' AND (stock-reserved) <= min_stock $wAnd", [], 0),
            'stock_sum' => (int)DB::val("SELECT COALESCE(SUM(stock-reserved),0) FROM products $w", [], 0),
            'value'     => (int)DB::val("SELECT COALESCE(SUM((stock-reserved)*COALESCE(NULLIF(discount_price,0),price)),0) FROM products $w", [], 0),
        ];
    }

    // ---------------- کمپین تبلیغ ----------------

    public static function campaigns(int $limit = 50): array
    {
        return DB::all("SELECT c.*, p.title AS product_title, p.code AS product_code, p.status AS product_status
                        FROM promo_campaigns c LEFT JOIN products p ON p.id=c.product_id
                        ORDER BY c.id DESC LIMIT " . max(1, min(200, $limit)));
    }

    public static function saveCampaign(array $in, ?int $adminId = null): array
    {
        $productId = (int)($in['product_id'] ?? 0);
        $p = self::product($productId);
        if (!$p) {
            return ['ok' => false, 'error' => 'کالا پیدا نشد.'];
        }
        $template = trim((string)($in['template'] ?? ''));
        if ($template === '') {
            $template = "🛍 <b>{title}</b>\n\n{description}\n\n💰 قیمت: {price}\n📦 موجودی: {stock} {unit}\n\n🛒 خرید: {link}";
        }
        $interval = max(1, min(720, (int)($in['interval_hours'] ?? 24)));
        $audience = (string)($in['audience'] ?? 'groups');
        if (!isset(Broadcast::audiences()[$audience])) {
            $audience = 'groups';
        }
        $now = time();
        $id  = (int)($in['id'] ?? 0);
        if ($id > 0) {
            DB::q("UPDATE promo_campaigns SET product_id=?, title=?, template=?, audience=?, interval_hours=?,
                     status='active', next_run_at=? WHERE id=?",
                [$productId, str_limit((string)($in['title'] ?? $p['title']), 118), $template, $audience,
                 $interval, $now + (int)($in['delay_minutes'] ?? 0) * 60, $id]);
            Logger::audit($adminId, 'panel', 'promo_update', 'campaign#' . $id, (string)$p['title']);
            return ['ok' => true, 'id' => $id];
        }
        DB::q("INSERT INTO promo_campaigns (product_id,title,template,audience,interval_hours,next_run_at,status,created_at)
               VALUES (?,?,?,?,?,?, 'active', ?)",
            [$productId, str_limit((string)($in['title'] ?? $p['title']), 118), $template, $audience, $interval,
             $now + (int)($in['delay_minutes'] ?? 0) * 60, $now]);
        $newId = (int)DB::pdo()->lastInsertId();
        Logger::audit($adminId, 'panel', 'promo_create', 'campaign#' . $newId, (string)$p['title']);
        return ['ok' => true, 'id' => $newId];
    }

    public static function toggleCampaign(int $id, bool $active, ?int $adminId = null): bool
    {
        $c = DB::one("SELECT * FROM promo_campaigns WHERE id=?", [$id]);
        if (!$c) {
            return false;
        }
        DB::q("UPDATE promo_campaigns SET status=?, next_run_at=? WHERE id=?",
            [$active ? 'active' : 'paused', $active ? time() : 0, $id]);
        Logger::audit($adminId, 'panel', $active ? 'promo_resume' : 'promo_pause', 'campaign#' . $id);
        return true;
    }

    /** متن تبلیغ برای یک کالا و یک گروه */
    public static function renderPromo(array $campaign, array $product, int $chatId = 0): string
    {
        $link  = self::buyLink($product);
        $price = self::price($product);
        $map = [
            '{title}'       => (string)$product['title'],
            '{description}' => str_limit((string)($product['description'] ?? ''), 800),
            '{price}'       => money($price),
            '{old_price}'   => money((int)$product['price']),
            '{stock}'       => digits_fa(self::available($product)),
            '{unit}'        => (string)($product['unit'] ?: 'عدد'),
            '{code}'        => (string)$product['code'],
            '{link}'        => $link,
            '{panel}'       => url('shop/'),
            '{shop}'        => (string)(DB::val("SELECT shop_name FROM sellers WHERE id=?", [(int)$product['seller_id']], null) ?? config('app.name', '')),
            '{group}'       => (string)(DB::val("SELECT title FROM chat_settings WHERE chat_id=?", [$chatId], null) ?? ''),
        ];
        return strtr((string)$campaign['template'], $map);
    }

    public static function buyLink(array $product): string
    {
        $bot = (string)(Schema::getSetting('bot_username', (string)config('bot.username', '')) ?: '');
        $bot = ltrim($bot !== '' ? $bot : 'bot', '@');
        return "https://ble.ir/{$bot}?start=buy_" . $product['code'];
    }

    /**
     * اجرای کمپین‌های سررسیده (از کرون) — هر کمپین یک دسته در صف ارسال
     * @return array<int,array<string,mixed>>
     */
    public static function runDueCampaigns(int $limit = 5): array
    {
        $rows = DB::all("SELECT * FROM promo_campaigns WHERE status='active' AND next_run_at <= ? ORDER BY next_run_at LIMIT " . max(1, $limit), [time()]);
        $out = [];
        foreach ($rows as $c) {
            $p = self::product((int)$c['product_id']);
            if (!$p || $p['status'] !== 'active' || self::available($p) <= 0) {
                DB::q("UPDATE promo_campaigns SET status='paused' WHERE id=?", [(int)$c['id']]);
                $out[] = ['campaign' => (int)$c['id'], 'skipped' => 'product inactive/out of stock'];
                continue;
            }
            $text = self::renderPromo($c, $p);
            $res  = Broadcast::enqueue([
                'text'     => $text,
                'title'    => 'تبلیغ: ' . mb_strimwidth((string)$p['title'], 0, 50, '…'),
                'audience' => (string)$c['audience'],
                'rate'     => 12,
            ]);
            if ($res['ok']) {
                DB::q("UPDATE promo_campaigns SET next_run_at=?, last_batch_id=?, sent_count=sent_count+1 WHERE id=?",
                    [time() + (int)$c['interval_hours'] * 3600, (int)$res['batch'], (int)$c['id']]);
                $out[] = ['campaign' => (int)$c['id'], 'batch' => (int)$res['batch'], 'targets' => (int)$res['queued']];
            } else {
                $out[] = ['campaign' => (int)$c['id'], 'error' => (string)($res['error'] ?? '')];
            }
        }
        return $out;
    }

    // ---------------- کمکی ----------------

    public static function slugify(string $text): string
    {
        $text = trim($text);
        $text = str_replace(['‌', ' ', '—', '–', '/', '\\'], '-', $text);
        $text = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $text) ?? '';
        $text = preg_replace('/-+/', '-', $text) ?? '';
        return trim($text, '-') !== '' ? trim($text, '-') : 'item';
    }

    private static function uniqueSlug(string $table, string $slug, int $ignoreId): string
    {
        $base = $slug;
        $i    = 2;
        while (true) {
            $row = DB::val("SELECT id FROM $table WHERE slug=? AND id<>?", [$slug, $ignoreId]);
            if (!$row) {
                return $slug;
            }
            $slug = $base . '-' . $i++;
            if ($i > 200) {
                return $base . '-' . random_int(1000, 9999);
            }
        }
    }

    public static function genCode(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            if (!DB::val("SELECT id FROM products WHERE code=?", [$code])) {
                return $code;
            }
        }
        return strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
    }
}
