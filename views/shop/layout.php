<?php
/** @var string $content,$page,$title; @var array $user */
$nav = [
    'dashboard' => ['🏠', 'داشبورد'],
    'products'  => ['📦', 'کالاهای من'],
    'orders'    => ['🧾', 'سفارش‌های من'],
    'wallet'    => ['💰', 'کیف پول و کمیسیون'],
    'invite'    => ['🌱', 'دعوت دوستان'],
    'seller'    => ['🏪', 'فروشندگی'],
];
$flashes = Auth::takeFlashes();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> — فروشگاه</title>
<link rel="stylesheet" href="<?= h(url('assets/style.css')) ?>">
<style>
  .shop-wrap{display:flex;min-height:100vh}
  .shop-side{width:230px;flex:0 0 230px;background:#0d1a2d;border-left:1px solid var(--line);padding:16px 12px}
  .shop-main{flex:1;padding:18px;min-width:0}
  .shop-side a{display:flex;gap:8px;align-items:center;padding:9px 11px;border-radius:9px;color:#c8d6ef;font-size:13.5px;margin-bottom:4px}
  .shop-side a:hover{background:#14233a;text-decoration:none}
  .shop-side a.active{background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff}
  .shop-brand{font-weight:bold;font-size:15px;margin:2px 4px 16px;display:flex;gap:8px;align-items:center}
  @media(max-width:760px){.shop-wrap{flex-direction:column}.shop-side{width:auto;flex:auto;border-left:0;border-bottom:1px solid var(--line)}}
</style>
</head>
<body>
<div class="shop-wrap">
  <aside class="shop-side">
    <div class="shop-brand">🛍 فروشگاه <span class="small muted"><?= h(ShopAuth::displayName($user)) ?></span></div>
    <?php foreach ($nav as $k => [$icon, $label]): ?>
      <a href="<?= h(url('shop/index.php', ['p' => $k])) ?>" class="<?= $page === $k || ($k === 'products' && in_array($page, ['product', 'product_view'], true)) ? 'active' : '' ?>"><?= $icon ?> <?= h($label) ?></a>
    <?php endforeach; ?>
    <hr>
    <a href="<?= h(url('shop/index.php')) ?>">🛒 فروشگاه</a>
    <a href="<?= h(url('shop/logout.php')) ?>">🚪 خروج</a>
  </aside>
  <main class="shop-main">
    <?php foreach ($flashes as $f): ?>
      <div class="alert <?= h($f['type']) ?>"><?= $f['msg'] ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>
