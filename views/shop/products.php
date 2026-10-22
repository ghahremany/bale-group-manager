<?php /** @var array $products,$seller; @var int $total */ ?>
<div class="card">
  <div class="card-header">
    <h2>📦 کالاهای من <span class="badge b-info"><?= digits_fa($total) ?></span></h2>
    <a class="btn sm" href="<?= h(url('shop/index.php', ['p' => 'product'])) ?>">➕ کالای جدید</a>
  </div>
  <?php if (!$products): ?>
    <div class="empty">هنوز کالایی اضافه نکرده‌ای.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>کد</th><th>عنوان</th><th>قیمت</th><th>موجودی</th><th>فروش</th><th>وضعیت</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($products as $p): $avail = Store::available($p); ?>
        <tr>
          <td class="mono small"><?= h((string)$p['code']) ?></td>
          <td class="small"><?= h((string)$p['title']) ?></td>
          <td class="small"><?= money(Store::price($p)) ?></td>
          <td class="small">
            <?= digits_fa($avail) ?>
            <?php if ($avail <= (int)$p['min_stock']): ?><span class="badge <?= $avail <= 0 ? 'b-err' : 'b-warn' ?>">کم</span><?php endif; ?>
          </td>
          <td class="small"><?= digits_fa((int)$p['sold_count']) ?></td>
          <td><span class="badge <?= $p['status'] === 'active' ? 'b-ok' : ($p['status'] === 'pending' ? 'b-warn' : 'b-mute') ?>">
            <?= h(Store::STATUSES[$p['status']] ?? $p['status']) ?></span></td>
          <td><a class="btn ghost sm" href="<?= h(url('shop/index.php', ['p' => 'product', 'id' => (int)$p['id']])) ?>">ویرایش</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
  <div class="hint mt">کالای جدید بعد از تایید ادمین در فروشگاه و پیام‌های تبلیغاتی نمایش داده می‌شود.</div>
</div>
