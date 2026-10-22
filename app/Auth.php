<?php
declare(strict_types=1);

/**
 * احراز هویت پنل: نشست امن + CSRF + قفل تلاش ناموفق + بررسی نشست در دیتابیس
 */
final class Auth
{
    private static ?array $admin = null;

    public static function boot(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $name   = (string)config('security.session_name', 'bgm_sid');
        $ttl    = (int)config('security.session_ttl', 21600);
        $secure = self::isHttps();
        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.gc_maxlifetime', (string)$ttl);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();

        // انقضای بی‌کاری
        $last = (int)($_SESSION['last'] ?? 0);
        if ($last && time() - $last > $ttl) {
            self::logout();
        }
        $_SESSION['last'] = time();

        // بررسی معتبر بودن نشست در دیتابیس (امکان ابطال از راه دور)
        if (!empty($_SESSION['admin_id'])) {
            $sid = session_id();
            $row = DB::one("SELECT * FROM panel_sessions WHERE id=? AND expires_at>?", [$sid, time()]);
            if (!$row) {
                self::logout();
            } else {
                DB::q("UPDATE panel_sessions SET last_seen_at=? WHERE id=?", [time(), $sid]);
            }
        }
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    public static function current(): ?array
    {
        if (self::$admin !== null) {
            return self::$admin;
        }
        $id = (int)($_SESSION['admin_id'] ?? 0);
        if (!$id) {
            return null;
        }
        $a = DB::one("SELECT * FROM panel_admins WHERE id=? AND is_active=1", [$id]);
        if (!$a) {
            self::logout();
            return null;
        }
        self::$admin = $a;
        return $a;
    }

    public static function requireLogin(): array
    {
        $a = self::current();
        if ($a === null) {
            $_SESSION['redirect_after'] = $_SERVER['REQUEST_URI'] ?? '';
            redirect('panel/login.php');
        }
        return $a;
    }

    public static function requireSuper(): array
    {
        $a = self::requireLogin();
        if ((string)$a['role'] !== 'super') {
            http_response_code(403);
            echo '<div style="font-family:sans-serif;padding:2rem">دسترسی فقط برای مدیر ارشد مجاز است.</div>';
            exit;
        }
        return $a;
    }

    public static function login(array $admin, bool $rememberDevice = false): void
    {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['last']     = time();
        $_SESSION['csrf']     = bin2hex(random_bytes(32));
        $ttl = (int)config('security.session_ttl', 21600);
        DB::q("INSERT INTO panel_sessions (id,admin_id,ip,user_agent,created_at,last_seen_at,expires_at)
               VALUES (?,?,?,?,?,?,?)
               ON DUPLICATE KEY UPDATE last_seen_at=VALUES(last_seen_at), expires_at=VALUES(expires_at)",
            [session_id(), (int)$admin['id'], client_ip(), str_limit((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 240),
             time(), time(), time() + $ttl]);
        DB::q("UPDATE panel_admins SET last_login_at=?, last_login_ip=?, failed_count=0, locked_until=NULL WHERE id=?",
            [time(), client_ip(), (int)$admin['id']]);
        // پاک‌سازی نشست‌های منقضی
        DB::q("DELETE FROM panel_sessions WHERE expires_at < ?", [time()]);
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'login', 'panel', 'ورود موفق');
    }

    public static function logout(): void
    {
        $id = (int)($_SESSION['admin_id'] ?? 0);
        if ($id) {
            DB::q("DELETE FROM panel_sessions WHERE id=?", [session_id()]);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
        }
        @session_destroy();
        self::$admin = null;
    }

    public static function csrf(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . h(self::csrf()) . '">';
    }

    public static function checkCsrf(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        $sent = (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($sent === '' || !hash_equals(self::csrf(), $sent)) {
            http_response_code(419);
            echo 'توکن امنیتی نامعتبر/منقضی است. صفحه را رفرش کن.';
            exit;
        }
    }

    public static function flash(string $type, string $msg): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
    }

    public static function takeFlashes(): array
    {
        $f = (array)($_SESSION['flash'] ?? []);
        unset($_SESSION['flash']);
        return $f;
    }

    public static function verifyPassword(array $admin, string $password): bool
    {
        if (!password_verify($password, (string)$admin['password_hash'])) {
            return false;
        }
        if (password_needs_rehash((string)$admin['password_hash'], PASSWORD_DEFAULT)) {
            DB::q("UPDATE panel_admins SET password_hash=? WHERE id=?", [password_hash($password, PASSWORD_DEFAULT), (int)$admin['id']]);
        }
        return true;
    }
}
