<?php
declare(strict_types=1);

/**
 * پایه‌ی ماژول‌ها
 *
 * هدف: بخش‌های مستقل (کانال، زیرمجموعه‌گیری، اخبار، اجبار عضویت، بازی، …) بدون
 * دست‌زدن به هسته اضافه/حذف شوند. هر ماژول یک کلاس در app/Modules/ است و:
 *   - کلید و عنوان و توضیح دارد
 *   - می‌تواند صفحه پنل، دستور ربات و کار کرون داشته باشد
 *   - روشن/خاموش شدنش در تنظیمات ذخیره می‌شود (module_on_<key>)
 *   - اگر جدول لازم دارد، خودش اعلام می‌کند (Schema::upgrade آن‌ها را می‌سازد)
 */
abstract class Module
{
    /** کلید یکتا (انگلیسی، بدون فاصله) */
    abstract public static function key(): string;

    /** عنوان فارسی برای پنل */
    abstract public static function title(): string;

    /** توضیح یک‌خطی */
    abstract public static function description(): string;

    /** ایموجی/آیکون منو */
    public static function icon(): string
    {
        return '🧩';
    }

    /** پیش‌فرض روشن باشد؟ */
    public static function defaultEnabled(): bool
    {
        return true;
    }

    /** آیا این ماژول در این نصب قابل استفاده است (مثلاً وابستگی) */
    public static function available(): bool
    {
        return true;
    }

    public static function isEnabled(): bool
    {
        $v = Schema::getSetting('module_on_' . static::key(), null);
        if ($v === null) {
            return static::defaultEnabled();
        }
        return (string)$v === '1';
    }

    public static function setEnabled(bool $on): void
    {
        Schema::setSetting('module_on_' . static::key(), $on ? '1' : '0');
        Logger::audit(null, 'panel', 'module_toggle', static::key(), $on ? 'روشن' : 'خاموش');
    }

    /**
     * دستورهای ربات این ماژول
     * @return array<string,string> نقشه دستور => نام متد هندلر (در همین کلاس)
     */
    public static function commands(): array
    {
        return [];
    }

    /**
     * هندل کردن دستور؛ پیش‌فرض: پیدا کردن متد هم‌نام و صدا زدنش
     * @param array<string,mixed> $ctx ساختار دستور در Commands::parse
     */
    public static function handleCommand(string $command, array $ctx): bool
    {
        $map = static::commands();
        if (!isset($map[$command])) {
            return false;
        }
        $method = $map[$command];
        if (method_exists(static::class, $method)) {
            static::$method($ctx);
            return true;
        }
        return false;
    }

    /**
     * دکمه‌های شیشه‌ای (callback) مربوط به این ماژول
     * @param array<string,mixed> $cb آپدیت callback_query
     * @return bool آیا این ماژول داده را مصرف کرد؟
     */
    public static function handleCallback(string $data, array $cb): bool
    {
        return false;
    }

    /**
     * پست تازه در کانالی که ربات ادمین آن است (آپدیت channel_post)
     * @param array<string,mixed> $post
     * @return bool آیا این ماژول پست را مصرف کرد؟
     */
    public static function handleChannelPost(array $post): bool
    {
        return false;
    }

    /** کارهای دوره‌ای (کرون) — پیش‌فرض هیچ */
    public static function cron(): void
    {
    }

    /**
     * داده‌های صفحه پنل این ماژول
     * @return array<string,mixed>|null null = صفحه ندارد
     */
    public static function panelData(): ?array
    {
        return null;
    }

    /** نام ویو پنل (views/modules/<key>.php) */
    public static function panelView(): ?string
    {
        return static::panelData() === null ? null : 'modules/' . static::key();
    }

    /**
     * عملیات POST پنل (فرم‌های ماژول)
     * @return string|null پیام فلش (ok) یا null
     */
    public static function handleAction(string $action, array $admin): ?string
    {
        return null;
    }

    /** آخرین پیام خطای عملیات (برای نمایش در پنل) */
    public static ?string $lastError = null;

    public static function resetError(): void
    {
        static::$lastError = null;
    }

    /** ثبت خطا و برگرداندن null؛ PanelActions پیام را به‌صورت flash خطا نشان می‌دهد */
    protected static function fail(string $msg): ?string
    {
        static::$lastError = $msg;
        return null;
    }

    /** آمار کوتاه برای کارت ماژول در صفحه «ماژول‌ها» */
    public static function badge(): string
    {
        return '';
    }

    // ---------- کمکی ----------

    /** ارسال پیام به هر چت (برای ماژول‌هایی که به کانال می‌فرستند) */
    protected static function sayTo(int|string $chatId, string $text, ?array $markup = null): ?int
    {
        return BaleApi::sendMessage($chatId, $text, null, $markup, 'HTML');
    }

    protected static function say(array $ctx, string $text): void
    {
        BaleApi::sendMessage((int)$ctx['chat_id'], $text, null, null, 'HTML');
    }

    /** بررسی ادمین بودن فرستنده در گروه */
    protected static function isAdmin(array $ctx): bool
    {
        return !empty($ctx['is_admin']);
    }
}
