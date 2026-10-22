<?php
/** @var array $refs,$overview,$top; @var bool $enabled,$notify; @var string $bot_username,$link_sample,$status;
 *  @var int $total,$off,$limit */
$base = ['p' => 'referrals'];
?>
<div class="grid g4 mb">
  <div class="kpi"><div class="i">✅</div><div class="v"><?= digits_fa($overview['verified']) ?></div><div class="l">دعوت تاییدشده</div></div>
  <div class="kpi"><div class="i">⏳</div><div class="v"><?= digits_fa($overview['pending']) ?></div><div class="l">در انتظار تایید</div></div>
  <div class="kpi"><div class="i">🚫</div><div class="v"><?= digits_fa($overview['rejected']) ?></div><div class="l">ردشده</div></div>
  <div class="kpi"><div class="i">👤</div><div class="v"><?= digits_fa($overview['inviters']) ?></div><div class="l">معرف فعال</div></div>
</div>

<div class="grid g2">
  <div class="card">
    <h2>📈 روند دعوت‌ها (۱۴ روز)</h2>
    <?= Metrics::line($overview['series'], $overview['labels'], '#22c55e', 520, 190) ?>
    <div class="hint mt">
      لینک دعوت هر کاربر: <code class="mono">https://ble.ir/<?= h($bot_username ?: 'bot') ?>?start=ref_CODE</code><br>
      کاربر اول در چت خصوصی ربات استارت می‌زند (pending) و بعد از اولین حضور در گروه، تایید می‌شود (verified) — ضد تقلب دومرحله‌ای.
    </div>
  </div>

  <div class="card">
    <h2>🏆 بهترین دعوت‌کننده‌ها</h2>
    <?php if (!$top): ?>
      <div class="empty">هنوز دعوت تاییدشده‌ای نیست.</div>
    <?php else: ?>
      <?= Metrics::hbars(array_map(static fn($t) => [
            'label' => trim(((string)($t['first_name'] ?? '')) . ' ' . ((string)($t['last_name'] ?? ''))) ?: ('#' . $t['inviter_id']),
            'value' => (int)$t['c'],
            'color' => '#f59e0b',
          ], $top), 460, 28) ?>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>⚙️ تنظیمات سامانه دعوت</h2>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g3">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="referral_settings">
    <div>
      <label class="check"><input type="checkbox" name="referral_enabled" value="1" <?= $enabled ? 'checked' : '' ?>> سامانه دعوت فعال باشد</label>
      <label class="check"><input type="checkbox" name="referral_notify" value="1" <?= $notify ? 'checked' : '' ?>> اطلاع به معرف هنگام تایید</label>
    </div>
    <div>
      <label>یوزرنیم ربات (برای ساخت لینک دعوت)</label>
      <input type="text" name="bot_username" class="mono" value="<?= h($bot_username) ?>" placeholder="my_group_bot">
      <div class="hint">بدون @ — اگر خالی باشد از getMe گرفته می‌شود.</div>
    </div>
    <div style="align-self:end"><button class="btn" type="submit">💾 ذخیره</button></div>
  </form>
  <?php if ($link_sample): ?>
    <div class="hint mt">نمونه لینک شما: <code class="mono"><?= h($link_sample) ?></code></div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header">
    <h2>📋 لیست دعوت‌ها <span class="badge b-info"><?= digits_fa($total) ?></span></h2>
    <form method="get" class="inline">
      <input type="hidden" name="p" value="referrals">
      <select name="status" onchange="this.form.submit()">
        <option value="">همه وضعیت‌ها</option>
        <?php foreach (['pending' => 'در انتظار', 'verified' => 'تاییدشده', 'rejected' => 'ردشده'] as $k => $l): ?>
          <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= h($l) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if (!$refs): ?>
    <div class="empty">دعوتی ثبت نشده است.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>معرف</th><th>دعوت‌شده</th><th>گروه</th><th>وضعیت</th><th>زمان</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($refs as $r): ?>
        <tr>
          <td class="small"><?= digits_fa((int)$r['id']) ?></td>
          <td class="small">
            <?= h(trim(((string)($r['inviter_name'] ?? '')) ) ?: '—') ?>
            <span class="mono muted"><?= h((string)$r['inviter_id']) ?></span>
          </td>
          <td class="small">
            <?= h(trim(((string)($r['invitee_name'] ?? '')) ) ?: '—') ?>
            <span class="mono muted"><?= h((string)$r['invitee_id']) ?></span>
          </td>
          <td class="mono small"><?= $r['chat_id'] ? h((string)$r['chat_id']) : '—' ?></td>
          <td>
            <?php
              $cls = $r['status'] === 'verified' ? 'b-ok' : ($r['status'] === 'pending' ? 'b-warn' : 'b-err');
              $lbl = ['verified' => 'تاییدشده', 'pending' => 'در انتظار', 'rejected' => 'ردشده'][$r['status']] ?? $r['status'];
            ?>
            <span class="badge <?= $cls ?>"><?= h($lbl) ?></span>
          </td>
          <td class="small"><?= h(dt((int)$r['created_at'], 'Y/m/d H:i')) ?></td>
          <td>
            <div class="btn-group">
              <?php if ($r['status'] !== 'verified'): ?>
                <form method="post" action="<?= h(url('panel/index.php')) ?>">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="referral_status">
                  <input type="hidden" name="status" value="verified">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn ok sm" type="submit">تایید</button>
                </form>
              <?php endif; ?>
              <?php if ($r['status'] !== 'rejected'): ?>
                <form method="post" action="<?= h(url('panel/index.php')) ?>">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="referral_status">
                  <input type="hidden" name="status" value="rejected">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn danger sm" type="submit">رد</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if ($total > $limit): ?>
      <div class="pager">
        <?php for ($i = 0; $i < min(20, (int)ceil($total / $limit)); $i++): ?>
          <a class="<?= $off == $i * $limit ? 'cur' : '' ?>" href="<?= h(url('panel/index.php', $base + ['status' => $status, 'off' => $i * $limit])) ?>"><?= digits_fa($i + 1) ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h2>📖 دستورهای ربات برای کاربران</h2>
  <div class="small">
    <code>دعوت</code> — دریافت لینک اختصاصی + آمار<br>
    <code>زیرمجموعه‌ها</code> — لیست دعوت‌شده‌ها<br>
    <code>تاپ_دعوت</code> — بهترین دعوت‌کننده‌های این گروه<br>
    <span class="muted">در چت خصوصی: <code>/start ref_CODE</code></span>
  </div>
</div>
