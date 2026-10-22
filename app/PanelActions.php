<?php
declare(strict_types=1);

/**
 * عملیات POST پنل (همه با CSRF + ثبت ممیزی)
 */
final class PanelActions
{
    public static function run(string $action, array $admin): void
    {
        $super = (string)$admin['role'] === 'super';
        try {
            switch ($action) {
                case 'group_update':      self::groupUpdate($admin); break;
                case 'group_reset':       self::groupReset($admin); break;
                case 'group_btn_add':     self::groupButtonAdd($admin); break;
                case 'group_btn_del':     self::groupButtonDel($admin); break;
                case 'group_kick_bot':    self::groupDelete($admin); break;
                case 'mod_action':        self::modAction($admin); break;
                case 'reports_close':     self::reportsClose($admin); break;
                case 'admin_save':        self::adminSave($admin, $super); break;
                case 'admin_delete':      self::adminDelete($admin, $super); break;
                case 'sessions_revoke':   self::sessionsRevoke($admin); break;
                case 'password_change':   self::passwordChange($admin); break;
                case 'account_save':      self::accountSave($admin); break;
                case 'system_webhook':    self::systemWebhook($admin, $super); break;
                case 'system_webhook_del':self::systemWebhookDelete($admin, $super); break;
                case 'system_test':       self::systemTest($admin, $super); break;
                case 'system_cron':       self::systemCron($admin, $super); break;
                case 'system_cache':      self::systemCache($admin, $super); break;
                case 'system_settings':   self::systemSettings($admin, $super); break;
                case 'broadcast':         self::broadcast($admin, $super); break;
                case 'badwords_save':     self::badWordsSave($admin); break;
                case 'backup_create':     self::backupCreate($admin, $super); break;
                case 'backup_delete':     self::backupDelete($admin, $super); break;
                case 'backup_restore':    self::backupRestore($admin, $super); break;
                case 'backup_upload':     self::backupUpload($admin, $super); break;
                case 'backup_settings':   self::backupSettings($admin, $super); break;
                case 'referral_settings': self::referralSettings($admin); break;
                case 'referral_status':   self::referralStatus($admin); break;
                case 'analytics_backfill': self::analyticsBackfill($admin, $super); break;
                case 'system_upgrade':    self::systemUpgrade($admin, $super); break;
                case 'broadcast_enqueue': self::broadcastEnqueue($admin, $super); break;
                case 'broadcast_cancel':  self::broadcastCancel($admin, $super); break;
                case 'broadcast_resume':  self::broadcastResume($admin, $super); break;
                case 'broadcast_drain':   self::broadcastDrain($admin, $super); break;
                case 'recovery_generate': self::recoveryGenerate($admin); break;
                case 'recovery_revoke':   self::recoveryRevoke($admin); break;
                case 'product_save':      self::productSave($admin); break;
                case 'product_status':    self::productStatus($admin); break;
                case 'product_stock':     self::productStock($admin); break;
                case 'seller_status':     self::sellerStatus($admin); break;
                case 'payment_verify':    self::paymentVerify($admin); break;
                case 'order_status':      self::orderStatus($admin); break;
                case 'promo_create':      self::promoCreate($admin, $super); break;
                case 'promo_save':        self::promoCreate($admin, $super); break;
                case 'promo_toggle':      self::promoToggle($admin, $super); break;
                case 'category_save':     self::categorySave($admin); break;
                case 'withdraw_decide':   self::withdrawDecide($admin, $super); break;
                case 'store_settings':    self::storeSettings($admin, $super); break;
                default:
                    Auth::flash('err', 'عملیات ناشناخته.');
            }
        } catch (Throwable $e) {
            Logger::error('panel', $action . ': ' . $e->getMessage(), ['at' => basename($e->getFile()) . ':' . $e->getLine()]);
            Auth::flash('err', 'خطا در انجام عملیات: ' . $e->getMessage());
        }
    }

    private static function back(string $p, array $q = []): void
    {
        redirect('panel/index.php', ['p' => $p] + $q);
    }

