<?php /** @var array $groups; @var int $total,$off,$limit; @var string $q */ ?>
<div class="card">
  <div class="card-header">
    <h2>گروه‌ها <span class="badge b-info"><?= digits_fa($total) ?></span></h2>
    <form method="get" class="inline">
      <input type="hidden" name="p" value="groups">
      <input type="search" name="q" value="<?= h($q) ?>" placeholder="جستجو نام یا آیدی گروه">
      <button class="btn sm" type="submit">جستجو</button>
    </form>
  </div>
  <?php if (!$groups): ?>
    <div class="empty">گروهی یافت نشد. ربات را به گروه اضافه کنید و ادمین کنید تا اینجا ثبت شود.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>گروه</th><th>آیدی</th><th>وضعیت</th><th>فیلترها</th><th>بن / سکوت / گزارش</th><th>رخداد ۲۴س</th><th>آخرین فعالیت</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($groups as $g): $on = (int)$g['is_active']; ?>
        <tr>
          <td><b><?= h((string)($g['title'] ?: 'بدون عنوان')) ?></b></td>
          <td class="mono small"><?= h((string)$g['chat_id']) ?></td>
          <td>
            <?php if ($on): ?><span class="badge b-ok">فعال</span>
            <?php else: ?><span class="badge b-err">غیرفعال</span><?php endif; ?>
          </td>
          <td class="small">
            <?= (int)$g['anti_link'] ? '🔗' : '' ?>
            <?= (int)$g['anti_spam'] ? '🌀' : '' ?>
            <?= (int)$g['bad_words'] ? '🤬' : '' ?>
            <?= (int)$g['captcha_on'] ? '🧩' : '' ?>
            <?= (int)$g['welcome_on'] ? '👋' : '' ?>
            <?= (int)$g['anti_forward'] ? '↪️' : '' ?>
            <?= (int)$g['anti_bot'] ? '🤖' : '' ?>
          </td>
          <td class="small"><?= digits_fa((int)$g['bans']) ?> / <?= digits_fa((int)$g['mutes']) ?> / <?= digits_fa((int)$g['reports']) ?></td>
          <td class="small"><?= digits_fa((int)$g['events24']) ?></td>
          <td class="small"><?= h(dt((int)$g['updated_at'], 'Y/m/d H:i')) ?></td>
          <td>
            <a class="btn sm" href="<?= h(url('panel/index.php', ['p' => 'group', 'chat_id' => (int)$g['chat_id']])) ?>">مدیریت</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($total > $limit): ?>
    <div class="pager">
      <?php for ($i = 0; $i < ceil($total / $limit); $i++): ?>
        <a class="<?= $off == $i * $limit ? 'cur' : '' ?>"
           href="<?= h(url('panel/index.php', ['p' => 'groups', 'q' => $q, 'off' => $i * $limit])) ?>"><?= digits_fa($i + 1) ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
