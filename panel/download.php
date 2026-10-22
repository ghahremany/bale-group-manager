<?php
declare(strict_types=1);

/**
 * دانلود امن فایل بکاپ (فقط مدیر ارشد، فقط فایل‌های مجاز پوشه بکاپ)
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

security_headers(false);
Auth::boot();
$needsLoginOnly = (string)($_GET['type'] ?? '') === 'receipt' || (string)($_GET['p'] ?? '') === 'csv';
$admin = $needsLoginOnly ? Auth::requireLogin() : Auth::requireSuper();

$type = (string)($_GET['type'] ?? 'backup');
$file = (string)($_GET['file'] ?? '');

// خروجی CSV انبار (کالا + موجودی) — در دسترس همه ادمین‌های پنل
if ((string)($_GET['p'] ?? '') === 'csv') {
    $csv = Store::exportCsv();
    Logger::audit((int)$admin['id'], (string)$admin['username'], 'inventory_export', 'csv',
        'rows=' . substr_count($csv, "\n"));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory-' . date('Ymd-His') . '.csv"');
    header('Content-Length: ' . (string)strlen($csv));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo $csv;
    exit;
}

// رسیدهای پرداخت: مدیر ارشد و اپراتور، با همان قواعد ضد Path Traversal
if ($type === 'receipt') {
    if (!preg_match('~^[A-Za-z0-9._-]+\.(jpg|jpeg|png|webp|pdf)$~i', $file)) {
        http_response_code(400);
        echo 'نام فایل نامعتبر';
        exit;
    }
    $dir = BGM_ROOT . '/storage/receipts';
    $real = realpath($dir . '/' . $file);
    $base = realpath($dir);
    if ($real === false || $base === false || !str_starts_with($real, $base) || !is_file($real)) {
        http_response_code(404);
        echo 'فایل پیدا نشد';
        exit;
    }
    Logger::audit((int)$admin['id'], (string)$admin['username'], 'receipt_view', $file);
    $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($ext === 'pdf' ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext)));
    header('Content-Disposition: inline; filename="' . basename($real) . '"');
    header('Content-Length: ' . (string)filesize($real));
    header('X-Content-Type-Options: nosniff');
    readfile($real);
    exit;
}

if ($type !== 'backup') {
    http_response_code(400);
    echo 'نوع نامعتبر';
    exit;
}
if (!preg_match('~^[A-Za-z0-9._-]+\.sql$~', $file)) {
    http_response_code(400);
    echo 'نام فایل نامعتبر';
    exit;
}
$dir  = Backup::dir();
$path = $dir . '/' . $file;
$real = realpath($path);
$base = realpath($dir);
if ($real === false || $base === false || !str_starts_with($real, $base) || !is_file($real)) {
    http_response_code(404);
    echo 'فایل پیدا نشد';
    exit;
}

Logger::audit((int)$admin['id'], (string)$admin['username'], 'backup_download', $file);

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . basename($real) . '"');
header('Content-Length: ' . (string)filesize($real));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$fh = fopen($real, 'rb');
if ($fh) {
    while (!feof($fh)) {
        echo fread($fh, 1024 * 128);
        flush();
    }
    fclose($fh);
}
exit;
