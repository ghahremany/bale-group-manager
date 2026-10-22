<?php
/**
 * نمونه فایل تنظیمات — نصب‌کننده (install.php) این فایل را به صورت خودکار می‌سازد.
 * این فایل را در مسیر public_html/config/config.php نگه دارید و دسترسی آن را 600 کنید.
 */
return [
    'app' => [
        'name'       => 'مدیر گروه بله',
        'url'        => 'https://example.com',          // آدرس دقیق نصب، بدون اسلش انتهایی
        'key'        => 'CHANGE_ME_32_BYTES_RANDOM_STRING',
        'timezone'   => 'Asia/Tehran',
        'debug'      => false,
        'locale'     => 'fa',
    ],

    'db' => [
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'bale_bot',
        'user'    => 'bale_bot',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'bot' => [
        'token'      => '',                              // توکن ربات از @BotFather بله
        'username'   => '',                              // بدون @ مثل MyGroupManagerBot
        'api_base'   => 'https://tapi.bale.ai',          // قابل تغییر برای پروکسی/میرور
        'mode'       => 'webhook',                       // webhook | polling
        'webhook_secret' => '',                          // رشته تصادفی، برای امنیت بیشتر
        'super_admins'   => [],                          // آیدی عددی ادمین‌های سراسری ربات
        'polling_timeout' => 30,
        'max_retries'     => 3,
    ],

    'security' => [
        'session_name'      => 'bgm_sid',
        'session_ttl'       => 21600,   // 6 ساعت
        'otp_ttl'           => 180,     // 3 دقیقه
        'otp_resend_after'  => 30,      // 30 ثانیه
        'otp_max_attempts'  => 5,
        'login_max_fails'   => 6,
        'login_lock_minutes'=> 15,
        'force_https'       => false,
    ],

    'cron' => [
        'secret' => 'CHANGE_ME_CRON_SECRET',   // برای صدا زدن cron از طریق URL
    ],
];
