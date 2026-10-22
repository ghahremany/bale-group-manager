<?php
declare(strict_types=1);

/**
 * پولینگ ربات (وقتی وب‌هوک ممکن نیست — مثلاً هاست بدون SSL یا پشت NAT)
 *
 * اجرا:
 *   php bin/poll.php                 # حلقه بی‌پایان
 *   php bin/poll.php --once          # یک دور (مناسب کرون هر دقیقه)
 *   php bin/poll.php --max-seconds=50
 *
 * توقف: ساخت فایل storage/stop-poller
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!is_installed()) {
    fwrite(STDERR, "ابتدا install.php را اجرا کنید.\n");
    exit(1);
}

$once       = in_array('--once', $argv, true);
$maxSeconds = 0;
foreach ($argv as $a) {
    if (preg_match('~^--max-seconds=(\d+)$~', $a, $m)) {
        $maxSeconds = (int)$m[1];
    }
}

$lock = Cache::lock('poller', 5);
if ($lock === false) {
    fwrite(STDERR, "پولر دیگری در حال اجراست.\n");
    exit(2);
}

@set_time_limit(0);
$started = time();
$stopFile = BGM_ROOT . '/storage/stop-poller';
$offset = (int)(Schema::getSetting('polling_offset', '0') ?: 0);

Logger::info('poll', 'poller started', ['offset' => $offset, 'once' => $once]);

$emptyLoops = 0;
while (true) {
    if (is_file($stopFile)) {
        @unlink($stopFile);
        Logger::info('poll', 'stop file detected');
        break;
    }
    if ($maxSeconds > 0 && (time() - $started) > $maxSeconds) {
        break;
    }
    try {
        $updates = BaleApi::getUpdates($offset ?: null, $once ? 0 : 25, 50);
    } catch (Throwable $e) {
        Logger::error('poll', 'getUpdates: ' . $e->getMessage());
        sleep(3);
        continue;
    }
    if (!$updates) {
        $emptyLoops++;
        Schema::setSetting('last_poll_at', (string)time());
        if ($once) {
            break;
        }
        sleep($emptyLoops > 3 ? 2 : 1);
        // هر ۲۰ دور خالی، اقدامات زمان‌بندی‌شده را هم اجرا کن
        if ($emptyLoops % 20 === 0) {
            Moderation::processScheduled();
        }
        continue;
    }
    $emptyLoops = 0;
    foreach ($updates as $u) {
        $offset = (int)($u['update_id'] ?? 0) + 1;
        Schema::setSetting('polling_offset', (string)$offset);
        Bot::run($u);
        // پولینگ تک‌رشته‌ای است؛ کمی نفس بکش تا CPU/نرخ API رعایت شود
        usleep(50000);
    }
    Moderation::processScheduled();
    if ($once) {
        break;
    }
}

Schema::setSetting('last_poll_at', (string)time());
Logger::info('poll', 'poller stopped', ['offset' => $offset]);
Cache::unlock('poller', $lock);
