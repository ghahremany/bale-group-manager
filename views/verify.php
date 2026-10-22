<?php /** @var array $admin_pending */ ?>
<div class="alert info">
  کد ۶ رقمی به <?= !empty($admin_pending['bale_user_id']) ? 'چت خصوصی بله شما' : 'کانال امن تنظیم‌شده' ?> ارسال شد.
  <div class="small muted mt">حساب: <b><?= h((string)($admin_pending['username'] ?? '')) ?></b></div>
</div>
<form method="post" action="<?= h(url('panel/login.php')) ?>" autocomplete="off">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="step" value="otp">
  <label>رمز یکبارمصرف</label>
  <input class="otp-input" type="text" name="code" inputmode="numeric" pattern="[0-9]{4,8}" maxlength="8" required autofocus placeholder="——————">
  <div class="mt">
    <button class="btn" type="submit" style="width:100%">ورود به پنل</button>
  </div>
  <div class="mt small muted center">
    کد را دریافت نکردی؟
  </div>
</form>
<form method="post" action="<?= h(url('panel/login.php')) ?>" class="mt center">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="step" value="resend">
  <button class="btn ghost sm" type="submit">ارسال دوباره کد</button>
  <a class="btn ghost sm" href="<?= h(url('panel/login.php')) ?>">شروع مجدد</a>
  <a class="btn ghost sm" href="<?= h(url('panel/recover.php')) ?>">کد بازیابی</a>
</form>
