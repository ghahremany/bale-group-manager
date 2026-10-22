<?php
/** @var array $me,$hook_info,$groups; @var string $hook_url,$cron_secret,$mode,$php; @var int $otp_chat,$def_log,$last_cron,$last_poll */
$base = rtrim((string)config('app.url', ''), '/');
?>
<div class="grid g2">
  <div class="card">
    <h2>🤖 ربات</h2>
    <div class="rowline"><span>وضعیت اتصال</span>
      <span class="badge <?= $me ? 'b-ok' : 'b-err' ?>"><?= $me ? 'متصل' : 'قطع' ?></span>
    </div>
    <div class="rowline"><span>نام ربات</span><span><?= $me ? h((string)($me['first_name'] ?? '')) : '—' ?></span></div>
    <div class="rowline"><span>یوزرنیم</span><span class="mono"><?= $me && !empty($me['username']) ? '@' . h((string)$me['username']) : '—' ?></span></div>
    <div class="rowline"><span>شناسه ربات</span><span class="mono"><?= $me ? h((string)($me['id'] ?? '')) : '—' ?></span></div>
    <div class="rowline"><span>حالت دریافت</span><span class="badge <?= $mode === 'webhook' ? 'b-ok' : 'b-warn' ?>"><?= h($mode) ?></span></div>
    <div class="rowline"><span>آخرین کرون</span><span><?= $last_cron ? h(dt($last_cron)) : '—' ?></span></div>
    <div class="rowline"><span>آخرین پولینگ</span><span><?= $last_poll ? h(dt($last_poll)) : '—' ?></span></div>
    <div class="rowline"><span>نسخه PHP</span><span class="mono"><?= h($php) ?></span></div>
    <div class="btn-group mt">
      <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
        <input type="hidden" name="a" value="system_test">
        <button class="btn sm" type="submit">تست ارتباط</button>
      </form>
      <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
        <input type="hidden" name="a" value="system_cron">
        <button class="btn ghost sm" type="submit">اجرای دستی کرون</button>
      </form>
      <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
        <input type="hidden" name="a" value="system_cache">
        <button class="btn ghost sm" type="submit">پاک‌کردن کش</button>
      </form>
      <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
        <input type="hidden" name="a" value="system_upgrade">
        <button class="btn ghost sm" type="submit">⬆️ آپدیت دیتابیس</button>
      </form>
    </div>
  </div>

  <div class="card">
    <h2>🔗 وب‌هوک</h2>
    <label>آدرس فعلی وب‌هوک</label>
    <div class="mono small"><?= $hook_url ? h($hook_url) : 'تنظیم نشده' ?></div>
    <?php if ($hook_info): ?>
      <div class="rowline mt"><span>URL در بله</span><span class="mono small"><?= h((string)($hook_info['url'] ?? '—')) ?></span></div>
      <div class="rowline"><span>پیام‌های در انتظار</span><span><?= h((string)($hook_info['pending_update_count'] ?? '0')) ?></span></div>
      <?php if (!empty($hook_info['last_error_message'])): ?>
        <div class="alert err mt">آخرین خطا: <?= h((string)$hook_info['last_error_message']) ?>
          <?= isset($hook_info['last_error_date']) ? ' (' . h(dt((int)$hook_info['last_error_date'])) . ')' : '' ?></div>
      <?php endif; ?>
    <?php endif; ?>
    <div class="btn-group mt">
      <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
        <input type="hidden" name="a" value="system_webhook">
        <button class="btn sm" type="submit">تنظیم / بازتنظیم وب‌هوک</button>
      </form>
      <form method="post" action="<?= h(url('panel/index.php')) ?>" onsubmit="return confirm('وب‌هوک حذف شود؟ (حالت پولینگ)')"><?= Auth::csrfField() ?>
        <input type="hidden" name="a" value="system_webhook_del">
        <button class="btn danger sm" type="submit">حذف وب‌هوک</button>
      </form>
    </div>
    <div class="hint mt">
      اگر وب‌هوک تنظیم نشد، پولینگ را با کرون هر دقیقه اجرا کن:<br>
      <code class="mono">php <?= h(BGM_ROOT) ?>/bin/poll.php --once</code>
    </div>
  </div>
