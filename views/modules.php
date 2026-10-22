<?php
/** @var array $modules لیست کارت‌های ماژول از ModuleManager::summary() */
?>
<div class="card">
  <div class="card-header">
    <h2>🧩 ماژول‌ها</h2>
    <span class="badge b-info"><?= digits_fa(count($modules)) ?> ماژول</span>
  </div>
  <div class="hint">
    هر بخش از ربات یک «ماژول» مستقل است: می‌توانی هر کدام را جدا روشن/خاموش کنی.
    برای افزودن قابلیت جدید (نظرسنجی، بازی، اخبار، …) فقط یک فایل در
    <code class="mono">app/Modules/</code> اضافه می‌شود؛ نیازی به تغییر هسته نیست.
  </div>
</div>

<div class="grid g2">
  <?php foreach ($modules as $m): ?>
    <div class="card">
      <div class="card-header">
        <h2><?= $m['icon'] ?> <?= h($m['title']) ?>
          <?php if ($m['enabled']): ?>
            <span class="badge b-ok">فعال</span>
          <?php else: ?>
            <span class="badge b-warn">خاموش</span>
          <?php endif; ?>
        </h2>
        <span class="pill mono small"><?= h($m['key']) ?></span>
      </div>
      <p class="hint"><?= h($m['description']) ?></p>
      <?php if ($m['badge'] !== ''): ?>
        <div class="hint mt">📊 <?= h($m['badge']) ?></div>
      <?php endif; ?>
      <?php if ($m['commands']): ?>
        <div class="hint mt">
          دستورهای ربات:
          <?php foreach ($m['commands'] as $c): ?>
            <code class="mono"><?= h($c) ?></code>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="btn-group mt">
        <?php if ($m['has_page']): ?>
          <a class="btn ghost sm" href="<?= h(url('panel/index.php', ['p' => 'module', 'm' => $m['key']])) ?>">⚙️ تنظیمات ماژول</a>
        <?php endif; ?>
        <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="a" value="module_toggle">
          <input type="hidden" name="m" value="<?= h($m['key']) ?>">
          <input type="hidden" name="on" value="<?= $m['enabled'] ? '0' : '1' ?>">
          <button class="btn <?= $m['enabled'] ? 'warn' : 'ok' ?> sm" type="submit">
            <?= $m['enabled'] ? '⏸ خاموش کردن' : '▶️ روشن کردن' ?>
          </button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>➕ افزودن ماژول جدید</h2>
  <div class="hint">
    یک فایل با نام دلخواه در <code class="mono">app/Modules/</code> بساز، کلاسی هم‌نام فایل در
    namespace <code class="mono">Modules</code> تعریف کن که از <code class="mono">Module</code> ارث می‌برد،
    و این متدها را پیاده کن: <code class="mono">key() title() description()</code>.
    اختیاری: <code class="mono">commands()</code> (دستور ربات)، <code class="mono">cron()</code> (کار دوره‌ای)،
    <code class="mono">panelData()</code> + <code class="mono">views/modules/&lt;key&gt;.php</code> (صفحه پنل)،
    <code class="mono">handleAction()</code> (فرم‌های پنل).
    بعد از ری‌لود، ماژول خودکار در این لیست ظاهر می‌شود.
  </div>
</div>
