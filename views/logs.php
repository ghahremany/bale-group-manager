<?php /** @var array $logs,$actions,$groups; @var int $chat_id,$off,$limit,$total; @var string $action */ ?>
<div class="card">
  <div class="card-header">
    <h2>📜 رخدادها <span class="badge b-info"><?= digits_fa($total) ?></span></h2>
  </div>
  <form method="get" class="inline mb">
    <input type="hidden" name="p" value="logs">
    <select name="chat_id" onchange="this.form.submit()">
      <option value="0">همه گروه‌ها</option>
      <?php foreach ($groups as $g): ?>
        <option value="<?= (int)$g['chat_id'] ?>" <?= (int)$g['chat_id'] === $chat_id ? 'selected' : '' ?>><?= h((string)($g['title'] ?: $g['chat_id'])) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="action" onchange="this.form.submit()">
      <option value="">همه اقدام‌ها</option>
      <?php foreach ($actions as $a): ?>
        <option value="<?= h((string)$a['action']) ?>" <?= $action === (string)$a['action'] ? 'selected' : '' ?>>
          <?= h((string)$a['action']) ?> (<?= digits_fa((int)$a['c']) ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <button class="btn ghost sm" type="submit">اعمال</button>
  </form>

  <?php if (!$logs): ?>
    <div class="empty">رخدادی مطابق فیلتر پیدا نشد.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>زمان</th><th>گروه</th><th>اقدام</th><th>ادمین</th><th>هدف</th><th>جزئیات</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td class="mono small"><?= h(dt((int)$l['ts'], 'Y/m/d H:i:s')) ?></td>
          <td class="mono small"><?= h((string)($l['chat_id'] ?? '—')) ?></td>
          <td><span class="badge b-info"><?= h((string)$l['action']) ?></span></td>
          <td class="mono small"><?= h((string)($l['actor_id'] ?? '—')) ?></td>
          <td class="mono small"><?= h((string)($l['target_id'] ?? '—')) ?></td>
          <td class="small"><?= mask_text((string)$l['detail'], 100) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="pager">
      <?php $pages = (int)ceil($total / $limit); for ($i = 0; $i < min($pages, 20); $i++): ?>
        <a class="<?= $off == $i * $limit ? 'cur' : '' ?>"
           href="<?= h(url('panel/index.php', ['p' => 'logs', 'chat_id' => $chat_id, 'action' => $action, 'off' => $i * $limit])) ?>"><?= digits_fa($i + 1) ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
