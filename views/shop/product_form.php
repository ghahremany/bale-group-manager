<?php /** @var ?array $product; @var array $categories */ ?>
<div class="card">
  <h2><?= $product ? '✏️ ویرایش کالا' : '➕ کالای جدید' ?></h2>
  <form method="post" action="<?= h(url('shop/index.php')) ?>">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="a" value="product_save">
    <input type="hidden" name="id" value="<?= (int)($product['id'] ?? 0) ?>">
    <div class="grid g2" style="gap:12px">
      <div>
        <label>عنوان کالا *</label>
        <input type="text" name="title" required value="<?= h((string)($product['title'] ?? '')) ?>">
        <label>دسته‌بندی</label>
        <select name="category_id">
          <option value="0">بدون دسته</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)($product['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= h((string)$c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <label>کد انبار (SKU)</label>
        <input type="text" name="sku" class="mono" value="<?= h((string)($product['sku'] ?? '')) ?>" dir="ltr">
        <label>واحد</label>
        <input type="text" name="unit" value="<?= h((string)($product['unit'] ?? 'عدد')) ?>">
      </div>
      <div>
        <label>قیمت (تومان) *</label>
        <input type="number" name="price" min="0" required value="<?= (int)($product['price'] ?? 0) ?>" dir="ltr">
        <label>قیمت با تخفیف (اختیاری)</label>
        <input type="number" name="discount_price" min="0" value="<?= (int)($product['discount_price'] ?? 0) ?>" dir="ltr">
        <label>موجودی انبار</label>
        <input type="number" name="stock" min="0" value="<?= (int)($product['stock'] ?? 0) ?>" dir="ltr">
        <label>حد هشدار موجودی</label>
        <input type="number" name="min_stock" min="0" value="<?= (int)($product['min_stock'] ?? 3) ?>" dir="ltr">
      </div>
    </div>
    <label>توضیحات</label>
    <textarea name="description" rows="5" placeholder="ویژگی‌ها، جنس، سایز، شرایط ارسال..."><?= h((string)($product['description'] ?? '')) ?></textarea>
    <label>لینک تصویر (اختیاری)</label>
    <input type="text" name="image" class="mono" dir="ltr" value="<?= h((string)($product['image'] ?? '')) ?>">
    <?php if ($product): ?>
      <div class="hint mt">
        کد خرید: <code class="mono"><?= h((string)$product['code']) ?></code> — لینک تبلیغ: <code class="mono"><?= h(Store::buyLink($product)) ?></code>
      </div>
    <?php endif; ?>
    <div class="btn-group mt"><button class="btn" type="submit">💾 ذخیره</button>
      <a class="btn ghost" href="<?= h(url('shop/index.php', ['p' => 'products'])) ?>">انصراف</a></div>
  </form>
</div>
