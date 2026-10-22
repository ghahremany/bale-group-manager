<?php /** @var int $balance,$min; @var array $tx,$withdrawals */ ?>
<div class="grid g2">
  <div class="card">
    <h2>💰 کیف پول</h2>
    <div class="kpi mb"><div class="v"><?= money($balance, '') ?></div><div class="l">موجودی قابل استفاده (تومان)</div></div>
    <h3>درخواست برداشت</h3>
    <?php if ($balance < $min): ?>
      <div class="hint">حداقل مبلغ برداشت <?= money($min) ?> است. با دعوت دوستان و فروش، موجودی‌ات زیاد می‌شود.</div>
    <?php else: ?>
      <form method="post" action="<?= h(url('shop/index.php')) ?>">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="a" value="withdraw">
        <label>مبلغ (حداقل <?= money($min) ?>)</label>
        <input type="number" name="amount" min="<?= (int)$min ?>" max="<?= (int)$balance ?>" value="<?= (int)$balance ?>" dir="ltr">
        <label>شماره کارت (۱۶ رقم)</label>
        <input type="text" name="card" class="mono" dir="ltr" required maxlength="19" placeholder="6037xxxxxxxxxxxx">
        <div class="btn-group mt"><button class="btn" type="submit">ثبت درخواست برداشت</button></div>
      </form>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>📜 گردش حساب</h2>
    <?php if (!$tx): ?>
      <div class="empty">تراکنشی نداری.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>زمان</th><th>نوع</th><th>مبلغ</th><th>توضیح</th></tr></thead>
        <tbody>
        <?php foreach ($tx as $t): ?>
          <tr>
            <td class="small"><?= h(dt((int)$t['created_at'], 'Y/m/d H:i')) ?></td>
            <td class="small"><?= h(['commission' => 'کمیسیون', 'withdraw' => 'برداشت', 'refund' => 'بازگشت', 'purchase' => 'خرید', 'topup' => 'شارژ', 'adjust' => 'اصلاح'][$t['kind']] ?? $t['kind']) ?></td>
            <td class="small" style="color:<?= (int)$t['amount'] >= 0 ? '#22c55e' : '#ef4444' ?>">
              <?= ((int)$t['amount'] >= 0 ? '+' : '') . money((int)$t['amount'], '') ?>
            </td>
            <td class="small muted"><?= h((string)($t['note'] ?? ($t['ref'] ?? ''))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
    <?php if ($withdrawals): ?>
      <hr>
      <h3>درخواست‌های برداشت</h3>
      <?php foreach ($withdrawals as $w): ?>
        <div class="rowline">
          <span class="small"><?= money((int)$w['amount']) ?> · <span class="mono"><?= h((string)$w['dest_card']) ?></span></span>
          <span class="badge <?= $w['status'] === 'paid' ? 'b-ok' : ($w['status'] === 'pending' ? 'b-warn' : 'b-err') ?>">
            <?= h(['paid' => 'پرداخت شد', 'pending' => 'در انتظار', 'rejected' => 'رد شد'][$w['status']] ?? $w['status']) ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
