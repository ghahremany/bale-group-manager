<?php
/**
 * @var array $batches,$audiences,$preview,$rows,$batch,$queueStats
 * @var int $rate,$pendingTotal,$batchId,$groupId
 * @var bool $super
 */
$statusMap = [
    'queued'   => ['در صف', 'b-info'],
    'sending'  => ['در حال ارسال', 'b-warn'],
    'done'     => ['تمام‌شده', 'b-ok'],
    'canceled' => ['متوقف‌شده', 'b-err'],
    'paused'   => ['مکث', 'b-mute'],
];
?>
<div class="grid g4 mb">
  <div class="kpi"><div class="i">📤</div><div class="v"><?= digits_fa($pendingTotal) ?></div><div class="l">پیام در صف</div></div>
  <div class="kpi"><div class="i">⚡</div><div class="v"><?= digits_fa($rate) ?>/ث</div><div class="l">نرخ ارسال تنظیم‌شده</div></div>
  <div class="kpi"><div class="i">📨</div><div class="v"><?= digits_fa((int)($queueStats['sent'] ?? 0)) ?></div><div class="l">ارسال‌شده (۲۴ ساعت)</div></div>
  <div class="kpi"><div class="i">⚠️</div><div class="v"><?= digits_fa((int)($queueStats['failed'] ?? 0)) ?></div><div class="l">ناموفق (۲۴ ساعت)</div></div>
</div>

