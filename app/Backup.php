<?php
declare(strict_types=1);

/**
 * بکاپ خودکار و دستی دیتابیس
 *
 * - dump کامل با PDO (بدون نیاز به mysqldump یا exec)
 * - نسخه‌گذاری با تاریخ + چرخش خودکار (نگه‌داری N نسخه)
 * - ارسال نسخه پشتیبان به کانال/چت بله (محدودیت حجم بله رعایت می‌شود)
 * - بازیابی از فایل آپلودی یا نسخه موجود
 * - ذخیره در پوشه‌ای خارج از وب‌روت (اگر ممکن باشد)، وگرنه محافظت .htaccess
 */
final class Backup
{
    public static function dir(): string
    {
        $custom = (string)(Schema::getSetting('backup_dir', '') ?: '');
        if ($custom !== '' && is_dir($custom) && is_writable($custom)) {
            return rtrim($custom, '/');
        }
        return BGM_ROOT . '/storage/backups';
    }

    public static function ensureDir(): string
    {
        $dir = self::dir();
        ensure_dir($dir);
        $ht = $dir . '/.htaccess';
        if (!is_file($ht)) {
            @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        }
        @file_put_contents($dir . '/index.html', '');
        return $dir;
    }

    public static function maxFiles(): int
    {
        return max(1, (int)(Schema::getSetting('backup_keep', '10') ?: 10));
    }

    public static function autoEnabled(): bool
    {
        return (string)Schema::getSetting('backup_auto', '1') === '1';
    }

    public static function frequencyHours(): int
    {
        return max(1, (int)(Schema::getSetting('backup_every_hours', '24') ?: 24));
    }

