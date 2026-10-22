<?php
/** @var array $rows,$top,$stats,$settings; @var int $total */
$f = (string)($_GET['f'] ?? '');
$statuses = ['verified' => 'تاییدشده', 'pending' => 'در انتظار', 'rejected' => 'رد‌شده'];
?>
<div class="grid g4 mb">
  <div class="kpi"><div class="i">✅</div><div class="v"><?= digits_fa((int)$stats['verified']) ?></div><div class="l">دعوت تاییدشده</div></div>
  <div class="kpi"><div class="i">⏳</div><div class="v"><?= digits_fa((int)$stats['pending']) ?></div><div class="l">در انتظار</div></div>
  <div class="kpi"><div class="i">🚫</div><div class="v"><?= digits_fa((int)$stats['rejected']) ?></div><div class="l">رد‌شده</div></div>
  <div class="kpi"><div class="i">👤</div><div class="v"><?= digits_fa((int)$stats['inviters']) ?></div><div class="l">معرف فعال</div></div>
</div>

<div class="grid g2">
  <div class="card">
    <h2>⚙️ تنظیمات ماژول</h2>
    <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="referral_save">
      <input type="hidden" name="m" value="referrals">
      <label class="check">
        <input type="checkbox" name="referral_notify" value="1" <?= $settings['notify'] === '1' ? 'checked' : '' ?>>
        اطلاع‌رسانی به معرف هنگام تایید زیرمجموعه
      </label>
      <div class="hint">
        روشن/خاموش کردن کل سیستم از صفحه <a href="<?= h(url('panel/index.php', ['p' => 'modules'])) ?>">ماژول‌ها</a> انجام می‌شود.
        منطق ضد تقلب دومرحله‌ای است: ثبت با لینک (pending) و تایید بعد از اولین حضور در گروه (verified).
      </div>
      <div><button class="btn" type="submit">💾 ذخیره</button></div>
    </form>
  </div>

  <div class="card">
    <h2>🏆 برترین معرف‌ها</h2>
    <?php if (!$top): ?>
      <div class="empty">هنوز دعوت تاییدشده‌ای نیست.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>معرف</th><th>تعداد</th></tr></thead>
        <tbody>
        <?php $i = 0; foreach ($top as $t): $i++; ?>
          <tr>
            <td class="small"><?= digits_fa($i) ?></td>
            <td><?= h(trim((string)($t['first_name'] ?? '') . ' ' . (string)($t['last_name'] ?? '')) ?: ('#' . (int)$t['inviter_id'])) ?></td>
            <td><b><?= digits_fa((int)$t['c']) ?></b></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>📋 دعوت‌ها <span class="badge b-info"><?= digits_fa((int)$total) ?></span></h2>
    <form method="get" class="inline">
      <input type="hidden" name="p" value="module">
      <input type="hidden" name="m" value="referrals">
      <select name="f" onchange="this.form.submit()">
        <option value="">همه</option>
        <?php foreach ($statuses as $k => $l): ?>
          <option value="<?= $k ?>" <?= $f === $k ? 'selected' : '' ?>><?= h($l) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <?php if (!$rows): ?>
    <div class="empty">دعوتی با این فیلتر وجود ندارد.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>معرف</th><th>دعوت‌شده</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="small"><?= digits_fa((int)$r['id']) ?></td>
          <td class="small"><?= h((string)($r['inviter_name'] ?: '#' . (int)$r['inviter_id'])) ?></td>
          <td class="small"><?= h((string)($r['invitee_name'] ?: '#' . (int)$r['invitee_id'])) ?></td>
          <td>
            <?php $cls = $r['status'] === 'verified' ? 'b-ok' : ($r['status'] === 'rejected' ? 'b-err' : 'b-warn'); ?>
            <span class="badge <?= $cls ?>"><?= h($statuses[(string)$r['status']] ?? (string)$r['status']) ?></span>
          </td>
          <td class="small mono"><?= dt((int)($r['verified_at'] ?: $r['created_at']), 'Y/m/d H:i') ?></td>
          <td>
            <div class="btn-group">
              <?php foreach (['verified' => ['✅ تایید', 'ok'], 'rejected' => ['⛔ رد', 'danger']] as $st => $meta): ?>
                <?php if ((string)$r['status'] !== $st): ?>
                  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="a" value="referral_set_status">
                    <input type="hidden" name="m" value="referrals">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="status" value="<?= $st ?>">
                    <button class="btn <?= $meta[1] ?> sm" type="submit"><?= h($meta[0]) ?></button>
                  </form>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>🤖 دستورهای ربات این ماژول</h2>
  <div class="hint">
    <code class="mono">لینک_دعوت_من</code> — لینک اختصاصی ·
    <code class="mono">دعوتها</code> — وضعیت زیرمجموعه‌ها ·
    <code class="mono">برترینها</code> — جدول برترین معرف‌ها
  </div>
</div>
