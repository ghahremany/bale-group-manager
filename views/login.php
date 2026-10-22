<form method="post" action="<?= h(url('panel/login.php')) ?>" autocomplete="off">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="step" value="password">
  <label>نام کاربری</label>
  <input type="text" name="username" class="mono" required autofocus placeholder="admin">
  <label>رمز عبور</label>
  <input type="password" name="password" required placeholder="••••••••">
  <div class="mt">
    <button class="btn" type="submit" style="width:100%">دریافت رمز یکبارمصرف</button>
  </div>
  <div class="hint mt center">پس از تأیید رمز، یک کد ۶ رقمی به بله شما ارسال می‌شود.</div>
  <div class="center mt">
    <a class="small" href="<?= h(url('panel/recover.php')) ?>">به بله دسترسی ندارم / رمز را فراموش کردم</a>
  </div>
</form>
