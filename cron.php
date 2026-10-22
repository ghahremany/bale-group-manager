<?php
declare(strict_types=1);

/**
 * کرون: اجرای اقدامات زمان‌بندی‌شده و تمیزکاری
 * روش 1 (CLI):  php /path/cron.php
 * روش 2 (URL):  https://domain/cron.php?key=CRON_SECRET
 * پیشنهاد: هر ۱ دقیقه
 */

require_once __DIR__ . '/app/bootstrap.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    security_headers(false);
    header('Content-Type: application/json; charset=utf-8');
    $key = (string)($_GET['key'] ?? $_GET['secret'] ?? '');
    $expected = (string)config('cron.secret', '');
    if ($expected === '' || !hash_equals($expected, $key)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }
}

if (!is_installed()) {
    echo json_encode(['ok' => false, 'error' => 'not installed']);
    exit;
}

// قفل ضد اجرای هم‌زمان
$lock = Cache::lock('cron', 5);
if ($lock === false) {
    echo json_encode(['ok' => true, 'skipped' => 'already running']);
    exit;
}

$done = [];
try {
    // 1) اقدامات زمان‌بندی‌شده (آن‌میوت/آنبن/کاهش اخطار)
    $done['scheduled'] = Moderation::processScheduled();

    // 2) کپچاهای منقضی → اخراج کاربر تاییدنشده
    $expired = DB::all("SELECT * FROM captcha_pending WHERE expires_at < ? LIMIT 30", [time()]);
    foreach ($expired as $c) {
        $chatId = (int)$c['chat_id'];
        $uid    = (int)$c['user_id'];
        Moderation::kick($chatId, $uid, 60, null);
        if (!empty($c['message_id'])) {
            BaleApi::deleteMessage($chatId, (int)$c['message_id']);
        }
        DB::q("DELETE FROM captcha_pending WHERE chat_id=? AND user_id=?", [$chatId, $uid]);
        $done['captcha_kicked'] = ($done['captcha_kicked'] ?? 0) + 1;
    }

    // 3) سکوت‌های تمام‌شده که به هر دلیل آزاد نشدند
    $stale = DB::all("SELECT * FROM mutes WHERE until_ts>0 AND until_ts < ? LIMIT 30", [time() - 5]);
    foreach ($stale as $m) {
        BaleApi::restrict((int)$m['chat_id'], (int)$m['user_id'], true);
        DB::q("DELETE FROM mutes WHERE chat_id=? AND user_id=?", [(int)$m['chat_id'], (int)$m['user_id']]);
        $done['stale_mutes'] = ($done['stale_mutes'] ?? 0) + 1;
    }

    // 4) بن‌های موقت تمام‌شده
    $staleBans = DB::all("SELECT * FROM bans WHERE until_ts>0 AND until_ts < ? LIMIT 30", [time() - 5]);
    foreach ($staleBans as $b) {
        BaleApi::call('unbanChatMember', ['chat_id' => (int)$b['chat_id'], 'user_id' => (int)$b['user_id']], 12);
        DB::q("DELETE FROM bans WHERE chat_id=? AND user_id=?", [(int)$b['chat_id'], (int)$b['user_id']]);
        $done['stale_bans'] = ($done['stale_bans'] ?? 0) + 1;
    }

    // 5) بکاپ خودکار + گزارش نموداری روزانه
    try {
        $bk = Backup::maybeAuto();
        if ($bk !== null) {
            $done['backup'] = $bk['ok'] ? (string)($bk['file'] ?? 'ok') : ('error: ' . ($bk['error'] ?? ''));
        }
        // آمار روز جاری از لاگ‌ها (برای نصب‌های قدیمی یا پس از ریست شمارنده)
        if (random_int(1, 20) === 1) {
            Metrics::backfill(7);
        }
        $repChat = (int)(Schema::getSetting('report_daily_chat', '0') ?: 0);
        $repHour = (int)(Schema::getSetting('report_daily_hour', '9') ?: 9);
        $repDays = max(1, (int)(Schema::getSetting('report_daily_days', '1') ?: 1));
        $today   = date('Y-m-d');
        $sentOn  = (string)Schema::getSetting('report_daily_sent_day', '');
        if ($repChat !== 0 && (int)date('G') >= $repHour && $sentOn !== $today) {
            BaleApi::sendMessage($repChat, Metrics::textReport($repDays) . "\n\n" . '🗄 آخرین بکاپ: '
                . ((int)(Schema::getSetting('last_backup_at', '0') ?: 0) ? dt((int)Schema::getSetting('last_backup_at', '0')) : 'ندارد')
                . "\n🔐 پنل: " . url('panel/'), null, null, 'HTML');
            Schema::setSetting('report_daily_sent_day', $today);
            $done['daily_report'] = 'sent';
        }
    } catch (Throwable $e) {
        Logger::error('cron', 'backup/report: ' . $e->getMessage());
    }

    // 6) برداشت صف ارسال همگانی (بودجه ۲۰ ثانیه در هر اجرا)
    try {
        $queue = Broadcast::drain(20);
        if ($queue['sent'] > 0 || $queue['failed'] > 0) {
            $done['broadcast'] = 'sent=' . $queue['sent'] . ' failed=' . $queue['failed'] . ' left=' . $queue['left'];
        }
    } catch (Throwable $e) {
        Logger::error('cron', 'broadcast drain: ' . $e->getMessage());
    }

    // 6.۵) کمپین‌های تبلیغ کالا + هشدار موجودی
    try {
        $camps = Store::runDueCampaigns(3);
        if ($camps) {
            $done['promo'] = $camps;
        }
        // هشدار موجودی کم، یک‌بار در روز
        if ((int)date('G') === 9 && (string)(Schema::getSetting('last_lowstock_day', '') ?: '') !== date('Y-m-d')) {
            $low = Store::lowStock(10);
            if ($low) {
                $log = (int)(Schema::getSetting('default_log_channel', '0') ?: 0);
                $target = $log ?: (int)(Schema::getSetting('report_daily_chat', '0') ?: 0);
                if ($target) {
                    $lines = ["⚠️ <b>هشدار موجودی انبار</b>"];
                    foreach ($low as $p) {
                        $lines[] = "• " . h((string)$p['title']) . " — " . digits_fa(Store::available($p)) . " " . h((string)$p['unit']);
                    }
                    $lines[] = url('panel/index.php', ['p' => 'store']);
                    BaleApi::sendMessage($target, implode("\n", $lines), null, null, 'HTML');
                }
                $done['low_stock'] = count($low);
            }
            Schema::setSetting('last_lowstock_day', date('Y-m-d'));
        }
    } catch (Throwable $e) {
        Logger::error('cron', 'store jobs: ' . $e->getMessage());
    }

    // 7) تمیزکاری‌ها
    $done['cache_files'] = Cache::gc();
    DB::q("DELETE FROM bot_updates WHERE handled_at < ?", [time() - 3 * 86400]);
    DB::q("DELETE FROM webhook_log WHERE ts < ?", [time() - 14 * 86400]);
    DB::q("DELETE FROM panel_sessions WHERE expires_at < ?", [time()]);
    DB::q("DELETE FROM login_otps WHERE created_at < ?", [time() - 2 * 86400]);
    DB::q("DELETE FROM audit_log WHERE ts < ?", [time() - 180 * 86400]);
    DB::q("DELETE FROM logs WHERE ts < ?", [time() - 90 * 86400]);

    // پاک‌سازی عمیق (یک‌بار در روز، ساعت ۳ بامداد)
    if ((int)date('G') === 3 && (string)(Schema::getSetting('last_purge_day', '') ?: '') !== date('Y-m-d')) {
        $done['purged_queue'] = Broadcast::purgeOld(30);
        $done['purged_otp_logs'] = Recovery::purgeLogs(90);
        $done['purged_shop_tokens'] = ShopAuth::purgeTokens();
        Schema::setSetting('last_purge_day', date('Y-m-d'));
    }

    Schema::setSetting('last_cron_at', (string)time());
    $done['ok'] = true;
} catch (Throwable $e) {
    Logger::error('cron', $e->getMessage(), ['at' => basename($e->getFile()) . ':' . $e->getLine()]);
    $done['ok'] = false;
    $done['error'] = $e->getMessage();
} finally {
    Cache::unlock('cron', $lock);
}

echo json_encode($done, JSON_UNESCAPED_UNICODE);
