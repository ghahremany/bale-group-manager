<?php /** @var array $rows */ ?>
<div class="card">
  <h2>🧾 لاگ ممیزی پنل <span class="badge b-info"><?= digits_fa(count($rows)) ?></span></h2>
  <div class="hint mb">همه اقدامات ادمین‌های پنل (ورود، تغییر تنظیمات، بن/سکوت، تغییر ادمین‌ها) اینجا ثبت می‌شود.</div>
  <?php if (!$rows): ?>
    <div class="empty">موردی ثبت نشده.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>زمان</th><th>ادمین</th><th>اقدام</th><th>هدف</th><th>جزئیات</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono small"><?= h(dt((int)$r['ts'], 'Y/m/d H:i:s')) ?></td>
          <td class="mono small"><?= h((string)($r['username'] ?: $r['admin_id'])) ?></td>
          <td><span class="badge b-info"><?= h((string)$r['action']) ?></span></td>
          <td class="small"><?= h((string)$r['target']) ?></td>
          <td class="small"><?= mask_text((string)$r['detail'], 80) ?></td>
          <td class="mono small"><?= h((string)$r['ip']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
