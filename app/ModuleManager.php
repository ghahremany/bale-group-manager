<?php
declare(strict_types=1);

/**
 * کشف و اجرای ماژول‌ها
 *
 * هر فایل در app/Modules/ یک کلاس با نام هم‌نام فایل و parent = Module تعریف می‌کند.
 * برای اضافه کردن قابلیت جدید (اخبار، بازی، نظرسنجی، …) کافی است یک فایل اضافه کنی؛
 * نه روتري لازم است نه تغییری در Bot/Cron/PanelActions.
 */
final class ModuleManager
{
    /** @var array<string,class-string<Module>>|null */
    private static ?array $registry = null;

    /** @return array<string,class-string<Module>> */
    public static function all(): array
    {
        if (self::$registry !== null) {
            return self::$registry;
        }
        $out = [];
        $dir = BGM_ROOT . '/app/Modules';
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $class = 'Modules\\' . basename($file, '.php');
            if (!class_exists($class)) {
                continue;
            }
            if (!is_subclass_of($class, Module::class)) {
                continue;
            }
            try {
                $key = (string)$class::key();
            } catch (Throwable $e) {
                Logger::warn('module', 'bad module ' . $class . ': ' . $e->getMessage());
                continue;
            }
            if ($key === '') {
                continue;
            }
            $out[$key] = $class;
        }
        ksort($out);
        self::$registry = $out;
        return $out;
    }

    /** @return array<string,class-string<Module>> */
    public static function enabled(): array
    {
        $out = [];
        foreach (self::all() as $key => $class) {
            try {
                if ($class::available() && $class::isEnabled()) {
                    $out[$key] = $class;
                }
            } catch (Throwable $e) {
                Logger::warn('module', 'enabled check failed: ' . $class . ' ' . $e->getMessage());
            }
        }
        return $out;
    }

    public static function get(string $key): ?string
    {
        return self::all()[$key] ?? null;
    }

    /** آیا ماژولی با این کلید روشن است؟ */
    public static function active(string $key): bool
    {
        $class = self::get($key);
        return $class !== null && $class::isEnabled() && $class::available();
    }

    /**
     * تلاش برای اجرای دستور در ماژول‌ها
     * @param array<string,mixed> $ctx
     */
    public static function dispatchCommand(string $command, array $ctx): bool
    {
        foreach (self::enabled() as $class) {
            try {
                if ($class::handleCommand($command, $ctx)) {
                    return true;
                }
            } catch (Throwable $e) {
                Logger::error('module', 'command failed: ' . $class . ' / ' . $command . ' — ' . $e->getMessage());
            }
        }
        return false;
    }

    /**
     * پخش callback (دکمه شیشه‌ای) بین ماژول‌های فعال
     * @param array<string,mixed> $cb
     */
    public static function dispatchCallback(string $data, array $cb): bool
    {
        foreach (self::enabled() as $class) {
            try {
                if (method_exists($class, 'handleCallback') && $class::handleCallback($data, $cb)) {
                    return true;
                }
            } catch (Throwable $e) {
                Logger::error('module', 'callback failed: ' . $class . ' / ' . $data . ' — ' . $e->getMessage());
            }
        }
        return false;
    }

    /**
     * پخش پست کانال بین ماژول‌های فعال
     * @param array<string,mixed> $post
     */
    public static function dispatchChannelPost(array $post): bool
    {
        foreach (self::enabled() as $class) {
            try {
                if (method_exists($class, 'handleChannelPost') && $class::handleChannelPost($post)) {
                    return true;
                }
            } catch (Throwable $e) {
                Logger::error('module', 'channel post failed: ' . $class . ' — ' . $e->getMessage());
            }
        }
        return false;
    }

    /** همه دستورهای ماژول‌های فعال (برای راهنما) */
    public static function commands(): array
    {
        $out = [];
        foreach (self::enabled() as $key => $class) {
            foreach ($class::commands() as $cmd => $_) {
                $out[$cmd] = $key;
            }
        }
        return $out;
    }

    /** کارهای کرون همه ماژول‌های فعال */
    public static function runCron(): array
    {
        $done = [];
        foreach (self::enabled() as $key => $class) {
            try {
                $class::cron();
                $done[$key] = 'ok';
            } catch (Throwable $e) {
                $done[$key] = 'error: ' . $e->getMessage();
                Logger::error('module', 'cron failed: ' . $key . ' — ' . $e->getMessage());
            }
        }
        return $done;
    }

    /**
     * همه دستورهای CREATE TABLE ماژول‌ها (که Schema::upgrade اجرا می‌کند)
     * @return array<int,string>
     */
    public static function migrations(): array
    {
        $out = [];
        foreach (self::all() as $class) {
            if (method_exists($class, 'migrations')) {
                foreach ((array)$class::migrations() as $sql) {
                    $out[] = (string)$sql;
                }
            }
        }
        return $out;
    }

    /** فهرست کارت‌ها برای صفحه «ماژول‌ها» */
    public static function summary(): array
    {
        $rows = [];
        foreach (self::all() as $key => $class) {
            $rows[] = [
                'key'         => $key,
                'title'       => (string)$class::title(),
                'description' => (string)$class::description(),
                'icon'        => (string)$class::icon(),
                'enabled'     => $class::isEnabled(),
                'available'   => $class::available(),
                'has_page'    => $class::panelData() !== null,
                'badge'       => (string)$class::badge(),
                'commands'    => array_keys($class::commands()),
            ];
        }
        return $rows;
    }
}
