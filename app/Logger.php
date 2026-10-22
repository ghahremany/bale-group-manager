<?php
declare(strict_types=1);

/**
 * لاگ‌گیری امن: چرخش روزانه + ماسک توکن + ثبت رخداد در دیتابیس
 */
final class Logger
{
    private static function write(string $level, string $channel, string $message, array $context = []): void
    {
        $text = self::mask($message);
        if ($context) {
            $json = json_encode(self::maskDeep($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $text .= ' ' . ($json !== false ? str_limit($json, 1200) : '');
        }
        $line = sprintf("[%s] %s.%s: %s\n", date('Y-m-d H:i:s'), $level, $channel, $text);
        $dir  = BGM_ROOT . '/storage/logs';
        ensure_dir($dir);
        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
        if (config('app.debug')) {
            error_log(trim($line));
        }
        // چرخش: نگه‌داری ۱۴ روز
        if (random_int(1, 200) === 1) {
            foreach (glob($dir . '/app-*.log') ?: [] as $f) {
                if (@filemtime($f) < time() - 14 * 86400) {
                    @unlink($f);
                }
            }
        }
    }

    public static function mask(string $s): string
    {
        $token = (string)(config('bot.token') ?? '');
        if ($token !== '' && strlen($token) > 10) {
            $s = str_replace($token, '«TOKEN»', $s);
        }
        return preg_replace('~bot\d+:[\w-]{20,}~', 'bot«TOKEN»', $s) ?? $s;
    }

    private static function maskDeep($v)
    {
        if (is_array($v)) {
            foreach ($v as $k => $vv) {
                if (in_array((string)$k, ['token', 'password', 'pass', 'secret', 'code', 'otp'], true)) {
                    $v[$k] = '***';
                } else {
                    $v[$k] = self::maskDeep($vv);
                }
            }
            return $v;
        }
        return is_string($v) ? self::mask($v) : $v;
    }

    public static function info(string $channel, string $msg, array $ctx = []): void
    {
        self::write('INFO', $channel, $msg, $ctx);
    }

    public static function warn(string $channel, string $msg, array $ctx = []): void
    {
        self::write('WARN', $channel, $msg, $ctx);
    }

    public static function error(string $channel, string $msg, array $ctx = []): void
    {
        self::write('ERROR', $channel, $msg, $ctx);
    }

    /** ثبت رخداد گروه در DB (برای نمایش در پنل) + شمارنده روزانه */
    public static function user(?int $chatId, ?int $actorId, ?int $targetId, string $action, string $detail = ''): void
    {
        try {
            DB::q("INSERT INTO logs (chat_id,actor_id,target_id,action,detail,ts) VALUES (?,?,?,?,?,?)",
                [$chatId, $actorId, $targetId, $action, str_limit($detail, 480), time()]);
            if ($chatId) {
                if (in_array($action, ['warn', 'ban', 'tempban', 'mute', 'kick'], true)) {
                    Metrics::bump($chatId, 'violations');
                } elseif ($action === 'join') {
                    Metrics::bump($chatId, 'joins');
                } elseif ($action === 'left') {
                    Metrics::bump($chatId, 'lefts');
                } elseif ($action === 'command') {
                    Metrics::bump($chatId, 'commands');
                }
            }
        } catch (Throwable $e) {
            self::warn('db', 'log insert failed: ' . $e->getMessage());
        }
    }

    /** ثبت اقدام ادمین پنل */
    public static function audit(?int $adminId, string $username, string $action, string $target = '', string $detail = ''): void
    {
        try {
            DB::q("INSERT INTO audit_log (admin_id,username,action,target,detail,ip,ts) VALUES (?,?,?,?,?,?,?)",
                [$adminId, str_limit($username, 60), $action, str_limit($target, 110), str_limit($detail, 2000), client_ip(), time()]);
        } catch (Throwable $e) {
            self::warn('db', 'audit insert failed: ' . $e->getMessage());
        }
    }
}
