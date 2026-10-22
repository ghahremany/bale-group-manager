<?php /** @var array $product,$me */ $avail = Store::available($product); ?>
<div class="grid g2">
  <div class="card">
    <h2><?= h((string)$product['title']) ?></h2>
    <div class="small muted"><?= nl2br(h((string)($product['description'] ?? ''))) ?></div>
    <hr>
    <div class="rowline"><span>قیمت</span><b><?= money(Store::price($product)) ?></b></div>
    <?php if ((int)$product['discount_price'] > 0): ?>
      <div class="rowline"><span>قیمت قبل</span><span class="muted" style="text-decoration:line-through"><?= money((int)$product['price']) ?></span></div>
    <?php endif; ?>
    <div class="rowline"><span>موجودی</span><span><?= digits_fa($avail) ?> <?= h((string)$product['unit']) ?></span></div>
    <div class="rowline"><span>فروش‌رفته</span><span><?= digits_fa((int)$product['sold_count']) ?></span></div>
    <div class="rowline"><span>کد خرید</span><span class="mono" dir="ltr"><?= h((string)$product['code']) ?></span></div>
    <div class="rowline"><span>امتیاز خریداران</span><span>
      <?php if ((int)$rating['count'] > 0): ?>
        <b><?= Reviews::stars((float)$rating['avg']) ?></b> <?= digits_fa((float)$rating['avg']) ?>
        <span class="muted small">(<?= digits_fa((int)$rating['count']) ?> نظر)</span>
      <?php else: ?><span class="muted">هنوز امتیازی ثبت نشده</span><?php endif; ?>
    </span></div>
  </div>
  <div class="card">
    <h2>🛒 ثبت سفارش</h2>
    <?php if ($avail <= 0): ?>
      <div class="alert warn">این کالا فعلاً ناموجود است.</div>
    <?php else: ?>
      <form method="post" action="<?= h(url('shop/index.php')) ?>">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="a" value="buy">
        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
        <label>تعداد</label>
        <input type="number" name="qty" min="1" max="<?= (int)min(50, $avail) ?>" value="1" dir="ltr">
        <label>نام و نام خانوادگی</label>
        <input type="text" name="buyer_name" required value="<?= h(ShopAuth::displayName($me)) ?>">
        <label>شماره تماس</label>
        <input type="text" name="buyer_phone" class="mono" dir="ltr" placeholder="09xxxxxxxxx">
        <label>آدرس / توضیح تحویل</label>
        <textarea name="address" rows="3"></textarea>
        <label>یادداشت (اختیاری)</label>
        <input type="text" name="note">
        <label>کد تخفیف (اختیاری)</label>
        <input type="text" name="coupon" class="mono" dir="ltr" placeholder="مثلاً YALDA20">
        <div class="btn-group mt"><button class="btn" type="submit">ثبت سفارش</button></div>
      </form>
      <div class="hint mt">پس از ثبت، مبلغ و شماره کارت نمایش داده می‌شود و رسید را آپلود می‌کنی.</div>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($reviews)): ?>
<div class="card mt">
  <h2>⭐ نظرات خریداران</h2>
  <?php foreach ($reviews as $r): ?>
    <div class="rowline" style="align-items:flex-start">
      <div>
        <div><b><?= Reviews::stars((float)$r['rating']) ?></b>
          <span class="muted small"><?= h(trim((string)($r['first_name'] ?? '') . ' ' . (string)($r['last_name'] ?? '')) ?: 'خریدار') ?></span>
          <span class="muted small mono"><?= dt((int)$r['created_at'], 'Y/m/d') ?></span>
        </div>
        <?php if (!empty($r['comment'])): ?>
          <div class="small"><?= nl2br(h((string)$r['comment'])) ?></div>
        <?php endif; ?>
      </div>
      <span class="badge b-ok">خرید تاییدشده</span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
