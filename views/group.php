<?php
/** @var array $settings,$buttons,$bans,$mutes,$warns,$reports,$logs,$badwords,$top_members
 *  @var int $chat_id; @var string $tab; @var ?int $member_cnt */
$base = ['p' => 'group', 'chat_id' => $chat_id];
$tabs = ['general' => '⚙️ عمومی و فیلترها', 'welcome' => '👋 خوش‌آمد', 'locks' => '🔒 قفل‌ها', 'words' => '🤬 کلمات', 'members' => '🛡 اعضا', 'reports' => '🚨 گزارش‌ها', 'logs' => '📜 رخدادها'];
$switches = [
    'anti_link' => 'ضد لینک/آیدی/شماره', 'anti_spam' => 'ضد اسپم (فلود)', 'bad_words' => 'ضد فحش',
    'anti_forward' => 'ضد فوروارد', 'anti_username' => 'ضد یوزرنیم', 'anti_bot' => 'ضد بات',
    'captcha_on' => 'کپچای ورود عضو جدید', 'welcome_on' => 'خوش‌آمدگویی', 'goodbye_on' => 'خداحافظی',
    'is_active' => 'ربات در این گروه فعال باشد',
];
$locks = [
    'lock_link' => 'لینک', 'lock_photo' => 'عکس', 'lock_video' => 'ویدیو', 'lock_gif' => 'گیف',
    'lock_sticker' => 'استیکر', 'lock_voice' => 'ویس/صدا', 'lock_document' => 'فایل', 'lock_forward' => 'فوروارد',
    'lock_contact' => 'مخاطب', 'lock_poll' => 'نظرسنجی', 'lock_location' => 'موقعیت', 'lock_bot' => 'بات',
];
?>
<div class="card tight">
  <div class="inline" style="justify-content:space-between">
    <div>
      <b><?= h((string)($settings['title'] ?: 'گروه ' . $chat_id)) ?></b>
      <span class="mono small muted"><?= h((string)$chat_id) ?></span>
      <?php if ($member_cnt !== null): ?><span class="badge b-info"><?= digits_fa($member_cnt) ?> عضو</span><?php endif; ?>
      <?php if ((int)$settings['is_active']): ?><span class="badge b-ok">فعال</span><?php else: ?><span class="badge b-err">غیرفعال</span><?php endif; ?>
    </div>
    <div class="btn-group">
      <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'moderation', 'chat_id' => $chat_id])) ?>">تخلفات</a>
      <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'logs', 'chat_id' => $chat_id])) ?>">رخدادها</a>
      <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'groups'])) ?>">بازگشت</a>
    </div>
  </div>
</div>

<div class="tabs">
  <?php foreach ($tabs as $k => $label): ?>
    <a class="<?= $tab === $k ? 'active' : '' ?>" href="<?= h(url('panel/index.php', $base + ['tab' => $k])) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'general'): ?>
