<?php
declare(strict_types=1);

/**
 * پنل فروشگاه (کاربران) — /shop/
 * ورود فقط با لینک یک‌بارمصرف بله؛ فروشنده بعد از تایید ادمین می‌تواند کالا اضافه کند.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

security_headers(true);
Auth::boot();

$user = ShopAuth::require();

// ---------- عملیات POST ----------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Auth::checkCsrf();
    $a = (string)input('a', '');

    if ($a === 'seller_request') {
        $r = Seller::request((int)$user['user_id'], (string)input('shop_name', ''), (string)input('phone', ''));
        Auth::flash($r['ok'] ? 'ok' : 'err', $r['ok']
            ? ((string)($r['note'] ?? 'درخواست ثبت شد؛ منتظر تایید ادمین باش.'))
            : (string)($r['error'] ?? 'ناموفق'));
        redirect('shop/index.php', ['p' => 'seller']);
    }

    if ($a === 'product_save') {
        $seller = Seller::approved((int)$user['user_id']);
        if (!$seller) {
            Auth::flash('err', 'برای افزودن کالا باید فروشنده تاییدشده باشی.');
            redirect('shop/index.php', ['p' => 'seller']);
        }
        $res = Store::save($_POST, (int)$seller['id'], null);
        if ($res['ok']) {
            Auth::flash('ok', 'کالا ذخیره شد' . (isset($res['code']) ? ' (کد: ' . $res['code'] . ')' : '')
                . ' — بعد از تایید ادمین در فروشگاه نمایش داده می‌شود.');
            redirect('shop/index.php', ['p' => 'products']);
        }
        Auth::flash('err', (string)($res['error'] ?? 'خطا در ذخیره'));
        redirect('shop/index.php', ['p' => 'product', 'id' => (int)input('id', 0)]);
    }

    if ($a === 'buy') {
        $p = Store::product((int)input('product_id', 0));
        if (!$p) {
            Auth::flash('err', 'کالا پیدا نشد.');
            redirect('shop/index.php');
        }
        $res = Orders::create([
            'user_id'     => (int)$user['user_id'],
            'product_id'  => (int)$p['id'],
            'qty'         => (int)input('qty', 1),
            'buyer_name'  => (string)input('buyer_name', ShopAuth::displayName($user)),
            'buyer_phone' => (string)input('buyer_phone', ''),
            'address'     => (string)input('address', ''),
            'note'        => (string)input('note', ''),
            'ref_code'    => (string)($user['referred_by'] ? '' : ''),
            'notify'      => true,
        ]);
        if (!$res['ok']) {
            Auth::flash('err', (string)$res['error']);
            redirect('shop/index.php', ['p' => 'product_view', 'id' => (int)$p['id']]);
        }
        Auth::flash('ok', 'سفارش ' . $res['code'] . ' ثبت شد. مبلغ: ' . money((int)$res['total'])
            . ' — لطفاً رسید پرداخت را آپلود کن.');
        redirect('shop/index.php', ['p' => 'order', 'code' => $res['code']]);
    }

    if ($a === 'receipt') {
        $code  = (string)input('code', '');
        $order = Orders::byCode($code);
        if (!$order || (int)$order['user_id'] !== (int)$user['user_id']) {
            Auth::flash('err', 'سفارش پیدا نشد.');
            redirect('shop/index.php', ['p' => 'orders']);
        }
        if (empty($_FILES['receipt']['tmp_name']) || (int)($_FILES['receipt']['error'] ?? 1) !== 0) {
            Auth::flash('err', 'فایل رسید انتخاب نشد.');
            redirect('shop/index.php', ['p' => 'order', 'code' => $code]);
        }
        $size = (int)($_FILES['receipt']['size'] ?? 0);
        $ext  = strtolower(pathinfo((string)($_FILES['receipt']['name'] ?? ''), PATHINFO_EXTENSION));
        if ($size > 5 * 1024 * 1024 || !in_array($ext, ['jpg', 'jpeg', 'png', 'pdf', 'webp'], true)) {
            Auth::flash('err', 'فقط تصویر/PDF تا ۵ مگابایت پذیرفته می‌شود.');
            redirect('shop/index.php', ['p' => 'order', 'code' => $code]);
        }
        $dir = BGM_ROOT . '/storage/receipts';
        ensure_dir($dir);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        $name = 'receipt-' . (int)$order['id'] . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
        if (!move_uploaded_file((string)$_FILES['receipt']['tmp_name'], $dir . '/' . $name)) {
            Auth::flash('err', 'انتقال فایل ناموفق بود.');
            redirect('shop/index.php', ['p' => 'order', 'code' => $code]);
        }
        $r = Orders::attachReceipt((int)$order['id'], $name, (int)input('amount', 0));
        Auth::flash($r['ok'] ? 'ok' : 'err', $r['ok'] ? 'رسید ثبت شد و در انتظار تایید است.' : (string)$r['error']);
        redirect('shop/index.php', ['p' => 'order', 'code' => $code]);
    }

    if ($a === 'withdraw') {
        $r = Wallet::requestWithdraw((int)$user['user_id'], (int)input('amount', 0), (string)input('card', ''));
        Auth::flash($r['ok'] ? 'ok' : 'err', $r['ok']
            ? 'درخواست برداشت ثبت شد؛ بعد از تایید، مبلغ به کارت واریز می‌شود.'
            : (string)($r['error'] ?? 'ناموفق'));
        redirect('shop/index.php', ['p' => 'wallet']);
    }

    redirect('shop/index.php');
}

// ---------- مسیرها ----------
$page  = (string)($_GET['p'] ?? 'dashboard');
$title = 'فروشگاه';
$data  = [];
$myId  = (int)$user['user_id'];
$seller = Seller::forUser($myId);
$super  = false;

switch ($page) {
    case 'products':
        $sellerApproved = Seller::approved($myId);
        if (!$sellerApproved) {
            redirect('shop/index.php', ['p' => 'seller']);
        }
        [$rows, $total] = Store::paginate(['seller_id' => (int)$sellerApproved['id']], 50, 0);
        $data += ['products' => $rows, 'total' => $total, 'seller' => $sellerApproved];
        $title = 'کالاهای من';
        break;

    case 'product':
        $sellerApproved = Seller::approved($myId);
        if (!$sellerApproved) {
            redirect('shop/index.php', ['p' => 'seller']);
        }
        $id  = (int)($_GET['id'] ?? 0);
        $row = $id ? Store::product($id) : null;
        if ($row && (int)$row['seller_id'] !== (int)$sellerApproved['id']) {
            Auth::flash('err', 'این کالا متعلق به شما نیست.');
            redirect('shop/index.php', ['p' => 'products']);
        }
        $data += ['product' => $row, 'categories' => Store::categories(true), 'seller' => $sellerApproved];
        $title = $row ? 'ویرایش کالا' : 'کالای جدید';
        break;

    case 'product_view':
        $p = Store::product((int)($_GET['id'] ?? 0));
        if (!$p) {
            redirect('shop/index.php');
        }
        DB::q("UPDATE products SET views=views+1 WHERE id=?", [(int)$p['id']]);
        $data += ['product' => $p, 'me' => $user];
        $title = (string)$p['title'];
        break;

    case 'orders':
        $data += ['orders' => Orders::forUser($myId, 40)];
        $title = 'سفارش‌های من';
        break;

    case 'order':
        $o = Orders::byCode((string)($_GET['code'] ?? ''));
        if (!$o || (int)$o['user_id'] !== $myId) {
            Auth::flash('err', 'سفارش پیدا نشد.');
            redirect('shop/index.php', ['p' => 'orders']);
        }
        $data += ['order' => $o, 'card' => (string)Schema::getSetting('store_card_number', '')];
        $title = 'سفارش ' . (string)$o['code'];
        break;

    case 'wallet':
        $data += [
            'balance' => Wallet::balance($myId),
            'tx'      => Wallet::tx($myId, 40),
            'min'     => (int)(Schema::getSetting('store_min_withdraw', '50000') ?: 50000),
            'withdrawals' => DB::all("SELECT * FROM withdraw_requests WHERE user_id=? ORDER BY id DESC LIMIT 20", [$myId]),
        ];
        $title = 'کیف پول و کمیسیون';
        break;

    case 'invite':
        $data += ['stats' => Referral::stats($myId), 'children' => Referral::children($myId, 30)];
        $title = 'دعوت و زیرمجموعه فروش';
        break;

    case 'seller':
        $data += ['seller' => $seller];
        $title = 'فروشندگی';
        break;

    case 'dashboard':
    default:
        $data += [
            'seller'      => $seller,
            'orders'      => Orders::forUser($myId, 5),
            'balance'     => Wallet::balance($myId),
            'stats'       => $seller ? Store::stats((int)$seller['id']) : null,
            'products'    => Store::active(6),
            'refStats'    => Referral::stats($myId),
            'orderStats'  => Orders::stats(null, 30),
        ];
        $title = 'داشبورد فروشگاه';
        break;
}

$viewMap = ['product' => 'product_form'];
View::render('shop/' . ($viewMap[$page] ?? $page), $data + [
    'page' => $page, 'title' => $title, 'user' => $user, 'seller' => $seller ?? null, 'me' => $user,
], 'shop/layout');