</div>

<div class="card">
  <h2>⏱ کرون</h2>
  <div class="hint mb">برای آن‌میوت/آنبن خودکار، اخراج کپچاهای منقضی و گزارش روزانه، این آدرس را هر ۱ دقیقه صدا بزن:</div>
  <div class="mono"><?= h($base) ?>/cron.php?key=<?= h($cron_secret) ?></div>
  <div class="hint mt">یا در کرون هاست: <code class="mono">* * * * * php <?= h(BGM_ROOT) ?>/cron.php >/dev/null 2>&1</code></div>
</div>

<div class="card">
  <h2>⚙️ تنظیمات سراسری</h2>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g3">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="system_settings">
    <div>
      <label>آیدی گروه/کانال جایگزین برای کد یکبارمصرف</label>
      <input type="text" name="otp_chat_id" class="mono" value="<?= (int)$otp_chat ?>" placeholder="-100123456789">
    </div>
    <div>
      <label>کانال لاگ سراسری (گزارش روزانه)</label>
      <input type="text" name="default_log_channel" class="mono" value="<?= (int)$def_log ?>" placeholder="-100123456789">
    </div>
    <div style="align-self:end"><button class="btn" type="submit">💾 ذخیره</button></div>
  </form>
</div>

<div class="card">
  <h2>📢 ارسال پیام همگانی به همه گروه‌ها</h2>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" onsubmit="return confirm('به همه گروه‌های فعال ارسال شود؟')">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="broadcast">
    <textarea name="text" placeholder="متن پیام... (HTML ساده مجاز است)" required></textarea>
    <div class="hint mt">تعداد گروه‌های فعال: <?= digits_fa(count($groups)) ?> — پیام در <b>صف</b> قرار می‌گیرد و کرون با نرخ‌سنجی ارسال می‌کند.</div>
    <div class="btn-group mt">
      <button class="btn warn" type="submit">افزودن به صف ارسال</button>
      <a class="btn ghost" href="<?= h(url('panel/index.php', ['p' => 'broadcast'])) ?>">صفحه صف و زمان‌بندی</a>
    </div>
  </form>
</div>

<div class="card">
  <h2>🤬 کلمات ممنوعه سراسری</h2>
  <?php $globalWords = DB::all("SELECT word FROM bad_words WHERE chat_id IS NULL ORDER BY word"); ?>
  <form method="post" action="<?= h(url('panel/index.php')) ?>">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="badwords_save">
    <input type="hidden" name="chat_id" value="0">
    <textarea name="words" class="mono" style="min-height:140px"><?= h(implode("\n", array_map(static fn($w) => (string)$w['word'], $globalWords))) ?></textarea>
    <div class="btn-group mt"><button class="btn" type="submit">💾 ذخیره لیست سراسری</button></div>
  </form>
</div>

<div class="card">
  <h2>📄 اطلاعات محیط</h2>
  <div class="rowline"><span>مسیر نصب</span><span class="mono small"><?= h(BGM_ROOT) ?></span></div>
  <div class="rowline"><span>نسخه اسکیما</span><span class="mono small"><?= h((string)Schema::getSetting('schema_version', '—')) ?> / <?= h(Schema::VERSION) ?></span></div>
  <div class="rowline"><span>زمان نصب</span><span class="small"><?= h(dt((int)(Schema::getSetting('installed_at', '0') ?: 0))) ?></span></div>
  <?php
    $exts = [];
    foreach (['pdo_mysql', 'curl', 'mbstring', 'openssl', 'json', 'fileinfo'] as $ext) {
        $exts[] = ($ext . ':' . (extension_loaded($ext) ? '✅' : '❌'));
    }
  ?>
  <div class="rowline"><span>افزونه‌ها</span><span class="small mono"><?= h(implode(' · ', $exts)) ?></span></div>
</div>
