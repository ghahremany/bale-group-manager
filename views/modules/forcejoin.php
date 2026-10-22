<?php
/** @var array $chats,$stats,$recent; @var bool $quiz; @var string $raw */
?>
<div class="grid g3 mb">
  <div class="kpi"><div class="i">✅</div><div class="v"><?= digits_fa((int)$stats['passed']) ?></div><div class="l">عبورکرده</div></div>
  <div class="kpi"><div class="i">⏳</div><div class="v"><?= digits_fa((int)$stats['pending']) ?></div><div class="l">در انتظار عضویت</div></div>
  <div class="kpi"><div class="i">⛔</div><div class="v"><?= digits_fa((int)$stats['failed']) ?></div><div class="l">ناموفق</div></div>
</div>

<div class="card">
  <h2>⚙️ تنظیم گیت عضویت</h2>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="forcejoin_save">
    <input type="hidden" name="m" value="forcejoin">
    <div>
      <label>کانال/گروه‌های الزامی</label>
      <textarea name="forcejoin_chats" rows="3" class="mono" placeholder="-1001234567890&#10;@my_channel"><?= h($raw) ?></textarea>
      <div class="hint">
        هر مقصد در یک خط یا جدا با کاما: آیدی عددی یا @یوزرنیم. ربات باید در آنجا <b>ادمین</b> باشد
        تا بتواند عضویت را با <code class="mono">getChatMember</code> بررسی کند.
      </div>
    </div>
    <label class="check">
      <input type="checkbox" name="forcejoin_quiz" value="1" <?= $quiz ? 'checked' : '' ?>>
      پرسیدن سؤال بازی (جمع دو عدد) قبل از تایید — ضد ربات
    </label>
    <div><button class="btn" type="submit">💾 ذخیره تنظیمات</button></div>
  </form>
  <div class="hint mt">
    وقتی ماژول روشن است، کاربر برای استفاده از فروشگاه/دعوت باید یک‌بار از این گیت بگذرد.
    کاربر دستور <code class="mono">عضو_شدم</code> (و همراه جواب بازی، مثلاً <code class="mono">عضو_شدم ۱۲</code>) می‌فرستد.
  </div>
</div>

<div class="card">
  <div class="card-header"><h2>🎯 مقصدهای فعلی</h2></div>
  <?php if (!$chats): ?>
    <div class="empty">هیچ مقصدی تنظیم نشده است؛ گیت عملاً غیرفعال است.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>برچسب</th><th>آیدی</th><th>یوزرنیم</th></tr></thead>
      <tbody>
      <?php $i = 0; foreach ($chats as $c): $i++; ?>
        <tr>
          <td class="small"><?= digits_fa($i) ?></td>
          <td class="mono small"><?= h((string)$c['label']) ?></td>
          <td class="mono small"><?= $c['chat_id'] ? h((string)$c['chat_id']) : '—' ?></td>
          <td class="mono small"><?= $c['username'] ? '@' . h((string)$c['username']) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header"><h2>👥 آخرین وضعیت کاربران</h2></div>
  <?php if (!$recent): ?>
    <div class="empty">هنوز کاربری از گیت عبور نکرده است.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>کاربر</th><th>وضعیت</th><th>بازی</th><th>تلاش</th><th>زمان</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td class="small"><?= h(trim((string)($r['first_name'] ?? '') . ' ' . (string)($r['last_name'] ?? '')) ?: ('#' . (int)$r['user_id'])) ?>
            <div class="mono small"><?= h((string)$r['user_id']) ?></div>
          </td>
          <td>
            <?php $cls = $r['status'] === 'passed' ? 'b-ok' : ($r['status'] === 'failed' ? 'b-err' : 'b-warn'); ?>
            <span class="badge <?= $cls ?>"><?= ['passed' => 'عبورکرده', 'failed' => 'ناموفق', 'pending' => 'در انتظار'][(string)$r['status']] ?? h((string)$r['status']) ?></span>
          </td>
          <td class="small"><?= (int)$r['quiz_ok'] === 1 ? '✅' : '—' ?></td>
          <td class="small"><?= digits_fa((int)$r['attempts']) ?></td>
          <td class="small mono"><?= dt((int)($r['verified_at'] ?: $r['created_at']), 'Y/m/d H:i') ?></td>
          <td>
            <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline" onsubmit="return confirm('وضعیت کاربر بازنشانی شود؟')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="a" value="forcejoin_reset">
              <input type="hidden" name="m" value="forcejoin">
              <input type="hidden" name="user_id" value="<?= (int)$r['user_id'] ?>">
              <button class="btn ghost sm" type="submit">↺ بازنشانی</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
