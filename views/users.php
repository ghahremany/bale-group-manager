<?php /** @var array $users; @var int $total,$off,$limit; @var string $q */ ?>
<div class="card">
  <div class="card-header">
    <h2>کاربران <span class="badge b-info"><?= digits_fa($total) ?></span></h2>
    <form method="get" class="inline">
      <input type="hidden" name="p" value="users">
      <input type="search" name="q" value="<?= h($q) ?>" placeholder="آیدی / یوزرنیم / نام">
      <button class="btn sm" type="submit">جستجو</button>
    </form>
  </div>
  <?php if (!$users): ?>
    <div class="empty">کاربری یافت نشد.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>نام</th><th>یوزرنیم</th><th>آیدی</th><th>پیام‌ها</th><th>تخلف</th><th>اولین‌بار</th><th>آخرین‌بار</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= h(trim(((string)$u['first_name']) . ' ' . ((string)$u['last_name'])) ?: '—') ?></td>
            <td class="mono small"><?= $u['username'] ? '@' . h((string)$u['username']) : '—' ?></td>
            <td class="mono small"><?= h((string)$u['user_id']) ?></td>
            <td class="small"><?= digits_fa((int)$u['messages_count']) ?></td>
            <td class="small"><?= digits_fa((int)$u['violations_count']) ?></td>
            <td class="small"><?= h(dt((int)$u['first_seen_at'], 'Y/m/d')) ?></td>
            <td class="small"><?= h(dt((int)$u['last_seen_at'], 'Y/m/d H:i')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($total > $limit): ?>
      <div class="pager">
        <?php for ($i = 0; $i < ceil($total / $limit); $i++): ?>
          <a class="<?= $off == $i * $limit ? 'cur' : '' ?>" href="<?= h(url('panel/index.php', ['p' => 'users', 'q' => $q, 'off' => $i * $limit])) ?>"><?= digits_fa($i + 1) ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
