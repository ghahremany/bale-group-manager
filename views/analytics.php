<?php
/** @var array $chart,$sum,$prev,$action_dist,$top_groups,$hour_dist,$groups,$referral; @var int $days,$chat_id,$backup_last */
$growth = static function (int $now, int $before): array {
    if ($before <= 0) {
        return [$now > 0 ? 100 : 0, $now > 0 ? 'up' : 'flat'];
    }
    $p = (($now - $before) / $before) * 100;
    return [round($p, 1), $p > 0 ? 'up' : ($p < 0 ? 'down' : 'flat')];
};
[$gMsg, $dMsg] = $growth((int)$sum['messages'], (int)($prev['m'] ?? 0));
[$gVio, $dVio] = $growth((int)$sum['violations'], (int)($prev['v'] ?? 0));
[$gJoin, $dJoin] = $growth((int)$sum['joins'], (int)($prev['j'] ?? 0));

$titles = ['messages' => 'پیام‌ها', 'violations' => 'تخلف/اقدام', 'joins' => 'ورود', 'commands' => 'دستورها'];
$base = ['p' => 'analytics', 'days' => $days, 'chat_id' => $chat_id];
?>
<div class="card tight">
  <form method="get" class="inline" style="justify-content:space-between">
    <input type="hidden" name="p" value="analytics">
    <div class="btn-group">
      <?php foreach ([7, 14, 30, 60, 90] as $d): ?>
        <a class="btn <?= $days === $d ? '' : 'ghost' ?> sm" href="<?= h(url('panel/index.php', ['p' => 'analytics', 'days' => $d, 'chat_id' => $chat_id])) ?>"><?= digits_fa($d) ?> روز</a>
      <?php endforeach; ?>
    </div>
    <div class="inline">
      <select name="chat_id" onchange="this.form.submit()">
        <option value="0">همه گروه‌ها</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int)$g['chat_id'] ?>" <?= (int)$g['chat_id'] === $chat_id ? 'selected' : '' ?>><?= h((string)($g['title'] ?: $g['chat_id'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<div class="grid g4 mb">
  <div class="kpi">
    <div class="i">💬</div>
    <div class="v"><?= digits_fa($sum['messages']) ?></div>
    <div class="l">پیام در <?= digits_fa($days) ?> روز
      <span class="badge <?= $dMsg === 'up' ? 'b-ok' : ($dMsg === 'down' ? 'b-err' : 'b-info') ?>">
        <?= $dMsg === 'up' ? '▲' : ($dMsg === 'down' ? '▼' : '•') ?> <?= digits_fa(abs($gMsg)) ?>%
      </span>
    </div>
  </div>
  <div class="kpi">
    <div class="i">🛡</div>
    <div class="v"><?= digits_fa($sum['violations']) ?></div>
    <div class="l">تخلف/اقدام
      <span class="badge <?= $dVio === 'down' ? 'b-ok' : ($dVio === 'up' ? 'b-warn' : 'b-info') ?>">
        <?= $dVio === 'up' ? '▲' : ($dVio === 'down' ? '▼' : '•') ?> <?= digits_fa(abs($gVio)) ?>%
      </span>
    </div>
  </div>
  <div class="kpi">
    <div class="i">🚪</div>
    <div class="v"><?= digits_fa($sum['joins']) ?> / <?= digits_fa($sum['lefts']) ?></div>
    <div class="l">ورود / خروج
      <span class="badge <?= $dJoin === 'up' ? 'b-ok' : ($dJoin === 'down' ? 'b-err' : 'b-info') ?>">
        <?= $dJoin === 'up' ? '▲' : ($dJoin === 'down' ? '▼' : '•') ?> <?= digits_fa(abs($gJoin)) ?>%
      </span>
    </div>
  </div>
  <div class="kpi">
    <div class="i">⚙️</div>
    <div class="v"><?= digits_fa($sum['commands']) ?></div>
    <div class="l">دستور اجرا شده</div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>📈 پیام‌ها و تخلف‌ها</h2>
    <div class="btn-group">
      <span class="tag"><span style="color:#3b82f6">■</span> پیام</span>
      <span class="tag"><span style="color:#ef4444">■</span> تخلف</span>
      <span class="tag"><span style="color:#22c55e">■</span> ورود</span>
    </div>
  </div>
  <?= Metrics::bars($chart, ['messages', 'violations', 'joins'], $titles) ?>
