<?php
/** @var array $backups,$orphans; @var string $dir; @var bool $dir_writable,$auto,$mysqldump;
 *  @var int $every,$keep,$send_chat,$last,$next_in,$report_chat,$report_hour,$report_days,$db_size,$db_tables */
?>
<div class="grid g4 mb">
  <div class="kpi"><div class="i">🗄</div><div class="v"><?= digits_fa(count($backups)) ?></div><div class="l">نسخه موجود</div></div>
  <div class="kpi"><div class="i">⏱</div><div class="v"><?= $last ? digits_fa(round((time() - $last) / 3600)) . ' ساعت' : '—' ?></div><div class="l">از آخرین بکاپ</div></div>
  <div class="kpi"><div class="i">📦</div><div class="v"><?= Backup::humanSize($db_size) ?></div><div class="l">حجم دیتابیس (<?= digits_fa($db_tables) ?> جدول)</div></div>
  <div class="kpi"><div class="i">🔄</div><div class="v"><?= $auto ? 'روشن' : 'خاموش' ?></div><div class="l">بکاپ خودکار هر <?= digits_fa($every) ?> ساعت</div></div>
</div>

<?php if (!$dir_writable): ?>
  <div class="alert err">پوشه بکاپ قابل نوشتن نیست: <code><?= h($dir) ?></code> — دسترسی ۷۷۵ بدهید یا مسیر دیگری در تنظیمات تعیین کنید.</div>
<?php endif; ?>

<div class="grid g2">
  <div class="card">
    <h2>📥 گرفتن بکاپ جدید</h2>
    <p class="small muted">خروجی یک فایل <code>.sql</code> کامل (ساختار + داده) است که با همین پنل یا phpMyAdmin قابل بازیابی است.</p>
    <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="backup_create">
      <button class="btn" type="submit">🗄 ساخت نسخه پشتیبان</button>
    </form>
    <div class="hint mt">
      مسیر ذخیره: <code class="mono"><?= h($dir) ?></code><br>
      روش PDO فعال است (بدون نیاز به exec). mysqldump روی سرور: <?= $mysqldump ? 'موجود ✅' : 'موجود نیست' ?>
    </div>
  </div>

  <div class="card">
    <h2>📤 آپلود فایل بکاپ</h2>
    <form method="post" action="<?= h(url('panel/index.php')) ?>" enctype="multipart/form-data">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="backup_upload">
      <label>فایل .sql</label>
      <input type="file" name="file" accept=".sql" required>
      <label class="check mt"><input type="checkbox" name="do_restore" value="1"> بلافاصله بعد از آپلود، بازیابی کن</label>
      <div class="hint">قبل از بازیابی، یک بکاپ ایمنی از وضعیت فعلی خودکار گرفته می‌شود.</div>
      <div class="btn-group mt"><button class="btn warn" type="submit">آپلود</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2>⚙️ تنظیمات بکاپ و گزارش خودکار</h2></div>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g3">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="backup_settings">
    <div>
      <label class="check"><input type="checkbox" name="backup_auto" value="1" <?= $auto ? 'checked' : '' ?>> بکاپ خودکار (توسط کرون)</label>
      <label>هر چند ساعت</label>
      <input type="number" name="backup_every_hours" min="1" max="720" value="<?= digits_fa($every) ?>" dir="ltr">
      <label>نگه‌داری چند نسخه آخر</label>
      <input type="number" name="backup_keep" min="1" max="200" value="<?= digits_fa($keep) ?>" dir="ltr">
    </div>
    <div>
      <label>ارسال نسخه پشتیبان به چت/کانال بله</label>
      <input type="text" name="backup_send_chat" class="mono" value="<?= $send_chat ?: '' ?>" placeholder="-100123456789">
      <div class="hint">آیدی عددی چت خودتان (یا کانال). بله برای فایل محدودیت حجم دارد (~۲۰MB).</div>
      <label>مسیر دلخواه برای ذخیره بکاپ (اختیاری)</label>
      <input type="text" name="backup_dir" class="mono" value="<?= h((string)Schema::getSetting('backup_dir', '')) ?>" placeholder="/home/user/backups">
      <div class="hint">بهتر است خارج از وب‌روت باشد. باید از سمت PHP قابل نوشتن باشد.</div>
    </div>
    <div>
      <label>گزارش روزانه به چت/کانال</label>
      <input type="text" name="report_daily_chat" class="mono" value="<?= $report_chat ?: '' ?>" placeholder="-100123456789">
      <label>ساعت ارسال گزارش (۰-۲۳)</label>
      <input type="number" name="report_daily_hour" min="0" max="23" value="<?= digits_fa($report_hour) ?>" dir="ltr">
      <label>بازه گزارش (روز)</label>
      <input type="number" name="report_daily_days" min="1" max="30" value="<?= digits_fa($report_days) ?>" dir="ltr">
    </div>
    <div style="align-self:end"><button class="btn" type="submit">💾 ذخیره تنظیمات</button></div>
  </form>
  <div class="hint mt">این تنظیمات در اجرای بعدی <code>cron.php</code> اعمال می‌شوند. برای اجرای فوری: سیستم → «اجرای دستی کرون».</div>
