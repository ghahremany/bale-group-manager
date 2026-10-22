<?php
/** @var array $codes,$logs,$summary,$newCodes; @var int $remaining,$adminId; @var bool $super; @var string $purpose */
$purposes = [
    '' => 'همه رخدادها',
    'login_otp' => 'کد ورود (OTP)',
    'magic_link' => 'لینک جادویی',
    'recovery_generate' => 'تولید کد بازیابی',
    'recovery_login' => 'ورود با کد بازیابی',
    'recovery_revoke' => 'ابطال کدها',
    'password_reset' => 'ریست رمز عبور',
];
$statusLabel = static function (string $s): string {
    return [
        'sent' => 'ارسال شد', 'success' => 'موفق', 'wrong_code' => 'کد اشتباه',
        'wrong_password' => 'رمز اشتباه', 'send_failed' => 'ارسال ناموفق',
        'reused' => 'استفاده مجدد از کد معتبر', 'rate_limited' => 'محدودیت نرخ',
        'invalid_format' => 'قالب نامعتبر', 'mismatch' => 'عدم تطابق',
        'generated:8' => '۸ کد تولید شد', 'revoked:8' => 'ابطال شد',
    ][$s] ?? $s;
};
$cls = static function (string $s): string {
    if (in_array($s, ['success', 'sent'], true) || str_starts_with($s, 'generated')) {
        return 'b-ok';
    }
    if (in_array($s, ['wrong_code', 'wrong_password', 'send_failed', 'mismatch'], true)) {
        return 'b-err';
    }
    return 'b-warn';
};
?>
<div class="grid g4 mb">
  <div class="kpi"><div class="i">🔑</div><div class="v"><?= digits_fa($remaining) ?></div><div class="l">کد بازیابی باقی‌مانده</div></div>
  <div class="kpi"><div class="i">📲</div><div class="v"><?= digits_fa((int)($summary['login_otp/sent'] ?? 0) + (int)($summary['login_otp/reused'] ?? 0)) ?></div><div class="l">کد ورود ارسال‌شده (۷ روز)</div></div>
  <div class="kpi"><div class="i">✅</div><div class="v"><?= digits_fa((int)($summary['login_otp/success'] ?? 0) + (int)($summary['magic_link/success'] ?? 0)) ?></div><div class="l">ورود موفق (۷ روز)</div></div>
  <div class="kpi"><div class="i">⚠️</div><div class="v"><?= digits_fa((int)($summary['login_otp/wrong_code'] ?? 0) + (int)($summary['recovery_login/mismatch'] ?? 0) + (int)($summary['recovery_login/wrong_password'] ?? 0)) ?></div><div class="l">تلاش ناموفق (۷ روز)</div></div>
</div>

<?php if ($newCodes): ?>
  <div class="card" style="border-color:#22c55e">
    <h2>✅ کدهای بازیابی جدید — همین حالا ذخیره کن</h2>
    <p class="small warn-text" style="color:#f59e0b">این کدها فقط همین یک‌بار نمایش داده می‌شوند و در دیتابیس فقط هش می‌شوند؛ امکان بازیابی دوباره وجود ندارد.</p>
    <div class="grid g2">
      <?php foreach ($newCodes as $c): ?>
        <div class="mono" style="background:#0a1424;border:1px dashed #2f5d8f;border-radius:8px;padding:8px 10px;text-align:center;letter-spacing:1px" dir="ltr"><?= h($c) ?></div>
      <?php endforeach; ?>
    </div>
    <div class="btn-group mt">
      <a class="btn ghost sm" download="bale-group-manager-recovery-codes.txt"
         href="data:text/plain;charset=utf-8,<?= rawurlencode("کدهای بازیابی پنل — " . date('Y-m-d H:i') . "\n\n" . implode("\n", $newCodes) . "\n\nهر کد فقط یک‌بار قابل استفاده است.\n") ?>">دانلود فایل متنی</a>
      <button class="btn ghost sm" type="button" onclick="window.print()">چاپ</button>
    </div>
  </div>
<?php endif; ?>

