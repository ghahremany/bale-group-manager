<?php
/** @var array $kpi, $recent, $topgroups, $open_reports, $bans_recent, $mode, $last_cron, $last_poll, $bot_username */
$cards = [
    ['👥', digits_fa($kpi['groups']), 'گروه فعال'],
    ['🙋', digits_fa($kpi['users']), 'کاربر شناخته‌شده'],
    ['📜', digits_fa($kpi['events24']), 'رخداد ۲۴ ساعت'],
    ['🚨', digits_fa($kpi['reports']), 'گزارش باز'],
    ['⛔', digits_fa($kpi['bans']), 'بن'],
    ['🔇', digits_fa($kpi['mutes']), 'در سکوت'],
    ['⚠️', digits_fa($kpi['warns']), 'اخطار فعال'],
    ['🔑', digits_fa($kpi['admins']), 'ادمین پنل'],
];
?>
<div class="grid g4 mb">
  <?php foreach ($cards as [$icon, $v, $l]): ?>
    <div class="kpi">
      <div class="i"><?= $icon ?></div>
      <div class="v"><?= $v ?></div>
      <div class="l"><?= h($l) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header">
    <h2>🛒 فروشگاه</h2>
    <div class="btn-group">
      <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'store'])) ?>">انبار</a>
      <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'orders'])) ?>">سفارش‌ها</a>
    </div>
  </div>
  <div class="grid g4 mb">
    <div class="kpi"><div class="v"><?= digits_fa((int)$storeStats['active']) ?></div><div class="l">کالای فعال</div></div>
    <div class="kpi"><div class="v"><?= digits_fa((int)$orderStats['today_count']) ?></div><div class="l">سفارش امروز</div></div>
    <div class="kpi"><div class="v"><?= digits_fa((int)$orderStats['pending']) ?></div><div class="l">رسید در انتظار</div></div>
    <div class="kpi"><div class="v"><?= money((int)$orderStats['period_sum'], '') ?></div><div class="l">فروش ۳۰ روز (تومان)</div></div>
  </div>
  <div class="small muted mb">فروش روزانه (تومان)</div>
  <?= Metrics::line($sales['amount'], $sales['labels'], '#22c55e', 760, 180) ?>
</div>

<div class="card">
  <div class="card-header">
    <h2>📈 ۱۴ روز اخیر</h2>
    <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'analytics'])) ?>">گزارش کامل</a>
  </div>
  <div class="grid g3">
    <div>
      <div class="small muted">پیام‌ها (۷ روز): <b><?= digits_fa((int)$sum7['messages']) ?></b></div>
      <?= Metrics::sparkline($spark['messages'], '#3b82f6', 300, 48) ?>
    </div>
    <div>
      <div class="small muted">تخلف/اقدام: <b><?= digits_fa((int)$sum7['violations']) ?></b></div>
      <?= Metrics::sparkline($spark['violations'], '#ef4444', 300, 48) ?>
    </div>
    <div>
      <div class="small muted">ورود اعضا: <b><?= digits_fa((int)$sum7['joins']) ?></b></div>
      <?= Metrics::sparkline($spark['joins'], '#22c55e', 300, 48) ?>
    </div>
  </div>
  <div class="rowline mt">
    <span>🌱 دعوت‌های تاییدشده: <b><?= digits_fa((int)$referral['verified']) ?></b> · در انتظار: <?= digits_fa((int)$referral['pending']) ?></span>
    <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'referrals'])) ?>">مدیریت</a>
  </div>
  <div class="rowline">
    <span>🗄 آخرین بکاپ: <b><?= $backup_last ? h(dt($backup_last, 'Y/m/d H:i')) : 'ندارد' ?></b></span>
    <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'backup'])) ?>">بکاپ</a>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2>وضعیت سیستم</h2>
    <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'system'])) ?>">مدیریت</a>
  </div>
  <div class="rowline"><span>حالت دریافت پیام</span>
    <span class="badge <?= $mode === 'webhook' ? 'b-ok' : 'b-warn' ?>"><?= $mode === 'webhook' ? 'وب‌هوک' : 'پولینگ' ?></span>
  </div>
  <div class="rowline"><span>ربات</span><span class="mono"><?= $bot_username ? '@' . h($bot_username) : '—' ?></span></div>
  <div class="rowline"><span>آخرین اجرای کرون</span>
    <span><?= $last_cron ? h(dt($last_cron)) : '<span class="badge b-err">هرگز اجرا نشده</span>' ?></span>
  </div>
  <div class="rowline"><span>آخرین پولینگ</span>
    <span><?= $last_poll ? h(dt($last_poll)) : '—' ?></span>
  </div>
  <?php if ($last_cron && time() - $last_cron > 600): ?>
    <div class="alert warn mt">کرون بیش از ۱۰ دقیقه اجرا نشده است. اقدامات زمان‌بندی‌شده (آن‌میوت/آنبن) انجام نمی‌شوند.</div>
  <?php endif; ?>
</div>

<div class="grid g2">
  <div class="card">
    <div class="card-header"><h2>آخرین رخدادها</h2>
      <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'logs'])) ?>">همه</a></div>
    <?php if (!$recent): ?>
      <div class="empty">رخدادی ثبت نشده است.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>زمان</th><th>گروه</th><th>اقدام</th><th>کاربر</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $r): ?>
            <tr>
              <td class="mono small"><?= h(dt((int)$r['ts'], 'm/d H:i')) ?></td>
              <td class="mono small"><?= h((string)$r['chat_id']) ?></td>
              <td><span class="badge b-info"><?= h((string)$r['action']) ?></span></td>
              <td class="mono small"><?= h((string)($r['target_id'] ?? $r['actor_id'] ?? '—')) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-header"><h2>گزارش‌های باز</h2>
      <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'moderation', 'tab' => 'reports'])) ?>">مدیریت</a></div>
    <?php if (!$open_reports): ?>
      <div class="empty">گزارش بازی وجود ندارد 🎉</div>
    <?php else: ?>
      <?php foreach ($open_reports as $r): ?>
        <div class="rowline">
          <span>#<?= digits_fa((int)$r['id']) ?> · <?= h((string)($r['title'] ?: $r['chat_id'])) ?></span>
          <span class="mono small">هدف <?= h((string)$r['target_user_id']) ?></span>
        </div>
        <div class="small muted"><?= mask_text((string)$r['reason'], 90) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<div class="grid g2">
  <div class="card">
    <div class="card-header"><h2>فعال‌ترین گروه‌ها (۷ روز)</h2></div>
    <?php if (!$topgroups): ?>
      <div class="empty">هنوز گروهی ثبت نشده. ربات را به گروه اضافه و ادمین کنید.</div>
    <?php else: ?>
      <?php foreach ($topgroups as $g): ?>
        <div class="rowline">
          <a href="<?= h(url('panel/index.php', ['p' => 'group', 'chat_id' => (int)$g['chat_id']])) ?>"><?= h((string)($g['title'] ?: $g['chat_id'])) ?></a>
          <span class="badge b-info"><?= digits_fa((int)$g['ev']) ?> رخداد</span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-header"><h2>آخرین بن‌ها</h2>
      <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'moderation'])) ?>">همه</a></div>
    <?php if (!$bans_recent): ?>
      <div class="empty">بن‌شده‌ای وجود ندارد.</div>
    <?php else: ?>
      <?php foreach ($bans_recent as $b): ?>
        <div class="rowline">
          <span class="mono small"><?= h((string)$b['user_id']) ?></span>
          <span class="small muted"><?= h((string)($b['reason'] ?: '—')) ?> · <?= h(dt((int)$b['ts'], 'm/d H:i')) ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
