<?php
/**
 * @var array $sources,$categories,$inbox,$groups,$channels,$stats
 * @var bool  $auto,$header
 * @var int   $default_target
 * @var array|null $test
 */
?>
<div class="grid g4 mb">
  <div class="kpi"><div class="i">📡</div><div class="v"><?= digits_fa((int)$stats['sources']) ?></div><div class="l">کانال منبع فعال</div></div>
  <div class="kpi"><div class="i">🏷</div><div class="v"><?= digits_fa((int)$stats['cats']) ?></div><div class="l">دسته فعال</div></div>
  <div class="kpi"><div class="i">✅</div><div class="v"><?= digits_fa((int)$stats['sent']) ?></div><div class="l">خبر ارسال‌شده</div></div>
  <div class="kpi"><div class="i">⏳</div><div class="v"><?= digits_fa((int)$stats['waiting']) ?></div><div class="l">در انتظار</div></div>
</div>

<div class="card">
  <h2>⚙️ تنظیمات کلی</h2>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g2">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="newshub_settings">
    <input type="hidden" name="m" value="newshub">
    <div>
      <label class="check"><input type="checkbox" name="newshub_auto" value="1" <?= $auto ? 'checked' : '' ?>> ارسال خودکار خبرهای تازه</label>
      <label class="check"><input type="checkbox" name="newshub_header" value="1" <?= $header ? 'checked' : '' ?>> درج نام دسته در ابتدای خبر</label>
      <div class="hint">اگر خودکار خاموش باشد، خبرها در «صندوق ورودی» می‌مانند و با دکمه «ارسال» فرستاده می‌شوند.</div>
    </div>
    <div>
      <label>کانال مقصد پیش‌فرض (برای دسته‌های بدون مقصد)</label>
      <input type="text" name="newshub_default_target" class="mono" dir="ltr"
             value="<?= h($default_target ? (string)$default_target : '') ?>" placeholder="-1001234567890">
      <div class="hint">آیدی عددی یا <span class="mono">@username</span> کانال مقصد.</div>
      <button class="btn mt" type="submit">💾 ذخیره تنظیمات</button>
    </div>
  </form>
</div>

