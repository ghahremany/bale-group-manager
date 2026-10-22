<?php /** @var array $admins,$sessions; @var array $admin */ ?>
<div class="card">
  <h2>🔑 ادمین‌های پنل</h2>
  <div class="hint mb">هر ادمین برای ورود به دو چیز نیاز دارد: رمز عبور + کد یکبارمصرف که به آیدی بله او ارسال می‌شود. حتماً آیدی عددی بله را برای هر ادمین ثبت کن.</div>
  <div class="table-wrap"><table>
    <thead><tr><th>#</th><th>نام کاربری</th><th>نقش</th><th>آیدی بله</th><th>وضعیت</th><th>آخرین ورود</th><th>اقدام</th></tr></thead>
    <tbody>
    <?php foreach ($admins as $a): ?>
      <tr>
        <td><?= digits_fa((int)$a['id']) ?></td>
        <td class="mono"><?= h((string)$a['username']) ?></td>
        <td><span class="badge <?= $a['role'] === 'super' ? 'b-warn' : 'b-info' ?>"><?= $a['role'] === 'super' ? 'مدیر ارشد' : 'ادمین' ?></span></td>
        <td class="mono small"><?= $a['bale_user_id'] ? h((string)$a['bale_user_id']) : '<span class="badge b-err">ثبت نشده</span>' ?></td>
        <td><?= (int)$a['is_active'] ? '<span class="badge b-ok">فعال</span>' : '<span class="badge b-err">غیرفعال</span>' ?></td>
        <td class="small"><?= h(dt((int)$a['last_login_at'])) ?></td>
        <td>
          <div class="btn-group">
            <form method="post" action="<?= h(url('panel/index.php')) ?>">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="a" value="sessions_revoke">
              <input type="hidden" name="admin_id" value="<?= (int)$a['id'] ?>">
              <button class="btn ghost sm" type="submit">بستن نشست‌ها</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="card">
  <h2>➕ ادمین جدید / ویرایش</h2>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g3">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="admin_save">
    <div>
      <label>شناسه (برای ویرایش)</label>
      <select name="id">
        <option value="0">— ادمین جدید —</option>
        <?php foreach ($admins as $a): ?>
          <option value="<?= (int)$a['id'] ?>">#<?= (int)$a['id'] ?> — <?= h((string)$a['username']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>نام کاربری</label><input type="text" name="username" class="mono" required></div>
    <div><label>رمز عبور (خالی = بدون تغییر)</label><input type="password" name="password" autocomplete="new-password"></div>
    <div><label>آیدی عددی بله</label><input type="text" name="bale_user_id" class="mono" placeholder="123456789"></div>
    <div><label>نقش</label>
      <select name="role"><option value="admin">ادمین</option><option value="super">مدیر ارشد</option></select>
    </div>
    <div><label>وضعیت</label>
      <select name="is_active"><option value="1">فعال</option><option value="0">غیرفعال</option></select>
    </div>
    <div style="align-self:end"><button class="btn" type="submit">💾 ذخیره</button></div>
  </form>
  <div class="hint mt">برای ویرایش یک ادمین، اول از لیست انتخابش کن، سپس نام کاربری (همان مقدار فعلی) و بقیه فیلدها را پر کن.</div>
</div>

<div class="card">
  <h2>🔒 حذف ادمین</h2>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline" onsubmit="return confirm('حذف قطعی ادمین؟')">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="admin_delete">
    <select name="id">
      <?php foreach ($admins as $a): ?>
        <option value="<?= (int)$a['id'] ?>">#<?= (int)$a['id'] ?> — <?= h((string)$a['username']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn danger sm" type="submit">حذف</button>
  </form>
</div>

<div class="card">
  <h2>📱 نشست‌های فعال</h2>
  <?php if (!$sessions): ?><div class="empty">نشست فعالی نیست.</div><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>ادمین</th><th>IP</th><th>مرورگر</th><th>شروع</th><th>آخرین فعالیت</th><th>انقضا</th></tr></thead>
      <tbody>
      <?php foreach ($sessions as $s): ?>
        <tr>
          <td class="mono small"><?= h((string)($s['username'] ?? $s['admin_id'])) ?></td>
          <td class="mono small"><?= h((string)$s['ip']) ?></td>
          <td class="small"><?= mask_text((string)$s['user_agent'], 40) ?></td>
          <td class="small"><?= h(dt((int)$s['created_at'], 'm/d H:i')) ?></td>
          <td class="small"><?= h(dt((int)$s['last_seen_at'], 'm/d H:i')) ?></td>
          <td class="small"><?= h(dt((int)$s['expires_at'], 'm/d H:i')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