<div class="grid g2">
  <div class="card">
    <div class="card-header"><h2>✍️ ارسال همگانی جدید</h2></div>
    <?php if (!$super): ?>
      <div class="alert err">ارسال همگانی فقط برای مدیر ارشد است.</div>
    <?php else: ?>
      <form method="post" action="<?= h(url('panel/index.php')) ?>">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="a" value="broadcast_enqueue">
        <label>عنوان (برای خودت، در لیست نشان داده می‌شود)</label>
        <input type="text" name="title" placeholder="مثلاً: اطلاع‌رسانی جشنواره مهر">

        <label>متن پیام (HTML مجاز: <code class="mono">&lt;b&gt; &lt;i&gt; &lt;a href&gt; &lt;code&gt;</code>)</label>
        <textarea name="text" rows="6" required placeholder="سلام {group} عزیز...&#10;&#10;متغیرها: {group} نام گروه · {chat_id} آیدی · {panel} لینک پنل"></textarea>

        <label>مخاطب</label>
        <select name="audience">
          <?php foreach ($audiences as $k => $v): ?>
            <option value="<?= h($k) ?>" <?= $k === 'groups' ? 'selected' : '' ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint">تعداد فعلی مخاطبان: <b><?= digits_fa($preview['count']) ?></b> — زمان تقریبی: <?= digits_fa((int)ceil($preview['count'] / max(1, $rate))) ?> ثانیه</div>

        <div class="grid g2" style="gap:10px">
          <div>
            <label>نرخ ارسال (پیام بر ثانیه)</label>
            <input type="number" name="rate" value="<?= digits_fa($rate) ?>" min="1" max="25" dir="ltr">
            <div class="hint">سقف ایمن بله ~۳۰/ثانیه؛ پیش‌فرض ۱۵ برای جلوگیری از 429.</div>
          </div>
          <div>
            <label>زمان‌بندی</label>
            <input type="datetime-local" name="scheduled_at" dir="ltr">
            <div class="hint">خالی = همین حالا (کرون در دقیقه بعد ارسال می‌کند).</div>
          </div>
        </div>

        <div class="btn-group mt">
          <button class="btn" type="submit">🕒 افزودن به صف</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>📊 وضعیت صف</h2>
    <div class="rowline"><span>پیام‌های در انتظار ارسال</span><b><?= digits_fa($pendingTotal) ?></b></div>
    <div class="rowline"><span>دسته‌های در جریان</span><b><?= digits_fa(count(array_filter($batches, static fn($b) => in_array($b['status'], ['queued','sending'], true)))) ?></b></div>
    <div class="rowline"><span>اجرای خودکار</span><span class="badge b-ok">کرون هر دقیقه</span></div>
    <hr>
    <h3>⏱ اجرای فوری صف</h3>
    <p class="small muted">بدون انتظار برای کرون، تا ۲۰ ثانیه از صف ارسال می‌کند.</p>
    <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="broadcast_drain">
      <button class="btn ghost" type="submit" <?= !$super ? 'disabled' : '' ?>>▶️ اجرای فوری</button>
    </form>
    <hr>
    <div class="small muted">
      • هر پیام یک ردیف در `broadcast_queue` است؛ اگر سرور ری‌استارت شود کار از همان‌جا ادامه می‌یابد.<br>
      • خطاها ۲ بار با فاصله دوباره تلاش می‌شوند، سپس failed.<br>
      • نرخ ارسال با فاصله‌گذاری میکروثانیه‌ای کنترل می‌شود (نه با sleep درشت).
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2>📚 دسته‌های ارسال</h2></div>
  <?php if (!$batches): ?>
    <div class="empty">هنوز ارسال همگانی‌ای ثبت نشده است.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>عنوان</th><th>مخاطب</th><th>پیشرفت</th><th>وضعیت</th><th>زمان</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($batches as $b): ?>
        <?php [$stLabel, $stCls] = $statusMap[$b['status']] ?? [(string)$b['status'], 'b-mute']; ?>
        <tr>
          <td class="small"><?= digits_fa((int)$b['id']) ?></td>
          <td class="small"><?= h(mb_strimwidth((string)($b['title'] ?? ''), 0, 42, '…')) ?></td>
          <td class="small"><?= h($audiences[$b['audience']] ?? (string)$b['audience']) ?></td>
          <td style="min-width:150px">
            <div class="small"><?= digits_fa($b['progress']['done']) ?> / <?= digits_fa((int)$b['target_count']) ?>
              <?php if ((int)$b['failed_count'] > 0): ?>
                <span class="badge b-err"><?= digits_fa((int)$b['failed_count']) ?> خطا</span>
              <?php endif; ?>
            </div>
            <div style="height:7px;background:#16233a;border-radius:6px;overflow:hidden;margin-top:4px">
              <div style="height:100%;width:<?= (int)$b['progress']['percent'] ?>%;background:linear-gradient(90deg,#2563eb,#22c55e)"></div>
            </div>
          </td>
          <td><span class="badge <?= $stCls ?>"><?= h($stLabel) ?></span></td>
          <td class="small">
            <?= h(dt((int)$b['created_at'], 'Y/m/d H:i')) ?>
            <?php if ((int)$b['scheduled_at'] > (int)$b['created_at'] + 30): ?>
              <div class="muted">زمان‌بندی: <?= h(dt((int)$b['scheduled_at'], 'Y/m/d H:i')) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <div class="btn-group">
              <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'broadcast', 'batch' => (int)$b['id']])) ?>">جزئیات</a>
              <?php if ($super && in_array($b['status'], ['queued','sending'], true)): ?>
                <form method="post" action="<?= h(url('panel/index.php')) ?>" onsubmit="return confirm('توقف این دسته؟')">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="broadcast_cancel">
                  <input type="hidden" name="batch" value="<?= (int)$b['id'] ?>">
                  <button class="btn danger sm" type="submit">توقف</button>
                </form>
              <?php endif; ?>
              <?php if ($super && $b['status'] === 'canceled'): ?>
                <form method="post" action="<?= h(url('panel/index.php')) ?>">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="broadcast_resume">
                  <input type="hidden" name="batch" value="<?= (int)$b['id'] ?>">
                  <button class="btn ok sm" type="submit">ادامه</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<?php if ($batch): ?>
  <div class="card">
    <div class="card-header">
      <h2>🔎 جزئیات دسته #<?= digits_fa((int)$batch['id']) ?></h2>
      <div class="btn-group">
        <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'broadcast', 'batch' => (int)$batch['id'], 'status' => 'pending'])) ?>">در انتظار</a>
        <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'broadcast', 'batch' => (int)$batch['id'], 'status' => 'sent'])) ?>">ارسال‌شده</a>
        <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'broadcast', 'batch' => (int)$batch['id'], 'status' => 'failed'])) ?>">ناموفق</a>
        <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'broadcast'])) ?>">بستن</a>
      </div>
    </div>
    <pre style="max-height:140px"><?= h((string)$batch['text']) ?></pre>
    <?php if (!$rows): ?>
      <div class="empty">ردیفی با این فیلتر نیست.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>چت</th><th>وضعیت</th><th>تلاش</th><th>خطا</th><th>زمان</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="mono small"><?= h((string)$r['chat_id']) ?></td>
            <td><span class="badge <?= $r['status'] === 'sent' ? 'b-ok' : ($r['status'] === 'failed' ? 'b-err' : 'b-warn') ?>">
              <?= ['sent' => 'ارسال شد', 'failed' => 'ناموفق', 'pending' => 'در انتظار'][$r['status']] ?? h($r['status']) ?>
            </span></td>
            <td class="small"><?= digits_fa((int)$r['attempts']) ?></td>
            <td class="small muted"><?= h(mb_strimwidth((string)($r['error'] ?? ''), 0, 60, '…')) ?></td>
            <td class="small"><?= h(dt((int)($r['sent_at'] ?: $r['created_at']), 'Y/m/d H:i')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
<?php endif; ?>
