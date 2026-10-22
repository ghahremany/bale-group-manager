<?php
/** @var array $news,$channels,$stats */
?>
<div class="grid g4 mb">
  <div class="kpi"><div class="i">🕒</div><div class="v"><?= digits_fa((int)$stats['scheduled']) ?></div><div class="l">زمان‌بندی‌شده</div></div>
  <div class="kpi"><div class="i">✅</div><div class="v"><?= digits_fa((int)$stats['sent']) ?></div><div class="l">ارسال‌شده</div></div>
  <div class="kpi"><div class="i">⚠️</div><div class="v"><?= digits_fa((int)$stats['failed']) ?></div><div class="l">ناموفق</div></div>
  <div class="kpi"><div class="i">📰</div><div class="v"><?= digits_fa(count($news)) ?></div><div class="l">کل خبرهای اخیر</div></div>
</div>

<div class="card">
  <h2>✍️ خبر جدید</h2>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g2">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="news_save">
    <input type="hidden" name="m" value="news">
    <div>
      <label>عنوان (اختیاری)</label>
      <input type="text" name="title" placeholder="تخفیف ویژه آخر هفته">
    </div>
    <div>
      <label>کانال مقصد</label>
      <select name="channel_id">
        <option value="0">همه کانال‌های فعال</option>
        <?php foreach ($channels as $c): ?>
          <option value="<?= (int)$c['chat_id'] ?>"><?= h((string)$c['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="hint">اگر کانالی ثبت نشده باشد، اول از ماژول «مدیریت کانال» اضافه کن.</div>
    </div>
    <div style="grid-column:1/-1">
      <label>متن خبر</label>
      <textarea name="body" rows="6" placeholder="متن خبر… (HTML ساده مجاز است)" required></textarea>
    </div>
    <div>
      <label>تصویر/رسانه (URL، اختیاری)</label>
      <input type="url" name="media_url" class="mono" placeholder="https://…">
    </div>
    <div>
      <label>زمان ارسال خودکار (اختیاری)</label>
      <input type="text" name="scheduled_at" class="mono" placeholder="۲۰۲۶-۱۰-۲۵ ۰۹:۰۰">
      <div class="hint">فرمت: <code class="mono">YYYY-MM-DD HH:MM</code> — خالی بگذار تا پیش‌نویس بماند.</div>
    </div>
    <div><button class="btn" type="submit">💾 ذخیره خبر</button></div>
  </form>
</div>

<div class="card">
  <div class="card-header"><h2>📰 خبرهای اخیر</h2></div>
  <?php if (!$news): ?>
    <div class="empty">خبری ثبت نشده است.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>عنوان</th><th>متن</th><th>وضعیت</th><th>زمان</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($news as $n): ?>
        <tr>
          <td class="small"><?= digits_fa((int)$n['id']) ?></td>
          <td><b><?= h((string)($n['title'] ?: '—')) ?></b></td>
          <td class="small"><?= h(mb_strimwidth((string)$n['body'], 0, 70, '…')) ?></td>
          <td>
            <?php
            $st = (string)$n['status'];
            $cls = $st === 'sent' ? 'b-ok' : ($st === 'failed' ? 'b-err' : ($st === 'scheduled' ? 'b-info' : 'b-warn'));
            $lbl = ['sent' => 'ارسال‌شده', 'failed' => 'ناموفق', 'scheduled' => 'زمان‌بندی', 'draft' => 'پیش‌نویس'][$st] ?? $st;
            ?>
            <span class="badge <?= $cls ?>"><?= h($lbl) ?></span>
            <?php if (!empty($n['error'])): ?>
              <div class="hint small"><?= h(mb_strimwidth((string)$n['error'], 0, 40, '…')) ?></div>
            <?php endif; ?>
          </td>
          <td class="small mono">
            <?= dt((int)($n['sent_at'] ?: ($n['scheduled_at'] ?: $n['created_at'])), 'Y/m/d H:i') ?>
          </td>
          <td>
            <div class="btn-group">
              <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="news_send">
                <input type="hidden" name="m" value="news">
                <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                <button class="btn ok sm" type="submit">📨 ارسال فوری</button>
              </form>
              <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline" onsubmit="return confirm('حذف شود؟')">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="news_delete">
                <input type="hidden" name="m" value="news">
                <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                <button class="btn danger sm" type="submit">🗑</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>🧠 چطور کار می‌کند؟</h2>
  <div class="hint">
    خبر با وضعیت «زمان‌بندی» ذخیره می‌شود و کرون (هر دقیقه) سررسیده‌ها را به کانال مقصد می‌فرستد.
    کاربران هم با دستور <code class="mono">اخبار</code> در ربات، سه خبر آخر را می‌بینند.
  </div>
</div>
