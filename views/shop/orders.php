<?php /** @var array $orders */ ?>
<div class="card">
  <h2>🧾 سفارش‌های من</h2>
  <?php if (!$orders): ?>
    <div class="empty">هنوز سفارشی ثبت نکرده‌ای.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>کد</th><th>کالا</th><th>تعداد</th><th>مبلغ</th><th>وضعیت</th><th>پرداخت</th><th>زمان</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td class="mono small"><?= h((string)$o['code']) ?></td>
          <td class="small"><?= h((string)($o['item_title'] ?? '-')) ?></td>
          <td class="small"><?= digits_fa((int)($o['qty'] ?? 1)) ?></td>
          <td class="small"><?= money((int)$o['total']) ?></td>
          <td><span class="badge b-info"><?= h(Orders::STATUSES[$o['status']] ?? $o['status']) ?></span></td>
          <td><span class="badge <?= $o['payment_status'] === 'verified' ? 'b-ok' : ($o['payment_status'] === 'pending_review' ? 'b-warn' : 'b-mute') ?>">
            <?= h(Orders::PAYMENTS[$o['payment_status']] ?? $o['payment_status']) ?></span></td>
          <td class="small"><?= h(dt((int)$o['created_at'], 'Y/m/d H:i')) ?></td>
          <td><a class="btn ghost sm" href="<?= h(url('shop/index.php', ['p' => 'order', 'code' => (string)$o['code']])) ?>">جزئیات</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
