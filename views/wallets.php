<?php /** @var array $withdrawals,$tx,$stats; @var string $status; @var int $comm, $pending */ ?>
<div class="grid g4 mb">
  <div class="kpi"><div class="i">💳</div><div class="v"><?= digits_fa((int)$stats['pending']) ?></div><div class="l">درخواست برداشت در انتظار</div></div>
  <div class="kpi"><div class="i">✅</div><div class="v"><?= money((int)$stats['paid_sum'], '') ?></div><div class="l">مجموع پرداخت‌شده (تومان)</div></div>
  <div class="kpi"><div class="i">👛</div><div class="v"><?= money((int)$stats['wallets'], '') ?></div><div class="l">موجودی کیف پول کاربران</div></div>
  <div class="kpi"><div class="i">🎁</div><div class="v"><?= money((int)$stats['comm_sum'], '') ?></div><div class="l">کمیسیون پرداخت‌شده</div></div>
</div>

<div class="card">
  <div class="card-header">
    <h2>💳 درخواست‌های برداشت</h2>
    <form method="get" class="inline">
      <input type="hidden" name="p" value="wallets">
      <select name="status" onchange="this.form.submit()">
        <option value="">همه</option>
        <?php foreach (['pending' => 'در انتظار', 'paid' => 'پرداخت‌شده', 'rejected' => 'ردشده'] as $k => $l): ?>
          <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= h($l) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <?php if (!$withdrawals): ?>
    <div class="empty">درخواستی نیست.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>کاربر</th><th>مبلغ</th><th>کارت</th><th>وضعیت</th><th>زمان</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($withdrawals as $w): ?>
        <tr>
          <td class="small"><?= digits_fa((int)$w['id']) ?></td>
          <td class="small"><?= h(trim(((string)($w['first_name'] ?? ''))) ?: (string)$w['user_id']) ?>
            <div class="mono muted small"><?= h((string)$w['user_id']) ?></div></td>
          <td class="small"><?= money((int)$w['amount']) ?></td>
          <td class="mono small" dir="ltr"><?= h((string)$w['dest_card']) ?></td>
          <td><span class="badge <?= $w['status'] === 'paid' ? 'b-ok' : ($w['status'] === 'pending' ? 'b-warn' : 'b-err') ?>">
            <?= h(['paid' => 'پرداخت شد', 'pending' => 'در انتظار', 'rejected' => 'رد شد'][$w['status']] ?? $w['status']) ?></span></td>
          <td class="small"><?= h(dt((int)$w['created_at'], 'Y/m/d H:i')) ?></td>
          <td>
            <?php if ($w['status'] === 'pending'): ?>
              <div class="btn-group">
                <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="withdraw_decide"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                  <input type="hidden" name="approve" value="1">
                  <button class="btn ok sm" type="submit">پرداخت شد</button>
                </form>
                <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="withdraw_decide"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                  <input type="hidden" name="approve" value="0">
                  <button class="btn danger sm" type="submit">رد</button>
                </form>
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header"><h2>⚙️ تنظیمات فروشگاه</h2></div>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g3">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="store_settings">
    <div>
      <label>درصد کمیسیون زیرمجموعه فروش</label>
      <input type="number" name="store_commission_rate" min="0" max="50" step="0.5" value="<?= digits_fa((float)Orders::commissionRate()) ?>" dir="ltr">
      <div class="hint">فقط بعد از «تحویل شد» آزاد می‌شود.</div>
      <label>هزینه ارسال پیش‌فرض (تومان)</label>
      <input type="number" name="store_shipping" min="0" value="<?= digits_fa(Orders::shipping()) ?>" dir="ltr">
    </div>
    <div>
      <label>شماره کارت برای پرداخت</label>
      <input type="text" name="store_card_number" class="mono" dir="ltr" value="<?= h((string)Schema::getSetting('store_card_number', '')) ?>">
      <label>نام صاحب کارت</label>
      <input type="text" name="store_card_name" value="<?= h((string)Schema::getSetting('store_card_name', '')) ?>">
    </div>
    <div>
      <label>حداقل مبلغ برداشت (تومان)</label>
      <input type="number" name="store_min_withdraw" min="0" value="<?= digits_fa((int)(Schema::getSetting('store_min_withdraw', '50000') ?: 50000)) ?>" dir="ltr">
      <label class="check"><input type="checkbox" name="store_seller_approval" value="1" <?= (string)Schema::getSetting('store_seller_approval', '1') === '1' ? 'checked' : '' ?>> تایید ادمین برای فروشنده الزامی باشد</label>
    </div>
    <div style="align-self:end"><button class="btn" type="submit">💾 ذخیره</button></div>
  </form>
</div>
