<?php /** @var array $flash */ ?>
<p class="center small muted" style="margin-bottom:10px">
  اگر به بله دسترسی نداری یا رمزت را فراموش کرده‌ای، با <b>کد بازیابی</b> وارد شو.
</p>

<div class="card tight" style="background:#0e1a2c;border:1px solid #1e2d47;margin-bottom:12px">
  <h3 style="margin:2px 0 8px;font-size:13.5px">۱) رمز را دارم، ولی کد بله به دستم نمی‌رسد</h3>
  <form method="post" action="<?= h(url('panel/recover.php')) ?>" autocomplete="off">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="mode" value="no_bale">
    <label>نام کاربری</label>
    <input type="text" name="username" class="mono" required placeholder="admin">
    <label>رمز عبور</label>
    <input type="password" name="password" required>
    <label>کد بازیابی</label>
    <input type="text" name="code" class="mono" required placeholder="BGM-XXXXX-XXXXX" dir="ltr" style="text-align:left">
    <div class="mt"><button class="btn" type="submit" style="width:100%">ورود با کد بازیابی</button></div>
  </form>
</div>

<div class="card tight" style="background:#0e1a2c;border:1px solid #1e2d47;margin-bottom:12px">
  <h3 style="margin:2px 0 8px;font-size:13.5px">۲) رمز را فراموش کرده‌ام</h3>
  <form method="post" action="<?= h(url('panel/recover.php')) ?>" autocomplete="off">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="mode" value="forgot_password">
    <label>نام کاربری</label>
    <input type="text" name="username" class="mono" required>
    <label>کد بازیابی</label>
    <input type="text" name="code" class="mono" required placeholder="BGM-XXXXX-XXXXX" dir="ltr" style="text-align:left">
    <label>رمز جدید (حداقل ۸ کاراکتر)</label>
    <input type="password" name="new_password" required minlength="8">
    <div class="mt"><button class="btn warn" type="submit" style="width:100%">تغییر رمز و ورود</button></div>
  </form>
</div>

<div class="hint center">
  کد بازیابی فقط یک‌بار قابل استفاده است و هر تلاش در «لاگ امنیت» ثبت می‌شود.<br>
  کد نداری؟ از ادمین ارشد بخواه در پنل → «کدهای بازیابی» برایت کد جدید بسازد،
  یا در چت خصوصی ربات بنویس <code>ورود</code> تا لینک یک‌بارمصرف بگیری.
</div>

<div class="center mt">
  <a class="btn ghost sm" href="<?= h(url('panel/login.php')) ?>">بازگشت به ورود</a>
</div>
