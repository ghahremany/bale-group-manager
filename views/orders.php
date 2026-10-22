<?php
/** @var array $orders,$stats,$top; @var int $total,$off,$limit; @var string $status,$q */
$base = ['p' => 'orders'];
?>
<div class="grid g4 mb">
  <div class="kpi"><div class="i">🧾</div><div class="v"><?= digits_fa((int)$stats['today_count']) ?></div><div class="l">سفارش امروز</div></div>
  <div class="kpi"><div class="i">⏳</div><div class="v"><?= digits_fa((int)$stats['pending']) ?></div><div class="l">رسید در انتظار بررسی</div></div>
  <div class="kpi"><div class="i">📦</div><div class="v"><?= digits_fa((int)$stats['to_ship']) ?></div><div class="l">آماده ارسال</div></div>
  <div class="kpi"><div class="i">💰</div><div class="v"><?= money((int)$stats['period_sum'], '') ?></div><div class="l">فروش تاییدشده (۳۰ روز)</div></div>
</div>

<div class="card">
  <div class="card-header">
    <h2>🧾 سفارش‌ها <span class="badge b-info"><?= digits_fa($total) ?></span></h2>
    <form method="get" class="inline">
      <input type="hidden" name="p" value="orders">
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="کد/تلفن/نام" style="width:150px">
      <select name="status" onchange="this.form.submit()">
        <option value="">همه وضعیت‌ها</option>
        <?php foreach (Orders::STATUSES as $k => $l): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?>
      </select>
      <select name="payment_status" onchange="this.form.submit()">
        <option value="">همه پرداخت‌ها</option>
        <?php foreach (Orders::PAYMENTS as $k => $l): ?><option value="<?= $k ?>" <?= ($_GET['payment_status'] ?? '') === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?>
      </select>
      <button class="btn ghost sm" type="submit">فیلتر</button>
    </form>
  </div>
  <?php if (!$orders): ?>
    <div class="empty">سفارشی با این فیلتر نیست.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>کد</th><th>خریدار</th><th>کالا</th><th>مبلغ</th><th>وضعیت</th><th>پرداخت</th><th>رسید</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td class="mono small"><?= h((string)$o['code']) ?><div class="muted"><?= h(dt((int)$o['created_at'], 'm/d H:i')) ?></div></td>
          <td class="small"><?= h((string)($o['buyer_name'] ?: ($o['first_name'] ?? ''))) ?>
            <div class="mono muted small"><?= h((string)($o['buyer_phone'] ?? $o['user_id'])) ?></div></td>
          <td class="small"><?= h(mb_strimwidth((string)($o['item_title'] ?? '-'), 0, 28, '…')) ?>
            <div class="muted">×<?= digits_fa((int)($o['qty'] ?? 1)) ?></div></td>
          <td class="small"><?= money((int)$o['total']) ?>
            <?php if ((int)$o['commission_amount'] > 0): ?><div class="muted" style="font-size:11px">کمیسیون <?= money((int)$o['commission_amount'], '') ?></div><?php endif; ?></td>
          <td><span class="badge b-info"><?= h(Orders::STATUSES[$o['status']] ?? $o['status']) ?></span></td>
          <td><span class="badge <?= $o['payment_status'] === 'verified' ? 'b-ok' : ($o['payment_status'] === 'pending_review' ? 'b-warn' : 'b-mute') ?>">
            <?= h(Orders::PAYMENTS[$o['payment_status']] ?? $o['payment_status']) ?></span></td>
          <td class="small">
            <?php $rc = (string)DB::val("SELECT receipt_file FROM payments WHERE order_id=? ORDER BY id DESC LIMIT 1", [(int)$o['id']], ''); ?>
            <?php if ($rc !== ''): ?>
              <a class="btn ghost sm" href="<?= h(url('panel/download.php', ['type' => 'receipt', 'file' => $rc])) ?>" target="_blank">مشاهده</a>
            <?php else: ?><span class="muted">—</span><?php endif; ?>
          </td>
          <td>
            <div class="btn-group">
              <?php if ($o['payment_status'] === 'pending_review'): ?>
                <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="payment_verify"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                  <input type="hidden" name="approve" value="1">
                  <button class="btn ok sm" type="submit">تایید پرداخت</button>
                </form>
                <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="payment_verify"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                  <input type="hidden" name="approve" value="0">
                  <button class="btn danger sm" type="submit">رد رسید</button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline"><?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="order_status"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                <select name="status" style="width:120px">
                  <?php foreach (Orders::STATUSES as $k => $l): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?>
                </select>
                <button class="btn ghost sm" type="submit">تغییر</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if ($total > $limit): ?>
      <div class="pager">
        <?php for ($i = 0; $i < min(20, (int)ceil($total / $limit)); $i++): ?>
          <a class="<?= $off == $i * $limit ? 'cur' : '' ?>" href="<?= h(url('panel/index.php', $base + ['status' => $status, 'q' => $q, 'off' => $i * $limit])) ?>"><?= digits_fa($i + 1) ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
  <div class="hint mt">با تغییر وضعیت به «تحویل شد»، کمیسیون زیرمجموعه فروش به کیف پول معرف واریز می‌شود.</div>
</div>

<div class="card">
  <h2>🏆 پرفروش‌ترین کالاها</h2>
  <?php if (!$top): ?>
    <div class="empty">فروشی ثبت نشده.</div>
  <?php else: ?>
    <?= Metrics::hbars(array_map(static fn($t) => [
        'label' => (string)$t['title'], 'value' => (int)$t['revenue'], 'color' => '#22c55e',
      ], $top), 520, 28) ?>
  <?php endif; ?>
</div>
