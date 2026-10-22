<?php
declare(strict_types=1);

/**
 * نصب‌کننده حرفه‌ای ربات مدیر گروه بله
 * ۱) بررسی پیش‌نیازها  ۲) دیتابیس  ۳) ربات و ادمین  ۴) نصب و پیکربندی وب‌هوک
 * پس از نصب، فایل config/installed.lock ساخته می‌شود و این صفحه قفل می‌گردد.
 */

define('BGM_ROOT', __DIR__);
require_once BGM_ROOT . '/app/helpers.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();

$configFile = BGM_ROOT . '/config/config.php';
$lockFile   = BGM_ROOT . '/config/installed.lock';
$installed  = is_file($lockFile);

$step   = (int)($_GET['step'] ?? 1);
$errors = [];
$notice = '';

function post(string $k, string $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function &sess(): array { return $_SESSION; }

/** بررسی پیش‌نیازها */
function check_requirements(): array
{
    $rows = [];
    $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
    $rows[] = ['PHP نسخه ۸.۰ یا بالاتر', $phpOk, 'نسخه فعلی: ' . PHP_VERSION];
    foreach (['pdo', 'pdo_mysql', 'curl', 'mbstring', 'openssl', 'json', 'fileinfo'] as $ext) {
        $rows[] = ["افزونه $ext", extension_loaded($ext), extension_loaded($ext) ? 'نصب است' : 'نصب نیست'];
    }
    $writable = [
        'config/'  => BGM_ROOT . '/config',
        'storage/' => BGM_ROOT . '/storage',
        'storage/logs' => BGM_ROOT . '/storage/logs',
        'storage/cache' => BGM_ROOT . '/storage/cache',
    ];
    foreach ($writable as $label => $path) {
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        $rows[] = ["قابل نوشتن: $label", is_dir($path) && is_writable($path), is_dir($path) ? (is_writable($path) ? 'OK' : 'دسترسی نوشتن ندارد') : 'ساخته نشد'];
    }
    $rows[] = ['افزونه mbstring برای فارسی', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'OK' : 'مشکل در نرمال‌سازی متن'];
    $rows[] = ['cURL برای ارتباط با بله', extension_loaded('curl'), extension_loaded('curl') ? 'OK' : 'ارتباط با API ممکن نیست'];
    $rows[] = ['allow_url_fopen / outbound HTTPS', true, 'در صورت بلاک بودن، سرور باید به tapi.bale.ai دسترسی داشته باشد'];
    return $rows;
}

$requirements = check_requirements();
$reqOk = !in_array(false, array_column($requirements, 1), true);

/** تست اتصال دیتابیس */
function db_test(string $host, int $port, string $name, string $user, string $pass, &$err = null): ?PDO
{
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        return $pdo;
    } catch (Throwable $e) {
        $err = $e->getMessage();
        return null;
    }
}

function bale_call(string $token, string $method, array $data = [], string $base = 'https://tapi.bale.ai'): ?array
{
    $ch = curl_init(rtrim($base, '/') . '/bot' . $token . '/' . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($body) || $body === '') {
        return ['__error' => 'no response (http ' . $code . ')'];
    }
    $json = json_decode($body, true);
    return is_array($json) ? $json : ['__error' => 'invalid json'];
}

$appUrlGuess = (function (): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return $scheme . '://' . $host . $dir;
})();

