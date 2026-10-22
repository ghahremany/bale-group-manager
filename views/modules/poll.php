<?php
/** @var array $polls,$groups,$stats */
?>
<div class="grid g4 mb">
  <div class="kpi"><div class="i">📊</div><div class="v"><?= digits_fa((int)$stats['total']) ?></div><div class="l">کل نظرسنجی‌ها</div></div>
  <div class="kpi"><div class="i">🟢</div><div class="v"><?= digits_fa((int)$stats['active']) ?></div><div class="l">فعال</div></div>
  <div class="kpi"><div class="i">🗳</div><div class="v"><?= digits_fa((int)$stats['votes']) ?></div><div class="l">کل رأی‌ها</div></div>
  <div class="kpi"><div class="i">👥</div><div class="v"><?= digits_fa((int)$stats['voters']) ?></div><div class="l">رأی‌دهنده یکتا</div></div>
</div>

<div class="card">
  <h2>➕ نظرسنجی جدید</h2>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g2">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="poll_create">
    <input type="hidden" name="m" value="poll">
    <div>
      <label>سؤال</label>
      <input type="text" name="question" placeholder="بهترین کالای این هفته کدام است؟" required>
      <label>توضیح (اختیاری)</label>
      <input type="text" name="description" placeholder="توضیح کوتاه…">
    </div>
    <div>
      <label>گزینه‌ها (هر خط یا با | جدا کن — حداقل ۲، حداکثر ۱۰)</label>
      <textarea name="options" rows="4" placeholder="گزینه یک | گزینه دو | گزینه سه" required></textarea>
      <label class="check"><input type="checkbox" name="multi" value="1"> چندگزینه‌ای (هر کاربر چند رأی)</label>
    </div>
    <div>
      <label>چت مقصد</label>
      <select name="chat_id">
        <option value="0">انتخاب نکن (فقط پیش‌نویس)</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int)$g['chat_id'] ?>"><?= h((string)$g['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="check"><input type="checkbox" name="publish_now" value="1" checked> همین حالا منتشر شود</label>
    </div>
    <div>
      <label>مهلت پایان (اختیاری)</label>
      <input type="text" name="expires_at" class="mono" dir="ltr" placeholder="2026-11-05 20:00">
      <div class="hint">خالی = بدون مهلت. بعد از مهلت، کرون خودکار می‌بندد.</div>
    </div>
    <div><button class="btn" type="submit">📊 ساخت نظرسنجی</button></div>
  </form>
</div>

<div class="card">
  <div class="card-header"><h2>📋 نظرسنجی‌ها</h2></div>
  <?php if (!$polls): ?>
    <div class="empty">هنوز نظرسنجی‌ای ساخته نشده است.</div>
  <?php else: ?>
    <?php foreach ($polls as $p): ?>
      <div class="card tight mb">
        <div class="rowline">
          <div>
            <b><?= h((string)$p['question']) ?></b>
            <span class="badge <?= $p['status'] === 'active' ? 'b-ok' : ($p['status'] === 'closed' ? 'b-mute' : 'b-warn') ?>">
              <?= ['active' => 'فعال', 'closed' => 'بسته', 'draft' => 'پیش‌نویس'][(string)$p['status']] ?? h((string)$p['status']) ?>
            </span>
            <span class="pill mono small"><?= h((string)$p['code']) ?></span>
            <?php if ((int)$p['multi']): ?><span class="badge b-info">چندگزینه‌ای</span><?php endif; ?>
          </div>
          <div class="small muted">
            🗳 <?= digits_fa((int)$p['results']['total']) ?> رأی
            · 👥 <?= digits_fa((int)$p['results']['voters']) ?> نفر
            <?php if (!empty($p['expires_at'])): ?>
              · ⏳ <?= dt((int)$p['expires_at'], 'Y/m/d H:i') ?>
            <?php endif; ?>
          </div>
        </div>
        <?php foreach ($p['results']['options'] as $o): ?>
          <div class="rowline">
            <span class="small"><?= digits_fa($o['idx']) ?>) <?= h($o['label']) ?></span>
            <span class="small">
              <span class="mono"><?= str_repeat('█', (int)round($o['percent'] / 10)) . str_repeat('░', 10 - (int)round($o['percent'] / 10)) ?></span>
              <?= digits_fa($o['percent']) ?>٪ (<?= digits_fa($o['votes']) ?>)
            </span>
          </div>
        <?php endforeach; ?>
        <div class="btn-group mt">
          <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="a" value="poll_publish">
            <input type="hidden" name="m" value="poll">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <select name="chat_id">
              <option value="">همان چت قبلی</option>
              <?php foreach ($groups as $g): ?>
                <option value="<?= (int)$g['chat_id'] ?>"><?= h((string)$g['title']) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn ok sm" type="submit">📤 انتشار</button>
          </form>
          <?php if ($p['status'] === 'active'): ?>
            <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="a" value="poll_close">
              <input type="hidden" name="m" value="poll">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button class="btn warn sm" type="submit">🔒 بستن</button>
            </form>
          <?php endif; ?>
          <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline" onsubmit="return confirm('حذف شود؟')">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="a" value="poll_delete">
            <input type="hidden" name="m" value="poll">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button class="btn danger sm" type="submit">🗑</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h2>🤖 دستورهای ربات</h2>
  <div class="hint">
    <code class="mono">نظرسنجی</code> — لیست نظرسنجی‌های فعال ·
    <code class="mono">رای CODE 2</code> — رأی دادن ·
    <code class="mono">نتیجه CODE</code> — نتایج ·
    <code class="mono">نظرسنجی_ساز سؤال | گزینه۱ | گزینه۲</code> — ساخت سریع (ادمین گروه)
  </div>
</div>
