<?php
declare(strict_types=1);

/**
 * آمار و نمودارها
 * - شمارنده‌های روزانه در جدول metrics_daily (بدون کوئری سنگین روی logs)
 * - رندر نمودار به صورت SVG خالص (بدون CDN / Chart.js → سازگار با CSP و آفلاین)
 */
final class Metrics
{
    /** افزایش شمارنده روز جاری */
    public static function bump(int $chatId, string $field, int $by = 1): void
    {
        $allowed = ['messages', 'violations', 'joins', 'lefts', 'commands'];
        if (!in_array($field, $allowed, true) || $by === 0) {
            return;
        }
        try {
            DB::q("INSERT INTO metrics_daily (day, chat_id, `$field`) VALUES (CURDATE(), ?, ?)
                   ON DUPLICATE KEY UPDATE `$field` = `$field` + VALUES(`$field`)", [$chatId, $by]);
        } catch (Throwable $e) {
            Logger::warn('metrics', 'bump failed: ' . $e->getMessage());
        }
    }

    /** سری زمانی n روز اخیر (شامل امروز) */
    public static function series(int $days = 14, ?int $chatId = null): array
    {
        $days = max(2, min(120, $days));
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $args = [$from];
        $where = 'WHERE day >= ?';
        if ($chatId) {
            $where .= ' AND chat_id = ?';
            $args[] = $chatId;
        }
        $rows = DB::all("SELECT day,
                                SUM(messages)   AS messages,
                                SUM(violations) AS violations,
                                SUM(joins)      AS joins,
                                SUM(lefts)      AS lefts,
                                SUM(commands)   AS commands
                         FROM metrics_daily $where GROUP BY day ORDER BY day", $args);
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[(string)$r['day']] = $r;
        }
        $out = ['labels' => [], 'messages' => [], 'violations' => [], 'joins' => [], 'lefts' => [], 'commands' => []];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime('-' . $i . ' days'));
            $out['labels'][] = self::dayLabel($d);
            $row = $byDay[$d] ?? [];
            foreach (['messages', 'violations', 'joins', 'lefts', 'commands'] as $f) {
                $out[$f][] = (int)($row[$f] ?? 0);
            }
        }
        return $out;
    }

    private static function dayLabel(string $ymd): string
    {
        $ts = strtotime($ymd . ' 12:00:00');
        return date('m/d', (int)$ts);
    }

    /**
     * پر کردن آمار روزانه از جدول logs برای روزهایی که metrics خالی است
     * (برای نصب‌های قبلی یا اگر شمارنده‌ها ریست شوند)
     */
    public static function backfill(int $days = 30): int
    {
        $from = date('Y-m-d', strtotime('-' . $days . ' days'));
        $rows = DB::all("SELECT DATE(FROM_UNIXTIME(ts)) day, chat_id, action, COUNT(*) c
                         FROM logs WHERE ts >= ? GROUP BY day, chat_id, action",
            [strtotime($from)]);
        $agg = [];
        foreach ($rows as $r) {
            $d = (string)$r['day'];
            $c = (int)$r['chat_id'];
            $a = (string)$r['action'];
            $agg[$d][$c][$a] = (int)$r['c'];
        }
        $n = 0;
        foreach ($agg as $d => $chats) {
            foreach ($chats as $chatId => $actions) {
                $messages   = 0;
                $violations = ($actions['warn'] ?? 0) + ($actions['ban'] ?? 0) + ($actions['tempban'] ?? 0)
                            + ($actions['mute'] ?? 0) + ($actions['kick'] ?? 0);
                $joins      = $actions['join'] ?? 0;
                $lefts      = $actions['left'] ?? 0;
                $commands   = $actions['command'] ?? 0;
                DB::q("INSERT INTO metrics_daily (day, chat_id, messages, violations, joins, lefts, commands)
                       VALUES (?,?,?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE
                         messages=GREATEST(messages, VALUES(messages)),
                         violations=GREATEST(violations, VALUES(violations)),
                         joins=GREATEST(joins, VALUES(joins)),
                         lefts=GREATEST(lefts, VALUES(lefts)),
                         commands=GREATEST(commands, VALUES(commands))",
                    [$d, (int)$chatId, $messages, $violations, $joins, $lefts, $commands]);
                $n++;
            }
        }
        return $n;
    }

    /** خلاصه دوره */
    public static function summary(int $days = 7, ?int $chatId = null): array
    {
        $s = self::series($days, $chatId);
        $sum = static fn(array $a) => array_sum($a);
        return [
            'messages'   => $sum($s['messages']),
            'violations' => $sum($s['violations']),
            'joins'      => $sum($s['joins']),
            'lefts'      => $sum($s['lefts']),
            'commands'   => $sum($s['commands']),
            'days'       => $days,
        ];
    }

    // ================= رندر SVG =================

    private static function col(string $key): string
    {
        return [
            'messages'   => '#3b82f6',
            'violations' => '#ef4444',
            'joins'      => '#22c55e',
            'lefts'      => '#f59e0b',
            'commands'   => '#8b5cf6',
        ][$key] ?? '#64748b';
    }

    private static function fa($v): string
    {
        return digits_fa(number_format((float)$v));
    }

    /** نمودار میله‌ای گروهی (چند سری) */
    public static function bars(array $series, array $keys, array $titles, int $w = 760, int $h = 240): string
    {
        $labels = $series['labels'];
        $n      = max(1, count($labels));
        $padL = 46; $padR = 12; $padT = 18; $padB = 30;
        $innerW = $w - $padL - $padR;
        $innerH = $h - $padT - $padB;

        $max = 1;
        foreach ($keys as $k) {
            $max = max($max, max($series[$k] ?: [0]));
        }
        $max  = (int)ceil($max * 1.15);
        $step = max(1, (int)ceil($max / 4));
        $max  = $step * 4;

        $groupW = $innerW / $n;
        $barW   = max(2.0, min(14.0, ($groupW * 0.68) / max(1, count($keys))));

        $svg  = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" height="' . $h . '" role="img" direction="ltr" style="font-family:Tahoma,sans-serif">';
        $svg .= '<rect x="0" y="0" width="' . $w . '" height="' . $h . '" fill="#0b1424"/>';
        // خطوط افقی + برچسب محور
        for ($i = 0; $i <= 4; $i++) {
            $y = $padT + $innerH - ($innerH * $i / 4);
            $v = $step * $i;
            $svg .= '<line x1="' . $padL . '" y1="' . round($y, 1) . '" x2="' . ($w - $padR) . '" y2="' . round($y, 1) . '" stroke="#1e2d47" stroke-width="1"/>';
            $svg .= '<text x="' . ($padL - 6) . '" y="' . round($y + 4, 1) . '" fill="#64748b" font-size="10" text-anchor="end" direction="ltr">' . self::fa($v) . '</text>';
        }
        foreach ($labels as $i => $label) {
            $gx = $padL + $groupW * $i + $groupW / 2;
            foreach ($keys as $j => $k) {
                $val = (int)($series[$k][$i] ?? 0);
                if ($val <= 0) {
                    continue;
                }
                $bh  = ($val / $max) * $innerH;
                $x   = $gx - ($barW * count($keys) + 2 * (count($keys) - 1)) / 2 + $j * ($barW + 2);
                $y   = $padT + $innerH - $bh;
                $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($barW, 1) . '" height="' . round($bh, 1) . '" rx="2" fill="' . self::col($k) . '">'
                      . '<title>' . h($titles[$k] ?? $k) . ' — ' . h($label) . ': ' . self::fa($val) . '</title></rect>';
            }
            if ($n <= 20 || $i % 2 === 0) {
                $svg .= '<text x="' . round($gx, 1) . '" y="' . ($h - 10) . '" fill="#8098bb" font-size="10" text-anchor="middle" direction="ltr">' . h($label) . '</text>';
            }
        }
        $svg .= '</svg>';
        return $svg;
    }

    /** نمودار خطی/ناحیه‌ای برای یک سری */
    public static function line(array $values, array $labels, string $color = '#3b82f6', int $w = 760, int $h = 200, string $title = ''): string
    {
        $n = max(1, count($values));
        $padL = 46; $padR = 12; $padT = 14; $padB = 28;
        $innerW = $w - $padL - $padR;
        $innerH = $h - $padT - $padB;
        $max = max(1, max($values ?: [0]));
        $max = (int)ceil($max * 1.2);

        $svg  = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" height="' . $h . '" role="img" direction="ltr" style="font-family:Tahoma,sans-serif">';
        $svg .= '<rect x="0" y="0" width="' . $w . '" height="' . $h . '" fill="#0b1424"/>';
        for ($i = 0; $i <= 3; $i++) {
            $y = $padT + $innerH - ($innerH * $i / 3);
            $svg .= '<line x1="' . $padL . '" y1="' . round($y, 1) . '" x2="' . ($w - $padR) . '" y2="' . round($y, 1) . '" stroke="#1e2d47"/>';
            $svg .= '<text x="' . ($padL - 6) . '" y="' . round($y + 4, 1) . '" fill="#64748b" font-size="10" text-anchor="end">' . self::fa((int)($max * $i / 3)) . '</text>';
        }
        $pts = [];
        $gap = $n > 1 ? $innerW / ($n - 1) : $innerW;
        foreach ($values as $i => $v) {
            $x = $padL + $gap * $i;
            $y = $padT + $innerH - (($v / $max) * $innerH);
            $pts[] = [round($x, 1), round($y, 1), $v, $labels[$i] ?? ''];
        }
        $poly = implode(' ', array_map(static fn($p) => $p[0] . ',' . $p[1], $pts));
        $area = $padL . ',' . ($padT + $innerH) . ' ' . $poly . ' ' . ($padL + $gap * ($n - 1)) . ',' . ($padT + $innerH);
        $svg .= '<polygon points="' . $area . '" fill="' . $color . '" opacity="0.16"/>';
        $svg .= '<polyline points="' . $poly . '" fill="none" stroke="' . $color . '" stroke-width="2.2" stroke-linejoin="round"/>';
        foreach ($pts as $p) {
            $svg .= '<circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="2.8" fill="' . $color . '">'
                  . '<title>' . h($p[3]) . ': ' . self::fa((int)$p[2]) . '</title></circle>';
        }
        foreach ($labels as $i => $label) {
            if ($n <= 20 || $i % 2 === 0) {
                $svg .= '<text x="' . round($padL + $gap * $i, 1) . '" y="' . ($h - 8) . '" fill="#8098bb" font-size="10" text-anchor="middle">' . h($label) . '</text>';
            }
        }
        $svg .= '</svg>';
        return $svg;
    }

    /** دونات برای توزیع (مثلاً انواع تخلف) */
    public static function donut(array $items, int $size = 210): string
    {
        $total = max(1, array_sum(array_map(static fn($i) => (int)$i['value'], $items)));
        $r = $size / 2 - 12;
        $cx = $cy = $size / 2;
        $circ = 2 * M_PI * $r;
        $off = 0.0;
        $svg = '<svg viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '" role="img">';
        $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="#16233a" stroke-width="20"/>';
        foreach ($items as $it) {
            $v = (int)$it['value'];
            if ($v <= 0) {
                continue;
            }
            $len = ($v / $total) * $circ;
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="' . ($it['color'] ?? '#3b82f6') . '" stroke-width="20"'
                  . ' stroke-dasharray="' . round($len, 2) . ' ' . round($circ - $len, 2) . '"'
                  . ' stroke-dashoffset="' . round(-$off, 2) . '" transform="rotate(-90 ' . $cx . ' ' . $cy . ')">'
                  . '<title>' . h((string)$it['label']) . ': ' . self::fa($v) . '</title></circle>';
            $off += $len;
        }
        $svg .= '<text x="' . $cx . '" y="' . ($cy - 2) . '" text-anchor="middle" fill="#e8eefb" font-size="18" font-weight="bold">' . self::fa($total) . '</text>';
        $svg .= '<text x="' . $cx . '" y="' . ($cy + 16) . '" text-anchor="middle" fill="#8098bb" font-size="10">مجموع</text>';
        $svg .= '</svg>';
        return $svg;
    }

    /** نمودار میله‌ای افقی برای تاپ‌ها */
    public static function hbars(array $items, int $w = 460, int $rowH = 26): string
    {
        $max  = max(1, max(array_map(static fn($i) => (int)$i['value'], $items ?: [['value' => 0]])));
        $h    = max($rowH, count($items) * $rowH + 8);
        $svg  = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" height="' . $h . '" role="img" direction="rtl" style="font-family:Tahoma,sans-serif">';
        $svg .= '<rect x="0" y="0" width="' . $w . '" height="' . $h . '" fill="#0b1424"/>';
        $barMaxW = $w - 150;
        foreach ($items as $i => $it) {
            $y = 6 + $i * $rowH;
            $bw = (int)round(($it['value'] / $max) * $barMaxW);
            $svg .= '<text x="' . ($w - 8) . '" y="' . ($y + 14) . '" fill="#c8d6ef" font-size="11" text-anchor="end">' . h(str_limit((string)$it['label'], 26)) . '</text>';
            $svg .= '<rect x="' . ($w - 150 - $barMaxW) . '" y="' . $y . '" width="' . $barMaxW . '" height="' . ($rowH - 10) . '" rx="3" fill="#16233a"/>';
            $svg .= '<rect x="' . ($w - 150 - $bw) . '" y="' . $y . '" width="' . max(2, $bw) . '" height="' . ($rowH - 10) . '" rx="3" fill="' . ($it['color'] ?? '#3b82f6') . '">'
                  . '<title>' . h((string)$it['label']) . ': ' . self::fa((int)$it['value']) . '</title></rect>';
            $svg .= '<text x="' . ($w - 150 - $barMaxW - 6) . '" y="' . ($y + 12) . '" fill="#93a4c4" font-size="10" text-anchor="end" direction="ltr">' . self::fa((int)$it['value']) . '</text>';
        }
        $svg .= '</svg>';
        return $svg;
    }

    /** کوچک‌ترین نمودار برای داشبورد */
    public static function sparkline(array $values, string $color = '#3b82f6', int $w = 220, int $h = 46): string
    {
        $n = max(1, count($values));
        $max = max(1, max($values ?: [0]));
        $pts = [];
        $gap = $n > 1 ? ($w - 4) / ($n - 1) : 0;
        foreach ($values as $i => $v) {
            $pts[] = round(2 + $gap * $i, 1) . ',' . round($h - 4 - (($v / $max) * ($h - 8)), 1);
        }
        $svg = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" role="img">';
        $svg .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . $color . '" stroke-width="1.8"/>';
        $svg .= '</svg>';
        return $svg;
    }

    /** جمع‌بندی متنی برای ارسال به بله (گزارش روزانه/هفتگی) */
    public static function textReport(int $days = 1, ?int $chatId = null): string
    {
        $s   = self::summary($days, $chatId);
        $max = max($s['messages'], $s['violations'], 1);
        $bar = static function (int $v, int $max): string {
            $n = (int)round(($v / max(1, $max)) * 8);
            return str_repeat('▇', $n) . str_repeat('▁', max(0, 8 - $n));
        };
        $title = $chatId ? 'گزارش این گروه' : 'گزارش کل سیستم';
        return "📊 <b>{$title}</b> — " . digits_fa($days) . " روز اخیر\n"
            . "▫️ پیام‌ها: " . digits_fa($s['messages']) . "\n"
            . "  <code>" . $bar($s['messages'], $max) . "</code>\n"
            . "▫️ تخلف/اقدام: " . digits_fa($s['violations']) . "\n"
            . "  <code>" . $bar($s['violations'], $max) . "</code>\n"
            . "▫️ ورود: " . digits_fa($s['joins']) . " | خروج: " . digits_fa($s['lefts']) . "\n"
            . "▫️ دستورها: " . digits_fa($s['commands']);
    }
}