// ------------------------------------------------------------------
// مرحله ۴: اجرای نصب
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'install') {
    $dbHost = post('db_host', 'localhost');
    $dbPort = (int)post('db_port', '3306');
    $dbName = post('db_name');
    $dbUser = post('db_user');
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $appUrl = rtrim(post('app_url', $appUrlGuess), '/');
    $token  = post('bot_token');
    $botUname = ltrim(post('bot_username'), '@');
    $mode   = post('mode', 'webhook') === 'polling' ? 'polling' : 'webhook';
    $apiBase = rtrim(post('api_base', 'https://tapi.bale.ai'), '/');
    $admUser = post('admin_username', 'admin');
    $admPass = (string)($_POST['admin_password'] ?? '');
    $admPass2 = (string)($_POST['admin_password2'] ?? '');
    $admBale = (int)post('admin_bale_id', '0');
    $otpChat = (int)post('otp_chat_id', '0');

    if ((int)($_POST['req_ok'] ?? 0) !== 1 && !$reqOk) {
        $errors[] = 'پیش‌نیازهای سرور کامل نیست.';
    }
    if ($dbName === '' || $dbUser === '') {
        $errors[] = 'اطلاعات دیتابیس کامل نیست.';
    }
    if (!preg_match('~^https?://~', $appUrl)) {
        $errors[] = 'آدرس سایت باید با http:// یا https:// شروع شود.';
    }
    if (!preg_match('~^\d{6,}:[A-Za-z0-9_\-]{20,}$~', $token)) {
        $errors[] = 'فرمت توکن ربات معتبر نیست (مثل 123456789:AAE...).';
    }
    if (!preg_match('~^[a-zA-Z0-9_.-]{3,32}$~', $admUser)) {
        $errors[] = 'نام کاربری ادمین باید ۳ تا ۳۲ کاراکتر لاتین/عدد باشد.';
    }
    if (mb_strlen($admPass) < 8) {
        $errors[] = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
    }
    if ($admPass !== $admPass2) {
        $errors[] = 'تکرار رمز عبور یکسان نیست.';
    }

    // اعتبارسنجی زنده توکن
    $me = null;
    if (!$errors) {
        $me = bale_call($token, 'getMe', [], $apiBase);
        if (!isset($me['ok']) || $me['ok'] !== true) {
            $errors[] = 'توکن ربات معتبر نیست یا ارتباط با tapi.bale.ai برقرار نشد. ' . (string)($me['description'] ?? $me['__error'] ?? '');
        }
    }

    // تست دیتابیس
    $pdo = null;
    if (!$errors) {
        $dberr = null;
        $pdo = db_test($dbHost, $dbPort, $dbName, $dbUser, $dbPass, $dberr);
        if (!$pdo) {
            $errors[] = 'اتصال به دیتابیس ناموفق: ' . $dberr;
        }
    }

    if (!$errors && $pdo) {
        try {
            $appKey = bin2hex(random_bytes(32));
            $hookSecret = bin2hex(random_bytes(16));
            $cronSecret = bin2hex(random_bytes(16));

            // 1) ساخت اسکیما
            require_once BGM_ROOT . '/app/DB.php';
            // DB::pdo() از config می‌خواند؛ برای نصب موقتاً با استاتیک جایگزین کار می‌کنیم
            $ref = new ReflectionClass('DB');
            $prop = $ref->getProperty('pdo');
            $prop->setAccessible(true);
            $prop->setValue(null, $pdo);

            require_once BGM_ROOT . '/app/Schema.php';
            Schema::install();
            Schema::seedDefaults();

            // 2) نوشتن فایل کانفیگ
            $cfg = [
                'app' => [
                    'name'     => 'مدیر گروه بله',
                    'url'      => $appUrl,
                    'key'      => $appKey,
                    'timezone' => 'Asia/Tehran',
                    'debug'    => false,
                    'locale'   => 'fa',
                ],
                'db' => ['host' => $dbHost, 'port' => $dbPort, 'name' => $dbName, 'user' => $dbUser, 'pass' => $dbPass, 'charset' => 'utf8mb4'],
                'bot' => [
                    'token' => $token,
                    'username' => $botUname,
                    'api_base' => $apiBase,
                    'mode' => $mode,
                    'webhook_secret' => $hookSecret,
                    'super_admins' => $admBale ? [$admBale] : [],
                    'polling_timeout' => 30,
                    'max_retries' => 3,
                ],
                'security' => [
                    'session_name' => 'bgm_sid',
                    'session_ttl' => 21600,
                    'otp_ttl' => 180,
                    'otp_resend_after' => 30,
                    'otp_max_attempts' => 5,
                    'login_max_fails' => 6,
                    'login_lock_minutes' => 15,
                    'force_https' => str_starts_with($appUrl, 'https://'),
                ],
                'cron' => ['secret' => $cronSecret],
            ];
            $php = "<?php\n// ساخته‌شده توسط نصب‌کننده — " . date('Y-m-d H:i') . "\n// این فایل حاوی اطلاعات حساس است. دسترسی: 600\nreturn " . var_export($cfg, true) . ";\n";
            if (!is_dir(BGM_ROOT . '/config')) {
                @mkdir(BGM_ROOT . '/config', 0775, true);
            }
            if (file_put_contents($configFile, $php, LOCK_EX) === false) {
                throw new RuntimeException('نوشتن config/config.php ممکن نشد. دسترسی پوشه config را بررسی کنید.');
            }
            @chmod($configFile, 0600);

            // 3) ادمین ارشد
            $hash = password_hash($admPass, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO panel_admins (username,password_hash,bale_user_id,full_name,role,is_active,created_at)
                           VALUES (?,?,?,?,'super',1,?)
                           ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), bale_user_id=VALUES(bale_user_id), is_active=1")
                ->execute([$admUser, $hash, $admBale ?: null, 'مدیر ارشد', time()]);

            // 4) تنظیمات سیستمی
            $set = $pdo->prepare("INSERT INTO settings (skey,svalue,updated_at) VALUES (?,?,?)
                                  ON DUPLICATE KEY UPDATE svalue=VALUES(svalue), updated_at=VALUES(updated_at)");
            $set->execute(['otp_chat_id', (string)$otpChat, time()]);
            $set->execute(['installed_at', (string)time(), time()]);
            $set->execute(['mode', $mode, time()]);
            if (isset($me['result']['id'])) {
                $set->execute(['bot_id', (string)(int)$me['result']['id'], time()]);
                $set->execute(['bot_username', (string)($me['result']['username'] ?? ''), time()]);
            }
            $set->execute(['cron_secret', $cronSecret, time()]);
            $set->execute(['webhook_secret', $hookSecret, time()]);

            // 5) وب‌هوک
            $hookUrl = $appUrl . '/webhook.php?s=' . urlencode($hookSecret);
            $hookResult = 'polling';
            if ($mode === 'webhook') {
                $res = bale_call($token, 'setWebhook', ['url' => $hookUrl, 'secret_token' => $hookSecret], $apiBase);
                if (($res['ok'] ?? false) === true) {
                    $hookResult = 'ok';
                    $set->execute(['webhook_url', $hookUrl, time()]);
                } else {
                    // تلاش دوم بدون پارامتر اضافی
                    $res2 = bale_call($token, 'setWebhook', ['url' => $hookUrl], $apiBase);
                    if (($res2['ok'] ?? false) === true) {
                        $hookResult = 'ok';
                        $set->execute(['webhook_url', $hookUrl, time()]);
                    } else {
                        $hookResult = 'failed: ' . (string)($res['description'] ?? $res2['description'] ?? 'unknown');
                        $set->execute(['mode', 'polling', time()]);
                    }
                }
            }

            // 6) قفل نصب
            file_put_contents($lockFile, 'installed ' . date('c') . "\nadmin=" . $admUser . "\n");
            @chmod($lockFile, 0600);

            // 7) پوشه‌های لازم
            foreach (['storage/logs', 'storage/cache', 'config'] as $d) {
                @mkdir(BGM_ROOT . '/' . $d, 0775, true);
            }

            $installResult = [
                'app_url'     => $appUrl,
                'admin'       => $admUser,
                'otp_chat'    => $otpChat,
                'hook'        => $hookResult,
                'mode'        => $hookResult === 'ok' ? 'webhook' : 'polling',
                'cron'        => $appUrl . '/cron.php?key=' . $cronSecret,
                'bot'         => (string)($me['result']['username'] ?? $botUname),
            ];
            // ذخیره نتیجه هم در نشست و هم در فایل یک‌بارمصرف (کار می‌کند حتی اگر کوکی نشست نباشد)
            $_SESSION['install_result'] = $installResult;
            @file_put_contents(BGM_ROOT . '/config/.install-result.json', json_encode($installResult, JSON_UNESCAPED_UNICODE), LOCK_EX);
            @chmod(BGM_ROOT . '/config/.install-result.json', 0600);
            header('Location: install.php?step=4');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'خطا در نصب: ' . $e->getMessage();
            if (is_file($lockFile)) {
                @unlink($lockFile);
            }
        }
    }
    if ($errors) {
        $step = 3; // امکان اصلاح اطلاعات و تلاش دوباره
    }
}

$resultFile = BGM_ROOT . '/config/.install-result.json';
$result = $_SESSION['install_result'] ?? null;
if (!$result && is_file($resultFile)) {
    $decoded = json_decode((string)file_get_contents($resultFile), true);
    if (is_array($decoded)) {
        $result = $decoded;
    }
}
if ($result && is_file($resultFile)) {
    @unlink($resultFile);
}
unset($_SESSION['install_result']);
if ($installed && !$result) {
    $step = 4;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>نصب ربات مدیر گروه بله</title>
<style>
:root{--bg:#0b1120;--card:#111c33;--line:#1f2f4d;--txt:#e7eefc;--muted:#93a4c4;--accent:#3b82f6;--ok:#16a34a;--err:#dc2626;--warn:#f59e0b}
*{box-sizing:border-box}
body{margin:0;background:linear-gradient(160deg,#070d1a,#0b1120 45%,#101b30);color:var(--txt);font-family:Tahoma,"Segoe UI",system-ui,sans-serif;line-height:1.9;min-height:100vh}
.wrap{max-width:920px;margin:0 auto;padding:28px 18px 60px}
.hero{display:flex;align-items:center;gap:14px;margin-bottom:22px}
.logo{width:54px;height:54px;border-radius:16px;background:linear-gradient(135deg,#3b82f6,#22d3ee);display:grid;place-items:center;font-size:26px;box-shadow:0 10px 30px rgba(59,130,246,.35)}
h1{font-size:20px;margin:0}
.sub{color:var(--muted);font-size:13px}
.steps{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0 22px}
.step{flex:1;min-width:120px;padding:10px 12px;border-radius:12px;background:#0f1a2e;border:1px solid var(--line);font-size:13px;color:var(--muted);text-align:center}
.step.active{background:linear-gradient(135deg,#1d4ed8,#3b82f6);color:#fff;border-color:transparent;font-weight:bold}
.step.done{color:#86efac;border-color:#14532d;background:#0c1f17}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px;margin-bottom:18px;box-shadow:0 20px 50px rgba(0,0,0,.25)}
h2{font-size:16px;margin:0 0 14px}
label{display:block;font-size:13px;margin:12px 0 6px;color:#c7d5ee}
input,select{width:100%;padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:#0a1424;color:var(--txt);font-family:inherit;font-size:14px}
input:focus,select:focus{outline:2px solid #1d4ed8;border-color:transparent}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:640px){.grid{grid-template-columns:1fr}}
.hint{font-size:12px;color:var(--muted);margin-top:6px}
.btn{display:inline-block;padding:11px 22px;border-radius:11px;border:0;background:linear-gradient(135deg,#2563eb,#3b82f6);color:#fff;font-family:inherit;font-size:14px;cursor:pointer;text-decoration:none;font-weight:bold}
.btn.ghost{background:#132039;border:1px solid var(--line);color:var(--txt)}
.btn:disabled{opacity:.5;cursor:not-allowed}
table{width:100%;border-collapse:collapse;font-size:13px}
td,th{padding:9px 8px;border-bottom:1px solid var(--line);text-align:right}
.badge{padding:2px 9px;border-radius:999px;font-size:12px;display:inline-block}
.b-ok{background:#052e16;color:#86efac;border:1px solid #14532d}
.b-err{background:#3f0d0d;color:#fecaca;border:1px solid #7f1d1d}
.alert{padding:12px 14px;border-radius:12px;margin-bottom:14px;font-size:13.5px}
.alert.err{background:#3f0d0d;border:1px solid #7f1d1d;color:#fecaca}
.alert.ok{background:#052e16;border:1px solid #14532d;color:#bbf7d0}
.alert.warn{background:#3b2a05;border:1px solid #78350f;color:#fde68a}
code{background:#0a1424;padding:2px 7px;border-radius:6px;font-size:12.5px;direction:ltr;display:inline-block}
.mono{direction:ltr;text-align:left;word-break:break-all}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.footer{color:var(--muted);font-size:12px;text-align:center;margin-top:24px}
.progress{height:6px;border-radius:99px;background:#0f1a2e;overflow:hidden;margin-bottom:20px}
.progress i{display:block;height:100%;background:linear-gradient(90deg,#22d3ee,#3b82f6)}
details{border:1px solid var(--line);border-radius:12px;padding:10px 14px;margin-top:12px;background:#0d1728}
summary{cursor:pointer;font-size:13px;color:#c7d5ee}
</style>
</head>
<body>
<div class="wrap">
  <div class="hero">
    <div class="logo">🤖</div>
    <div>
      <h1>نصب ربات مدیر گروه بله</h1>
      <div class="sub">PHP + MySQL — نسخه ۱.۰.۰</div>
    </div>
  </div>

  <div class="progress"><i style="width:<?= max(5, min(100, $step * 25)) ?>%"></i></div>
  <div class="steps">
    <div class="step <?= $step === 1 ? 'active' : ($step > 1 ? 'done' : '') ?>">۱. بررسی پیش‌نیاز</div>
    <div class="step <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>">۲. دیتابیس</div>
    <div class="step <?= $step === 3 ? 'active' : ($step > 3 ? 'done' : '') ?>">۳. ربات و ادمین</div>
    <div class="step <?= $step >= 4 ? 'active' : '' ?>">۴. پایان</div>
  </div>

<?php foreach ($errors as $e): ?>
  <div class="alert err"><?= h($e) ?></div>
<?php endforeach; ?>

<?php if ($result): ?>
  <!-- ================= پایان ================= -->
  <div class="card">
    <h2>✅ نصب با موفقیت انجام شد</h2>
    <table>
      <tr><th>آدرس پنل</th><td class="mono"><a href="<?= h($result['app_url']) ?>/panel/" style="color:#93c5fd"><?= h($result['app_url']) ?>/panel/</a></td></tr>
      <tr><th>نام کاربری</th><td class="mono"><?= h($result['admin']) ?></td></tr>
      <tr><th>ربات</th><td><?= $result['bot'] ? '@' . h($result['bot']) : '—' ?></td></tr>
      <tr><th>حالت دریافت پیام</th><td><?= $result['hook'] === 'ok' ? 'وب‌هوک (فعال ✅)' : 'پولینگ — وب‌هوک تنظیم نشد (' . h((string)$result['hook']) . ')' ?></td></tr>
      <tr><th>آدرس کرون</th><td class="mono"><?= h($result['cron']) ?></td></tr>
    </table>

    <div class="alert warn" style="margin-top:16px">
      <b>۳ کار مهم قبل از استفاده:</b><br>
      ۱) ربات را به گروه اضافه و <b>ادمین</b> کنید (دسترسی حذف پیام، بن، سکوت، سنجاق).<br>
      ۲) بعد از اولین ورود، از پنل → «حساب من» آیدی عددی بله خود را ثبت کنید تا کد یکبارمصرف ارسال شود.<br>
      ۳) فایل <code>install.php</code> را از سرور حذف کنید و کرون را روی آدرس بالا (هر ۱ دقیقه) تنظیم کنید.
    </div>

    <div class="actions">
      <a class="btn" href="<?= h($result['app_url']) ?>/panel/">ورود به پنل</a>
      <a class="btn ghost" href="<?= h($result['app_url']) ?>/panel/index.php?p=setup">راهنمای راه‌اندازی</a>
    </div>
  </div>

<?php elseif ($installed && $step >= 4): ?>
  <div class="card">
    <h2>🔒 نصب‌کننده قفل است</h2>
    <p>این سیستم قبلاً نصب شده است. برای نصب مجدد، فایل <code>config/installed.lock</code> را حذف کنید (با احتیاط: ادمین‌ها و تنظیمات دیتابیس بازنویسی می‌شوند).</p>
    <div class="actions"><a class="btn" href="<?= h($appUrlGuess) ?>/panel/">ورود به پنل</a></div>
  </div>

<?php elseif ($step === 1): ?>
  <!-- ================= مرحله ۱ ================= -->
  <div class="card">
    <h2>۱. بررسی پیش‌نیازهای سرور</h2>
    <table>
      <?php foreach ($requirements as [$label, $ok, $note]): ?>
        <tr>
          <td><?= h($label) ?></td>
          <td class="hint"><?= h($note) ?></td>
          <td style="width:90px"><span class="badge <?= $ok ? 'b-ok' : 'b-err' ?>"><?= $ok ? '✓ آماده' : '✕ ناموجود' ?></span></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php if (!$reqOk): ?>
      <div class="alert err" style="margin-top:14px">موارد قرمز را رفع کنید و صفحه را رفرش کنید. اگر کنترل پنل داری (cPanel/DirectAdmin)، افزونه‌های PHP و دسترسی پوشه‌ها را تنظیم کن.</div>
    <?php endif; ?>
    <div class="actions">
      <a class="btn" href="install.php?step=2" <?= $reqOk ? '' : 'style="pointer-events:none;opacity:.5"' ?>>ادامه →</a>
    </div>
  </div>

<?php elseif ($step === 2): ?>
  <!-- ================= مرحله ۲ ================= -->
  <form method="post" action="install.php?step=3">
    <div class="card">
      <h2>۲. اطلاعات دیتابیس MySQL</h2>
      <div class="grid">
        <div>
          <label>هاست دیتابیس</label>
          <input name="db_host" value="<?= h((string)($_POST['db_host'] ?? 'localhost')) ?>" required>
        </div>
        <div>
          <label>پورت</label>
          <input name="db_port" value="<?= h((string)($_POST['db_port'] ?? '3306')) ?>" required>
        </div>
        <div>
          <label>نام دیتابیس</label>
          <input name="db_name" value="<?= h((string)($_POST['db_name'] ?? 'bale_bot')) ?>" required>
        </div>
        <div>
          <label>نام کاربری دیتابیس</label>
          <input name="db_user" value="<?= h((string)($_POST['db_user'] ?? 'bale_bot')) ?>" required>
        </div>
        <div>
          <label>رمز دیتابیس</label>
          <input name="db_pass" type="password" value="<?= h((string)($_POST['db_pass'] ?? '')) ?>">
        </div>
        <div>
          <label>آدرس کامل سایت</label>
          <input name="app_url" class="mono" value="<?= h((string)($_POST['app_url'] ?? $appUrlGuess)) ?>" required>
          <div class="hint">برای وب‌هوک و لینک‌های پنل استفاده می‌شود. باید با https باشد (بله برای وب‌هوک SSL می‌خواهد).</div>
        </div>
      </div>
      <div class="alert warn" style="margin-top:14px">اگر دیتابیس و کاربر را ساخته‌ای، فقط اطلاعات را وارد کن. دیتابیس باید <code>utf8mb4</code> باشد (نصب‌کننده جدول‌ها را با همین کدگذاری می‌سازد).</div>
    </div>
    <div class="actions">
      <a class="btn ghost" href="install.php?step=1">→ قبل</a>
      <button class="btn" type="submit">ادامه →</button>
    </div>
  </form>

<?php elseif ($step === 3): ?>
  <!-- ================= مرحله ۳ ================= -->
  <?php
    $db = [
      'host' => (string)($_POST['db_host'] ?? 'localhost'),
      'port' => (string)($_POST['db_port'] ?? '3306'),
      'name' => (string)($_POST['db_name'] ?? ''),
      'user' => (string)($_POST['db_user'] ?? ''),
      'pass' => (string)($_POST['db_pass'] ?? ''),
    ];
    $appUrl = (string)($_POST['app_url'] ?? $appUrlGuess);
    $dbError = null;
    $dbOk = false;
    $existingTables = 0;
    if ($db['name'] !== '' && $db['user'] !== '') {
      $tmp = db_test($db['host'], (int)$db['port'], $db['name'], $db['user'], $db['pass'], $dbError);
      if ($tmp) {
        $dbOk = true;
        $existingTables = (int)$tmp->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=" . $tmp->quote($db['name']))->fetchColumn();
      }
    }
  ?>
  <?php if (!$dbOk): ?>
    <div class="card">
      <h2>❌ اتصال دیتابیس ناموفق</h2>
      <div class="alert err"><?= h((string)$dbError) ?></div>
      <div class="actions"><a class="btn ghost" href="install.php?step=2">بازگشت و اصلاح</a></div>
    </div>
  <?php else: ?>
    <form method="post" action="install.php?step=4">
      <input type="hidden" name="do" value="install">
      <input type="hidden" name="req_ok" value="<?= $reqOk ? 1 : 0 ?>">
      <input type="hidden" name="db_host" value="<?= h($db['host']) ?>">
      <input type="hidden" name="db_port" value="<?= h($db['port']) ?>">
      <input type="hidden" name="db_name" value="<?= h($db['name']) ?>">
      <input type="hidden" name="db_user" value="<?= h($db['user']) ?>">
      <input type="hidden" name="db_pass" value="<?= h($db['pass']) ?>">
      <input type="hidden" name="app_url" value="<?= h($appUrl) ?>">

      <div class="card">
        <h2>۳. ربات بله</h2>
        <div class="alert ok">اتصال دیتابیس برقرار شد ✅ (<?= (int)$existingTables ?> جدول موجود)</div>
        <label>توکن ربات</label>
        <input name="bot_token" class="mono" placeholder="123456789:AAE..." value="<?= h((string)($_POST['bot_token'] ?? '')) ?>" required>
        <div class="hint">توکن را از <code>@BotFather</code> بله بگیر. هنگام ذخیره، صحت آن با <code>getMe</code> بررسی می‌شود.</div>
        <div class="grid">
          <div>
            <label>یوزرنیم ربات (اختیاری)</label>
            <input name="bot_username" class="mono" placeholder="MyGroupBot" value="<?= h((string)($_POST['bot_username'] ?? '')) ?>">
          </div>
          <div>
            <label>آدرس API بله (پیشرفته)</label>
            <input name="api_base" class="mono" value="<?= h((string)($_POST['api_base'] ?? 'https://tapi.bale.ai')) ?>">
            <div class="hint">فقط اگر از پروکسی/میرور استفاده می‌کنی تغییر بده.</div>
          </div>
          <div>
            <label>حالت دریافت پیام</label>
            <select name="mode">
              <option value="webhook" <?= (($_POST['mode'] ?? '') === 'webhook') ? 'selected' : '' ?>>وب‌هوک (پیشنهادی — نیاز به SSL معتبر)</option>
              <option value="polling" <?= (($_POST['mode'] ?? '') === 'polling') ? 'selected' : '' ?>>پولینگ (بدون SSL — نیاز به اجرای cron/process)</option>
            </select>
          </div>
        </div>
      </div>

      <div class="card">
        <h2>۴. حساب مدیر ارشد و رمز یکبارمصرف</h2>
        <div class="grid">
          <div>
            <label>نام کاربری پنل</label>
            <input name="admin_username" class="mono" value="<?= h((string)($_POST['admin_username'] ?? 'admin')) ?>" required>
          </div>
          <div>
            <label>آیدی عددی بله شما (برای ارسال کد یکبارمصرف)</label>
            <input name="admin_bale_id" class="mono" placeholder="123456789" value="<?= h((string)($_POST['admin_bale_id'] ?? '')) ?>">
            <div class="hint">در بله ربات را استارت کن و بنویس <code>من</code> تا آیدی‌ات را ببینی.</div>
          </div>
          <div>
            <label>رمز عبور (حداقل ۸ کاراکتر)</label>
            <input name="admin_password" type="password" required>
          </div>
          <div>
            <label>تکرار رمز عبور</label>
            <input name="admin_password2" type="password" required>
          </div>
        </div>
        <label>آیدی گروه/کانال جایگزین برای ارسال کد (اختیاری)</label>
        <input name="otp_chat_id" class="mono" placeholder="-100123456789" value="<?= h((string)($_POST['otp_chat_id'] ?? '')) ?>">
        <div class="hint">اگر چت خصوصی ربات به هر دلیل در دسترس نبود، کد یکبارمصرف به این گروه/کانال امن ارسال می‌شود. ربات باید در آن ادمین باشد.</div>
      </div>

      <div class="actions">
        <a class="btn ghost" href="install.php?step=2">→ قبل</a>
        <button class="btn" type="submit">🚀 نصب و پیکربندی</button>
      </div>
    </form>
  <?php endif; ?>

<?php endif; ?>

  <div class="footer">
    ربات مدیر گروه بله • PHP/MySQL • امنیت: CSRF، OTP، قفل تلاش، محدودیت نرخ، لاگ کامل
  </div>
</div>
</body>
</html>
