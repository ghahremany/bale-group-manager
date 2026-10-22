<?php
declare(strict_types=1);

/**
 * ورود پنل فروشگاه فقط با لینک یک‌بارمصرف ارسال‌شده در چت خصوصی بله
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

security_headers(true);
Auth::boot();

if (ShopAuth::check()) {
    redirect('shop/index.php');
}

$flash = Auth::takeFlashes();

// ورود کاربر از طریق پارامتر start بله (ربات این را می‌سازد)
$token = (string)($_GET['t'] ?? '');
if ($token !== '') {
    $u = ShopAuth::verifyToken($token);
    if ($u) {
        ShopAuth::login((int)$u['user_id']);
        $redir = (string)($_SESSION['shop_redirect'] ?? '');
        unset($_SESSION['shop_redirect']);
        Auth::flash('ok', 'خوش آمدی ' . ShopAuth::displayName($u) . '!');
        if ($redir !== '' && str_starts_with($redir, '/shop/')) {
            header('Location: ' . $redir);
            exit;
        }
        redirect('shop/index.php');
    }
    $flash[] = ['type' => 'err', 'msg' => 'لینک نامعتبر یا منقضی شده است. دوباره از ربات درخواست کن.'];
}

View::render('shop/login', ['flash' => $flash], 'auth');
