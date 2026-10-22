<?php
declare(strict_types=1);

/**
 * تنظیمات هر گروه + کش
 */
final class Settings
{
    /** پیش‌فرض‌ها (همه در یک جا؛ نه پخش‌شده در کد مثل نسخه پایتون) */
    public const DEFAULTS = [
        'anti_link'        => 1,
        'anti_spam'        => 1,
        'spam_window_sec'  => 8,
        'spam_max_msgs'    => 6,
        'bad_words'        => 1,
        'anti_bot'         => 0,
        'anti_forward'     => 0,
        'anti_username'    => 1,
        'anti_flood_action'=> 'mute',
        'flood_mute_sec'   => 300,
        'warn_limit'       => 3,
        'warn_action'      => 'mute',
        'warn_mute_sec'    => 3600,
        'welcome_on'       => 1,
        'welcome_text'     => "سلام {name} خوش اومدی 👋\n{group}",
        'goodbye_on'       => 0,
        'goodbye_text'     => 'خداحافظ {name}',
        'rules'            => '',
        'lock_link'        => 0,
        'lock_photo'       => 0,
        'lock_video'       => 0,
        'lock_gif'         => 0,
        'lock_sticker'     => 0,
        'lock_voice'       => 0,
        'lock_document'    => 0,
        'lock_forward'     => 0,
        'lock_contact'     => 0,
        'lock_poll'        => 0,
        'lock_location'    => 0,
        'lock_bot'         => 0,
        'captcha_on'       => 0,
        'captcha_timeout_sec' => 90,
        'log_channel_id'   => 0,
        'report_channel_id'=> 0,
        'lang'             => 'fa',
        'is_active'        => 1,
    ];

    public static function ensure(int $chatId, string $title = '', string $type = ''): array
    {
        $row = DB::one("SELECT * FROM chat_settings WHERE chat_id=?", [$chatId]);
        if (!$row) {
            DB::q("INSERT INTO chat_settings (chat_id,title,chat_type,created_at,updated_at) VALUES (?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE updated_at=VALUES(updated_at)",
                [$chatId, str_limit($title, 250), str_limit($type, 30), time(), time()]);
            $row = DB::one("SELECT * FROM chat_settings WHERE chat_id=?", [$chatId]) ?: [];
        } elseif ($title !== '' && ($row['title'] ?? '') !== $title) {
            DB::q("UPDATE chat_settings SET title=? WHERE chat_id=?", [str_limit($title, 250), $chatId]);
            $row['title'] = $title;
        }
        return self::hydration($row);
    }

    public static function get(int $chatId): array
    {
        $key = 'settings:' . $chatId;
        $hit = Cache::get($key);
        if (is_array($hit)) {
            return $hit;
        }
        $row = DB::one("SELECT * FROM chat_settings WHERE chat_id=?", [$chatId]);
        if (!$row) {
            $data = self::DEFAULTS + ['chat_id' => $chatId, 'title' => '', 'chat_type' => ''];
            return $data;
        }
        $data = self::hydration($row);
        Cache::set($key, $data, 120);
        return $data;
    }

    private static function hydration(array $row): array
    {
        $out = $row;
        foreach (self::DEFAULTS as $k => $v) {
            if (!array_key_exists($k, $out) || $out[$k] === null) {
                $out[$k] = $v;
            }
            if (is_int($v)) {
                $out[$k] = (int)$out[$k];
            }
        }
        return $out;
    }

    public static function flush(int $chatId): void
    {
        Cache::delete('settings:' . $chatId);
    }

    public static function set(int $chatId, string $field, $value): bool
    {
        $allowed = array_keys(self::DEFAULTS);
        if (!in_array($field, $allowed, true)) {
            return false;
        }
        if (is_int(self::DEFAULTS[$field])) {
            $value = (int)$value;
        } elseif (is_string(self::DEFAULTS[$field])) {
            $value = (string)$value;
        }
        self::ensure($chatId);
        DB::q("UPDATE chat_settings SET `$field`=?, updated_at=? WHERE chat_id=?", [$value, time(), $chatId]);
        self::flush($chatId);
        return true;
    }

    public static function toggle(int $chatId, string $field): int
    {
        $cur = (int)(self::get($chatId)[$field] ?? 0);
        $new = $cur ? 0 : 1;
        self::set($chatId, $field, $new);
        return $new;
    }

    /** @return string[] */
    public static function badWords(int $chatId): array
    {
        return Cache::remember('badwords:' . $chatId, 120, function () use ($chatId) {
            $rows = DB::all("SELECT word FROM bad_words WHERE chat_id IS NULL OR chat_id=?", [$chatId]);
            return array_map(static fn($r) => (string)$r['word'], $rows);
        });
    }

    public static function flushBadWords(int $chatId): void
    {
        Cache::delete('badwords:' . $chatId);
    }

    /** گروه‌هایی که ربات در آن‌هاست */
    public static function allActive(): array
    {
        return DB::all("SELECT * FROM chat_settings WHERE is_active=1 ORDER BY updated_at DESC");
    }
}
