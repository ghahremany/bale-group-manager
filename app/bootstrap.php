<?php
declare(strict_types=1);

/**
 * بوت‌استرپ مشترک همه ورودی‌ها (پنل، وب‌هوک، کرون)
 */

if (!defined('BGM_ROOT')) {
    define('BGM_ROOT', dirname(__DIR__));
}

require_once BGM_ROOT . '/app/helpers.php';

spl_autoload_register(static function (string $class): void {
    $file = BGM_ROOT . '/app/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// اگر نصب نشده، به نصب‌کننده هدایت کن
if (!is_installed()) {
    if (PHP_SAPI !== 'cli' && !str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), 'install.php')) {
        header('Location: ' . rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/install.php');
        exit;
    }
    return;
}

date_default_timezone_set((string)config('app.timezone', 'Asia/Tehran'));
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// خطاها: هرگز به کاربر نمایش داده نشوند، همیشه لاگ شوند
$debug = (bool)config('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

set_error_handler(static function (int $no, string $str, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    Logger::warn('php', $str, ['at' => basename($file) . ':' . $line]);
    return true;
});

set_exception_handler(static function (Throwable $e) use ($debug): void {
    Logger::error('uncaught', get_class($e) . ': ' . $e->getMessage(), [
        'at'   => basename($e->getFile()) . ':' . $e->getLine(),
        'trace' => $debug ? str_limit($e->getTraceAsString(), 1500) : null,
    ]);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    if ($debug) {
        echo '<pre style="direction:ltr">' . h($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';
    } else {
        echo '<div style="font-family:sans-serif;direction:rtl;padding:2rem">خطای داخلی رخ داد. لطفاً بعداً تلاش کنید.</div>';
    }
});

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        Logger::error('fatal', $e['message'], ['at' => basename($e['file']) . ':' . $e['line']]);
    }
});

function security_headers(bool $html = true): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    if ($html) {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'");
    }
}
