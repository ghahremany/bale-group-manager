<?php
/** @var array $order; @var string $card */
$o = $order;
$pay = $o['payment'] ?? null;
$steps = ['awaiting_payment' => 0, 'paid' => 1, 'preparing' => 2, 'shipped' => 3, 'delivered' => 4];
$step  = $steps[$o['status']] ?? 0;
$labels = ['ثبت', 'پرداخت', 'آماده‌سازی', 'ارسال', 'تحویل'];
?>
<div class="card">
  <div class="card-header">
    <h2>سفارش <span class="mono" dir="ltr"><?= h((string)$o['code']) ?></span></h2>
    <span class="badge b-info"><?= h(Orders::STATUSES[$o['status']] ?? $o['status']) ?></span>
  </div>
  <div class="grid g4 mb">
    <?php foreach ($labels as $i => $l): ?>
      <div class="kpi" style="<?= $i <= $step ? 'border-color:#22c55e' : 'opacity:.6' ?>">
        <div class="v" style="font-size:16px"><?= $i < $step ? '✅' : ($i === $step ? '⏳' : '•') ?></div>
        <div class="l"><?= h($l) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="grid g2">
    <div>
      <div class="rowline"><span>کالا</span><b><?= h((string)($o['items'][0]['title_snapshot'] ?? '-')) ?></b></div>
      <div class="rowline"><span>تعداد</span><span><?= digits_fa((int)($o['items'][0]['qty'] ?? 1)) ?></span></div>
      <div class="rowline"><span>جمع کالا</span><span><?= money((int)$o['subtotal']) ?></span></div>
      <div class="rowline"><span>هزینه ارسال</span><span><?= money((int)$o['shipping']) ?></span></div>
      <div class="rowline"><span><b>مبلغ کل</b></span><b><?= money((int)$o['total']) ?></b></div>
      <div class="rowline"><span>وضعیت پرداخت</span>
        <span class="badge <?= $o['payment_status'] === 'verified' ? 'b-ok' : ($o['payment_status'] === 'pending_review' ? 'b-warn' : 'b-mute') ?>">
          <?= h(Orders::PAYMENTS[$o['payment_status']] ?? $o['payment_status']) ?></span></div>
      <div class="rowline"><span>گیرنده</span><span class="small"><?= h((string)($o['buyer_name'] ?? '—')) ?> · <?= h((string)($o['buyer_phone'] ?? '')) ?></span></div>
      <div class="rowline"><span>آدرس</span><span class="small"><?= h((string)($o['address'] ?? '—')) ?></span></div>
    </div>
    <div>
      <?php if ($o['payment_status'] !== 'verified'): ?>
        <h3>💳 پرداخت</h3>
        <?php if ($card !== ''): ?>
          <div class="mono" style="background:#0a1424;border:1px dashed #2f5d8f;border-radius:8px;padding:10px;text-align:center" dir="ltr"><?= h($card) ?></div>
          <div class="hint">مبلغ <b><?= money((int)$o['total']) ?></b> را به این کارت واریز کن، سپس رسید را آپلود کن.</div>
        <?php else: ?>
          <div class="alert warn">شماره کارت هنوز تنظیم نشده؛ با پشتیبانی تماس بگیر.</div>
        <?php endif; ?>
        <form method="post" action="<?= h(url('shop/index.php')) ?>" enctype="multipart/form-data" class="mt">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="a" value="receipt">
          <input type="hidden" name="code" value="<?= h((string)$o['code']) ?>">
          <label>تصویر رسید (jpg/png/webp/pdf تا ۵MB)</label>
          <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
          <div class="btn-group mt"><button class="btn" type="submit">📤 ارسال رسید</button></div>
        </form>
      <?php else: ?>
        <div class="alert ok">✅ پرداخت این سفارش تایید شده است.</div>
      <?php endif; ?>
      <?php if ($pay && !empty($pay['receipt_file'])): ?>
        <div class="hint mt">رسید ارسال‌شده: <code class="mono"><?= h((string)$pay['receipt_file']) ?></code>
          · وضعیت: <?= h(['pending' => 'در انتظار بررسی', 'verified' => 'تایید', 'rejected' => 'رد'][$pay['status']] ?? $pay['status']) ?></div>
        <?php if (!empty($pay['note'])): ?><div class="hint">یادداشت ادمین: <?= h((string)$pay['note']) ?></div><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<div class="center"><a class="btn ghost sm" href="<?= h(url('shop/index.php', ['p' => 'orders'])) ?>">بازگشت به سفارش‌ها</a></div>