    /**
     * ساخت نسخه پشتیبان
     * @return array{ok:bool,file?:string,size?:int,tables?:int,rows?:int,error?:string}
     */
    public static function create(string $kind = 'manual', ?int $adminId = null, string $note = ''): array
    {
        $dir = self::ensureDir();
        if (!is_writable($dir)) {
            return ['ok' => false, 'error' => 'پوشه بکاپ قابل نوشتن نیست: ' . $dir];
        }
        $file = $dir . '/backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.sql';
        $fh   = @fopen($file, 'wb');
        if (!$fh) {
            return ['ok' => false, 'error' => 'ساخت فایل بکاپ ناموفق بود.'];
        }
        $tables = 0;
        $rows   = 0;
        $pdo    = DB::pdo();
        try {
            $header = "-- Bale Group Manager backup\n-- date: " . date('c') . "\n"
                . "-- host: " . (string)config('db.host', '') . " db: " . (string)config('db.name', '') . "\n"
                . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";
            fwrite($fh, $header);

            $names = DB::all("SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type='BASE TABLE' ORDER BY table_name");
            foreach ($names as $row) {
                $table = (string)($row['t'] ?? $row['TABLE_NAME'] ?? '');
                if ($table === '') {
                    continue;
                }
                $create = DB::one("SHOW CREATE TABLE `$table`");
                $ddl    = (string)($create['Create Table'] ?? $create['Create View'] ?? '');
                fwrite($fh, "DROP TABLE IF EXISTS `$table`;\n" . $ddl . ";\n\n");
                $tables++;

                $st = $pdo->query("SELECT * FROM `$table`");
                $batch = [];
                while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    $vals = [];
                    foreach ($r as $v) {
                        if ($v === null) {
                            $vals[] = 'NULL';
                        } elseif (is_int($v) || is_float($v)) {
                            $vals[] = (string)$v;
                        } else {
                            $vals[] = $pdo->quote((string)$v);
                        }
                    }
                    $batch[] = '(' . implode(',', $vals) . ')';
                    $rows++;
                    if (count($batch) >= 200) {
                        fwrite($fh, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }
                if ($batch) {
                    fwrite($fh, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
                }
                fwrite($fh, "\n");
            }
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fh);

            $size = (int)@filesize($file);
            DB::q("INSERT INTO backup_log (filename,size_bytes,tables_count,rows_count,kind,admin_id,note,created_at)
                   VALUES (?,?,?,?,?,?,?,?)",
                [basename($file), $size, $tables, $rows, $kind, $adminId, str_limit($note, 250), time()]);
            Logger::audit($adminId, 'backup', 'backup_create', basename($file), "$tables جدول، $rows ردیف، " . self::humanSize($size));
            self::prune();
            Schema::setSetting('last_backup_at', (string)time());
            return ['ok' => true, 'file' => basename($file), 'size' => $size, 'tables' => $tables, 'rows' => $rows, 'path' => $file];
        } catch (Throwable $e) {
            @fclose($fh);
            @unlink($file);
            Logger::error('backup', 'create failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** اجرای بکاپ خودکار در صورت رسیدن زمان */
    public static function maybeAuto(): ?array
    {
        if (!self::autoEnabled()) {
            return null;
        }
        $last = (int)(Schema::getSetting('last_backup_at', '0') ?: 0);
        if ($last > 0 && (time() - $last) < self::frequencyHours() * 3600) {
            return null;
        }
        // قفل برای جلوگیری از اجرای هم‌زمان
        $lock = Cache::lock('backup', 5);
        if ($lock === false) {
            return null;
        }
        try {
            $res = self::create('cron', null, 'خودکار');
            if ($res['ok']) {
                $sendTo = (int)(Schema::getSetting('backup_send_chat', '0') ?: 0);
                if ($sendTo !== 0) {
                    BaleApi::sendDocument($sendTo, (string)$res['path'],
                        "🗄 نسخه پشتیبان خودکار\n" . date('Y-m-d H:i') . "\n" . self::humanSize((int)$res['size'])
                        . "\n" . digits_fa((int)$res['tables']) . " جدول · " . digits_fa((int)$res['rows']) . " ردیف");
                }
            }
            return $res;
        } finally {
            Cache::unlock('backup', $lock);
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function list(): array
    {
        $dir  = self::dir();
        $rows = DB::all("SELECT * FROM backup_log ORDER BY id DESC LIMIT 200");
        foreach ($rows as &$r) {
            $path = $dir . '/' . $r['filename'];
            $r['exists'] = is_file($path);
            $r['path']   = $path;
            $r['size_h'] = self::humanSize((int)$r['size_bytes']);
        }
        return $rows;
    }

    /** فایل‌های موجود در پوشه که در جدول نیستند (بکاپ دستی کپی‌شده) */
    public static function orphanFiles(): array
    {
        $dir = self::dir();
        $known = array_column(DB::all("SELECT filename FROM backup_log"), 'filename');
        $out = [];
        foreach (glob($dir . '/*.sql*') ?: [] as $f) {
            if (!in_array(basename($f), $known, true)) {
                $out[] = ['filename' => basename($f), 'size' => (int)@filesize($f)];
            }
        }
        return $out;
    }

    public static function delete(string $filename, ?int $adminId = null): bool
    {
        if (!preg_match('~^[A-Za-z0-9._-]+$~', $filename)) {
            return false;
        }
        $path = self::dir() . '/' . $filename;
        $ok   = is_file($path) ? @unlink($path) : true;
        DB::q("DELETE FROM backup_log WHERE filename=?", [$filename]);
        Logger::audit($adminId, 'backup', 'backup_delete', $filename);
        return $ok;
    }

    /** چرخش: فقط N فایل آخر بماند */
    public static function prune(): int
    {
        $keep = self::maxFiles();
        $rows = DB::all("SELECT filename FROM backup_log ORDER BY id DESC LIMIT 500");
        $n = 0;
        foreach ($rows as $i => $r) {
            if ($i < $keep) {
                continue;
            }
            $path = self::dir() . '/' . $r['filename'];
            if (is_file($path)) {
                @unlink($path);
                $n++;
            }
            DB::q("DELETE FROM backup_log WHERE filename=?", [$r['filename']]);
        }
        return $n;
    }

    /**
     * بازیابی از فایل SQL
     * - پیش از بازیابی یک بکاپ ایمنی از وضعیت فعلی گرفته می‌شود
     * - اجرا در تراکنش نیست (DDL) اما FOREIGN_KEY_CHECKS خاموش و سپس روشن می‌شود
     */
    public static function restore(string $sqlPath, ?int $adminId = null): array
    {
        if (!is_file($sqlPath) || !is_readable($sqlPath)) {
            return ['ok' => false, 'error' => 'فایل بکاپ پیدا نشد.'];
        }
        $size = (int)filesize($sqlPath);
        if ($size > 200 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'فایل بیش از ۲۰۰MB است؛ با ابزار خط فرمان بازیابی کنید.'];
        }
        $safety = self::create('manual', $adminId, 'قبل از بازیابی');
        $sql    = (string)file_get_contents($sqlPath);
        if (trim($sql) === '') {
            return ['ok' => false, 'error' => 'فایل خالی است.'];
        }
        $pdo = DB::pdo();
        $ok = 0;
        $err = [];
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach (self::splitStatements($sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || str_starts_with($stmt, '--')) {
                    continue;
                }
                try {
                    $pdo->exec($stmt);
                    $ok++;
                } catch (Throwable $e) {
                    $err[] = str_limit($e->getMessage(), 160);
                }
            }
        } catch (Throwable $e) {
            $err[] = $e->getMessage();
        } finally {
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable $e) {
            }
        }
        Cache::gc();
        Logger::audit($adminId, 'backup', 'backup_restore', basename($sqlPath),
            "اجرا {$ok} دستور، خطا " . count($err) . ($safety['ok'] ? ' | بکاپ ایمنی: ' . $safety['file'] : ''));
        if ($err) {
            return ['ok' => count($err) < 3, 'executed' => $ok, 'errors' => array_slice($err, 0, 5),
                'safety' => $safety['file'] ?? null];
        }
        return ['ok' => true, 'executed' => $ok, 'safety' => $safety['file'] ?? null];
    }

    /** تقسیم SQL به دستورها با احترام به رشته‌ها و کامنت‌ها */
    public static function splitStatements(string $sql): array
    {
        $out = [];
        $buf = '';
        $len = strlen($sql);
        $inS = false;   // '
        $inD = false;   // "
        $inC = false;   // -- comment
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $sql[$i + 1] ?? '';
            if ($inC) {
                if ($ch === "\n") {
                    $inC = false;
                }
                continue;
            }
            if ($inS) {
                $buf .= $ch;
                if ($ch === '\\') {
                    $buf .= $next;
                    $i++;
                    continue;
                }
                if ($ch === "'") {
                    $inS = false;
                }
                continue;
            }
            if ($inD) {
                $buf .= $ch;
                if ($ch === '\\') {
                    $buf .= $next;
                    $i++;
                    continue;
                }
                if ($ch === '"') {
                    $inD = false;
                }
                continue;
            }
            if ($ch === '-' && $next === '-') {
                $inC = true;
                $i++;
                continue;
            }
            if ($ch === "'") {
                $inS = true;
                $buf .= $ch;
                continue;
            }
            if ($ch === '"') {
                $inD = true;
                $buf .= $ch;
                continue;
            }
            if ($ch === ';') {
                if (trim($buf) !== '') {
                    $out[] = $buf;
                }
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $out[] = $buf;
        }
        return $out;
    }

    public static function humanSize(int $bytes): string
    {
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $v = (float)$bytes;
        while ($v >= 1024 && $i < 3) {
            $v /= 1024;
            $i++;
        }
        return digits_fa(number_format($v, $i === 0 ? 0 : 1)) . ' ' . $u[$i];
    }

    /** اختلاف زمان تا بکاپ بعدی (ثانیه) */
    public static function nextRunIn(): int
    {
        $last = (int)(Schema::getSetting('last_backup_at', '0') ?: 0);
        if (!$last) {
            return 0;
        }
        return max(0, ($last + self::frequencyHours() * 3600) - time());
    }
}