    // ---------------- گروه ----------------
    private static function groupUpdate(array $admin): void
    {
        $chatId = (int)input('chat_id', 0);
        if (!$chatId) {
            Auth::flash('err', 'گروه نامعتبر.');
            self::back('groups');
            return;
        }
        Settings::ensure($chatId);
        $intFields = [
            'spam_window_sec', 'spam_max_msgs', 'flood_mute_sec', 'warn_limit', 'warn_mute_sec',
            'captcha_timeout_sec', 'log_channel_id', 'report_channel_id',
        ];
        $enum = [
            'anti_flood_action' => ['warn', 'delete', 'mute'],
            'warn_action'       => ['mute', 'kick', 'ban'],
        ];
        $text = ['welcome_text', 'goodbye_text', 'rules'];

        foreach ($intFields as $f) {
            if (isset($_POST[$f])) {
                Settings::set($chatId, $f, (int)$_POST[$f]);
            }
        }
        foreach ($enum as $f => $allowed) {
            $v = (string)($_POST[$f] ?? '');
            if (in_array($v, $allowed, true)) {
                Settings::set($chatId, $f, $v);
            }
        }
        foreach ($text as $f) {
            if (isset($_POST[$f])) {
                Settings::set($chatId, $f, (string)$_POST[$f]);
            }
        }
        // سوییچ‌ها — فقط وقتی فرم کامل ارسال شده باشد (وگرنه مقدار فعلی حفظ می‌شود)
        $fullForm = (string)input('form', '') === 'group_settings';
        $switchFields = [
                'anti_link','anti_spam','bad_words','anti_bot','anti_forward','anti_username',
                'welcome_on','goodbye_on','captcha_on','lock_link','lock_photo','lock_video','lock_gif',
            'lock_sticker','lock_voice','lock_document','lock_forward','lock_contact','lock_poll',
            'lock_location','lock_bot','is_active',
        ];
        if ($fullForm) {
            foreach ($switchFields as $f) {
                Settings::set($chatId, $f, isset($_POST['sw_' . $f]) ? 1 : 0);
            }
        } else {
            foreach ($switchFields as $f) {
                if (isset($_POST['sw_' . $f])) {
                    Settings::set($chatId, $f, (int)$_POST['sw_' . $f] ? 1 : 0);
                }
            }
        }
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'group_update', (string)$chatId);
        Auth::flash('ok', 'تنظیمات گروه ذخیره شد.');
        self::back('group', ['chat_id' => $chatId]);
    }

    private static function groupReset(array $admin): void
    {
        $chatId = (int)input('chat_id', 0);
        foreach (Settings::DEFAULTS as $f => $v) {
            Settings::set($chatId, $f, $v);
        }
        DB::q("DELETE FROM welcome_buttons WHERE chat_id=?", [$chatId]);
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'group_reset', (string)$chatId);
        Auth::flash('ok', 'تنظیمات به پیش‌فرض بازگشت.');
        self::back('group', ['chat_id' => $chatId]);
    }

    private static function groupButtonAdd(array $admin): void
    {
        $chatId = (int)input('chat_id', 0);
        $label  = trim((string)input('label', ''));
        $url    = trim((string)input('url', ''));
        if ($label === '' || !preg_match('~^https?://~i', $url)) {
            Auth::flash('err', 'عنوان و لینک (با http/https) لازم است.');
        } else {
            DB::q("INSERT INTO welcome_buttons (chat_id,label,url,sort) VALUES (?,?,?,?)",
                [$chatId, str_limit($label, 70), str_limit($url, 480), (int)input('sort', 0)]);
            Auth::flash('ok', 'دکمه اضافه شد.');
        }
        self::back('group', ['chat_id' => $chatId]);
    }

    private static function groupButtonDel(array $admin): void
    {
        $chatId = (int)input('chat_id', 0);
        DB::q("DELETE FROM welcome_buttons WHERE id=? AND chat_id=?", [(int)input('id', 0), $chatId]);
        Auth::flash('ok', 'دکمه حذف شد.');
        self::back('group', ['chat_id' => $chatId]);
    }

    private static function groupDelete(array $admin): void
    {
        $chatId = (int)input('chat_id', 0);
        BaleApi::call('leaveChat', ['chat_id' => $chatId], 15);
        Settings::set($chatId, 'is_active', 0);
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'bot_leave', (string)$chatId);
        Auth::flash('ok', 'ربات از گروه خارج شد (و غیرفعال شد).');
        self::back('groups');
    }

    // ---------------- اقدامات مدیریتی از پنل ----------------
    private static function modAction(array $admin): void
    {
        $chatId = (int)input('chat_id', 0);
        $userId = (int)input('user_id', 0);
        $op     = (string)input('op', '');
        if (!$chatId || !$userId) {
            Auth::flash('err', 'گروه/کاربر نامعتبر.');
            self::back('groups');
            return;
        }
        $dur = parse_duration((string)input('duration', ''));
        switch ($op) {
            case 'mute':
                Moderation::mute($chatId, $userId, $dur ?: 600, 'از پنل', (int)$admin['bale_user_id'] ?: null);
                Auth::flash('ok', 'کاربر سکوت شد.');
                break;
            case 'unmute':
                Moderation::unmute($chatId, $userId, (int)$admin['bale_user_id'] ?: null);
                Auth::flash('ok', 'سکوت برداشته شد.');
                break;
            case 'ban':
                Moderation::ban($chatId, $userId, $dur, 'از پنل', (int)$admin['bale_user_id'] ?: null);
                Auth::flash('ok', 'کاربر بن شد.');
                break;
            case 'unban':
                Moderation::unban($chatId, $userId, (int)$admin['bale_user_id'] ?: null);
                Auth::flash('ok', 'بن برداشته شد.');
                break;
            case 'kick':
                Moderation::kick($chatId, $userId, $dur ?: 60, (int)$admin['bale_user_id'] ?: null);
                Auth::flash('ok', 'کاربر اخراج شد.');
                break;
            case 'warn':
                Moderation::warn($chatId, $userId, (string)input('reason', 'از پنل'), (int)$admin['bale_user_id'] ?: null);
                Auth::flash('ok', 'اخطار ثبت شد.');
                break;
            case 'clear_warn':
                DB::q("DELETE FROM warns WHERE chat_id=? AND user_id=?", [$chatId, $userId]);
                Auth::flash('ok', 'اخطارها پاک شد.');
                break;
        }
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'mod_' . $op, "$chatId/$userId");
        self::back('group', ['chat_id' => $chatId, 'tab' => 'members']);
    }

    private static function reportsClose(array $admin): void
    {
        DB::q("UPDATE reports SET status='closed' WHERE id=?", [(int)input('id', 0)]);
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'report_close', (string)input('id', ''));
        Auth::flash('ok', 'گزارش بسته شد.');
        self::back('moderation', ['tab' => 'reports']);
    }

    // ---------------- ادمین‌ها ----------------
    private static function adminSave(array $admin, bool $super): void
    {
        if (!$super) {
            Auth::flash('err', 'فقط مدیر ارشد.');
            self::back('dashboard');
            return;
        }
        $id       = (int)input('id', 0);
        $username = trim((string)input('username', ''));
        $role     = input('role', 'admin') === 'super' ? 'super' : 'admin';
        $baleId   = (int)input('bale_user_id', 0);
        $active   = (int)input('is_active', 0);
        $pass     = (string)($_POST['password'] ?? '');

        if (!preg_match('~^[a-zA-Z0-9_.-]{3,32}$~', $username)) {
            Auth::flash('err', 'نام کاربری نامعتبر (۳-۳۲ کاراکتر لاتین/عدد).');
            self::back('admins');
            return;
        }
        if ($id > 0) {
            DB::q("UPDATE panel_admins SET username=?, role=?, bale_user_id=?, is_active=? WHERE id=?",
                [$username, $role, $baleId ?: null, $active, $id]);
            if ($pass !== '') {
                if (mb_strlen($pass) < 8) {
                    Auth::flash('err', 'رمز جدید حداقل ۸ کاراکتر باشد.');
                    self::back('admins');
                    return;
                }
                DB::q("UPDATE panel_admins SET password_hash=?, must_change_password=0 WHERE id=?", [password_hash($pass, PASSWORD_DEFAULT), $id]);
                DB::q("DELETE FROM panel_sessions WHERE admin_id=?", [$id]);
            }
            Auth::flash('ok', 'ادمین به‌روزرسانی شد.');
            Logger::audit((int)$admin['id'], (string)$admin['username'], 'admin_update', $username);
        } else {
            if (mb_strlen($pass) < 8) {
                Auth::flash('err', 'رمز عبور حداقل ۸ کاراکتر.');
                self::back('admins');
                return;
            }
            $exists = DB::val("SELECT id FROM panel_admins WHERE username=?", [$username]);
            if ($exists) {
                Auth::flash('err', 'این نام کاربری قبلاً ثبت شده.');
                self::back('admins');
                return;
            }
            DB::q("INSERT INTO panel_admins (username,password_hash,bale_user_id,full_name,role,is_active,must_change_password,created_at)
                   VALUES (?,?,?,?,?,?,1,?)",
                [$username, password_hash($pass, PASSWORD_DEFAULT), $baleId ?: null, (string)input('full_name', ''), $role, $active ?: 1, time()]);
            Auth::flash('ok', 'ادمین ساخته شد. رمز را در اختیار او بگذار و بخواه تغییر دهد.');
            Logger::audit((int)$admin['id'], (string)$admin['username'], 'admin_create', $username);
        }
        self::back('admins');
    }

    private static function adminDelete(array $admin, bool $super): void
    {
        if (!$super) {
            Auth::flash('err', 'فقط مدیر ارشد.');
            self::back('dashboard');
            return;
        }
        $id = (int)input('id', 0);
        if ($id === (int)$admin['id']) {
            Auth::flash('err', 'نمی‌توانی خودت را حذف کنی.');
            self::back('admins');
            return;
        }
        $supers = (int)DB::val("SELECT COUNT(*) FROM panel_admins WHERE role='super' AND is_active=1", [], 0);
        $target = DB::one("SELECT * FROM panel_admins WHERE id=?", [$id]);
        if ($target && $target['role'] === 'super' && $supers <= 1) {
            Auth::flash('err', 'آخرین مدیر ارشد قابل حذف نیست.');
            self::back('admins');
            return;
        }
        DB::q("DELETE FROM panel_admins WHERE id=?", [$id]);
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'admin_delete', (string)$id);
        Auth::flash('ok', 'ادمین حذف شد.');
        self::back('admins');
    }

    private static function sessionsRevoke(array $admin): void
    {
        $id = (int)input('admin_id', (int)$admin['id']);
        if ((string)$admin['role'] !== 'super' && $id !== (int)$admin['id']) {
            Auth::flash('err', 'دسترسی ندارید.');
            self::back('account');
            return;
        }
        DB::q("DELETE FROM panel_sessions WHERE admin_id=?", [$id]);
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'sessions_revoke', (string)$id);
        Auth::flash('ok', 'همه نشست‌های فعال آن حساب بسته شد.');
        if ($id === (int)$admin['id']) {
            Auth::logout();
            redirect('panel/login.php');
        }
        self::back('admins');
    }

    private static function passwordChange(array $admin): void
    {
        $cur = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $rep = (string)($_POST['new_password2'] ?? '');
        if (!password_verify($cur, (string)$admin['password_hash'])) {
            Auth::flash('err', 'رمز فعلی اشتباه است.');
            self::back('account');
            return;
        }
        if (mb_strlen($new) < 8) {
            Auth::flash('err', 'رمز جدید حداقل ۸ کاراکتر باشد.');
            self::back('account');
            return;
        }
        if ($new !== $rep) {
            Auth::flash('err', 'تکرار رمز جدید یکسان نیست.');
            self::back('account');
            return;
        }
        DB::q("UPDATE panel_admins SET password_hash=?, must_change_password=0 WHERE id=?",
            [password_hash($new, PASSWORD_DEFAULT), (int)$admin['id']]);
        DB::q("DELETE FROM panel_sessions WHERE admin_id=? AND id<>?", [(int)$admin['id'], session_id()]);
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'password_change', 'self');
        Auth::flash('ok', 'رمز عبور تغییر کرد و سایر نشست‌ها بسته شد.');
        self::back('account');
    }

    private static function accountSave(array $admin): void
    {
        $baleId = (int)input('bale_user_id', 0);
        $name   = trim((string)input('full_name', ''));
        DB::q("UPDATE panel_admins SET bale_user_id=?, full_name=? WHERE id=?", [$baleId ?: null, str_limit($name, 110), (int)$admin['id']]);
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'account_update', 'self', 'bale_id=' . $baleId);
        Auth::flash('ok', 'حساب به‌روزرسانی شد.');
        self::back('account');
    }


    // ---------------- بکاپ ----------------
    private static function backupCreate(array $admin, bool $super): void
    {
        if (!$super) {
            Auth::flash('err', 'فقط مدیر ارشد.');
            self::back('dashboard');
            return;
        }
        $res = Backup::create('manual', (int)$admin['id'], 'از پنل');
        if (!$res['ok']) {
            Auth::flash('err', 'بکاپ ناموفق: ' . (string)($res['error'] ?? ''));
        } else {
            Auth::flash('ok', 'بکاپ ساخته شد: ' . $res['file'] . ' — ' . Backup::humanSize((int)$res['size'])
                . ' (' . digits_fa((int)$res['tables']) . ' جدول، ' . digits_fa((int)$res['rows']) . ' ردیف)');
        }
        self::back('backup');
    }

    private static function backupDelete(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        $file = (string)input('filename', '');
        Backup::delete($file, (int)$admin['id']);
        Auth::flash('ok', 'فایل بکاپ حذف شد.');
        self::back('backup');
    }

    private static function backupRestore(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        $file = (string)input('filename', '');
        if (!preg_match('~^[A-Za-z0-9._-]+$~', $file)) {
            Auth::flash('err', 'نام فایل نامعتبر.');
            self::back('backup');
            return;
        }
        $path = Backup::dir() . '/' . $file;
        $res  = Backup::restore($path, (int)$admin['id']);
        if ($res['ok']) {
            Auth::flash('ok', 'بازیابی انجام شد (' . digits_fa((int)($res['executed'] ?? 0)) . ' دستور). بکاپ ایمنی: ' . h((string)($res['safety'] ?? '-')));
        } else {
            Auth::flash('err', 'بازیابی با خطا: ' . h(implode(' | ', (array)($res['errors'] ?? [$res['error'] ?? '']))) );
        }
        self::back('backup');
    }

    private static function backupUpload(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        if (empty($_FILES['file']['tmp_name']) || (int)($_FILES['file']['error'] ?? 1) !== 0) {
            Auth::flash('err', 'فایل انتخاب نشد یا آپلود کامل نشد (محدودیت upload_max_filesize را بررسی کن).');
            self::back('backup');
            return;
        }
        $name = (string)($_FILES['file']['name'] ?? 'upload.sql');
        if (!preg_match('~\.sql$~i', $name)) {
            Auth::flash('err', 'فقط فایل .sql پذیرفته می‌شود.');
            self::back('backup');
            return;
        }
        $size = (int)($_FILES['file']['size'] ?? 0);
        if ($size > 200 * 1024 * 1024) {
            Auth::flash('err', 'فایل بیش از ۲۰۰MB است.');
            self::back('backup');
            return;
        }
        $dir = Backup::ensureDir();
        $dest = $dir . '/upload-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.sql';
        if (!move_uploaded_file((string)$_FILES['file']['tmp_name'], $dest)) {
            Auth::flash('err', 'انتقال فایل آپلودی ناموفق بود (دسترسی پوشه بکاپ).');
            self::back('backup');
            return;
        }
        $restore = (string)input('do_restore', '') === '1';
        if ($restore) {
            $res = Backup::restore($dest, (int)$admin['id']);
            Auth::flash($res['ok'] ? 'ok' : 'err', $res['ok']
                ? 'فایل آپلود و بازیابی شد (' . digits_fa((int)($res['executed'] ?? 0)) . ' دستور).'
                : 'آپلود شد ولی بازیابی خطا داشت: ' . h((string)(($res['errors'][0] ?? ''))));
        } else {
            DB::q("INSERT INTO backup_log (filename,size_bytes,tables_count,rows_count,kind,admin_id,note,created_at)
                   VALUES (?,?,0,0,'manual',?,?,?)",
                [basename($dest), (int)filesize($dest), (int)$admin['id'], 'آپلود دستی', time()]);
            Auth::flash('ok', 'فایل آپلود شد و در لیست موجود است (برای بازیابی دکمه بازیابی را بزن).');
        }
        self::back('backup');
    }

    private static function backupSettings(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        Schema::setSetting('backup_auto', input('backup_auto') ? '1' : '0');
        Schema::setSetting('backup_every_hours', (string)max(1, min(720, (int)input('backup_every_hours', 24))));
        Schema::setSetting('backup_keep', (string)max(1, min(200, (int)input('backup_keep', 10))));
        Schema::setSetting('backup_send_chat', (string)(int)input('backup_send_chat', 0));
        $dir = trim((string)input('backup_dir', ''));
        if ($dir !== '' && (!is_dir($dir) || !is_writable($dir))) {
            Auth::flash('warn', 'مسیر دلخواه معتبر/قابل نوشتن نبود؛ پوشه پیش‌فرض استفاده می‌شود.');
            $dir = '';
        }
        Schema::setSetting('backup_dir', $dir);
        Schema::setSetting('report_daily_chat', (string)(int)input('report_daily_chat', 0));
        Schema::setSetting('report_daily_hour', (string)max(0, min(23, (int)input('report_daily_hour', 9))));
        Schema::setSetting('report_daily_days', (string)max(1, min(30, (int)input('report_daily_days', 1))));
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'backup_settings', 'system');
        Auth::flash('ok', 'تنظیمات بکاپ و گزارش ذخیره شد.');
        self::back('backup');
    }

    // ---------------- زیرمجموعه ----------------
    private static function referralSettings(array $admin): void
    {
        if ((string)$admin['role'] !== 'super') {
            self::back('dashboard');
            return;
        }
        Schema::setSetting('referral_enabled', input('referral_enabled') ? '1' : '0');
        Schema::setSetting('referral_notify', input('referral_notify') ? '1' : '0');
        $u = trim((string)input('bot_username', ''));
        if ($u !== '') {
            Schema::setSetting('bot_username', ltrim($u, '@ '));
        }
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'referral_settings', 'system');
        Auth::flash('ok', 'تنظیمات دعوت ذخیره شد.');
        self::back('referrals');
    }

    private static function referralStatus(array $admin): void
    {
        $id     = (int)input('id', 0);
        $status = (string)input('status', '');
        Referral::setStatus($id, $status, (int)$admin['id'], (string)input('note', ''));
        Auth::flash('ok', 'وضعیت دعوت به‌روزرسانی شد.');
        self::back('referrals');
    }

    // ---------------- آمار ----------------
    private static function analyticsBackfill(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        $n = Metrics::backfill(60);
        Auth::flash('ok', 'آمار ۶۰ روز گذشته از لاگ‌ها بازسازی شد (' . digits_fa($n) . ' رکورد روز/گروه).');
        self::back('analytics');
    }

    private static function systemUpgrade(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        $log = Schema::upgrade();
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'schema_upgrade', 'system', implode(' | ', array_slice($log, 0, 20)));
        Auth::flash($log ? 'ok' : 'ok', $log
            ? 'آپدیت انجام شد: ' . h(implode(' · ', array_slice($log, 0, 8)))
            : 'دیتابیس از قبل به‌روز است.');
        self::back('system');
    }


    // ---------------- صف ارسال همگانی ----------------
    private static function broadcastEnqueue(array $admin, bool $super): void
    {
        if (!$super) {
            Auth::flash('err', 'ارسال همگانی فقط برای مدیر ارشد است.');
            self::back('dashboard');
            return;
        }
        $whenRaw = trim((string)input('scheduled_at', ''));
        $when    = 0;
        if ($whenRaw !== '') {
            $ts = strtotime(str_replace('T', ' ', $whenRaw));
            $when = $ts !== false ? max(0, $ts) : 0;
        }
        $res = Broadcast::enqueue([
            'text'         => (string)input('text', ''),
            'title'        => (string)input('title', ''),
            'audience'     => (string)input('audience', 'groups'),
            'rate'         => (int)input('rate', 0),
            'scheduled_at' => $when,
            'admin_id'     => (int)$admin['id'],
        ]);
        if (!$res['ok']) {
            Auth::flash('err', (string)($res['error'] ?? 'ناموفق'));
            self::back('broadcast');
            return;
        }
        Schema::setSetting('broadcast_rate', (string)max(1, min(25, (int)input('rate', 15))));
        $msg = 'دسته #' . $res['batch'] . ' با ' . digits_fa((int)$res['queued']) . ' مخاطب در صف قرار گرفت.';
        $msg .= $when > 0 ? ' ارسال در ' . dt($when, 'Y/m/d H:i') : ' کرون در دقیقه بعد ارسال را شروع می‌کند.';
        Auth::flash('ok', $msg);
        self::back('broadcast');
    }

    private static function broadcastCancel(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        $id = (int)input('batch', 0);
        $ok = Broadcast::cancel($id, (int)$admin['id']);
        Auth::flash($ok ? 'ok' : 'warn', $ok ? 'دسته متوقف شد.' : 'امکان توقف نبود (تمام‌شده یا نامعتبر).');
        self::back('broadcast');
    }

    private static function broadcastResume(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        $id = (int)input('batch', 0);
        $ok = Broadcast::resume($id, (int)$admin['id']);
        Auth::flash($ok ? 'ok' : 'warn', $ok ? 'دسته دوباره در صف قرار گرفت.' : 'امکان ادامه نبود.');
        self::back('broadcast');
    }

    private static function broadcastDrain(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        $st = Broadcast::drain(18);
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'broadcast_drain',
            'queue', 'sent=' . $st['sent'] . ' failed=' . $st['failed'] . ' left=' . $st['left']);
        Auth::flash($st['sent'] > 0 ? 'ok' : 'warn',
            'اجرای فوری: ' . digits_fa((int)$st['sent']) . ' ارسال، ' . digits_fa((int)$st['failed'])
            . ' خطا، ' . digits_fa((int)$st['left']) . ' در صف — ' . digits_fa((int)$st['elapsed']) . ' ثانیه');
        self::back('broadcast');
    }

    // ---------------- کدهای بازیابی ----------------
    private static function recoveryGenerate(array $admin): void
    {
        $target = (string)$admin['role'] === 'super' && (int)input('admin_id', 0) ? (int)input('admin_id', 0) : (int)$admin['id'];
        $count  = max(1, min(20, (int)input('count', Recovery::COUNT_DEFAULT)));
        $_SESSION['recovery_new_codes'] = Recovery::generate($target, $count, (int)$admin['id']);
        Auth::flash('ok', digits_fa($count) . ' کد بازیابی ساخته شد. همین حالا ذخیره کن — دوباره نمایش داده نمی‌شوند.');
        self::back('recovery');
    }

    private static function recoveryRevoke(array $admin): void
    {
        $target = (string)$admin['role'] === 'super' && (int)input('admin_id', 0) ? (int)input('admin_id', 0) : (int)$admin['id'];
        $n = Recovery::revokeAll($target, (int)$admin['id']);
        Auth::flash('ok', digits_fa($n) . ' کد بازیابی فعال باطل شد.');
        self::back('recovery');
    }


    // ---------------- فروشگاه / انبار ----------------
    private static function productSave(array $admin): void
    {
        $res = Store::save($_POST, null, (int)$admin['id']);
        if ($res['ok']) {
            Auth::flash('ok', 'کالا ذخیره شد' . (isset($res['code']) ? ' (کد ' . $res['code'] . ')' : ''));
        } else {
            Auth::flash('err', (string)($res['error'] ?? 'ناموفق'));
        }
        self::back('store');
    }

    private static function productStatus(array $admin): void
    {
        $id     = (int)input('id', 0);
        $status = (string)input('status', '');
        $ok = Store::setStatus($id, $status, (int)$admin['id']);
        Auth::flash($ok ? 'ok' : 'err', $ok ? 'وضعیت کالا تغییر کرد.' : 'تغییر وضعیت ناموفق بود.');
        self::back('store');
    }

    private static function productStock(array $admin): void
    {
        $id    = (int)input('id', 0);
        $delta = (int)input('delta', 0);
        if ($delta === 0) {
            Auth::flash('warn', 'تغییر موجودی صفر است.');
            self::back('store');
            return;
        }
        $ok = Store::adjustStock($id, $delta, 'adjust', [], (int)$admin['id'], 'اصلاح دستی از پنل');
        Auth::flash($ok ? 'ok' : 'err', $ok
            ? 'موجودی ' . ($delta > 0 ? 'افزایش' : 'کاهش') . ' یافت (' . digits_fa(abs($delta)) . ').'
            : 'اعمال موجودی ناموفق (موجودی منفی می‌شد یا کالا نیست).');
        self::back('store');
    }

    private static function sellerStatus(array $admin): void
    {
        $id     = (int)input('id', 0);
        $status = (string)input('status', '');
        $ok = Seller::setStatus($id, $status, (int)$admin['id'], (string)input('note', ''));
        Auth::flash($ok ? 'ok' : 'err', $ok ? 'وضعیت فروشنده تغییر کرد.' : 'تغییر وضعیت ناموفق.');
        self::back('store');
    }

    private static function categorySave(array $admin): void
    {
        $res = Store::saveCategory($_POST);
        Auth::flash($res['ok'] ? 'ok' : 'err', $res['ok'] ? 'دسته ذخیره شد.' : (string)($res['error'] ?? 'ناموفق'));
        self::back('store');
    }

    private static function promoCreate(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        $res = Store::saveCampaign($_POST, (int)$admin['id']);
        Auth::flash($res['ok'] ? 'ok' : 'err', $res['ok']
            ? 'کمپین ساخته شد. کرون در زمان مقرر یک دسته در صف ارسال می‌سازد.'
            : (string)($res['error'] ?? 'ناموفق'));
        self::back('store');
    }

    private static function promoToggle(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        $ok = Store::toggleCampaign((int)input('id', 0), (string)input('active', '0') === '1', (int)$admin['id']);
        Auth::flash($ok ? 'ok' : 'err', $ok ? 'وضعیت کمپین تغییر کرد.' : 'ناموفق');
        self::back('store');
    }

    private static function paymentVerify(array $admin): void
    {
        $res = Orders::verifyPayment((int)input('id', 0), (string)input('approve', '0') === '1',
            (int)$admin['id'], (string)input('note', ''));
        Auth::flash($res['ok'] ? 'ok' : 'err', $res['ok']
            ? ((string)input('approve', '0') === '1' ? 'پرداخت تایید شد و موجودی انبار کم شد.' : 'رسید رد شد؛ خریدار مطلع شد.')
            : (string)($res['error'] ?? 'ناموفق'));
        self::back('orders');
    }

    private static function orderStatus(array $admin): void
    {
        $res = Orders::setStatus((int)input('id', 0), (string)input('status', ''), (int)$admin['id'], (string)input('note', ''));
        Auth::flash($res['ok'] ? 'ok' : 'err', $res['ok']
            ? 'وضعیت سفارش تغییر کرد.' . (string)($res['commission'] ?? '')
            : (string)($res['error'] ?? 'ناموفق'));
        self::back('orders');
    }

    private static function withdrawDecide(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        $ok = Wallet::decideWithdraw((int)input('id', 0), (string)input('approve', '0') === '1',
            (int)$admin['id'], (string)input('note', ''));
        Auth::flash($ok ? 'ok' : 'err', $ok ? 'درخواست برداشت پردازش شد.' : 'درخواست پیدا نشد یا قبلاً بررسی شده.');
        self::back('wallets');
    }

    private static function storeSettings(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        Schema::setSetting('store_commission_rate', (string)max(0, min(50, (float)input('store_commission_rate', 10))));
        Schema::setSetting('store_shipping', (string)max(0, (int)input('store_shipping', 0)));
        Schema::setSetting('store_card_number', preg_replace('/[^0-9-]/', '', (string)input('store_card_number', '')) ?? '');
        Schema::setSetting('store_card_name', trim((string)input('store_card_name', '')));
        Schema::setSetting('store_min_withdraw', (string)max(0, (int)input('store_min_withdraw', 50000)));
        Schema::setSetting('store_seller_approval', input('store_seller_approval') ? '1' : '0');
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'store_settings', 'store');
        Auth::flash('ok', 'تنظیمات فروشگاه ذخیره شد.');
        self::back('wallets');
    }

    // ---------------- سیستم ----------------

    private static function systemWebhook(array $admin, bool $super): void
    {
        if (!$super) {
            Auth::flash('err', 'فقط مدیر ارشد.');
            self::back('dashboard');
            return;
        }
        $r = Bot::registerWebhook();
        if ($r['ok']) {
            Auth::flash('ok', 'وب‌هوک تنظیم شد: ' . ($r['url'] ?? ''));
            Logger::audit((int)$admin['id'], (string)$admin['username'], 'webhook_set', $r['url'] ?? '');
        } else {
            Auth::flash('err', 'تنظیم وب‌هوک ناموفق: ' . ($r['error'] ?? ''));
        }
        self::back('system');
    }

    private static function systemWebhookDelete(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        Bot::removeWebhook();
        Auth::flash('ok', 'وب‌هوک حذف شد (حالت پولینگ).');
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'webhook_delete', '');
        self::back('system');
    }

    private static function systemTest(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        $me = BaleApi::getMe();
        if ($me) {
            DB::q("INSERT INTO settings (skey,svalue,updated_at) VALUES ('bot_id',?,?)
                   ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)", [(string)(int)($me['id'] ?? 0), time()]);
            Auth::flash('ok', 'ارتباط با بله برقرار است: @' . h((string)($me['username'] ?? '')) . ' (' . h((string)($me['first_name'] ?? '')) . ')');
        } else {
            Auth::flash('err', 'ارتباط ناموفق: ' . h((string)BaleApi::$lastError));
        }
        self::back('system');
    }

    private static function systemCron(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        $n = Moderation::processScheduled();
        $gc = Cache::gc();
        Schema::setSetting('last_cron_at', (string)time());
        Auth::flash('ok', "کرون اجرا شد: {$n} اقدام زمان‌بندی‌شده، {$gc} فایل کش پاک شد.");
        self::back('system');
    }

    private static function systemCache(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        $n = Cache::gc();
        foreach (Settings::allActive() as $g) {
            Settings::flush((int)$g['chat_id']);
        }
        Auth::flash('ok', "کش پاک شد ({$n} فایل).");
        self::back('system');
    }

    private static function systemSettings(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        $otpChat    = (int)input('otp_chat_id', 0);
        $globalLog  = (int)input('default_log_channel', 0);
        $superAdmins = trim((string)input('super_admins', ''));
        Schema::setSetting('otp_chat_id', (string)$otpChat);
        Schema::setSetting('default_log_channel', (string)$globalLog);
        Schema::setSetting('super_admins_text', $superAdmins);
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'system_settings', 'global');
        Auth::flash('ok', 'تنظیمات سراسری ذخیره شد. (آیدی ادمین‌های سراسری ربات در config/config.php اعمال می‌شود)');
        self::back('system');
    }

    private static function broadcast(array $admin, bool $super): void
    {
        if (!$super) {
            self::back('dashboard');
            return;
        }
        $text = trim((string)input('text', ''));
        if ($text === '') {
            Auth::flash('err', 'متن خالی است.');
            self::back('system');
            return;
        }
        // نسخه ۱.۲: ارسال مستقیم حذف شد؛ همه‌چیز از صف با نرخ‌سنجی می‌رود
        $res = Broadcast::enqueue([
            'text'     => "📢 " . $text,
            'audience' => 'groups',
            'admin_id' => (int)$admin['id'],
            'title'    => 'ارسال سریع از صفحه سیستم',
        ]);
        if (!$res['ok']) {
            Auth::flash('err', (string)($res['error'] ?? 'ناموفق'));
            self::back('system');
            return;
        }
        Auth::flash('ok', 'در صف قرار گرفت: ' . digits_fa((int)$res['queued']) . ' گروه — کرون در دقیقه بعد ارسال می‌کند.');
        self::back('broadcast');
    }

    private static function badWordsSave(array $admin): void
    {
        $chatId = (int)input('chat_id', 0);
        $raw    = (string)input('words', '');
        DB::q("DELETE FROM bad_words WHERE chat_id=?", [$chatId ?: null]);
        if ($chatId === 0) {
            DB::q("DELETE FROM bad_words WHERE chat_id IS NULL");
        }
        $words = preg_split('~[\n,،]+~u', $raw) ?: [];
        $n = 0;
        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '') {
                continue;
            }
            DB::q("INSERT INTO bad_words (chat_id,word,is_regex,created_at) VALUES (?,?,?,?)",
                [$chatId ?: null, str_limit($w, 110), preg_match('~^/.*/[a-z]*$~i', $w) ? 1 : 0, time()]);
            $n++;
        }
        // کش کلمات همه گروه‌ها را پاک کن (لیست سراسری روی همه اثر دارد)
        foreach (DB::all("SELECT chat_id FROM chat_settings") as $g) {
            Settings::flushBadWords((int)$g['chat_id']);
        }
        Settings::flushBadWords(0);
        Logger::audit((int)$admin['id'], (string)$admin['username'], 'badwords_save', (string)$chatId, "count=$n");
        Auth::flash('ok', "{$n} کلمه ذخیره شد.");
        redirect('panel/index.php', ['p' => 'group', 'chat_id' => $chatId, 'tab' => 'words']);
    }
}