<div class="card">
  <h2>📡 کانال‌های منبع</h2>
  <div class="hint mb">
    ربات باید در هر کانال منبع <b>ادمین</b> باشد، وگرنه بله پست‌های آن کانال را برای ربات نمی‌فرستد.
    آیدی عددی یا <span class="mono">@username</span> کانال را وارد کن.
  </div>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline mb">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="newshub_source_add">
    <input type="hidden" name="m" value="newshub">
    <input type="text" name="chat_id" class="mono" dir="ltr" placeholder="-1001234567890 یا @mynews" required>
    <input type="text" name="title" placeholder="نام نمایشی (اختیاری)">
    <button class="btn ok" type="submit">➕ افزودن منبع</button>
  </form>
  <?php if (!$sources): ?>
    <div class="empty">هنوز کانال منبعی ثبت نشده است.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>نام</th><th>آیدی</th><th>وضعیت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($sources as $s): ?>
        <tr>
          <td class="small"><?= digits_fa((int)$s['id']) ?></td>
          <td class="small"><?= h((string)$s['title']) ?></td>
          <td class="small mono" dir="ltr"><?= h((string)$s['chat_id']) ?></td>
          <td>
            <span class="badge <?= (int)$s['is_active'] ? 'b-ok' : 'b-mute' ?>"><?= (int)$s['is_active'] ? 'فعال' : 'خاموش' ?></span>
          </td>
          <td class="btn-group">
            <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="a" value="newshub_source_toggle">
              <input type="hidden" name="m" value="newshub">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <input type="hidden" name="on" value="<?= (int)$s['is_active'] ? '0' : '1' ?>">
              <button class="btn ghost sm" type="submit"><?= (int)$s['is_active'] ? '⏸ خاموش' : '▶️ روشن' ?></button>
            </form>
            <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline" onsubmit="return confirm('حذف شود؟')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="a" value="newshub_source_delete">
              <input type="hidden" name="m" value="newshub">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <button class="btn danger sm" type="submit">🗑</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>🏷 دسته‌بندی و مقصدها</h2>
  <div class="hint mb">
    هر خبر با کلیدواژه‌های دسته‌ها تطبیق داده می‌شود و به <b>دسته‌ای که بیشترین تطبیق را دارد</b> می‌رود؛
    سپس به «کانال مقصد» همان دسته ارسال می‌شود. اگر دسته‌ای مقصد نداشته باشد از مقصد پیش‌فرض استفاده می‌شود.
  </div>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g2 mb">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="newshub_category_save">
    <input type="hidden" name="m" value="newshub">
    <div>
      <label>نام دسته</label>
      <input type="text" name="name" placeholder="اقتصادی / ورزشی / فناوری" required>
      <label>کلیدواژه‌ها (با کاما جدا کن)</label>
      <textarea name="keywords" rows="2" placeholder="دلار، طلا، بورس، تورم" required></textarea>
    </div>
    <div>
      <label>کانال مقصد این دسته</label>
      <input type="text" name="target_chat" class="mono" dir="ltr" placeholder="-1001234567890">
      <label>اولویت (عدد کمتر = بالاتر)</label>
      <input type="number" name="sort" value="0" dir="ltr">
      <button class="btn mt" type="submit">➕ افزودن دسته</button>
    </div>
  </form>
  <?php if (!$categories): ?>
    <div class="empty">هنوز دسته‌ای ساخته نشده است.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>دسته</th><th>کلیدواژه‌ها</th><th>مقصد</th><th>اولویت</th><th>وضعیت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($categories as $c): ?>
        <tr>
          <td class="small"><?= digits_fa((int)$c['id']) ?></td>
          <td class="small"><b><?= h((string)$c['name']) ?></b></td>
          <td class="small"><?= h(str_limit((string)$c['keywords'], 90)) ?></td>
          <td class="small mono" dir="ltr"><?= (int)($c['target_chat'] ?? 0) ? h((string)$c['target_chat']) : '<span class="muted">پیش‌فرض</span>' ?></td>
          <td class="small"><?= digits_fa((int)$c['sort']) ?></td>
          <td><span class="badge <?= (int)$c['is_active'] ? 'b-ok' : 'b-mute' ?>"><?= (int)$c['is_active'] ? 'فعال' : 'خاموش' ?></span></td>
          <td>
            <div class="btn-group">
              <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline inline-form">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="newshub_category_save">
                <input type="hidden" name="m" value="newshub">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <input type="hidden" name="is_active" value="<?= (int)$c['is_active'] ?>">
                <input type="text" name="keywords" value="<?= h((string)$c['keywords']) ?>" class="small">
                <input type="hidden" name="name" value="<?= h((string)$c['name']) ?>">
                <input type="hidden" name="target_chat" value="<?= h((string)($c['target_chat'] ?? '')) ?>">
                <input type="hidden" name="sort" value="<?= (int)$c['sort'] ?>">
                <button class="btn ghost sm" type="submit">💾 کلیدواژه</button>
              </form>
              <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline" onsubmit="return confirm('حذف شود؟')">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="newshub_category_delete">
                <input type="hidden" name="m" value="newshub">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="btn danger sm" type="submit">🗑</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>🧪 آزمایش دسته‌بندی</h2>
  <form method="get" action="<?= h(url('panel/index.php')) ?>" class="inline mb">
    <input type="hidden" name="p" value="module">
    <input type="hidden" name="m" value="newshub">
    <input type="text" name="try" value="<?= h((string)($test['text'] ?? '')) ?>" placeholder="مثلاً: قیمت دلار امروز بالا رفت" style="min-width:320px">
    <button class="btn ghost" type="submit">🔍 بررسی</button>
  </form>
  <?php if ($test !== null): ?>
    <?php if ($test['cat'] !== null): ?>
      <div class="hint">
        این متن در دسته <b><?= h((string)$test['cat']['name']) ?></b> قرار می‌گیرد
        (امتیاز تطبیق: <?= digits_fa((int)$test['score']) ?>) و به
        <span class="mono" dir="ltr"><?= $test['target'] ? h((string)$test['target']) : 'مقصد ندارد' ?></span> ارسال می‌شود.
      </div>
    <?php else: ?>
      <div class="hint">هیچ کلیدواژه‌ای تطبیق نداشت؛ این خبر «بدون دسته» است
        <?= $default_target ? 'و به مقصد پیش‌فرض (' . h((string)$default_target) . ') می‌رود.' : 'و بدون مقصد رد می‌شود.' ?></div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h2>✍️ ارسال دستی خبر</h2>
  <div class="hint mb">اگر دسترسی به پست‌های کانال ممکن نبود، متن خبر را اینجا بگذار؛ همان دسته‌بندی و مقصد اعمال می‌شود.</div>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g2">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="newshub_send_manual">
    <input type="hidden" name="m" value="newshub">
    <div>
      <label>متن خبر</label>
      <textarea name="body" rows="4" placeholder="متن کامل خبر…" required></textarea>
    </div>
    <div>
      <label>دسته (اختیاری — خالی = تشخیص خودکار)</label>
      <select name="category_id">
        <option value="0">تشخیص خودکار</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= h((string)$c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn mt" type="submit">📤 پردازش و ارسال</button>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-header"><h2>📥 صندوق ورودی (۴۰ خبر آخر)</h2></div>
  <?php if (!$inbox): ?>
    <div class="empty">هنوز خبری دریافت نشده است.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>زمان</th><th>متن</th><th>دسته</th><th>مقصد</th><th>وضعیت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($inbox as $i): ?>
        <?php $cls = ['sent' => 'b-ok', 'new' => 'b-warn', 'failed' => 'b-err', 'skipped' => 'b-mute'][(string)$i['status']] ?? 'b-mute'; ?>
        <tr>
          <td class="small"><?= digits_fa((int)$i['id']) ?></td>
          <td class="small mono"><?= dt((int)$i['created_at'], 'm/d H:i') ?></td>
          <td class="small"><?= h(str_limit((string)$i['body'], 70)) ?></td>
          <td class="small"><?= h((string)($i['category_name'] ?? '—')) ?></td>
          <td class="small mono" dir="ltr"><?= (int)($i['target_chat'] ?? 0) ? h((string)$i['target_chat']) : '—' ?></td>
          <td class="small">
            <span class="badge <?= $cls ?>"><?= \Modules\NewsHub::faStatus((string)$i['status']) ?></span>
            <?php if (!empty($i['error'])): ?><div class="small muted"><?= h(str_limit((string)$i['error'], 40)) ?></div><?php endif; ?>
          </td>
          <td class="btn-group">
            <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="a" value="newshub_send_item">
              <input type="hidden" name="m" value="newshub">
              <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
              <button class="btn ok sm" type="submit">📤 ارسال</button>
            </form>
            <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline" onsubmit="return confirm('حذف شود؟')">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="a" value="newshub_delete_item">
              <input type="hidden" name="m" value="newshub">
              <input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
              <button class="btn danger sm" type="submit">🗑</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>🤖 دستورهای ربات</h2>
  <div class="hint">
    <code class="mono">کانال_خبر</code> — نمایش منابع و دسته‌ها ·
    <code class="mono">خبر_دستی متن خبر</code> — ثبت و ارسال فوری خبر از طرف ادمین
  </div>
</div>
