<?php
declare(strict_types=1);

/**
 * بازیابی دسترسی به پنل
 *  - مسیر الف: رمز عبور را داری ولی به بله دسترسی نداری → نام کاربری + رمز + کد بازیابی
 *  - مسیر ب: رمز عبور را فراموش کردی (ولی کد بازیابی داری) → نام کاربری + کد بازیابی + رمز جدید
 * هر کد بازیابی یک‌بار مصرف است و هر تلاش در otp_logs ثبت می‌شود.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

security_headers(true);
Auth::boot();

if (Auth::current() !== null) {
    redirect('panel/index.php', ['p' => 'dashboard']);
}

$flash = Auth::takeFlashes();
$step  = 'form';       // form | codes
$newCodes = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    Auth::checkCsrf();
    $ip       = client_ip();
    $mode     = (string)($_POST['mode'] ?? 'no_bale');
    $username = trim((string)($_POST['username'] ?? ''));
    $code     = (string)($_POST['code'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $newPass  = (string)($_POST['new_password'] ?? '');

    if (RateLimit::hit('recover:' . $ip, 8, 900)) {
        $flash[] = ['type' => 'err', 'msg' => 'تلاش‌های زیاد از این IP؛ ۱۵ دقیقه دیگر امتحان کن.'];
    } else {
        $admin = $username !== ''
            ? DB::one("SELECT * FROM panel_admins WHERE username=? AND is_active=1", [$username])
            : null;
        if (!$admin) {
            // تأخیر مصنوعی + پیام یکسان (جلوگیری از تشخیص وجود کاربر)
            usleep(300000);
            $flash[] = ['type' => 'err', 'msg' => 'نام کاربری یا کد بازیابی نامعتبر است.'];
        } elseif (Recovery::failures((int)$admin['id'], 15) >= 5) {
            $flash[] = ['type' => 'err', 'msg' => 'تلاش‌های ناموفق زیاد بود؛ ۱۵ دقیقه صبر کن یا از OTP بله استفاده کن.'];
        } else {
            if ($mode === 'forgot_password') {
                // مسیر ب: کد بازیابی + رمز جدید
                if (mb_strlen($newPass) < 8) {
                    $flash[] = ['type' => 'err', 'msg' => 'رمز جدید باید حداقل ۸ کاراکتر باشد.'];
                } else {
                    $res = Recovery::verify((int)$admin['id'], $code);
                    if ($res['ok']) {
                        DB::q("UPDATE panel_admins SET password_hash=?, failed_count=0, locked_until=NULL WHERE id=?",
                            [password_hash($newPass, PASSWORD_DEFAULT), (int)$admin['id']]);
                        Logger::audit((int)$admin['id'], (string)$admin['username'], 'password_reset', 'recovery-code', 'رمز عبور با کد بازیابی ریست شد');
                        Recovery::log((int)$admin['id'], 'password_reset', 'panel', null, 'success');
                        Auth::login($admin);
                        Auth::flash('ok', 'رمز عبور تغییر کرد و وارد شدی. کدهای بازیابی باقی‌مانده: ' . digits_fa((int)($res['left'] ?? 0)));
                        redirect('panel/index.php', ['p' => 'recovery']);
                    }
                    $flash[] = ['type' => 'err', 'msg' => (string)($res['reason'] ?? 'کد نامعتبر')];
                }
            } else {
                // مسیر الف: رمز + کد بازیابی
                $passOk = Auth::verifyPassword($admin, $password);
                DB::q("INSERT INTO login_attempts (username,ip,kind,success,created_at) VALUES (?,?,'recovery',?,?)",
                    [$username, $ip, $passOk ? 1 : 0, time()]);
                if ($passOk) {
                    $res = Recovery::verify((int)$admin['id'], $code);
                    if ($res['ok']) {
                        Auth::login($admin);
                        $left = (int)($res['left'] ?? 0);
                        Auth::flash('ok', 'ورود با کد بازیابی انجام شد ✅ — کدهای باقی‌مانده: ' . digits_fa($left));
                        if ($left <= 2) {
                            Auth::flash('warn', 'کدهای بازیابی‌ات کم شده؛ همین حالا مجموعه جدید بساز.');
                        }
                        redirect('panel/index.php', ['p' => $left <= 2 ? 'recovery' : 'dashboard']);
                    }
                    $flash[] = ['type' => 'err', 'msg' => (string)($res['reason'] ?? 'کد نامعتبر')];
                } else {
                    if ($admin) {
                        DB::q("UPDATE panel_admins SET failed_count=failed_count+1 WHERE id=?", [(int)$admin['id']]);
                    }
                    Recovery::log((int)$admin['id'], 'recovery_login', 'panel', null, 'wrong_password');
                    $flash[] = ['type' => 'err', 'msg' => 'رمز عبور یا کد بازیابی نامعتبر است.'];
                }
            }
        }
    }
}

View::render('recover', ['flash' => $flash, 'step' => $step, 'newCodes' => $newCodes], 'auth');