<div class="grid g2">
  <div class="card">
    <h2>🔑 کدهای بازیابی من</h2>
    <p class="small muted">اگر به بله دسترسی نداشتی یا رمزت را فراموش کردی، با این کدها وارد می‌شوی: <code class="mono">/panel/recover.php</code></p>
    <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline"
          onsubmit="return confirm('کدهای استفاده‌نشده فعلی باطل و ۸ کد جدید ساخته می‌شود. ادامه؟')">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="recovery_generate">
      <label style="margin:0">تعداد</label>
      <input type="number" name="count" value="8" min="1" max="20" style="width:80px" dir="ltr">
      <button class="btn" type="submit">تولید کد جدید</button>
    </form>
    <?php if ($codes): ?>
      <div class="table-wrap mt">
        <table>
          <thead><tr><th>شناسه</th><th>۴ رقم آخر</th><th>ساخت</th><th>وضعیت</th></tr></thead>
          <tbody>
          <?php foreach ($codes as $c): ?>
            <tr>
              <td class="small"><?= digits_fa((int)$c['id']) ?></td>
              <td class="mono"><span dir="ltr">••••-<?= h((string)$c['hint']) ?></span></td>
              <td class="small"><?= h(dt((int)$c['created_at'], 'Y/m/d H:i')) ?></td>
              <td><?= $c['used_at'] ? '<span class="badge b-mute">استفاده‌شده</span>' : '<span class="badge b-ok">فعال</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty mt">هنوز کد بازیابی نساخته‌ای. حداقل یک مجموعه ۸ عددی بساز.</div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>📲 راه‌های ورود و بازیابی</h2>
    <div class="rowline"><span>۱. رمز عبور + کد یکبارمصرف در چت خصوصی بله</span><span class="badge b-ok">فعال</span></div>
    <div class="rowline"><span>۲. لینک یکبارمصرف با نوشتن <code>ورود</code> در چت ربات</span><span class="badge b-ok">فعال</span></div>
    <div class="rowline"><span>۳. کد بازیابی (بدون نیاز به بله)</span><span class="badge <?= $remaining > 0 ? 'b-ok' : 'b-err' ?>"><?= $remaining > 0 ? 'آماده' : 'کد نداری' ?></span></div>
    <div class="rowline"><span>۴. کانال پشتیبان OTP (اگر آیدی بله ثبت نشده باشد)</span>
      <span class="badge <?= (int)Schema::getSetting('otp_chat_id', '0') ? 'b-ok' : 'b-mute' ?>">
        <?= (int)Schema::getSetting('otp_chat_id', '0') ? 'تنظیم شده' : 'تنظیم نشده' ?>
      </span>
    </div>
    <hr>
    <div class="small muted">
      • کد ۶ رقمی: ۳ دقیقه اعتبار، ۵ تلاش، ارسال مجدد با فاصله ۳۰ ثانیه، حداکثر ۱۰ کد در ساعت.<br>
      • کد بازیابی: یک‌بار مصرف، ۱۰ کاراکتر تصادفی امن، ذخیره‌شده به‌صورت HMAC-SHA256.<br>
      • بعد از ۵ تلاش ناموفق بازیابی، حساب موقتاً ۱۵ دقیقه بسته می‌شود.
    </div>
    <form method="post" action="<?= h(url('panel/index.php')) ?>" class="mt"
          onsubmit="return confirm('همه کدهای بازیابی استفاده‌نشده باطل شوند؟')">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="recovery_revoke">
      <button class="btn danger sm" type="submit">ابطال همه کدهای فعال</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>🧾 لاگ امنیت ورود (OTP)</h2>
    <form method="get" class="inline">
      <input type="hidden" name="p" value="recovery">
      <select name="purpose" onchange="this.form.submit()">
        <?php foreach ($purposes as $k => $l): ?>
          <option value="<?= h($k) ?>" <?= $purpose === $k ? 'selected' : '' ?>><?= h($l) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <?php if (!$logs): ?>
    <div class="empty">رخدادی ثبت نشده است.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead>
        <tr>
          <th>زمان</th>
          <?php if ($super): ?><th>ادمین</th><?php endif; ?>
          <th>نوع</th><th>کانال</th><th>مقصد</th><th>وضعیت</th><th>IP</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td class="small"><?= h(dt((int)$l['created_at'], 'Y/m/d H:i:s')) ?></td>
          <?php if ($super): ?><td class="small"><?= h((string)($l['username'] ?? '—')) ?></td><?php endif; ?>
          <td class="small"><?= h($purposes[$l['purpose']] ?? (string)$l['purpose']) ?></td>
          <td class="small"><?= h((string)$l['channel']) ?></td>
          <td class="mono small"><?= h((string)($l['destination'] ?? '—')) ?></td>
          <td><span class="badge <?= $cls((string)$l['status']) ?>"><?= h($statusLabel((string)$l['status'])) ?></span></td>
          <td class="mono small"><?= h((string)($l['ip'] ?? '—')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
  <div class="hint mt">لاگ‌ها بعد از ۹۰ روز خودکار پاک می‌شوند (کرون).</div>
</div>
