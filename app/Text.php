<?php
declare(strict_types=1);

/**
 * نرمال‌سازی متن فارسی/عربی + تشخیص لینک/فحش/اسپم
 * نکته مهم: نرمال‌سازی «قبل از» تشخیص انجام می‌شود تا دور زدن با
 *  - نیم‌فاصله، کاراکترهای عربی (ي/ك)، اعراب، ZWNJ/ZWJ، اموجی
 *  - فاصله بین حروف (ک ی ر)
 *  - اعداد فارسی
 *  ممکن نباشد.
 */
final class Text
{
    private const PERSIAN_DIGITS = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    private const ARABIC_DIGITS  = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];

    public static function normalize(?string $s): string
    {
        $s = (string)$s;
        $s = str_replace(['ي', 'ى', 'ك', 'ۀ', 'ة', 'أ', 'إ', 'آ'], ['ی', 'ی', 'ک', 'ه', 'ه', 'ا', 'ا', 'ا'], $s);
        $s = str_replace(self::PERSIAN_DIGITS, range('0', '9'), $s);
        $s = str_replace(self::ARABIC_DIGITS, range('0', '9'), $s);
        $s = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{FEFF}]/u', '', $s); // ZWJ/ZWNJ و کنترل‌ها
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $s); // اعراب
        $s = preg_replace('/[ \t\x{00A0}]+/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim(mb_strtolower($s, 'UTF-8'));
    }

    /** نسخه فشرده‌شده: بدون فاصله و بدون ایموجی/نماد — برای تشخیص فحش‌های دور زده‌شده */
    public static function squash(?string $s): string
    {
        $s = self::normalize($s);
        $s = preg_replace('/[^\p{L}\p{N}]+/u', '', $s);
        return (string)$s;
    }

    public static function hasLink(?string $text): bool
    {
        $t = (string)$text;
        if ($t === '') {
            return false;
        }
        $patterns = [
            '~https?://\S+~iu',
            '~www\.\S+~iu',
            '~\bt\.me/\S+~iu',
            '~\bble\.ir/\S+~iu',
            '~\btelegram\.me/\S+~iu',
            '~\b(?:[a-z0-9-]+\.)+(?:com|net|org|ir|io|me|xyz|info|co|dev|app|site|online|shop|link|top|club|tv|ru|uk|de|ca|au|biz)\b~iu',
            '~(?:^|\s)@[a-z0-9_]{4,}\b~iu',                     // یوزرنیم
            '~\b(?:۰|0)?9\d{9}\b~u',                            // شماره موبایل
            '~(?:t\.me|telegram\.me)/\+?\w+~iu',
            '~\bjoinchat/\w+~iu',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $t)) {
                return true;
            }
        }
        // لینک با فاصله/کاراکتر مخفی: h t t p : / /
        $squashed = preg_replace('/\s+/u', '', mb_strtolower($t));
        if (preg_match('~(https?://|www\.|t\.me/)~iu', $squashed) && str_contains($squashed, '.')) {
            return true;
        }
        return false;
    }

    /** @param string[] $words */
    public static function hasBadWord(?string $text, array $words): ?string
    {
        if (!$words || !$text) {
            return null;
        }
        $norm    = self::normalize($text);
        $squash  = self::squash($text);
        foreach ($words as $w) {
            $w = trim((string)$w);
            if ($w === '') {
                continue;
            }
            if (preg_match('~^/.*/[a-z]*$~i', $w)) { // regex سفارشی
                if (@preg_match($w, $norm)) {
                    return $w;
                }
                continue;
            }
            $wn = self::normalize($w);
            if ($wn === '') {
                continue;
            }
            $ws = self::squash($w);
            // کلمات ۳ حرف به بالا: حتی با فاصله/نماد در میان متنِ چسبیده هم پیدا می‌شوند
            if (mb_strlen($ws) >= 3 && str_contains($squash, $ws)) {
                return $w;
            }
            // کلمات کوتاه (۲ تا ۳ حرف): فقط با مرز واژه، تا متن سالم جریمه نشود
            if (preg_match('~(?<![\p{L}\p{N}])' . preg_quote($wn, '~') . '(?![\p{L}\p{N}])~u', $norm)) {
                return $w;
            }
        }
        return null;
    }

    /** تشخیص تکرار/کپی (اسپم تکراری) */
    public static function fingerprint(?string $text): string
    {
        return sha1(self::squash($text));
    }

    /** آیا متن، دستور فارسی/انگلیسی است؟ (کلمات با _ یا کامندهای شناخته‌شده) */
    public static function looksLikeCommand(?string $text, array $commands): ?array
    {
        $t = self::normalize($text);
        if ($t === '') {
            return null;
        }
        // پشتیبانی از /command و !command و بدون پیشوند
        $t = preg_replace('~^[/!#]~u', '', $t);
        $parts = explode(' ', $t, 2);
        $cmd   = $parts[0];
        $rest  = $parts[1] ?? '';
        if (in_array($cmd, $commands, true)) {
            return ['cmd' => $cmd, 'rest' => trim($rest), 'raw' => $text];
        }
        return null;
    }
}
