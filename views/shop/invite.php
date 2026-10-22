<?php /** @var array $stats,$children */ ?>
<div class="grid g3 mb">
  <div class="kpi"><div class="v"><?= digits_fa((int)$stats['verified']) ?></div><div class="l">دعوت تاییدشده</div></div>
  <div class="kpi"><div class="v"><?= digits_fa((int)$stats['pending']) ?></div><div class="l">در انتظار</div></div>
  <div class="kpi"><div class="v"><?= digits_fa((int)$stats['level2']) ?></div><div class="l">سطح ۲</div></div>
</div>
<div class="card">
  <h2>🌱 لینک دعوت شما</h2>
  <p class="small muted">هر کسی با این لینک وارد شود و در یکی از گروه‌های ربات عضو شود، زیرمجموعه فروش تو می‌شود و از خریدهایش کمیسیون می‌گیری (بعد از تحویل سفارش).</p>
  <div class="mono" style="background:#0a1424;border:1px dashed #2f5d8f;border-radius:8px;padding:10px;text-align:center;word-break:break-all" dir="ltr"><?= h((string)$stats['link']) ?></div>
  <div class="hint mt">نرخ کمیسیون فعلی: <b><?= digits_fa((float)Orders::commissionRate()) ?>٪</b> از مبلغ هر سفارش</div>
</div>
<div class="card">
  <h2>👥 زیرمجموعه‌ها</h2>
  <?php if (!$children): ?>
    <div class="empty">هنوز کسی با لینک تو عضو نشده.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>کاربر</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
      <tbody>
      <?php foreach ($children as $c): ?>
        <tr>
          <td class="small"><?= h(trim(((string)($c['first_name'] ?? '')) . ' ' . ((string)($c['last_name'] ?? ''))) ?: (string)$c['invitee_id']) ?></td>
          <td><span class="badge <?= $c['status'] === 'verified' ? 'b-ok' : ($c['status'] === 'pending' ? 'b-warn' : 'b-err') ?>">
            <?= h(['verified' => 'تاییدشده', 'pending' => 'در انتظار', 'rejected' => 'ردشده'][$c['status']] ?? $c['status']) ?></span></td>
          <td class="small"><?= h(dt((int)$c['created_at'], 'Y/m/d')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