</div>

<div class="card">
  <div class="card-header">
    <h2>📚 نسخه‌های موجود</h2>
    <span class="tag">کرون بعدی: <?= $auto ? ($next_in > 0 ? digits_fa((int)ceil($next_in / 60)) . ' دقیقه دیگر' : 'همین حالا') : 'خاموش' ?></span>
  </div>
  <?php if (!$backups): ?>
    <div class="empty">هنوز بکاپی ساخته نشده است.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>فایل</th><th>حجم</th><th>محتوا</th><th>نوع</th><th>زمان</th><th>وضعیت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($backups as $b): ?>
        <tr>
          <td class="mono small"><?= h((string)$b['filename']) ?></td>
          <td class="small"><?= h((string)$b['size_h']) ?></td>
          <td class="small"><?= digits_fa((int)$b['tables_count']) ?> جدول · <?= digits_fa((int)$b['rows_count']) ?> ردیف</td>
          <td><span class="badge <?= $b['kind'] === 'cron' ? 'b-info' : 'b-mute' ?>"><?= $b['kind'] === 'cron' ? 'خودکار' : 'دستی' ?></span></td>
          <td class="small"><?= h(dt((int)$b['created_at'], 'Y/m/d H:i')) ?></td>
          <td><?= $b['exists'] ? '<span class="badge b-ok">موجود</span>' : '<span class="badge b-err">فایل نیست</span>' ?></td>
          <td>
            <div class="btn-group">
              <?php if ($b['exists']): ?>
                <a class="btn ghost sm" href="<?= h(url('panel/download.php', ['type' => 'backup', 'file' => (string)$b['filename']])) ?>">دانلود</a>
                <form method="post" action="<?= h(url('panel/index.php')) ?>" onsubmit="return confirm('بازیابی از این نسخه؟ داده‌های فعلی با نسخه ایمنی جایگزین می‌شوند.')">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="backup_restore">
                  <input type="hidden" name="filename" value="<?= h((string)$b['filename']) ?>">
                  <button class="btn warn sm" type="submit">بازیابی</button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= h(url('panel/index.php')) ?>" onsubmit="return confirm('حذف فایل بکاپ؟')">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="backup_delete">
                <input type="hidden" name="filename" value="<?= h((string)$b['filename']) ?>">
                <button class="btn danger sm" type="submit">حذف</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>

  <?php if ($orphans): ?>
    <hr>
    <h3>فایل‌های بدون رکورد در جدول</h3>
    <div class="hint">این فایل‌ها در پوشه بکاپ هستند ولی در دیتابیس ثبت نشده‌اند (مثلاً دستی کپی شده‌اند).</div>
    <?php foreach ($orphans as $o): ?>
      <div class="rowline"><span class="mono small"><?= h((string)$o['filename']) ?></span><span class="small"><?= Backup::humanSize((int)$o['size']) ?></span></div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h2>ℹ️ راهنمای بازیابی دستی</h2>
  <div class="small">
    <p>اگر پنل در دسترس نیست:</p>
    <pre class="mono">mysql -u USER -p DBNAME &lt; <?= h($dir) ?>/backup-YYYYmmdd-HHMMSS.sql</pre>
    <p>یا در phpMyAdmin → Import → انتخاب فایل.</p>
    <p class="muted">نکته: بازیابی، جدول‌های موجود را DROP و بازسازی می‌کند. اگر از تنظیمات/کلمات ممنوعه بکاپ می‌خواهید، همین فایل شامل همه است.</p>
  </div>
</div>
