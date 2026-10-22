<?php
/** @var array $raffles,$groups,$stats */
?>
<div class="grid g4 mb">
  <div class="kpi"><div class="i">🎁</div><div class="v"><?= digits_fa((int)$stats['total']) ?></div><div class="l">کل قرعه‌کشی‌ها</div></div>
  <div class="kpi"><div class="i">🟢</div><div class="v"><?= digits_fa((int)$stats['active']) ?></div><div class="l">در حال شرکت</div></div>
  <div class="kpi"><div class="i">🏆</div><div class="v"><?= digits_fa((int)$stats['drawn']) ?></div><div class="l">قرعه‌کشی‌شده</div></div>
  <div class="kpi"><div class="i">👥</div><div class="v"><?= digits_fa((int)$stats['entries']) ?></div><div class="l">کل شرکت‌ها</div></div>
</div>

<div class="card">
  <h2>➕ قرعه‌کشی جدید</h2>
  <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g2">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="raffle_create">
    <input type="hidden" name="m" value="raffle">
    <div>
      <label>عنوان</label>
      <input type="text" name="title" placeholder="قرعه‌کشی یلدا" required>
      <label>جایزه</label>
      <input type="text" name="prize" placeholder="یک عدد پاوربانک" required>
    </div>
    <div>
      <label>توضیح (اختیاری)</label>
      <textarea name="description" rows="3" placeholder="شرایط شرکت…"></textarea>
      <label>شرط عضویت در کانال (اختیاری — آیدی عددی)</label>
      <input type="text" name="require_chat" class="mono" dir="ltr" placeholder="-1001234567890">
    </div>
    <div>
      <label>چت اعلام و شرکت</label>
      <select name="chat_id">
        <option value="0">انتخاب نکن (فقط پیش‌نویس)</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int)$g['chat_id'] ?>"><?= h((string)$g['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="check"><input type="checkbox" name="publish_now" value="1" checked> همین حالا منتشر شود</label>
    </div>
    <div>
      <label>مهلت شرکت</label>
      <input type="text" name="ends_at" class="mono" dir="ltr" placeholder="2026-11-05 20:00">
      <div class="hint">خالی = بدون مهلت (قرعه‌کشی دستی). با مهلت، کرون خودکار برنده را انتخاب می‌کند.</div>
      <label>تعداد برنده</label>
      <input type="number" name="winners_count" value="1" min="1" max="20" dir="ltr">
    </div>
    <div><button class="btn" type="submit">🎁 ساخت قرعه‌کشی</button></div>
  </form>
</div>

<div class="card">
  <div class="card-header"><h2>📋 قرعه‌کشی‌ها</h2></div>
  <?php if (!$raffles): ?>
    <div class="empty">هنوز قرعه‌کشی‌ای ساخته نشده است.</div>
  <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th>#</th><th>عنوان</th><th>جایزه</th><th>شرکت‌کننده</th><th>مهلت</th><th>وضعیت</th><th>برنده‌ها</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($raffles as $r): ?>
        <tr>
          <td class="small"><?= digits_fa((int)$r['id']) ?><div class="mono small"><?= h((string)$r['code']) ?></div></td>
          <td class="small"><b><?= h((string)$r['title']) ?></b></td>
          <td class="small"><?= h((string)$r['prize']) ?></td>
          <td class="small"><?= digits_fa((int)$r['entrant_count']) ?></td>
          <td class="small mono"><?= (int)$r['ends_at'] > 0 ? dt((int)$r['ends_at'], 'Y/m/d H:i') : '—' ?></td>
          <td>
            <?php $cls = $r['status'] === 'active' ? 'b-ok' : ($r['status'] === 'drawn' ? 'b-info' : 'b-mute'); ?>
            <span class="badge <?= $cls ?>">
              <?= ['active' => 'در حال شرکت', 'drawn' => 'قرعه‌کشی شد', 'canceled' => 'لغو', 'draft' => 'پیش‌نویس'][(string)$r['status']] ?? h((string)$r['status']) ?>
            </span>
          </td>
          <td class="small">
            <?php if ($r['winners_list']): ?>
              <?php foreach ($r['winners_list'] as $w): ?>
                <div>🥇 <?= h((string)$w['name']) ?></div>
              <?php endforeach; ?>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <div class="btn-group">
              <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="raffle_publish">
                <input type="hidden" name="m" value="raffle">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <select name="chat_id">
                  <option value="">همان چت قبلی</option>
                  <?php foreach ($groups as $g): ?>
                    <option value="<?= (int)$g['chat_id'] ?>"><?= h((string)$g['title']) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn ok sm" type="submit">📤</button>
              </form>
              <?php if ($r['status'] !== 'drawn'): ?>
                <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline" onsubmit="return confirm('برنده انتخاب شود؟')">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="raffle_draw">
                  <input type="hidden" name="m" value="raffle">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn warn sm" type="submit">🎲 قرعه‌کشی</button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline" onsubmit="return confirm('حذف شود؟')">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="raffle_delete">
                <input type="hidden" name="m" value="raffle">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
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
  <h2>🤖 دستورهای ربات</h2>
  <div class="hint">
    <code class="mono">قرعه‌کشی</code> — لیست قرعه‌کشی‌های فعال ·
    <code class="mono">شرکت CODE</code> — شرکت کردن ·
    <code class="mono">برنده CODE</code> — برندگان ·
    <code class="mono">قرعه‌کشی_ساز عنوان | جایزه | توضیح | تعداد</code> — ساخت سریع (ادمین گروه)
  </div>
</div>
