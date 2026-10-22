<?php
declare(strict_types=1);

/**
 * توابع کمکی عمومی
 */

if (!function_exists('h')) {
    function h($v): string
    {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    function config(string $path, $default = null)
    {
        static $cfg = null;
        if ($cfg === null) {
            $file = BGM_ROOT . '/config/config.php';
            $cfg  = is_file($file) ? (require $file) : [];
        }
        $ref = $cfg;
        foreach (explode('.', $path) as $seg) {
            if (!is_array($ref) || !array_key_exists($seg, $ref)) {
                return $default;
            }
            $ref = $ref[$seg];
        }
        return $ref;
    }
}

if (!function_exists('url')) {
    function url(string $path = '', array $q = []): string
    {
        $base = rtrim((string)config('app.url', ''), '/');
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
            $base   = $scheme . '://' . $host . $dir;
        }
        $u = $base . '/' . ltrim($path, '/');
        if ($q) {
            $u .= (str_contains($u, '?') ? '&' : '?') . http_build_query($q);
        }
        return $u;
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, array $q = []): void
    {
        header('Location: ' . url($path, $q));
        exit;
    }
}

if (!function_exists('now')) {
    function now(): int
    {
        return time();
    }
}

if (!function_exists('dt')) {
    /** نمایش تاریخ شمسی خوانا از timestamp */
    function dt(?int $ts, string $format = 'Y/m/d H:i'): string
    {
        if (!$ts) {
            return '—';
        }
        return date($format, $ts);
    }
}

if (!function_exists('duration_human')) {
    function duration_human(int $sec): string
    {
        if ($sec <= 0) {
            return '0';
        }
        $units = [86400 => 'روز', 3600 => 'ساعت', 60 => 'دقیقه', 1 => 'ثانیه'];
        $out   = [];
        foreach ($units as $u => $label) {
            if ($sec >= $u) {
                $n     = intdiv($sec, $u);
                $out[] = $n . ' ' . $label;
                $sec  -= $n * $u;
            }
            if (count($out) >= 2) {
                break;
            }
        }
        return implode(' و ', $out);
    }
}

if (!function_exists('parse_duration')) {
    /**
     * تبدیل «10m / 1h / 2d / 30s» یا «10 دقیقه» به ثانیه. 0 = نامعتبر
     */
    function parse_duration(string $s): int
    {
        $s = trim(mb_strtolower($s));
        if ($s === '') {
            return 0;
        }
        if (is_numeric($s)) {
            return (int)$s; // ثانیه
        }
        $s = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'], ['0','1','2','3','4','5','6','7','8','9'], $s);
        $map = [
            'ثانیه' => 1, 'ث' => 1, 's' => 1, 'sec' => 1,
            'دقیقه' => 60, 'د' => 60, 'm' => 60, 'min' => 60,
            'ساعت'  => 3600, 'س' => 3600, 'h' => 3600, 'hr' => 3600,
            'روز'   => 86400, 'ر' => 86400, 'd' => 86400, 'day' => 86400,
            'هفته'  => 604800, 'w' => 604800,
            'ماه'   => 2592000, 'mo' => 2592000,
        ];
        if (preg_match('/^(\d+)\s*([a-z\p{Arabic}]+)?$/u', $s, $m)) {
            $n    = (int)$m[1];
            $unit = $m[2] ?? 'm';
            if (isset($map[$unit])) {
                return $n * $map[$unit];
            }
        }
        return 0;
    }
}

if (!function_exists('mask_text')) {
    function mask_text(?string $text, int $limit = 120): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
        if (mb_strlen($text) > $limit) {
            $text = mb_substr($text, 0, $limit) . '…';
        }
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('json_out')) {
    function json_out(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('input')) {
    function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
}

if (!function_exists('random_str')) {
    function random_str(int $len = 32): string
    {
        $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max      = strlen($alphabet) - 1;
        $out      = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }
}

if (!function_exists('digits_fa')) {
    /** قالب‌بندی مبلغ: ۱٬۲۵۰٬۰۰۰ تومان */
    function money($amount, string $unit = 'تومان'): string
    {
        return digits_fa(number_format((float)$amount)) . ($unit !== '' ? ' ' . $unit : '');
    }

    function digits_fa($v): string
    {
        return str_replace(
            ['0','1','2','3','4','5','6','7','8','9'],
            ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
            (string)$v
        );
    }
}

if (!function_exists('n2fa')) {
    function n2fa($v): string
    {
        return digits_fa(number_format((float)$v));
    }
}

if (!function_exists('flatten_errors')) {
    function flatten_errors(array $errors): string
    {
        return implode('<br>', array_map('h', $errors));
    }
}

if (!function_exists('ensure_dir')) {
    function ensure_dir(string $dir): bool
    {
        return is_dir($dir) || @mkdir($dir, 0775, true);
    }
}

if (!function_exists('is_installed')) {
    function is_installed(): bool
    {
        return is_file(BGM_ROOT . '/config/installed.lock');
    }
}

if (!function_exists('client_ip')) {
    function client_ip(): string
    {
        return (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}

if (!function_exists('str_limit')) {
    function str_limit(?string $s, int $n): string
    {
        $s = (string)$s;
        return mb_strlen($s) > $n ? mb_substr($s, 0, $n) : $s;
    }
}

/* ---------- polyfill سبک برای سرورهایی که mbstring ندارند (توصیه: mbstring نصب شود) ---------- */
if (!function_exists('mb_strlen')) {
    function mb_strlen($s, $enc = null): int { return strlen((string)$s); }
    function mb_substr($s, $start, $len = null, $enc = null): string { return $len === null ? substr((string)$s, $start) : substr((string)$s, $start, $len); }
    function mb_strtolower($s, $enc = null): string { return strtolower((string)$s); }
    function mb_strtoupper($s, $enc = null): string { return strtoupper((string)$s); }
    function mb_internal_encoding($enc = null) { return true; }
}
