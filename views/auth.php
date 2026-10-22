<?php /** @var string $content */ ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>ورود به پنل — مدیر گروه بله</title>
<link rel="stylesheet" href="<?= h(url('assets/style.css')) ?>">
</head>
<body>
<div class="auth-wrap">
  <div style="width:100%;max-width:420px">
    <div class="auth-card">
      <div class="logo">🔐</div>
      <h1 class="center" style="font-size:17px">پنل مدیریت ربات گروه</h1>
      <p class="center muted small" style="margin-top:2px">ورود دو مرحله‌ای با رمز یکبارمصرف بله</p>
      <?php foreach (($flash ?? []) as $f): ?>
        <div class="alert <?= h($f['type']) ?>"><?= $f['msg'] ?></div>
      <?php endforeach; ?>
      <?= $content ?>
    </div>
    <div class="center muted small" style="margin-top:14px">
      <a href="<?= h(url('panel/index.php', ['p' => 'setup'])) ?>">راهنمای راه‌اندازی</a>
    </div>
  </div>
</div>
</body>
</html>
