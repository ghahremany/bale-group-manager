<?php
/** @var array $order,$can; @var bool $already */
$item = $order['items'][0] ?? [];
?>
<div class="card">
  <div class="card-header">
    <h2>⭐ ثبت نظر برای سفارش <span class="mono"><?= h((string)$order['code']) ?></span></h2>
    <a class="btn ghost sm" href="<?= h(url('shop/index.php', ['p' => 'order', 'code' => (string)$order['code']])) ?>">بازگشت به سفارش</a>
  </div>
  <div class="rowline"><span>کالا</span><b><?= h((string)($item['title_snapshot'] ?? '—')) ?></b></div>
  <div class="rowline"><span>تعداد</span><span><?= digits_fa((int)($item['qty'] ?? 1)) ?></span></div>
  <div class="rowline"><span>وضعیت سفارش</span><span class="badge b-ok">تحویل‌شده</span></div>
</div>

<?php if ($already): ?>
  <div class="card">
    <div class="alert ok">برای این سفارش قبلاً نظر ثبت کرده‌ای. ممنون! 🌟</div>
    <a class="btn ghost" href="<?= h(url('shop/index.php', ['p' => 'product_view', 'id' => (int)($item['product_id'] ?? 0)])) ?>">دیدن صفحه کالا</a>
  </div>
<?php elseif (!$can['ok']): ?>
  <div class="card">
    <div class="alert warn"><?= h((string)($can['error'] ?? 'ثبت نظر ممکن نیست.')) ?></div>
  </div>
<?php else: ?>
  <div class="card">
    <h2>امتیاز و نظر تو</h2>
    <form method="post" action="<?= h(url('shop/index.php')) ?>">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="review_save">
      <input type="hidden" name="code" value="<?= h((string)$order['code']) ?>">

      <label>امتیاز (۱ تا ۵ ستاره)</label>
      <div class="btn-group" style="margin-bottom:10px">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <label class="btn ghost sm" style="cursor:pointer">
            <input type="radio" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?> style="margin-left:4px">
            <?= str_repeat('★', $i) ?>
          </label>
        <?php endfor; ?>
      </div>

      <label>نظر شما (اختیاری)</label>
      <textarea name="comment" rows="4" maxlength="600" placeholder="از کیفیت کالا و ارسال بگو…"></textarea>
      <div class="hint">نظرها فقط برای خریدارانی نمایش داده می‌شود که سفارش تحویل‌شده دارند و با نام «خرید تاییدشده» منتشر می‌شوند.</div>
      <div class="btn-group mt"><button class="btn" type="submit">📩 ثبت نظر</button></div>
    </form>
  </div>
<?php endif; ?>
