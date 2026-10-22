<?php
/** @var array $user,$orders,$products,$stats,$refStats,$orderStats; @var int $balance; @var ?array $seller */
$statusMap = Orders::STATUSES;
?>
<div class="grid g4 mb">
  <div class="kpi"><div class="i">💰</div><div class="v"><?= money($balance, '') ?></div><div class="l">موجودی کیف پول (تومان)</div></div>
  <div class="kpi"><div class="i">🧾</div><div class="v"><?= digits_fa((int)$orderStats['period_cnt']) ?></div><div class="l">سفارش ۳۰ روز</div></div>
  <div class="kpi"><div class="i">🌱</div><div class="v"><?= digits_fa((int)$refStats['verified']) ?></div><div class="l">دعوت تاییدشده</div></div>
  <div class="kpi"><div class="i">🏪</div><div class="v"><?= $seller ? h(Seller::STATUSES[$seller['status']] ?? $seller['status']) : 'مشتری' ?></div><div class="l">وضعیت فروشندگی</div></div>
</div>

<?php if (!$seller): ?>
  <div class="card">
    <h2>🏪 فروشنده شو</h2>
    <p class="small muted">با تایید ادمین می‌توانی کالا به انبار اضافه کنی و در گروه‌ها تبلیغ کنی.</p>
    <a class="btn" href="<?= h(url('shop/index.php', ['p' => 'seller'])) ?>">درخواست فروشندگی</a>
  </div>
<?php elseif ($seller['status'] === 'pending'): ?>
  <div class="alert info">درخواست فروشندگی شما در انتظار تایید ادمین است.</div>
<?php elseif ($seller['status'] === 'approved' && $stats): ?>
  <div class="grid g4 mb">
    <div class="kpi"><div class="v"><?= digits_fa((int)$stats['total']) ?></div><div class="l">کالا</div></div>
    <div class="kpi"><div class="v"><?= digits_fa((int)$stats['active']) ?></div><div class="l">فعال</div></div>
    <div class="kpi"><div class="v"><?= digits_fa((int)$stats['stock_sum']) ?></div><div class="l">موجودی کل</div></div>
    <div class="kpi"><div class="v"><?= money((int)$stats['value'], '') ?></div><div class="l">ارزش انبار (تومان)</div></div>
  </div>
  <?php if ((int)$stats['low'] > 0): ?>
    <div class="alert warn">⚠️ <?= digits_fa((int)$stats['low']) ?> کالا به حد هشدار موجودی رسیده. <a href="<?= h(url('shop/index.php', ['p' => 'products'])) ?>">بررسی</a></div>
  <?php endif; ?>
<?php endif; ?>

<div class="card">
  <div class="card-header"><h2>🛒 کالاهای موجود</h2><a class="btn ghost sm" href="<?= h(url('shop/index.php', ['p' => 'orders'])) ?>">سفارش‌های من</a></div>
  <?php if (!$products): ?>
    <div class="empty">هنوز کالایی در فروشگاه نیست.</div>
  <?php else: ?>
    <div class="grid g3">
      <?php foreach ($products as $p): ?>
        <div class="card tight" style="margin:0">
          <div style="font-weight:bold;font-size:13.5px"><?= h((string)$p['title']) ?></div>
          <div class="small muted mt"><?= mb_strimwidth((string)($p['description'] ?? ''), 0, 80, '…') ?></div>
          <div class="mt"><?= money(Store::price($p)) ?>
            <?php if ((int)$p['discount_price'] > 0): ?><span class="small muted" style="text-decoration:line-through"><?= money((int)$p['price']) ?></span><?php endif; ?>
          </div>
          <div class="small muted">موجودی: <?= digits_fa(Store::available($p)) ?> <?= h((string)$p['unit']) ?></div>
          <a class="btn sm mt" href="<?= h(url('shop/index.php', ['p' => 'product_view', 'id' => (int)$p['id']])) ?>">مشاهده و خرید</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header"><h2>🧾 آخرین سفارش‌ها</h2></div>
  <?php if (!$orders): ?>
    <div class="empty">سفارشی نداری.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>کد</th><th>کالا</th><th>مبلغ</th><th>وضعیت</th><th>پرداخت</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td class="mono small"><?= h((string)$o['code']) ?></td>
          <td class="small"><?= h((string)($o['item_title'] ?? '-')) ?></td>
          <td class="small"><?= money((int)$o['total']) ?></td>
          <td><span class="badge b-info"><?= h($statusMap[$o['status']] ?? $o['status']) ?></span></td>
          <td><span class="badge <?= $o['payment_status'] === 'verified' ? 'b-ok' : ($o['payment_status'] === 'pending_review' ? 'b-warn' : 'b-mute') ?>">
            <?= h(Orders::PAYMENTS[$o['payment_status']] ?? $o['payment_status']) ?></span></td>
          <td><a class="btn ghost sm" href="<?= h(url('shop/index.php', ['p' => 'order', 'code' => (string)$o['code']])) ?>">جزئیات</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