<form method="post" action="<?= h(url('panel/index.php')) ?>">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="a" value="group_update">
  <input type="hidden" name="form" value="group_settings">
  <input type="hidden" name="chat_id" value="<?= (int)$chat_id ?>">

  <div class="card">
    <h2>فیلترها</h2>
    <div class="switch-list">
      <?php foreach ($switches as $f => $label): ?>
        <label class="check">
          <input type="checkbox" name="sw_<?= h($f) ?>" value="1" <?= (int)$settings[$f] ? 'checked' : '' ?>>
          <?= h($label) ?>
        </label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="grid g2">
    <div class="card">
      <h2>ضد اسپم</h2>
      <label>پنجره زمانی (ثانیه)</label>
      <input type="number" name="spam_window_sec" min="2" max="120" value="<?= (int)$settings['spam_window_sec'] ?>">
      <label>حداکثر پیام در پنجره</label>
      <input type="number" name="spam_max_msgs" min="2" max="60" value="<?= (int)$settings['spam_max_msgs'] ?>">
      <label>اقدام هنگام اسپم</label>
      <select name="anti_flood_action">
        <?php foreach (['mute' => 'سکوت خودکار', 'delete' => 'فقط حذف پیام', 'warn' => 'اخطار'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= $settings['anti_flood_action'] === $v ? 'selected' : '' ?>><?= h($l) ?></option>
        <?php endforeach; ?>
      </select>
      <label>مدت سکوت اسپم (ثانیه)</label>
      <input type="number" name="flood_mute_sec" min="30" max="86400" value="<?= (int)$settings['flood_mute_sec'] ?>">
    </div>

    <div class="card">
      <h2>اخطارها</h2>
      <label>سقف اخطار</label>
      <input type="number" name="warn_limit" min="1" max="20" value="<?= (int)$settings['warn_limit'] ?>">
      <label>اقدام پس از رسیدن به سقف</label>
      <select name="warn_action">
        <?php foreach (['mute' => 'سکوت', 'kick' => 'اخراج', 'ban' => 'بن'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= $settings['warn_action'] === $v ? 'selected' : '' ?>><?= h($l) ?></option>
        <?php endforeach; ?>
      </select>
      <label>مدت سکوت هنگام اخطار (ثانیه)</label>
      <input type="number" name="warn_mute_sec" min="60" max="2592000" value="<?= (int)$settings['warn_mute_sec'] ?>">
      <label>مهلت کپچا (ثانیه)</label>
      <input type="number" name="captcha_timeout_sec" min="30" max="600" value="<?= (int)$settings['captcha_timeout_sec'] ?>">
    </div>
  </div>

  <div class="card">
    <h2>قوانین گروه</h2>
    <textarea name="rules" placeholder="قوانین گروه..."><?= h((string)$settings['rules']) ?></textarea>
    <div class="hint">با دستور «قوانین» در گروه نمایش داده می‌شود و متغیر {rules} در خوش‌آمد قابل استفاده است.</div>
  </div>

  <div class="card">
    <h2>کانال لاگ و گزارش</h2>
    <div class="grid g2">
      <div>
        <label>آیدی کانال لاگ (رخدادها)</label>
        <input type="text" name="log_channel_id" class="mono" value="<?= (int)$settings['log_channel_id'] ?>" placeholder="-100123456789">
        <div class="hint">۰ = غیرفعال. ربات باید در کانال ادمین باشد.</div>
      </div>
      <div>
        <label>آیدی کانال گزارش‌های کاربران</label>
        <input type="text" name="report_channel_id" class="mono" value="<?= (int)$settings['report_channel_id'] ?>" placeholder="-100123456789">
      </div>
    </div>
  </div>

  <div class="btn-group">
    <button class="btn" type="submit">💾 ذخیره تنظیمات</button>
  </div>
</form>
<form method="post" action="<?= h(url('panel/index.php')) ?>" onsubmit="return confirm('همه تنظیمات این گروه به پیش‌فرض برگردد؟')">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="a" value="group_reset">
  <input type="hidden" name="chat_id" value="<?= (int)$chat_id ?>">
  <div class="btn-group mt">
    <button class="btn warn sm" type="submit">♻️ بازگشت به پیش‌فرض</button>
  </div>
</form>

<?php elseif ($tab === 'welcome'): ?>
<form method="post" action="<?= h(url('panel/index.php')) ?>">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="a" value="group_update">
  <input type="hidden" name="form" value="group_settings">
  <input type="hidden" name="chat_id" value="<?= (int)$chat_id ?>">
  <div class="grid g2">
    <div class="card">
      <h2>👋 خوش‌آمدگویی</h2>
      <label class="check"><input type="checkbox" name="sw_welcome_on" value="1" <?= (int)$settings['welcome_on'] ? 'checked' : '' ?>> فعال</label>
      <textarea name="welcome_text" style="min-height:130px"><?= h((string)$settings['welcome_text']) ?></textarea>
      <div class="hint">متغیرها: <code>{name}</code> <code>{username}</code> <code>{id}</code> <code>{group}</code> <code>{count}</code> <code>{rules}</code> — تگ‌های HTML ساده مجاز است.</div>
    </div>
    <div class="card">
      <h2>👋 خداحافظی</h2>
      <label class="check"><input type="checkbox" name="sw_goodbye_on" value="1" <?= (int)$settings['goodbye_on'] ? 'checked' : '' ?>> فعال</label>
      <textarea name="goodbye_text" style="min-height:130px"><?= h((string)$settings['goodbye_text']) ?></textarea>
      <div class="hint">همان متغیرهای خوش‌آمد در اینجا هم کار می‌کنند.</div>
    </div>
  </div>
  <button class="btn" type="submit">💾 ذخیره</button>
</form>

<div class="card">
  <h2>🔘 دکمه‌های شیشه‌ای خوش‌آمد</h2>
  <?php if (!$buttons): ?>
    <div class="empty">دکمه‌ای ثبت نشده است.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>عنوان</th><th>لینک</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($buttons as $b): ?>
          <tr>
            <td><?= h((string)$b['label']) ?></td>
            <td class="mono small"><?= h((string)$b['url']) ?></td>
            <td>
              <form method="post" action="<?= h(url('panel/index.php')) ?>">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="group_btn_del">
                <input type="hidden" name="chat_id" value="<?= (int)$chat_id ?>">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <button class="btn danger sm" type="submit">حذف</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g3 mt">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="group_btn_add">
    <input type="hidden" name="chat_id" value="<?= (int)$chat_id ?>">
    <div><label>عنوان دکمه</label><input type="text" name="label" required placeholder="قوانین گروه"></div>
    <div><label>لینک (https)</label><input type="text" name="url" class="mono" required placeholder="https://..."></div>
    <div style="align-self:end"><button class="btn sm" type="submit">افزودن دکمه</button></div>
  </form>
</div>

<?php elseif ($tab === 'locks'): ?>
<form method="post" action="<?= h(url('panel/index.php')) ?>">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="a" value="group_update">
  <input type="hidden" name="form" value="group_settings">
  <input type="hidden" name="chat_id" value="<?= (int)$chat_id ?>">
  <div class="card">
    <h2>🔒 قفل انواع پیام</h2>
    <div class="switch-list">
      <?php foreach ($locks as $f => $label): ?>
        <label class="check">
          <input type="checkbox" name="sw_<?= h($f) ?>" value="1" <?= (int)$settings[$f] ? 'checked' : '' ?>>
          قفل <?= h($label) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <div class="hint mt">پاک‌کردن قفل‌ها به‌صورت خودکار روی گروه اعمال نمی‌شود؛ ربات هنگام ارسال پیام آن را حذف می‌کند.</div>
  </div>
  <button class="btn" type="submit">💾 ذخیره</button>
</form>

<?php elseif ($tab === 'words'): ?>
<div class="card">
  <h2>🤬 کلمات ممنوعه</h2>
  <div class="hint mb">هر کلمه در یک خط. کلمه ساده یا الگوی Regex بین دو اسلش (مثل <code>/k[ií]r/</code>) پذیرفته می‌شود. کلمات با نیم‌فاصله/فاصله/اعراب و اعداد فارسی هم تشخیص داده می‌شوند.</div>
  <form method="post" action="<?= h(url('panel/index.php')) ?>">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="badwords_save">
    <input type="hidden" name="chat_id" value="<?= (int)$chat_id ?>">
    <textarea name="words" class="mono" style="min-height:180px;direction:rtl"><?php
      echo h(implode("\n", array_map(static fn($w) => (string)$w['word'], $badwords)));
    ?></textarea>
    <div class="hint mt">
      کلمات سراسری + اختصاصی این گروه در یک لیست نمایش داده می‌شوند؛ با ذخیره، لیست این گروه جایگزین می‌شود.
      برای ویرایش لیست سراسری از صفحه <a href="<?= h(url('panel/index.php', ['p' => 'system'])) ?>">سیستم</a> استفاده کن.
    </div>
    <div class="btn-group mt"><button class="btn" type="submit">💾 ذخیره کلمات</button></div>
  </form>
</div>

<?php elseif ($tab === 'members'): ?>
<div class="card">
  <h2>🛡 اقدام مدیریتی سریع</h2>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g4">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="mod_action">
    <input type="hidden" name="chat_id" value="<?= (int)$chat_id ?>">
    <div><label>آیدی عددی کاربر</label><input type="text" name="user_id" class="mono" required placeholder="123456789"></div>
    <div><label>عملیات</label>
      <select name="op">
        <option value="mute">سکوت</option><option value="unmute">حذف سکوت</option>
        <option value="ban">بن</option><option value="unban">حذف بن</option>
        <option value="kick">اخراج</option><option value="warn">اخطار</option>
        <option value="clear_warn">پاک‌کردن اخطارها</option>
      </select>
    </div>
    <div><label>مدت (مثل 10m/2h/1d)</label><input type="text" name="duration" placeholder="2h"></div>
    <div style="align-self:end"><button class="btn" type="submit">اجرا</button></div>
  </form>
</div>

<div class="grid g2">
  <div class="card">
    <h2>⛔ بن‌ها <span class="badge b-info"><?= digits_fa(count($bans)) ?></span></h2>
    <?php if (!$bans): ?><div class="empty">لیست خالی</div><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>کاربر</th><th>تا</th><th>دلیل</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($bans as $b): ?>
          <tr>
            <td class="mono small"><?= h((string)$b['user_id']) ?></td>
            <td class="small"><?= (int)$b['until_ts'] ? h(dt((int)$b['until_ts'])) : 'دائمی' ?></td>
            <td class="small"><?= h((string)($b['reason'] ?: '—')) ?></td>
            <td>
              <form method="post" action="<?= h(url('panel/index.php')) ?>">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="mod_action">
                <input type="hidden" name="op" value="unban">
                <input type="hidden" name="chat_id" value="<?= (int)$chat_id ?>">
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

  <div class="card">
    <h2>🔇 سکوت‌ها</h2>
    <?php if (!$mutes): ?><div class="empty">لیست خالی</div><?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>کاربر</th><th>باقی‌مانده</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($mutes as $m): $left = (int)$m['until_ts'] - time(); ?>
          <tr>
            <td class="mono small"><?= h((string)$m['user_id']) ?></td>
            <td class="small"><?= $left > 0 ? h(duration_human($left)) : '<span class="badge b-warn">منقضی</span>' ?></td>
            <td>
              <form method="post" action="<?= h(url('panel/index.php')) ?>">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="mod_action">
                <input type="hidden" name="op" value="unmute">
                <input type="hidden" name="chat_id" value="<?= (int)$chat_id ?>">
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
</div>

<div class="card">
  <h2>⚠️ اخطارهای فعال</h2>
  <?php if (!$warns): ?><div class="empty">هیچ کاربری اخطار فعال ندارد.</div><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>کاربر</th><th>اخطار</th><th>آخرین</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($warns as $w): ?>
        <tr>
          <td>
            <?= h(trim(((string)($w['first_name'] ?? '')) . ' ' . ((string)($w['last_name'] ?? '')))) ?>
            <span class="mono small muted"><?= h((string)$w['user_id']) ?></span>
          </td>
          <td><span class="badge b-warn"><?= digits_fa((int)$w['count']) ?></span></td>
          <td class="small"><?= h(dt((int)$w['updated_at'], 'Y/m/d H:i')) ?></td>
          <td>
            <form method="post" action="<?= h(url('panel/index.php')) ?>">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="a" value="mod_action">
              <input type="hidden" name="op" value="clear_warn">
              <input type="hidden" name="chat_id" value="<?= (int)$chat_id ?>">
              <input type="hidden" name="user_id" value="<?= (int)$w['user_id'] ?>">
              <button class="btn ghost sm" type="submit">پاک‌کردن</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'reports'): ?>
<div class="card">
  <h2>🚨 گزارش‌های این گروه</h2>
  <?php if (!$reports): ?><div class="empty">گزارشی نیست.</div><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>هدف</th><th>گزارش‌دهنده</th><th>دلیل</th><th>وضعیت</th><th>زمان</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($reports as $r): ?>
        <tr>
          <td><?= digits_fa((int)$r['id']) ?></td>
          <td class="mono small"><?= h((string)$r['target_user_id']) ?></td>
          <td class="mono small"><?= h((string)$r['reporter_user_id']) ?></td>
          <td class="small"><?= mask_text((string)$r['reason'], 70) ?></td>
          <td><span class="badge <?= $r['status'] === 'open' ? 'b-err' : 'b-ok' ?>"><?= h((string)$r['status']) ?></span></td>
          <td class="small"><?= h(dt((int)$r['ts'], 'm/d H:i')) ?></td>
          <td>
            <?php if ($r['status'] === 'open'): ?>
            <form method="post" action="<?= h(url('panel/index.php')) ?>">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="a" value="reports_close">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn ok sm" type="submit">بستن</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<?php else: ?>
<div class="card">
  <h2>📜 رخدادهای این گروه</h2>
  <?php if (!$logs): ?><div class="empty">رخدادی ثبت نشده.</div><?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>زمان</th><th>اقدام</th><th>ادمین</th><th>هدف</th><th>جزئیات</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td class="mono small"><?= h(dt((int)$l['ts'])) ?></td>
          <td><span class="badge b-info"><?= h((string)$l['action']) ?></span></td>
          <td class="mono small"><?= h((string)($l['actor_id'] ?? '—')) ?></td>
          <td class="mono small"><?= h((string)($l['target_id'] ?? '—')) ?></td>
          <td class="small"><?= mask_text((string)$l['detail'], 90) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>
