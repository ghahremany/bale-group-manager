<?php /** @var array $admin,$sessions,$audit */ ?>
<div class="grid g2">
  <div class="card">
    <h2>👤 اطلاعات حساب</h2>
    <form method="post" action="<?= h(url('panel/index.php')) ?>">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="account_save">
      <label>نام نمایشی</label>
      <input type="text" name="full_name" value="<?= h((string)$admin['full_name']) ?>">
      <label>آیدی عددی بله (برای دریافت کد یکبارمصرف)</label>
      <input type="text" name="bale_user_id" class="mono" value="<?= $admin['bale_user_id'] ? (int)$admin['bale_user_id'] : '' ?>" placeholder="123456789">
      <div class="hint">آیدی خود را با فرستادن «من» به ربات ببین. اگر ثبت نشود، کد به کانال جایگزین ارسال می‌شود.</div>
      <div class="btn-group mt"><button class="btn" type="submit">💾 ذخیره</button></div>
    </form>
  </div>

  <div class="card">
    <h2>🔑 تغییر رمز عبور</h2>
    <form method="post" action="<?= h(url('panel/index.php')) ?>">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="password_change">
      <label>رمز فعلی</label>
      <input type="password" name="current_password" required autocomplete="current-password">
      <label>رمز جدید (حداقل ۸ کاراکتر)</label>
      <input type="password" name="new_password" required autocomplete="new-password">
      <label>تکرار رمز جدید</label>
      <input type="password" name="new_password2" required autocomplete="new-password">
      <div class="btn-group mt"><button class="btn" type="submit">تغییر رمز</button></div>
    </form>
  </div>
</div>

<div class="card">
  <h2>📱 نشست‌های من</h2>
  <?php if (!$sessions): ?><div class="empty">نشست فعالی نیست.</div><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>IP</th><th>مرورگر</th><th>شروع</th><th>آخرین فعالیت</th></tr></thead>
      <tbody>
      <?php foreach ($sessions as $s): ?>
        <tr>
          <td class="mono small"><?= h((string)$s['ip']) ?></td>
          <td class="small"><?= mask_text((string)$s['user_agent'], 50) ?></td>
          <td class="small"><?= h(dt((int)$s['created_at'], 'Y/m/d H:i')) ?></td>
          <td class="small"><?= h(dt((int)$s['last_seen_at'], 'Y/m/d H:i')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="mt" onsubmit="return confirm('همه نشست‌ها (به‌جز این یکی) بسته شود؟')">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="sessions_revoke">
    <input type="hidden" name="admin_id" value="<?= (int)$admin['id'] ?>">
    <button class="btn warn sm" type="submit">بستن همه نشست‌ها</button>
  </form>
</div>

<div class="card">
  <h2>🧾 آخرین فعالیت‌های من</h2>
  <?php if (!$audit): ?><div class="empty">فعالیتی ثبت نشده.</div><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>زمان</th><th>اقدام</th><th>هدف</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($audit as $a): ?>
        <tr>
          <td class="mono small"><?= h(dt((int)$a['ts'], 'Y/m/d H:i')) ?></td>
          <td><span class="badge b-info"><?= h((string)$a['action']) ?></span></td>
          <td class="small"><?= h((string)$a['target']) ?></td>
          <td class="mono small"><?= h((string)$a['ip']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
