<?php /** @var string $content */ ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= h($title ?? 'پنل') ?> — <?= h((string)config('app.name', 'مدیر گروه بله')) ?></title>
<link rel="stylesheet" href="<?= h(url('assets/style.css')) ?>">
<style>body{background-image:radial-gradient(1000px 500px at 85% -10%,#12294a 0%,transparent 60%)}</style>
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="brand">
      <div class="logo">🤖</div>
      <div>
        <b>مدیر گروه بله</b>
        <span>نسخه ۱.۰.۰</span>
      </div>
    </div>
    <nav class="nav">
      <a href="<?= h(url('panel/index.php', ['p' => 'dashboard'])) ?>" class="<?= $page === 'dashboard' ? 'active' : '' ?>">📊 داشبورد</a>
      <a href="<?= h(url('panel/index.php', ['p' => 'groups'])) ?>" class="<?= $page === 'groups' || $page === 'group' ? 'active' : '' ?>">👥 گروه‌ها</a>
      <a href="<?= h(url('panel/index.php', ['p' => 'moderation'])) ?>" class="<?= $page === 'moderation' ? 'active' : '' ?>">🛡 تخلفات</a>
      <a href="<?= h(url('panel/index.php', ['p' => 'users'])) ?>" class="<?= $page === 'users' ? 'active' : '' ?>">🙋 کاربران</a>
      <a href="<?= h(url('panel/index.php', ['p' => 'logs'])) ?>" class="<?= $page === 'logs' ? 'active' : '' ?>">📜 رخدادها</a>
      <a href="<?= h(url('panel/index.php', ['p' => 'analytics'])) ?>" class="<?= $page === 'analytics' ? 'active' : '' ?>">📊 گزارش نموداری</a>
      <a href="<?= h(url('panel/index.php', ['p' => 'referrals'])) ?>" class="<?= $page === 'referrals' ? 'active' : '' ?>">🌱 زیرمجموعه‌گیری</a>
      <a href="<?= h(url('panel/index.php', ['p' => 'modules'])) ?>" class="<?= $page === 'modules' || $page === 'module' ? 'active' : '' ?>">🧩 ماژول‌ها</a>
      <a href="<?= h(url('panel/index.php', ['p' => 'broadcast'])) ?>" class="<?= $page === 'broadcast' ? 'active' : '' ?>">📤 ارسال همگانی</a>
      <a href="<?= h(url('panel/index.php', ['p' => 'store'])) ?>" class="<?= $page === 'store' ? 'active' : '' ?>">📦 انبار</a>
      <a href="<?= h(url('panel/index.php', ['p' => 'orders'])) ?>" class="<?= $page === 'orders' ? 'active' : '' ?>">🧾 سفارش‌ها</a>
      <div class="sep">مدیریت سیستم</div>
      <?php if ($super): ?>
        <a href="<?= h(url('panel/index.php', ['p' => 'admins'])) ?>" class="<?= $page === 'admins' ? 'active' : '' ?>">🔑 ادمین‌های پنل</a>
        <a href="<?= h(url('panel/index.php', ['p' => 'system'])) ?>" class="<?= $page === 'system' ? 'active' : '' ?>">⚙️ سیستم و وب‌هوک</a>
        <a href="<?= h(url('panel/index.php', ['p' => 'audit'])) ?>" class="<?= $page === 'audit' ? 'active' : '' ?>">🧾 لاگ ممیزی</a>
        <a href="<?= h(url('panel/index.php', ['p' => 'backup'])) ?>" class="<?= $page === 'backup' ? 'active' : '' ?>">🗄 بکاپ و گزارش خودکار</a>
        <a href="<?= h(url('panel/index.php', ['p' => 'recovery'])) ?>" class="<?= $page === 'recovery' ? 'active' : '' ?>">🔑 کدهای بازیابی</a>
        <a href="<?= h(url('panel/index.php', ['p' => 'wallets'])) ?>" class="<?= $page === 'wallets' ? 'active' : '' ?>">💳 کیف پول و برداشت</a>
      <?php endif; ?>
      <a href="<?= h(url('panel/index.php', ['p' => 'account'])) ?>" class="<?= $page === 'account' ? 'active' : '' ?>">👤 حساب من</a>
      <a href="<?= h(url('panel/index.php', ['p' => 'setup'])) ?>" class="<?= $page === 'setup' ? 'active' : '' ?>">🚀 راه‌اندازی</a>
      <div class="sep">خروج</div>
      <a href="<?= h(url('panel/logout.php')) ?>">🚪 خروج از پنل</a>
    </nav>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <b><?= h($title ?? '') ?></b>
        <?php if (($mode ?? '') === 'polling'): ?>
          <span class="badge b-warn">حالت پولینگ</span>
        <?php elseif (($mode ?? '') === 'webhook'): ?>
          <span class="badge b-ok">وب‌هوک</span>
        <?php endif; ?>
      </div>
      <div class="inline">
        <?php $lastBackup = (int)(Schema::getSetting('last_backup_at', '0') ?: 0); ?>
        <?php if ($lastBackup && time() - $lastBackup > 3 * 86400): ?>
          <span class="badge b-warn" title="بکاپ خودکار">بکاپ کهنه</span>
        <?php endif; ?>
        <span class="pill"><?= h((string)$admin['username']) ?> · <?= $super ? 'مدیر ارشد' : 'ادمین' ?></span>
        <a class="btn ghost sm" href="<?= h(url('panel/logout.php')) ?>">خروج</a>
      </div>
    </div>

    <div class="content">
      <?php foreach (($flash ?? []) as $f): ?>
        <div class="alert <?= h($f['type']) ?>"><?= $f['msg'] ?></div>
      <?php endforeach; ?>
      <?php if (!empty($admin['must_change_password'])): ?>
        <div class="alert warn">رمز عبور شما موقت است. لطفاً از صفحه <a href="<?= h(url('panel/index.php', ['p' => 'account'])) ?>">حساب من</a> آن را تغییر دهید.</div>
      <?php endif; ?>
      <?= $content ?>
    </div>
  </main>
</div>
</body>
</html>
