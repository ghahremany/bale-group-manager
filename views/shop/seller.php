<?php /** @var ?array $seller */ ?>
<div class="card">
  <h2>🏪 فروشندگی</h2>
  <?php if ($seller && $seller['status'] === 'approved'): ?>
    <div class="alert ok">فروشگاه شما تایید شده است: <b><?= h((string)$seller['shop_name']) ?></b></div>
    <a class="btn" href="<?= h(url('shop/index.php', ['p' => 'products'])) ?>">مدیریت کالاها</a>
    <a class="btn ghost" href="<?= h(url('shop/index.php', ['p' => 'product'])) ?>">➕ کالای جدید</a>
  <?php else: ?>
    <?php if ($seller && $seller['status'] === 'pending'): ?>
      <div class="alert warn">درخواست شما در انتظار تایید ادمین است.</div>
    <?php elseif ($seller && $seller['status'] === 'suspended'): ?>
      <div class="alert err">فروشندگی شما تعلیق شده است. <?= h((string)($seller['note'] ?? '')) ?></div>
    <?php endif; ?>
    <p class="small muted">با تایید ادمین می‌توانی کالا اضافه کنی، موجودی را مدیریت کنی و کالاهایت در گروه‌ها تبلیغ شوند.</p>
    <form method="post" action="<?= h(url('shop/index.php')) ?>">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="seller_request">
      <label>نام فروشگاه *</label>
      <input type="text" name="shop_name" required value="<?= h((string)($seller['shop_name'] ?? '')) ?>">
      <label>شماره تماس</label>
      <input type="text" name="phone" class="mono" dir="ltr" value="<?= h((string)($seller['phone'] ?? '')) ?>">
      <div class="btn-group mt"><button class="btn" type="submit">ارسال درخواست</button></div>
    </form>
  <?php endif; ?>
</div>
