<?php /** @var array $bans,$mutes,$reports,$groups; @var string $tab; @var int $chat_id */ ?>
<div class="tabs">
  <a class="<?= $tab === 'bans' ? 'active' : '' ?>" href="<?= h(url('panel/index.php', ['p' => 'moderation', 'tab' => 'bans', 'chat_id' => $chat_id])) ?>">⛔ بن‌ها</a>
  <a class="<?= $tab === 'mutes' ? 'active' : '' ?>" href="<?= h(url('panel/index.php', ['p' => 'moderation', 'tab' => 'mutes', 'chat_id' => $chat_id])) ?>">🔇 سکوت‌ها</a>
  <a class="<?= $tab === 'reports' ? 'active' : '' ?>" href="<?= h(url('panel/index.php', ['p' => 'moderation', 'tab' => 'reports', 'chat_id' => $chat_id])) ?>">🚨 گزارش‌ها</a>
</div>

<div class="card tight">
  <form method="get" class="inline">
    <input type="hidden" name="p" value="moderation">
    <input type="hidden" name="tab" value="<?= h($tab) ?>">
    <label style="margin:0">فیلتر گروه</label>
    <select name="chat_id" onchange="this.form.submit()">
      <option value="0">همه گروه‌ها</option>
      <?php foreach ($groups as $g): ?>
        <option value="<?= (int)$g['chat_id'] ?>" <?= (int)$g['chat_id'] === $chat_id ? 'selected' : '' ?>>
          <?= h((string)($g['title'] ?: $g['chat_id'])) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if ($tab === 'bans'): ?>
  <div class="card">
    <h2>⛔ لیست بن‌ها <span class="badge b-info"><?= digits_fa(count($bans)) ?></span></h2>
    <?php if (!$bans): ?><div class="empty">لیست خالی است.</div><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>گروه</th><th>کاربر</th><th>توسط</th><th>دلیل</th><th>زمان</th><th>تا</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($bans as $b): ?>
          <tr>
            <td class="mono small"><?= h((string)$b['chat_id']) ?></td>
            <td class="mono small"><?= h((string)$b['user_id']) ?></td>
            <td class="mono small"><?= h((string)($b['by_admin_id'] ?? '—')) ?></td>
            <td class="small"><?= mask_text((string)$b['reason'], 50) ?></td>
            <td class="small"><?= h(dt((int)$b['ts'], 'm/d H:i')) ?></td>
            <td class="small"><?= (int)$b['until_ts'] ? h(dt((int)$b['until_ts'], 'm/d H:i')) : 'دائمی' ?></td>
            <td>
              <form method="post" action="<?= h(url('panel/index.php')) ?>">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="mod_action">
                <input type="hidden" name="op" value="unban">
                <input type="hidden" name="chat_id" value="<?= (int)$b['chat_id'] ?>">
                <input type="hidden" name="user_id" value="<?= (int)$b['user_id'] ?>">
                <button class="btn ok sm" type="submit">رفع بن</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'mutes'): ?>
  <div class="card">
    <h2>🔇 لیست سکوت‌ها <span class="badge b-info"><?= digits_fa(count($mutes)) ?></span></h2>
    <?php if (!$mutes): ?><div class="empty">لیست خالی است.</div><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>گروه</th><th>کاربر</th><th>باقی‌مانده</th><th>دلیل</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($mutes as $m): $left = (int)$m['until_ts'] - time(); ?>
          <tr>
            <td class="mono small"><?= h((string)$m['chat_id']) ?></td>
            <td class="mono small"><?= h((string)$m['user_id']) ?></td>
            <td class="small">
              <?php if ((int)$m['until_ts'] === 0): ?><span class="badge b-mute">دائمی/نامشخص</span>
              <?php elseif ($left > 0): ?><?= digits_fa(duration_human($left)) ?>
              <?php else: ?><span class="badge b-warn">منقضی</span><?php endif; ?>
            </td>
            <td class="small"><?= mask_text((string)$m['reason'], 50) ?></td>
            <td>
              <form method="post" action="<?= h(url('panel/index.php')) ?>">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="mod_action">
                <input type="hidden" name="op" value="unmute">
                <input type="hidden" name="chat_id" value="<?= (int)$m['chat_id'] ?>">
                <input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
                <button class="btn ok sm" type="submit">رفع سکوت</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

<?php else: ?>
  <div class="card">
    <h2>🚨 گزارش‌های کاربران <span class="badge b-info"><?= digits_fa(count($reports)) ?></span></h2>
    <?php if (!$reports): ?><div class="empty">گزارشی ثبت نشده.</div><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>گروه</th><th>هدف</th><th>گزارش‌دهنده</th><th>دلیل</th><th>وضعیت</th><th>زمان</th><th>اقدام</th></tr></thead>
        <tbody>
        <?php foreach ($reports as $r): ?>
          <tr>
            <td><?= digits_fa((int)$r['id']) ?></td>
            <td class="mono small"><?= h((string)$r['chat_id']) ?></td>
            <td class="mono small"><?= h((string)$r['target_user_id']) ?></td>
            <td class="mono small"><?= h((string)$r['reporter_user_id']) ?></td>
            <td class="small"><?= mask_text((string)$r['reason'], 60) ?></td>
            <td><span class="badge <?= $r['status'] === 'open' ? 'b-err' : 'b-ok' ?>"><?= h((string)$r['status']) ?></span></td>
            <td class="small"><?= h(dt((int)$r['ts'], 'm/d H:i')) ?></td>
            <td>
              <div class="btn-group">
                <form method="post" action="<?= h(url('panel/index.php')) ?>">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="mod_action">
                  <input type="hidden" name="op" value="mute">
                  <input type="hidden" name="chat_id" value="<?= (int)$r['chat_id'] ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$r['target_user_id'] ?>">
                  <input type="hidden" name="duration" value="1h">
                  <button class="btn warn sm" type="submit">سکوت ۱ساعت</button>
                </form>
                <form method="post" action="<?= h(url('panel/index.php')) ?>">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="mod_action">
                  <input type="hidden" name="op" value="ban">
                  <input type="hidden" name="chat_id" value="<?= (int)$r['chat_id'] ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$r['target_user_id'] ?>">
                  <button class="btn danger sm" type="submit">بن</button>
                </form>
                <?php if ($r['status'] === 'open'): ?>
                <form method="post" action="<?= h(url('panel/index.php')) ?>">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="reports_close">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn ghost sm" type="submit">بستن</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>
<?php endif; ?>
