<?php
/**
 * @var array $links,$admins,$pairs,$events,$low,$settings,$stats
 */
?>
<div class="grid g4 mb">
  <div class="kpi"><div class="i">🤖</div><div class="v"><?= digits_fa((int)$stats['links']) ?></div><div class="l">چت متصل به پنل</div></div>
  <div class="kpi"><div class="i">🚨</div><div class="v"><?= digits_fa((int)$stats['critical24']) ?></div><div class="l">رویداد بحرانی (۲۴س)</div></div>
  <div class="kpi"><div class="i">📦</div><div class="v"><?= digits_fa((int)$stats['low']) ?></div><div class="l">کالای کم‌موجود</div></div>
  <div class="kpi"><div class="i">🔐</div><div class="v"><?= digits_fa((int)$stats['otps']) ?></div><div class="l">کد تایید ۲۴ساعته</div></div>
</div>

<div class="card">
  <h2>🔗 اتصال چت بله به حساب پنل</h2>
  <div class="hint mb">
    کد اتصال یک‌بارمصرف بساز و در <b>چت خصوصی ربات</b> بفرست: <code class="mono">اتصال CODE</code>.
    بعد از آن هر بار با دستور <code class="mono">تایید</code> یک کد شش‌رقمی در بله دریافت می‌شود و دسترسی
    <?= digits_fa((int)round((int)$settings['sess_ttl'] / 60)) ?> دقیقه باز می‌ماند.
  </div>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline mb">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="adminbot_pair">
    <input type="hidden" name="m" value="adminbot">
    <select name="admin_id">
      <?php foreach ($admins as $a): ?>
        <option value="<?= (int)$a['id'] ?>"><?= h((string)$a['username']) ?> (<?= h((string)$a['role']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <button class="btn ok" type="submit">🎟 ساخت کد اتصال</button>
  </form>

  <?php if ($pairs): ?>
    <div class="table-wrap"><table>
      <thead><tr><th>کد</th><th>ادمین</th><th>انقضا</th></tr></thead>
      <tbody>
      <?php foreach ($pairs as $p): ?>
        <tr>
          <td class="mono"><b><?= h((string)$p['code']) ?></b></td>
          <td class="small"><?= h((string)$p['username']) ?></td>
          <td class="small mono"><?= dt((int)$p['expires_at'], 'H:i') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header"><h2>📋 چت‌های متصل</h2></div>
  <?php if (!$links): ?>
    <div class="empty">هنوز هیچ چتی به پنل وصل نشده است.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>ادمین</th><th>آیدی بله</th><th>نشست</th><th>هشدار</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($links as $l): ?>
        <tr>
          <td class="small"><?= digits_fa((int)$l['id']) ?></td>
          <td class="small"><b><?= h((string)$l['username']) ?></b> <span class="badge b-info"><?= h((string)$l['role']) ?></span></td>
          <td class="small mono" dir="ltr"><?= h((string)$l['bale_user_id']) ?></td>
          <td class="small">
            <?php if ((int)$l['otp_ok_until'] > time()): ?>
              <span class="badge b-ok">باز تا <?= dt((int)$l['otp_ok_until'], 'H:i') ?></span>
            <?php else: ?>
              <span class="badge b-mute">بسته</span>
            <?php endif; ?>
          </td>
          <td>
            <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="a" value="adminbot_alert_opt">
              <input type="hidden" name="m" value="adminbot">
              <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
              <input type="hidden" name="on" value="<?= (int)$l['alert_opt'] ? '0' : '1' ?>">
              <button class="btn ghost sm" type="submit">
                <?= (int)$l['alert_opt'] ? '🔔 روشن' : '🔕 خاموش' ?>
              </button>
            </form>
          </td>
          <td>
            <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline" onsubmit="return confirm('اتصال حذف شود؟')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="a" value="adminbot_unlink">
              <input type="hidden" name="m" value="adminbot">
              <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
              <button class="btn danger sm" type="submit">🗑 قطع اتصال</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>⚙️ تنظیمات هشدار و کد تایید</h2>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g2">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="adminbot_settings">
    <input type="hidden" name="m" value="adminbot">
    <div>
      <label>حداقل شدت برای ارسال هشدار</label>
      <select name="alert_level">
        <?php foreach (['info' => 'همه رویدادها', 'warn' => 'هشدار و بحرانی', 'critical' => 'فقط بحرانی'] as $k => $lbl): ?>
          <option value="<?= $k ?>" <?= $settings['level'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
      <label>فاصله خنک‌شدن هشدار (ثانیه)</label>
      <input type="number" name="alert_cooldown" value="<?= (int)$settings['cooldown'] ?>" min="0" dir="ltr">
      <div class="hint">برای جلوگیری از بمباران پیام؛ رویدادهای تکراری فقط ثبت می‌شوند.</div>
      <label>چت پشتیبان هشدار (آیدی عددی — اختیاری)</label>
      <input type="text" name="alert_chat" class="mono" dir="ltr" value="<?= (int)$settings['alert_chat'] ?: '' ?>" placeholder="123456789">
    </div>
    <div>
      <label class="check"><input type="checkbox" name="stock_enabled" value="1" <?= $settings['stock_on'] ? 'checked' : '' ?>> هشدار خودکار کمبود موجودی</label>
      <label>فاصله تکرار هشدار موجودی (ثانیه)</label>
      <input type="number" name="stock_cooldown" value="<?= (int)$settings['stock_cool'] ?>" min="0" dir="ltr">
      <label>اعتبار کد تایید (ثانیه)</label>
      <input type="number" name="otp_ttl" value="<?= (int)$settings['otp_ttl'] ?>" min="60" dir="ltr">
      <label>طول نشست مدیریتی پس از تایید (ثانیه)</label>
      <input type="number" name="session_ttl" value="<?= (int)$settings['sess_ttl'] ?>" min="120" dir="ltr">
      <button class="btn mt" type="submit">💾 ذخیره تنظیمات</button>
    </div>
  </form>
  <div class="btn-group mt">
    <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="adminbot_test">
      <input type="hidden" name="m" value="adminbot">
      <button class="btn ghost sm" type="submit">🧪 ارسال پیام آزمایشی</button>
    </form>
    <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="adminbot_stock_now">
      <input type="hidden" name="m" value="adminbot">
      <button class="btn ghost sm" type="submit">📦 بررسی فوری موجودی</button>
    </form>
    <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline" onsubmit="return confirm('رویدادهای قدیمی پاک شوند؟')">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="adminbot_clear_events">
      <input type="hidden" name="m" value="adminbot">
      <button class="btn ghost sm" type="submit">🧹 پاک‌سازی رویدادهای قدیمی</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2>🚨 رویدادهای امنیتی اخیر</h2></div>
  <?php if (!$events): ?>
    <div class="empty">رویدادی ثبت نشده است. ✅</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>زمان</th><th>نوع</th><th>شدت</th><th>IP</th><th>جزئیات</th><th>هشدار</th></tr></thead>
      <tbody>
      <?php foreach ($events as $e): ?>
        <?php $cls = $e['severity'] === 'critical' ? 'b-err' : ($e['severity'] === 'warn' ? 'b-warn' : 'b-info'); ?>
        <tr>
          <td class="small"><?= digits_fa((int)$e['id']) ?></td>
          <td class="small mono"><?= dt((int)$e['created_at'], 'm/d H:i:s') ?></td>
          <td class="small"><?= h(\Modules\AdminBot::eventLabel((string)$e['etype'])) ?>
            <div class="mono small muted"><?= h((string)$e['etype']) ?></div>
          </td>
          <td><span class="badge <?= $cls ?>"><?= h(\Events::severityFa((string)$e['severity'])) ?></span></td>
          <td class="small mono" dir="ltr"><?= h((string)($e['ip'] ?? '')) ?></td>
          <td class="small"><?= h(str_limit((string)($e['detail'] ?? ''), 70)) ?></td>
          <td class="small"><?= (int)$e['notified'] ? '✅' : '⏳' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header"><h2>📦 کالاهای کم‌موجود</h2></div>
  <?php if (!$low): ?>
    <div class="empty">همه کالاها موجودی کافی دارند. ✅</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>کد</th><th>کالا</th><th>قابل فروش</th><th>حد هشدار</th><th>وضعیت</th></tr></thead>
      <tbody>
      <?php foreach ($low as $p): ?>
        <?php $out = (int)$p['avail'] <= 0; ?>
        <tr>
          <td class="small mono"><?= h((string)$p['code']) ?></td>
          <td class="small"><?= h(str_limit((string)$p['title'], 50)) ?></td>
          <td class="small"><b><?= digits_fa((int)$p['avail']) ?></b> <?= h((string)$p['unit']) ?></td>
          <td class="small"><?= digits_fa((int)$p['min_stock']) ?></td>
          <td><span class="badge <?= $out ? 'b-err' : 'b-warn' ?>"><?= $out ? 'ناموجود' : 'کم‌موجود' ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>🤖 دستورهای ربات (فقط چت خصوصی + کد تایید)</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>دستور</th><th>کار</th><th>نیاز به تایید</th></tr></thead>
    <tbody>
      <tr><td class="mono small">اتصال CODE</td><td class="small">اتصال این چت به حساب پنل</td><td class="small">—</td></tr>
      <tr><td class="mono small">تایید</td><td class="small">دریافت کد شش‌رقمی در بله</td><td class="small">—</td></tr>
      <tr><td class="mono small">تایید 123456</td><td class="small">باز کردن نشست مدیریتی</td><td class="small">کد</td></tr>
      <tr><td class="mono small">گزارش [روز]</td><td class="small">گزارش کاربران، فروش، سفارش، صف، امنیت</td><td class="small">✅</td></tr>
      <tr><td class="mono small">امنیت [تعداد]</td><td class="small">آخرین رویدادهای امنیتی</td><td class="small">✅</td></tr>
      <tr><td class="mono small">انبار</td><td class="small">خلاصه انبار و کالاهای کم‌موجود</td><td class="small">✅</td></tr>
      <tr><td class="mono small">موجودی CODE</td><td class="small">موجودی یک کالا</td><td class="small">✅</td></tr>
      <tr><td class="mono small">افزودن_کالا عنوان | قیمت | موجودی | حد</td><td class="small">ثبت کالای تازه</td><td class="small">✅</td></tr>
      <tr><td class="mono small">ویرایش_موجودی CODE 10</td><td class="small">تنظیم موجودی روی عدد</td><td class="small">✅</td></tr>
      <tr><td class="mono small">حذف_کالا CODE</td><td class="small">بایگانی کالا و برداشتن از فروشگاه</td><td class="small">✅</td></tr>
      <tr><td class="mono small">ورود_پنل</td><td class="small">لینک ورود یک‌بارمصرف به پنل</td><td class="small">✅</td></tr>
      <tr><td class="mono small">قفل</td><td class="small">بستن نشست مدیریتی</td><td class="small">—</td></tr>
    </tbody>
  </table></div>
</div>
