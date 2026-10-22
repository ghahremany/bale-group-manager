<?php /** @var array $flash; @var bool $noBot */ ?>
<div class="card" style="max-width:520px;margin:40px auto">
  <h1 style="font-size:18px">🛍 ورود به فروشگاه</h1>
  <p class="small muted">
    ورود بدون رمز و پیامک است: با حساب <b>بله</b> خودت وارد می‌شوی.
  </p>
  <ol class="small" style="line-height:2">
    <li>در چت خصوصی ربات بنویس <code>فروشگاه</code> یا <code>ورود</code>.</li>
    <li>ربات یک لینک یک‌بارمصرف (۱۰ دقیقه) می‌فرستد؛ روی آن بزن.</li>
    <li>اگر برای اولین بار وارد می‌شوی، حساب فروشگاهت خودکار ساخته می‌شود.</li>
  </ol>
  <div class="hint mt">
    بدون دسترسی به بله؟ با ادمین تماس بگیر تا لینک ورود برایت بسازد.
  </div>
  <?php if (!empty($flash)): ?>
    <div class="mt">
      <?php foreach ($flash as $f): ?><div class="alert <?= h($f['type']) ?>"><?= $f['msg'] ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="center mt"><a class="btn ghost sm" href="<?= h(url('panel/login.php')) ?>">ورود مدیران</a></div>
</div>
