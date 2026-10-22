<?php
declare(strict_types=1);

/**
 * نقطه ورود اصلی: هدایت به نصب‌کننده یا پنل
 */
$installed = is_file(__DIR__ . '/config/installed.lock');
if (!$installed) {
    header('Location: install.php');
    exit;
}
header('Location: panel/');
exit;
