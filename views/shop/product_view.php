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
        <div class="btn-group mt"><button class="btn" type="submit">ثبت سفارش</button></div>
      </form>
      <div class="hint mt">پس از ثبت، مبلغ و شماره کارت نمایش داده می‌شود و رسید را آپلود می‌کنی.</div>
    <?php endif; ?>
  </div>
</div>
