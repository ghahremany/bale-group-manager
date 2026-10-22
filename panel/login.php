<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

security_headers(true);
Auth::boot();

if (Auth::current() !== null) {
    redirect('panel/index.php', ['p' => 'dashboard']);
}

$flash = Auth::takeFlashes();
$mode  = 'login';

// ---- ورود با لینک یک‌بارمصرف ----
$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
if ($token !== '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $admin = Login::verifyToken($token);
    if ($admin) {
        Auth::login($admin);
        Auth::flash('ok', 'خوش آمدی! ورود با لینک یک‌بارمصرف انجام شد.');
        redirect('panel/index.php', ['p' => 'dashboard']);
    }
    $flash[] = ['type' => 'err', 'msg' => 'لینک نامعتبر یا منقضی شده است. دوباره وارد شو.'];
}

$pendingId = (int)($_SESSION['pending_admin'] ?? 0);

// ---- مرحله ۱: نام کاربری و رمز ----
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['step'] ?? '') === 'password') {
    Auth::checkCsrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $ip       = client_ip();

    if (RateLimit::loginBlocked($ip, $username)) {
        Events::emit('security.login_locked', ['detail' => 'user=' . str_limit($username, 40)], 'critical');
        $flash[] = ['type' => 'err', 'msg' => 'تلاش‌های ناموفق زیاد بود. چند دقیقه بعد تلاش کن.'];
    } else {
        $row = DB::one("SELECT * FROM panel_admins WHERE username=? AND is_active=1", [$username]);
        $ok  = false;
        if ($row) {
            if (!empty($row['locked_until']) && (int)$row['locked_until'] > time()) {
                $flash[] = ['type' => 'err', 'msg' => 'این حساب موقتاً قفل است.'];
            } else {
                $ok = Auth::verifyPassword($row, $password);
            }
        } else {
            // مقایسه‌ی زمان‌ثابت برای جلوگیری از تشخیص وجود کاربر
            password_verify($password, '$2y$10$abcdefghijklmnopqrstuv');
        }
        DB::q("INSERT INTO login_attempts (username,ip,kind,success,created_at) VALUES (?,?,'password',?,?)",
            [$username, $ip, $ok ? 1 : 0, time()]);

        if ($ok && $row) {
            RateLimit::loginSuccess($ip, $username);
            // ارسال رمز یکبارمصرف
            $sent = Login::send($row);
            if ($sent['ok']) {
                $_SESSION['pending_admin'] = (int)$row['id'];
                $pendingId = (int)$row['id'];
                $flash[] = ['type' => 'ok', 'msg' => !empty($sent['note']) ? (string)$sent['note'] : 'رمز یکبارمصرف به بله شما ارسال شد.'];
            } else {
                $flash[] = ['type' => 'err', 'msg' => $sent['error'] ?? 'ارسال کد ناموفق بود.'];
            }
        } else {
            RateLimit::loginFail($ip, $username);
            $fails = (int)Cache::get('login_fail_ip:' . $ip, 0);
            Events::emit('security.login_failed', [
                'detail' => 'user=' . str_limit($username, 40) . ' fails_ip=' . $fails,
                'known'  => $row ? 1 : 0,
            ], $fails >= 5 ? 'critical' : 'warn');
            if ($row) {
                DB::q("UPDATE panel_admins SET failed_count=failed_count+1 WHERE id=?", [(int)$row['id']]);
            }
            $flash[] = ['type' => 'err', 'msg' => 'نام کاربری یا رمز عبور اشتباه است. (' . digits_fa(RateLimit::remainingAttempts($ip, $username)) . ' تلاش باقی‌مانده)'];
        }
    }
}

// ---- مرحله ۲: کد یک‌بارمصرف ----
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['step'] ?? '') === 'otp') {
    Auth::checkCsrf();
    $code = (string)($_POST['code'] ?? '');
    $id   = (int)($_SESSION['pending_admin'] ?? 0);
    if (!$id) {
        $flash[] = ['type' => 'err', 'msg' => 'نشست منقضی شده؛ از ابتدا وارد شو.'];
    } else {
        [$ok, $msg] = Login::verify($id, $code);
        if ($ok) {
            $row = DB::one("SELECT * FROM panel_admins WHERE id=? AND is_active=1", [$id]);
            if ($row) {
                Auth::login($row);
                unset($_SESSION['pending_admin']);
                $redir = (string)($_SESSION['redirect_after'] ?? '');
                unset($_SESSION['redirect_after']);
                Auth::flash('ok', 'ورود موفق ✅');
                header('Location: ' . ($redir !== '' && str_starts_with($redir, '/') ? $redir : url('panel/index.php', ['p' => 'dashboard'])));
                exit;
            }
            $flash[] = ['type' => 'err', 'msg' => 'حساب غیرفعال است.'];
        } else {
            $flash[] = ['type' => 'err', 'msg' => $msg];
        }
    }
}

// ---- درخواست کد جدید ----
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['step'] ?? '') === 'resend') {
    Auth::checkCsrf();
    $id  = (int)($_SESSION['pending_admin'] ?? 0);
    $row = $id ? DB::one("SELECT * FROM panel_admins WHERE id=? AND is_active=1", [$id]) : null;
    if (!$row) {
        $flash[] = ['type' => 'err', 'msg' => 'نشست منقضی شده؛ از ابتدا وارد شو.'];
    } else {
        $sent = Login::send($row);
        $flash[] = $sent['ok']
            ? ['type' => 'ok', 'msg' => 'کد جدید ارسال شد.']
            : ['type' => 'err', 'msg' => $sent['error'] ?? 'ارسال ناموفق'];
    }
}

$showOtp = (bool)$pendingId && !empty($_SESSION['pending_admin']);

View::render(
    $showOtp ? 'verify' : 'login',
    ['flash' => $flash, 'showOtp' => $showOtp, 'admin_pending' => $showOtp ? DB::one("SELECT username,bale_user_id FROM panel_admins WHERE id=?", [$pendingId]) : null],
    'auth'
);
