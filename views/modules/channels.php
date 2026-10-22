<?php
/** @var array $channels,$stats,$recent */
?>
<div class="grid g3 mb">
  <div class="kpi"><div class="i">📣</div><div class="v"><?= digits_fa((int)$stats['total']) ?></div><div class="l">کانال/گروه ثبت‌شده</div></div>
  <div class="kpi"><div class="i">✅</div><div class="v"><?= digits_fa((int)$stats['active']) ?></div><div class="l">فعال</div></div>
  <div class="kpi"><div class="i">📤</div><div class="v"><?= digits_fa((int)$stats['posts']) ?></div><div class="l">پست ارسال‌شده</div></div>
</div>

<div class="grid g2">
  <div class="card">
    <h2>➕ افزودن کانال مقصد</h2>
    <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="channel_add">
      <input type="hidden" name="m" value="channels">
      <div>
        <label>آیدی عددی یا @یوزرنیم کانال</label>
        <input type="text" name="chat_id" class="mono" placeholder="-1001234567890 یا @my_channel" required>
        <div class="hint">ربات باید <b>ادمین کانال</b> باشد تا بتواند پست بگذارد و عضویت را بررسی کند.</div>
      </div>
      <div>
        <label>عنوان نمایشی</label>
        <input type="text" name="title" placeholder="کانال اخبار فروشگاه">
      </div>
      <div>
        <label>امضای پایان هر پست (اختیاری)</label>
        <input type="text" name="signature" placeholder="🛒 @shop_channel">
      </div>
      <div>
        <label>نوع</label>
        <select name="kind">
          <option value="channel">کانال</option>
          <option value="group">گروه</option>
        </select>
      </div>
      <div><button class="btn" type="submit">💾 ثبت کانال</button></div>
    </form>
  </div>

  <div class="card">
    <h2>📤 ارسال پست به کانال‌ها</h2>
    <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="channel_publish">
      <input type="hidden" name="m" value="channels">
      <div>
        <label>مقصد</label>
        <select name="channel_id">
          <option value="0">همه کانال‌های فعال</option>
          <?php foreach ($channels as $c): ?>
            <?php if ((int)$c['is_active'] === 1): ?>
              <option value="<?= (int)$c['id'] ?>"><?= h((string)$c['title']) ?></option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>متن پست (HTML مجاز)</label>
        <textarea name="text" rows="5" placeholder="متن پست…" required></textarea>
      </div>
      <label class="check"><input type="checkbox" name="queue" value="1"> ارسال با صف و رعایت محدودیت نرخ</label>
      <div><button class="btn ok" type="submit">📨 ارسال</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2>📋 کانال‌ها</h2></div>
  <?php if (!$channels): ?>
    <div class="empty">کانالی ثبت نشده است.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>عنوان</th><th>آیدی</th><th>یوزرنیم</th><th>نوع</th><th>پست‌ها</th><th>وضعیت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($channels as $c): ?>
        <tr>
          <td class="small"><?= digits_fa((int)$c['id']) ?></td>
          <td><b><?= h((string)$c['title']) ?></b></td>
          <td class="mono small"><?= h((string)$c['chat_id']) ?></td>
          <td class="mono small"><?= $c['username'] ? '@' . h((string)$c['username']) : '—' ?></td>
          <td class="small"><?= $c['kind'] === 'group' ? 'گروه' : 'کانال' ?></td>
          <td class="small"><?= digits_fa((int)$c['post_count']) ?></td>
          <td>
            <?php if ((int)$c['is_active'] === 1): ?>
              <span class="badge b-ok">فعال</span>
            <?php else: ?>
              <span class="badge b-warn">غیرفعال</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="btn-group">
              <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="channel_toggle">
                <input type="hidden" name="m" value="channels">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <input type="hidden" name="on" value="<?= (int)$c['is_active'] === 1 ? '0' : '1' ?>">
                <button class="btn ghost sm" type="submit"><?= (int)$c['is_active'] === 1 ? '⏸ خاموش' : '▶️ روشن' ?></button>
              </form>
              <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline" onsubmit="return confirm('حذف شود؟')">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="channel_delete">
                <input type="hidden" name="m" value="channels">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
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

<?php if (!empty($recent)): ?>
<div class="card">
  <h2>🗞 آخرین خبرهای ماژول اخبار</h2>
  <div class="hint">این کارت‌ها از ماژول «اخبار کانال» می‌آیند؛ برای مدیریت کامل به صفحه آن ماژول برو.</div>
  <div class="table-wrap mt"><table>
    <thead><tr><th>#</th><th>عنوان</th><th>وضعیت</th><th>زمان</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $n): ?>
      <tr>
        <td class="small"><?= digits_fa((int)$n['id']) ?></td>
        <td><?= h((string)(($n['title'] ?: mb_strimwidth((string)$n['body'], 0, 40, '…')))) ?></td>
        <td class="small"><?= h((string)$n['status']) ?></td>
        <td class="small mono"><?= dt((int)$n['created_at'], 'Y/m/d H:i') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
