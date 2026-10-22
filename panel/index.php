<?php
declare(strict_types=1);

/**
 * کنترلر اصلی پنل
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

security_headers(true);
Auth::boot();
$admin = Auth::requireLogin();
$super = (string)$admin['role'] === 'super';

// عملیات POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Auth::checkCsrf();
    $action = (string)($_POST['a'] ?? '');
    if ($action !== '') {
        PanelActions::run($action, $admin);
        if (!headers_sent()) {
            redirect('panel/index.php', ['p' => 'dashboard']);
        }
        exit;
    }
}

$page  = (string)($_GET['p'] ?? 'dashboard');
$flash = Auth::takeFlashes();

// ---------------- داده هر صفحه ----------------
$data = [
    'admin' => $admin,
    'super' => $super,
    'flash' => $flash,
    'page'  => $page,
    'mode'  => (string)(Schema::getSetting('mode', (string)config('bot.mode', 'webhook'))),
];

switch ($page) {
    case 'groups':
        $q     = trim((string)($_GET['q'] ?? ''));
        $limit = 25;
        $off   = max(0, (int)($_GET['off'] ?? 0));
        $where = '';
        $args  = [];
        if ($q !== '') {
            $where = "WHERE title LIKE ? OR chat_id = ?";
            $args  = ['%' . $q . '%', (int)$q];
        }
        $rows  = DB::all("SELECT * FROM chat_settings $where ORDER BY updated_at DESC LIMIT $limit OFFSET $off", $args);
        $total = (int)DB::val("SELECT COUNT(*) FROM chat_settings $where", $args, 0);
        foreach ($rows as &$r) {
            $cid = (int)$r['chat_id'];
            $r['bans']  = (int)DB::val("SELECT COUNT(*) FROM bans WHERE chat_id=?", [$cid], 0);
            $r['mutes'] = (int)DB::val("SELECT COUNT(*) FROM mutes WHERE chat_id=? AND until_ts>?", [$cid, time()], 0);
            $r['reports'] = (int)DB::val("SELECT COUNT(*) FROM reports WHERE chat_id=? AND status='open'", [$cid], 0);
            $r['events24'] = (int)DB::val("SELECT COUNT(*) FROM logs WHERE chat_id=? AND ts>?", [$cid, time() - 86400], 0);
        }
        unset($r);
        $data += ['groups' => $rows, 'total' => $total, 'q' => $q, 'off' => $off, 'limit' => $limit];
        $title = 'گروه‌ها';
        break;

    case 'group':
        $chatId = (int)($_GET['chat_id'] ?? 0);
        if (!$chatId) {
            redirect('panel/index.php', ['p' => 'groups']);
        }
        $s = Settings::get($chatId);
        $data += [
            'settings'   => $s,
            'chat_id'    => $chatId,
            'tab'        => (string)($_GET['tab'] ?? 'general'),
            'buttons'    => DB::all("SELECT * FROM welcome_buttons WHERE chat_id=? ORDER BY sort,id", [$chatId]),
            'bans'       => DB::all("SELECT * FROM bans WHERE chat_id=? ORDER BY ts DESC LIMIT 100", [$chatId]),
            'mutes'      => DB::all("SELECT * FROM mutes WHERE chat_id=? ORDER BY until_ts DESC LIMIT 100", [$chatId]),
            'warns'      => DB::all("SELECT w.*,u.first_name,u.last_name,u.username FROM warns w LEFT JOIN users u ON u.user_id=w.user_id WHERE w.chat_id=? AND w.count>0 ORDER BY w.count DESC LIMIT 100", [$chatId]),
            'reports'    => DB::all("SELECT * FROM reports WHERE chat_id=? ORDER BY ts DESC LIMIT 100", [$chatId]),
            'logs'       => DB::all("SELECT * FROM logs WHERE chat_id=? ORDER BY ts DESC LIMIT 100", [$chatId]),
            'badwords'   => DB::all("SELECT * FROM bad_words WHERE chat_id IS NULL OR chat_id=? ORDER BY word", [$chatId]),
            'top_members'=> DB::all("SELECT * FROM users ORDER BY messages_count DESC LIMIT 10"),
            'member_cnt' => BaleApi::hasToken() ? BaleApi::getChatMemberCount($chatId) : null,
        ];
        $title = 'مدیریت گروه: ' . ($s['title'] ?: $chatId);
        break;

    case 'users':
        $q     = trim((string)($_GET['q'] ?? ''));
        $limit = 30;
        $off   = max(0, (int)($_GET['off'] ?? 0));
        $where = '';
        $args  = [];
        if ($q !== '') {
            $where = "WHERE user_id=? OR username LIKE ? OR first_name LIKE ? OR last_name LIKE ?";
            $args  = [(int)$q, "%$q%", "%$q%", "%$q%"];
        }
        $rows  = DB::all("SELECT * FROM users $where ORDER BY last_seen_at DESC LIMIT $limit OFFSET $off", $args);
        $total = (int)DB::val("SELECT COUNT(*) FROM users $where", $args, 0);
        $data += ['users' => $rows, 'total' => $total, 'q' => $q, 'off' => $off, 'limit' => $limit];
        $title = 'کاربران';
        break;

    case 'moderation':
        $chatFilter = (int)($_GET['chat_id'] ?? 0);
        $sqlW = $chatFilter ? "WHERE chat_id=?" : "";
        $args = $chatFilter ? [$chatFilter] : [];
        $data += [
            'tab'     => (string)($_GET['tab'] ?? 'bans'),
            'chat_id' => $chatFilter,
            'bans'    => DB::all("SELECT * FROM bans $sqlW ORDER BY ts DESC LIMIT 200", $args),
            'mutes'   => DB::all("SELECT * FROM mutes $sqlW ORDER BY until_ts DESC LIMIT 200", $args),
            'reports' => DB::all("SELECT * FROM reports $sqlW ORDER BY ts DESC LIMIT 200", $args),
            'groups'  => DB::all("SELECT chat_id,title FROM chat_settings ORDER BY title LIMIT 500"),
        ];
        $title = 'مدیریت تخلفات';
        break;

    case 'logs':
        $chatFilter = (int)($_GET['chat_id'] ?? 0);
        $action     = trim((string)($_GET['action'] ?? ''));
        $limit      = 100;
        $off        = max(0, (int)($_GET['off'] ?? 0));
        $w = [];
        $a = [];
        if ($chatFilter) {
            $w[] = 'chat_id = ?';
            $a[] = $chatFilter;
        }
        if ($action !== '') {
            $w[] = 'action = ?';
            $a[] = $action;
        }
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        $data += [
            'logs'    => DB::all("SELECT * FROM logs $where ORDER BY ts DESC LIMIT $limit OFFSET $off", $a),
            'actions' => DB::all("SELECT action, COUNT(*) c FROM logs GROUP BY action ORDER BY c DESC"),
            'groups'  => DB::all("SELECT chat_id,title FROM chat_settings ORDER BY title LIMIT 500"),
            'chat_id' => $chatFilter,
            'action'  => $action,
            'off'     => $off,
            'limit'   => $limit,
            'total'   => (int)DB::val("SELECT COUNT(*) FROM logs $where", $a, 0),
        ];
        $title = 'گزارش رخدادها';
        break;

    case 'admins':
        if (!$super) {
            redirect('panel/index.php', ['p' => 'dashboard']);
        }
        $data += [
            'admins'   => DB::all("SELECT * FROM panel_admins ORDER BY id"),
            'sessions' => DB::all("SELECT s.*, a.username FROM panel_sessions s LEFT JOIN panel_admins a ON a.id=s.admin_id WHERE s.expires_at>? ORDER BY s.last_seen_at DESC", [time()]),
        ];
        $title = 'ادمین‌های پنل';
        break;

    case 'account':
        $data += [
            'sessions' => DB::all("SELECT * FROM panel_sessions WHERE admin_id=? AND expires_at>? ORDER BY last_seen_at DESC", [(int)$admin['id'], time()]),
            'audit'    => DB::all("SELECT * FROM audit_log WHERE admin_id=? ORDER BY ts DESC LIMIT 30", [(int)$admin['id']]),
        ];
        $title = 'حساب من';
        break;

    case 'system':
        if (!$super) {
            redirect('panel/index.php', ['p' => 'dashboard']);
        }
        $hookInfo = BaleApi::hasToken() ? BaleApi::getWebhookInfo() : null;
        $data += [
            'hook_info'  => $hookInfo,
            'hook_url'   => Schema::getSetting('webhook_url', ''),
            'me'         => BaleApi::hasToken() ? BaleApi::getMe() : null,
            'cron_secret'=> (string)config('cron.secret', ''),
            'otp_chat'   => (int)(Schema::getSetting('otp_chat_id', '0') ?: 0),
            'def_log'    => (int)(Schema::getSetting('default_log_channel', '0') ?: 0),
            'last_cron'  => (int)(Schema::getSetting('last_cron_at', '0') ?: 0),
            'last_poll'  => (int)(Schema::getSetting('last_poll_at', '0') ?: 0),
            'mode'       => (string)(Schema::getSetting('mode', (string)config('bot.mode', 'webhook'))),
            'php'        => PHP_VERSION,
            'groups'     => Settings::allActive(),
        ];
        $title = 'سیستم و وب‌هوک';
        break;

    case 'audit':
        if (!$super) {
            redirect('panel/index.php', ['p' => 'dashboard']);
        }
        $data += [
            'rows' => DB::all("SELECT * FROM audit_log ORDER BY ts DESC LIMIT 300"),
        ];
        $title = 'لاگ ممیزی پنل';
        break;

    case 'setup':
        $data += [];
        $title = 'راهنمای راه‌اندازی';
        break;

    case 'analytics':
        $days = (int)($_GET['days'] ?? 14);
        $days = in_array($days, [7, 14, 30, 60, 90], true) ? $days : 14;
        $chatFilter = (int)($_GET['chat_id'] ?? 0);
        $chart = Metrics::series($days, $chatFilter ?: null);
        $sum   = [
            'messages'   => array_sum($chart['messages']),
            'violations' => array_sum($chart['violations']),
            'joins'      => array_sum($chart['joins']),
            'lefts'      => array_sum($chart['lefts']),
            'commands'   => array_sum($chart['commands']),
        ];
        $prevFrom = strtotime('-' . ($days * 2) . ' days');
        $prevRows = DB::all("SELECT SUM(messages) m, SUM(violations) v, SUM(joins) j
                             FROM metrics_daily WHERE day >= ? AND day < ?" . ($chatFilter ? ' AND chat_id=?' : ''),
            array_merge([date('Y-m-d', $prevFrom), date('Y-m-d', strtotime('-' . $days . ' days'))], $chatFilter ? [$chatFilter] : []));
        $prev = $prevRows[0] ?? ['m' => 0, 'v' => 0, 'j' => 0];
        $actionDist = DB::all("SELECT action, COUNT(*) c FROM logs WHERE ts > ?" . ($chatFilter ? ' AND chat_id=?' : '') . " GROUP BY action ORDER BY c DESC LIMIT 8",
            array_merge([time() - $days * 86400], $chatFilter ? [$chatFilter] : []));
        $topGroups = DB::all("SELECT c.chat_id, c.title, SUM(m.messages) msgs, SUM(m.violations) vio
                              FROM metrics_daily m JOIN chat_settings c ON c.chat_id=m.chat_id
                              WHERE m.day >= ? GROUP BY c.chat_id ORDER BY msgs DESC LIMIT 8",
            [date('Y-m-d', strtotime('-' . ($days - 1) . ' days'))]);
        $hourDist = DB::all("SELECT HOUR(FROM_UNIXTIME(ts)) h, COUNT(*) c FROM logs WHERE ts > ? GROUP BY h ORDER BY h", [time() - 7 * 86400]);
        $data += [
            'days' => $days, 'chat_id' => $chatFilter, 'chart' => $chart, 'sum' => $sum, 'prev' => $prev,
            'action_dist' => $actionDist, 'top_groups' => $topGroups, 'hour_dist' => $hourDist,
            'groups' => DB::all("SELECT chat_id,title FROM chat_settings ORDER BY title LIMIT 500"),
            'referral' => Referral::overview($days),
            'backup_last' => (int)(Schema::getSetting('last_backup_at', '0') ?: 0),
        ];
        $title = 'گزارش نموداری';
        break;

    case 'backup':
        if (!$super) {
            redirect('panel/index.php', ['p' => 'dashboard']);
        }
        $dir = Backup::ensureDir();
        $data += [
            'backups'  => Backup::list(),
            'orphans'  => Backup::orphanFiles(),
            'dir'      => $dir,
            'dir_writable' => is_writable($dir),
            'auto'     => Backup::autoEnabled(),
            'every'    => Backup::frequencyHours(),
            'keep'     => Backup::maxFiles(),
            'send_chat'=> (int)(Schema::getSetting('backup_send_chat', '0') ?: 0),
            'last'     => (int)(Schema::getSetting('last_backup_at', '0') ?: 0),
            'next_in'  => Backup::nextRunIn(),
            'report_chat' => (int)(Schema::getSetting('report_daily_chat', '0') ?: 0),
            'report_hour' => (int)(Schema::getSetting('report_daily_hour', '9') ?: 9),
            'report_days' => (int)(Schema::getSetting('report_daily_days', '1') ?: 1),
            'db_size'  => (int)DB::val("SELECT SUM(data_length+index_length) FROM information_schema.tables WHERE table_schema=DATABASE()", [], 0),
            'db_tables'=> (int)DB::val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'", [], 0),
            'mysqldump'=> trim((string)@shell_exec('command -v mysqldump 2>/dev/null')) !== '',
        ];
        $title = 'بکاپ و گزارش خودکار';
        break;

    case 'referrals':
        $status = (string)($_GET['status'] ?? '');
        $limit  = 50;
        $off    = max(0, (int)($_GET['off'] ?? 0));
        [$refs, $total] = Referral::paginate($status, $limit, $off);
        $data += [
            'refs' => $refs, 'total' => $total, 'off' => $off, 'limit' => $limit, 'status' => $status,
            'overview' => Referral::overview(14),
            'top' => Referral::top(10),
            'enabled' => Referral::isEnabled(),
            'notify' => (string)Schema::getSetting('referral_notify', '1') === '1',
            'bot_username' => Referral::botUsername(),
            'link_sample' => $admin['bale_user_id'] ? Referral::link((int)$admin['bale_user_id']) : '',
        ];
        $title = 'زیرمجموعه‌گیری';
        break;

    case 'broadcast':
        $rate        = (int)(Schema::getSetting('broadcast_rate', '15') ?: 15);
        $rate        = max(1, min(25, $rate));
        $batchId     = (int)($_GET['batch'] ?? 0);
        $batch       = $batchId ? Broadcast::batch($batchId) : null;
        $rows        = $batchId ? Broadcast::queueRows($batchId, (string)($_GET['status'] ?? ''), 60) : [];
        $audiences   = Broadcast::audiences();
        $aud         = (string)($_GET['audience'] ?? 'groups');
        $queueStats  = [
            'sent'   => (int)DB::val("SELECT COUNT(*) FROM broadcast_queue WHERE status='sent' AND sent_at > ?", [time() - 86400], 0),
            'failed' => (int)DB::val("SELECT COUNT(*) FROM broadcast_queue WHERE status='failed' AND created_at > ?", [time() - 86400], 0),
        ];
        $data += [
            'batches'      => Broadcast::batches(25),
            'audiences'    => $audiences,
            'preview'      => Broadcast::preview(isset($audiences[$aud]) ? $aud : 'groups'),
            'rate'         => $rate,
            'pendingTotal' => Broadcast::pendingCount(),
            'batch'        => $batch,
            'rows'         => $rows,
            'batchId'      => $batchId,
            'queueStats'   => $queueStats,
        ];
        $title = 'ارسال همگانی (صف)';
        break;

    case 'recovery':
        $isSuper = (string)($admin['role'] ?? '') === 'super';
        $targetId = $isSuper && (int)($_GET['admin'] ?? 0) ? (int)$_GET['admin'] : (int)$admin['id'];
        $newCodes = $_SESSION['recovery_new_codes'] ?? [];
        unset($_SESSION['recovery_new_codes']);
        $data += [
            'codes'     => Recovery::list($targetId),
            'remaining' => Recovery::remaining($targetId),
            'logs'      => Recovery::logs($isSuper && empty($_GET['admin']) ? null : $targetId, (string)($_GET['purpose'] ?? ''), 120),
            'summary'   => Recovery::logSummary($isSuper && empty($_GET['admin']) ? null : $targetId, 7),
            'newCodes'  => is_array($newCodes) ? $newCodes : [],
            'adminId'   => $targetId,
            'purpose'   => (string)($_GET['purpose'] ?? ''),
            'admins'    => $isSuper ? DB::all("SELECT id,username FROM panel_admins ORDER BY id") : [],
        ];
        $title = 'کدهای بازیابی و لاگ امنیت';
        break;

    case 'store':
        $tab = in_array($_GET['tab'] ?? '', ['products', 'sellers', 'campaigns', 'categories', 'coupons', 'reviews', 'csv'], true)
            ? (string)$_GET['tab'] : 'products';
        $status  = (string)($_GET['status'] ?? '');
        $q       = trim((string)($_GET['q'] ?? ''));
        $limit   = 40;
        $off     = max(0, (int)($_GET['off'] ?? 0));
        [$products, $total] = Store::paginate(['status' => $status, 'q' => $q], $limit, $off);
        $data += [
            'tab' => $tab, 'products' => $products, 'total' => $total, 'off' => $off, 'limit' => $limit,
            'status' => $status, 'q' => $q,
            'sellers' => Seller::list((string)($_GET['sstatus'] ?? '')),
            'campaigns' => Store::campaigns(50),
            'categories' => Store::categories(),
            'stats' => Store::stats(),
            'sellerStats' => Seller::stats(),
            'coupons' => Coupon::list((string)($_GET['cq'] ?? '')),
            'reviews' => Reviews::list((string)($_GET['rstatus'] ?? ''), 60, 0),
            'rstatus' => (string)($_GET['rstatus'] ?? ''),
            'couponStats' => Coupon::stats(),
            'reviewStats' => Reviews::stats(),
        ];
        $title = 'انبار و فروشگاه';
        break;

    case 'orders':
        $status  = (string)($_GET['status'] ?? '');
        $payStat = (string)($_GET['payment_status'] ?? '');
        $q       = trim((string)($_GET['q'] ?? ''));
        $limit   = 40;
        $off     = max(0, (int)($_GET['off'] ?? 0));
        [$orders, $total] = Orders::paginate(['status' => $status, 'payment_status' => $payStat, 'q' => $q], $limit, $off);
        $data += [
            'orders' => $orders, 'total' => $total, 'off' => $off, 'limit' => $limit,
            'status' => $status, 'q' => $q,
            'stats' => Orders::stats(null, 30),
            'top' => Orders::topProducts(6),
        ];
        $title = 'سفارش‌ها';
        break;

    case 'wallets':
        if (!$super) {
            redirect('panel/index.php', ['p' => 'dashboard']);
        }
        $wStatus = in_array($_GET['status'] ?? '', ['pending', 'paid', 'rejected'], true) ? (string)$_GET['status'] : '';
        $data += [
            'withdrawals' => Wallet::withdrawals($wStatus),
            'tx' => DB::all("SELECT t.*, u.first_name FROM wallet_tx t LEFT JOIN users u ON u.user_id=t.user_id ORDER BY t.id DESC LIMIT 50"),
            'stats' => Wallet::stats(),
            'status' => $wStatus,
        ];
        $title = 'کیف پول و برداشت‌ها';
        break;

    case 'modules':
        $data += [
            'modules' => ModuleManager::summary(),
        ];
        $title = 'ماژول‌ها';
        break;

    case 'module':
        $mkey   = (string)($_GET['m'] ?? '');
        $mclass = ModuleManager::get($mkey);
        if ($mclass === null) {
            redirect('panel/index.php', ['p' => 'modules']);
        }
        $mdata = $mclass::panelData();
        $mview = $mclass::panelView();
        if ($mdata === null || $mview === null) {
            redirect('panel/index.php', ['p' => 'modules']);
        }
        $data = array_merge($data, $mdata);
        $data['module_key']   = $mkey;
        $data['module_title'] = $mclass::title();
        $data['module_icon']  = $mclass::icon();
        $data['module_on']    = $mclass::isEnabled();
        $data['module_view']  = $mview;
        $title = $mclass::title() . ' — ماژول';
        break;

    case 'dashboard':
    default:
        $now = time();
        $data += [
            'kpi' => [
                'groups'    => (int)DB::val("SELECT COUNT(*) FROM chat_settings WHERE is_active=1", [], 0),
                'users'     => (int)DB::val("SELECT COUNT(*) FROM users", [], 0),
                'events24'  => (int)DB::val("SELECT COUNT(*) FROM logs WHERE ts>?", [$now - 86400], 0),
                'bans'      => (int)DB::val("SELECT COUNT(*) FROM bans", [], 0),
                'mutes'     => (int)DB::val("SELECT COUNT(*) FROM mutes WHERE until_ts>?", [$now], 0),
                'warns'     => (int)DB::val("SELECT COALESCE(SUM(count),0) FROM warns", [], 0),
                'reports'   => (int)DB::val("SELECT COUNT(*) FROM reports WHERE status='open'", [], 0),
                'admins'    => (int)DB::val("SELECT COUNT(*) FROM panel_admins WHERE is_active=1", [], 0),
            ],
            'recent'    => DB::all("SELECT * FROM logs ORDER BY ts DESC LIMIT 15"),
            'topgroups' => DB::all("SELECT c.chat_id,c.title,(SELECT COUNT(*) FROM logs l WHERE l.chat_id=c.chat_id AND l.ts>?) ev FROM chat_settings c WHERE c.is_active=1 ORDER BY ev DESC LIMIT 8", [$now - 7 * 86400]),
            'open_reports' => DB::all("SELECT r.*, c.title FROM reports r LEFT JOIN chat_settings c ON c.chat_id=r.chat_id WHERE r.status='open' ORDER BY r.ts DESC LIMIT 8"),
            'bans_recent'  => DB::all("SELECT * FROM bans ORDER BY ts DESC LIMIT 8"),
            'mode'         => (string)(Schema::getSetting('mode', (string)config('bot.mode', 'webhook'))),
            'last_cron'    => (int)(Schema::getSetting('last_cron_at', '0') ?: 0),
            'last_poll'    => (int)(Schema::getSetting('last_poll_at', '0') ?: 0),
            'bot_username' => (string)(Schema::getSetting('bot_username', (string)config('bot.username', ''))),
            'spark'    => Metrics::series(14),
            'sum7'     => Metrics::summary(7),
            'backup_last' => (int)(Schema::getSetting('last_backup_at', '0') ?: 0),
            'referral' => Referral::overview(14),
            'storeStats' => Store::stats(),
            'orderStats' => Orders::stats(null, 30),
            'sales' => Orders::salesSeries(14),
            'pendingPayments' => Orders::stats()['pending'],
        ];
        $title = 'داشبورد';
        break;
}

$data['title'] = $title;
if ($page === 'module' && !empty($data['module_view'])) {
    View::render((string)$data['module_view'], $data);
} else {
    View::render($page === 'dashboard' ? 'dashboard' : $page, $data);
}