</div>

<div class="grid g2">
  <div class="card">
    <h2>💬 روند پیام‌ها</h2>
    <?= Metrics::line($chart['messages'], $chart['labels'], '#3b82f6') ?>
  </div>
  <div class="card">
    <h2>🛡 روند تخلف‌ها</h2>
    <?= Metrics::line($chart['violations'], $chart['labels'], '#ef4444') ?>
  </div>
</div>

<div class="grid g3">
  <div class="card">
    <h2>🍩 توزیع اقدام‌ها</h2>
    <?php
      $palette = ['#3b82f6', '#ef4444', '#22c55e', '#f59e0b', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];
      $items = [];
      foreach ($action_dist as $i => $a) {
          $items[] = ['label' => (string)$a['action'], 'value' => (int)$a['c'], 'color' => $palette[$i % count($palette)]];
      }
    ?>
    <?php if (!$items): ?>
      <div class="empty">داده‌ای برای نمایش نیست.</div>
    <?php else: ?>
      <div class="center"><?= Metrics::donut($items, 200) ?></div>
      <div class="mt small">
        <?php foreach ($items as $it): ?>
          <div class="rowline"><span><span style="color:<?= h($it['color']) ?>">■</span> <?= h($it['label']) ?></span><span><?= digits_fa($it['value']) ?></span></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>🏆 فعال‌ترین گروه‌ها</h2>
    <?php if (!$top_groups): ?>
      <div class="empty">داده‌ای نیست.</div>
    <?php else: ?>
      <?= Metrics::hbars(array_map(static fn($g) => [
            'label' => (string)($g['title'] ?: $g['chat_id']),
            'value' => (int)$g['msgs'],
            'color' => '#3b82f6',
          ], $top_groups), 420, 28) ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>🕐 ساعت‌های پرترافیک (۷ روز)</h2>
    <?php
      $hourValues = array_fill(0, 24, 0);
      foreach ($hour_dist as $h) {
          $hourValues[(int)$h['h']] = (int)$h['c'];
      }
      $hourLabels = array_map(static fn($h) => (string)$h, range(0, 23));
    ?>
    <?= Metrics::line($hourValues, $hourLabels, '#8b5cf6', 420, 170) ?>
    <div class="hint mt">ساعت‌ها به وقت سرور (Asia/Tehran) است؛ برای زمان‌بندی ارسال همگانی مفید است.</div>
  </div>
</div>

<div class="grid g2">
  <div class="card">
    <h2>🌱 زیرمجموعه‌گیری</h2>
    <div class="grid g2 mb">
      <div class="kpi"><div class="v"><?= digits_fa($referral['verified']) ?></div><div class="l">دعوت تاییدشده</div></div>
      <div class="kpi"><div class="v"><?= digits_fa($referral['pending']) ?></div><div class="l">در انتظار</div></div>
    </div>
    <?= Metrics::line($referral['series'], $referral['labels'], '#22c55e', 440, 160) ?>
    <a class="btn ghost sm mt" href="<?= h(url('panel/index.php', ['p' => 'referrals'])) ?>">مدیریت دعوت‌ها</a>
  </div>

  <div class="card">
    <h2>🗄 بکاپ</h2>
    <div class="rowline"><span>آخرین بکاپ</span><span><?= $backup_last ? h(dt($backup_last, 'Y/m/d H:i')) : '<span class="badge b-warn">ندارد</span>' ?></span></div>
    <div class="hint mt">بکاپ خودکار روزانه + ارسال به بله + بازیابی از پنل.</div>
    <a class="btn ghost sm mt" href="<?= h(url('panel/index.php', ['p' => 'backup'])) ?>">مدیریت بکاپ</a>
    <hr>
    <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="analytics_backfill">
      <button class="btn ghost sm" type="submit">♻️ بازسازی آمار از لاگ‌ها</button>
    </form>
    <div class="hint mt">اگر آمار روزهای قبل خالی است (نصب قدیمی)، این دکمه از جدول رخدادها جمع‌بندی می‌کند.</div>
  </div>
</div>
