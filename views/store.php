<?php
/** @var array $products,$sellers,$campaigns,$categories,$stats,$sellerStats; @var int $total,$off,$limit; @var string $tab,$status,$q */
$base = ['p' => 'store', 'tab' => $tab];
?>
<div class="tabs mb">
  <a class="btn <?= $tab === 'products' ? '' : 'ghost' ?> sm" href="<?= h(url('panel/index.php', ['p' => 'store', 'tab' => 'products'])) ?>">📦 کالاها</a>
  <a class="btn <?= $tab === 'sellers' ? '' : 'ghost' ?> sm" href="<?= h(url('panel/index.php', ['p' => 'store', 'tab' => 'sellers'])) ?>">🏪 فروشندگان</a>
  <a class="btn <?= $tab === 'campaigns' ? '' : 'ghost' ?> sm" href="<?= h(url('panel/index.php', ['p' => 'store', 'tab' => 'campaigns'])) ?>">📣 کمپین تبلیغ</a>
  <a class="btn <?= $tab === 'categories' ? '' : 'ghost' ?> sm" href="<?= h(url('panel/index.php', ['p' => 'store', 'tab' => 'categories'])) ?>">🗂 دسته‌بندی</a>
  <a class="btn <?= $tab === 'coupons' ? '' : 'ghost' ?> sm" href="<?= h(url('panel/index.php', ['p' => 'store', 'tab' => 'coupons'])) ?>">🎟 کد تخفیف</a>
  <a class="btn <?= $tab === 'reviews' ? '' : 'ghost' ?> sm" href="<?= h(url('panel/index.php', ['p' => 'store', 'tab' => 'reviews'])) ?>">⭐ نظرات</a>
  <a class="btn <?= $tab === 'csv' ? '' : 'ghost' ?> sm" href="<?= h(url('panel/index.php', ['p' => 'store', 'tab' => 'csv'])) ?>">📄 CSV انبار</a>
  <a class="btn ghost sm" href="<?= h(url('shop/index.php')) ?>">👁 مشاهده فروشگاه</a>
</div>

<div class="grid g4 mb">
  <div class="kpi"><div class="i">📦</div><div class="v"><?= digits_fa((int)$stats['total']) ?></div><div class="l">کالا (<?= digits_fa((int)$stats['active']) ?> فعال، <?= digits_fa((int)$stats['pending']) ?> در انتظار)</div></div>
  <div class="kpi"><div class="i">📊</div><div class="v"><?= digits_fa((int)$stats['stock_sum']) ?></div><div class="l">موجودی کل انبار</div></div>
  <div class="kpi"><div class="i">💰</div><div class="v"><?= money((int)$stats['value'], '') ?></div><div class="l">ارزش انبار (تومان)</div></div>
  <div class="kpi"><div class="i">⚠️</div><div class="v"><?= digits_fa((int)$stats['low']) ?></div><div class="l">کالای کم‌موجود</div></div>
</div>

<?php if ($tab === 'products'): ?>
  <div class="card">
    <div class="card-header">
      <h2>📦 کالاهای انبار</h2>
      <form method="get" class="inline">
        <input type="hidden" name="p" value="store"><input type="hidden" name="tab" value="products">
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="جست‌وجو: عنوان/SKU/کد" style="width:180px">
        <select name="status" onchange="this.form.submit()">
          <option value="">همه وضعیت‌ها</option>
          <?php foreach (Store::STATUSES as $k => $l): ?>
            <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= h($l) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn ghost sm" type="submit">فیلتر</button>
      </form>
    </div>
    <?php if (!$products): ?>
      <div class="empty">کالایی با این فیلتر نیست.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>کد</th><th>عنوان</th><th>فروشنده</th><th>قیمت</th><th>موجودی</th><th>فروش</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): $avail = Store::available($p); ?>
          <tr>
            <td class="mono small"><?= h((string)$p['code']) ?></td>
            <td class="small"><?= h((string)$p['title']) ?><div class="muted mono"><?= h((string)($p['sku'] ?? '')) ?></div></td>
            <td class="small"><?= h((string)($p['shop_name'] ?? '—')) ?></td>
            <td class="small"><?= money(Store::price($p)) ?></td>
            <td class="small"><?= digits_fa($avail) ?>
              <?php if ($avail <= (int)$p['min_stock']): ?><span class="badge <?= $avail <= 0 ? 'b-err' : 'b-warn' ?>">کم</span><?php endif; ?>
            </td>
            <td class="small"><?= digits_fa((int)$p['sold_count']) ?></td>
            <td><span class="badge <?= $p['status'] === 'active' ? 'b-ok' : ($p['status'] === 'pending' ? 'b-warn' : 'b-mute') ?>"><?= h(Store::STATUSES[$p['status']] ?? $p['status']) ?></span></td>
            <td>
              <div class="btn-group">
                <?php if ($p['status'] !== 'active'): ?>
                  <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
                    <input type="hidden" name="a" value="product_status"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="status" value="active">
                    <button class="btn ok sm" type="submit">تایید</button>
                  </form>
                <?php endif; ?>
                <?php if ($p['status'] === 'active'): ?>
                  <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
                    <input type="hidden" name="a" value="product_status"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="status" value="archived">
                    <button class="btn ghost sm" type="submit">بایگانی</button>
                  </form>
                <?php endif; ?>
                <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline"><?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="product_stock"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <input type="number" name="delta" value="0" style="width:70px" dir="ltr" title="تغییر موجودی (+/-)">
                  <button class="btn ghost sm" type="submit">اعمال</button>
                </form>
                <?php if ($p['status'] === 'active'): ?>
                  <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
                    <input type="hidden" name="a" value="promo_create"><input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                    <button class="btn ghost sm" type="submit">📣 کمپین</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if ($total > $limit): ?>
        <div class="pager">
          <?php for ($i = 0; $i < min(20, (int)ceil($total / $limit)); $i++): ?>
            <a class="<?= $off == $i * $limit ? 'cur' : '' ?>" href="<?= h(url('panel/index.php', $base + ['status' => $status, 'q' => $q, 'off' => $i * $limit])) ?>"><?= digits_fa($i + 1) ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>➕ افزودن کالا به انبار (بدون فروشنده)</h2>
    <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g3">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="product_save">
      <div>
        <label>عنوان *</label><input type="text" name="title" required>
        <label>دسته</label>
        <select name="category_id"><option value="0">بدون دسته</option>
          <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h((string)$c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>قیمت (تومان) *</label><input type="number" name="price" min="0" required dir="ltr">
        <label>قیمت با تخفیف</label><input type="number" name="discount_price" min="0" dir="ltr">
        <label>موجودی</label><input type="number" name="stock" min="0" value="0" dir="ltr">
      </div>
      <div>
        <label>توضیحات</label><textarea name="description" rows="4"></textarea>
        <label class="check"><input type="checkbox" name="status" value="active" checked> فعال باشد</label>
      </div>
      <div style="align-self:end"><button class="btn" type="submit">💾 ثبت کالا</button></div>
    </form>
  </div>

<?php elseif ($tab === 'sellers'): ?>
  <div class="card">
    <h2>🏪 فروشندگان <span class="badge b-warn"><?= digits_fa((int)$sellerStats['pending']) ?> در انتظار</span></h2>
    <?php if (!$sellers): ?>
      <div class="empty">فروشنده‌ای ثبت نشده است.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>فروشگاه</th><th>کاربر</th><th>کالا</th><th>فروش</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($sellers as $s): ?>
          <tr>
            <td class="small"><b><?= h((string)$s['shop_name']) ?></b><div class="muted mono"><?= h((string)($s['phone'] ?? '')) ?></div></td>
            <td class="small"><?= h(trim(((string)($s['first_name'] ?? '')) . ' ' . ((string)($s['last_name'] ?? ''))) ?: (string)$s['user_id']) ?>
              <div class="muted mono small"><?= h((string)$s['user_id']) ?></div></td>
            <td class="small"><?= digits_fa((int)$s['products_count']) ?></td>
            <td class="small"><?= money((int)$s['revenue']) ?></td>
            <td><span class="badge <?= $s['status'] === 'approved' ? 'b-ok' : ($s['status'] === 'pending' ? 'b-warn' : 'b-err') ?>">
              <?= h(Seller::STATUSES[$s['status']] ?? $s['status']) ?></span></td>
            <td>
              <div class="btn-group">
                <?php if ($s['status'] !== 'approved'): ?>
                  <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
                    <input type="hidden" name="a" value="seller_status"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <input type="hidden" name="status" value="approved">
                    <button class="btn ok sm" type="submit">تایید</button>
                  </form>
                <?php else: ?>
                  <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
                    <input type="hidden" name="a" value="seller_status"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                    <input type="hidden" name="status" value="suspended">
                    <button class="btn danger sm" type="submit">تعلیق</button>
                  </form>
                <?php endif; ?>
                <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="seller_status"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <input type="hidden" name="status" value="pending">
                  <button class="btn ghost sm" type="submit">بازبینی</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'campaigns'): ?>
  <div class="card">
    <div class="card-header">
      <h2>📣 کمپین‌های تبلیغ کالا</h2>
      <span class="tag">از موتور صف ارسال استفاده می‌کند (نرخ‌سنجی + زمان‌بندی)</span>
    </div>
    <?php if (!$campaigns): ?>
      <div class="empty">کمپینی نساخته‌ای. از تب «کالاها» روی «📣 کمپین» بزن.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>کالا</th><th>مخاطب</th><th>هر</th><th>بعدی</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($campaigns as $c): ?>
          <tr>
            <td class="small"><?= digits_fa((int)$c['id']) ?></td>
            <td class="small"><?= h((string)($c['product_title'] ?? '—')) ?>
              <div class="mono muted"><?= h((string)($c['product_code'] ?? '')) ?></div></td>
            <td class="small"><?= h(Broadcast::audiences()[$c['audience']] ?? (string)$c['audience']) ?>
              <?php if (($c['mode'] ?? 'single') === 'rotate'): ?>
                <span class="badge b-info">🔄 چرخشی (<?= digits_fa(count(Store::parsePool((string)($c['rotate_pool'] ?? '')))) ?>)</span>
              <?php endif; ?>
            </td>
            <td class="small"><?= digits_fa((int)$c['interval_hours']) ?> ساعت</td>
            <td class="small"><?= (int)$c['next_run_at'] ? h(dt((int)$c['next_run_at'], 'Y/m/d H:i')) : '—' ?></td>
            <td><span class="badge <?= $c['status'] === 'active' ? 'b-ok' : 'b-mute' ?>">
              <?= h(['active' => 'فعال', 'paused' => 'متوقف', 'done' => 'تمام'][$c['status']] ?? $c['status']) ?></span></td>
            <td>
              <form method="post" action="<?= h(url('panel/index.php')) ?>"><?= Auth::csrfField() ?>
                <input type="hidden" name="a" value="promo_toggle">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <input type="hidden" name="active" value="<?= $c['status'] === 'active' ? '0' : '1' ?>">
                <button class="btn <?= $c['status'] === 'active' ? 'danger' : 'ok' ?> sm" type="submit">
                  <?= $c['status'] === 'active' ? 'توقف' : 'فعال‌سازی' ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
    <hr>
    <h3>کمپین جدید</h3>
    <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g2">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="promo_save">
      <div>
        <label>کالا</label>
        <select name="product_id" required>
          <?php foreach ($products as $p): ?>
            <?php if ($p['status'] !== 'active') { continue; } ?>
            <option value="<?= (int)$p['id'] ?>"><?= h((string)$p['title']) ?> (<?= digits_fa(Store::available($p)) ?> موجود)</option>
          <?php endforeach; ?>
        </select>
        <label>مخاطب</label>
        <select name="audience">
          <?php foreach (Broadcast::audiences() as $k => $l): ?><option value="<?= h($k) ?>"><?= h($l) ?></option><?php endforeach; ?>
        </select>
        <label>فاصله ارسال (ساعت)</label>
        <input type="number" name="interval_hours" value="24" min="1" max="720" dir="ltr">
        <label>حالت ارسال</label>
        <select name="mode">
          <option value="single">یک کالای ثابت</option>
          <option value="rotate">🔄 چرخش خودکار بین کالاها</option>
        </select>
        <label>استخر چرخش (آیدی کالاها، خالی = همه کالاهای فعال)</label>
        <input type="text" name="rotate_pool" class="mono" dir="ltr" placeholder="12,15,18">
        <div class="hint">در حالت چرخشی، هر بار نوبت کالای بعدی است و بعد از آخرین کالا به اول برمی‌گردد.</div>
      </div>
      <div>
        <label>قالب پیام</label>
        <textarea name="template" rows="6">🛍 <b>{title}</b>

{description}

💰 قیمت: {price}
📦 موجودی: {stock} {unit}

🛒 خرید: {link}</textarea>
        <div class="hint">متغیرها: {title} {description} {price} {old_price} {stock} {unit} {code} {link} {shop} {group}</div>
      </div>
      <div><button class="btn" type="submit">➕ ساخت کمپین</button></div>
    </form>
  </div>

<?php elseif ($tab === 'coupons'): ?>
  <?php $cst = Coupon::stats(); ?>
  <div class="grid g4 mb">
    <div class="kpi"><div class="i">🎟</div><div class="v"><?= digits_fa((int)$cst['total']) ?></div><div class="l">کل کدها</div></div>
    <div class="kpi"><div class="i">✅</div><div class="v"><?= digits_fa((int)$cst['active']) ?></div><div class="l">فعال</div></div>
    <div class="kpi"><div class="i">🔁</div><div class="v"><?= digits_fa((int)$cst['uses']) ?></div><div class="l">بار استفاده</div></div>
    <div class="kpi"><div class="i">💸</div><div class="v"><?= money((int)$cst['saved'], '') ?></div><div class="l">تخفیف داده‌شده</div></div>
  </div>

  <div class="card">
    <div class="card-header"><h2>🎟 کدهای تخفیف</h2>
      <span class="tag">درصدی یا مبلغ ثابت</span></div>
    <?php if (!$coupons): ?>
      <div class="empty">کدی ساخته نشده است.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>کد</th><th>نوع</th><th>مقدار</th><th>حداقل سبد</th><th>استفاده</th><th>محدوده</th><th>انقضا</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($coupons as $c): ?>
          <tr>
            <td class="mono"><b><?= h((string)$c['code']) ?></b></td>
            <td class="small"><?= $c['type'] === 'percent' ? 'درصدی' : 'مبلغ ثابت' ?></td>
            <td class="small"><?= $c['type'] === 'percent' ? digits_fa((int)$c['value']) . '٪' : money((int)$c['value']) ?></td>
            <td class="small"><?= (int)$c['min_total'] > 0 ? money((int)$c['min_total']) : '—' ?></td>
            <td class="small"><?= digits_fa((int)$c['used_count']) ?><?= (int)$c['max_uses'] > 0 ? ' / ' . digits_fa((int)$c['max_uses']) : '' ?>
              <div class="muted">هر کاربر: <?= digits_fa((int)$c['per_user']) ?></div></td>
            <td class="small"><?= h((string)($c['product_title'] ?? ($c['shop_name'] ?? 'همه کالاها'))) ?></td>
            <td class="small mono"><?= (int)$c['expires_at'] > 0 ? dt((int)$c['expires_at'], 'Y/m/d') : '—' ?></td>
            <td><span class="badge <?= (int)$c['is_active'] ? 'b-ok' : 'b-mute' ?>"><?= (int)$c['is_active'] ? 'فعال' : 'خاموش' ?></span></td>
            <td>
              <div class="btn-group">
                <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline"><?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="coupon_toggle">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <input type="hidden" name="on" value="<?= (int)$c['is_active'] ? '0' : '1' ?>">
                  <button class="btn ghost sm" type="submit"><?= (int)$c['is_active'] ? '⏸' : '▶️' ?></button>
                </form>
                <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline" onsubmit="return confirm('حذف شود؟')"><?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="coupon_delete">
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
    <hr>
    <h3>کد جدید</h3>
    <form method="post" action="<?= h(url('panel/index.php')) ?>" class="grid g3">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="a" value="coupon_save">
      <div>
        <label>کد (خالی = تصادفی)</label>
        <input type="text" name="code" class="mono" dir="ltr" placeholder="YALDA40">
      </div>
      <div>
        <label>نوع</label>
        <select name="type">
          <option value="percent">درصدی</option>
          <option value="fixed">مبلغ ثابت (تومان)</option>
        </select>
      </div>
      <div>
        <label>مقدار</label>
        <input type="number" name="value" min="1" required dir="ltr" placeholder="20">
      </div>
      <div>
        <label>حداقل مبلغ سبد (اختیاری)</label>
        <input type="number" name="min_total" min="0" value="0" dir="ltr">
      </div>
      <div>
        <label>سقف کل استفاده (۰ = بی‌نهایت)</label>
        <input type="number" name="max_uses" min="0" value="0" dir="ltr">
      </div>
      <div>
        <label>سقف هر کاربر</label>
        <input type="number" name="per_user" min="1" value="1" dir="ltr">
      </div>
      <div>
        <label>محدود به کالا (اختیاری)</label>
        <select name="product_id">
          <option value="0">همه کالاها</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= h((string)$p['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>محدود به فروشنده (اختیاری)</label>
        <select name="seller_id">
          <option value="0">همه فروشندگان</option>
          <?php foreach ($sellers as $sl): ?>
            <option value="<?= (int)$sl['id'] ?>"><?= h((string)$sl['shop_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>تاریخ انقضا (اختیاری)</label>
        <input type="text" name="expires_at" class="mono" dir="ltr" placeholder="2026-12-31">
      </div>
      <div><button class="btn" type="submit">➕ ساخت کد</button></div>
    </form>
  </div>

<?php elseif ($tab === 'reviews'): ?>
  <?php $rst = Reviews::stats(); ?>
  <div class="grid g4 mb">
    <div class="kpi"><div class="i">⭐</div><div class="v"><?= digits_fa((int)$rst['total']) ?></div><div class="l">کل نظرات</div></div>
    <div class="kpi"><div class="i">✅</div><div class="v"><?= digits_fa((int)$rst['approved']) ?></div><div class="l">تاییدشده</div></div>
    <div class="kpi"><div class="i">⏳</div><div class="v"><?= digits_fa((int)$rst['pending']) ?></div><div class="l">در انتظار بازبینی</div></div>
    <div class="kpi"><div class="i">📊</div><div class="v"><?= digits_fa((float)$rst['avg']) ?></div><div class="l">میانگین امتیاز</div></div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>⭐ نظرات خریداران</h2>
      <form method="get" class="inline">
        <input type="hidden" name="p" value="store"><input type="hidden" name="tab" value="reviews">
        <select name="rstatus" onchange="this.form.submit()">
          <option value="">همه</option>
          <?php foreach (['pending' => 'در انتظار', 'approved' => 'تاییدشده', 'rejected' => 'رد‌شده'] as $k => $l): ?>
            <option value="<?= $k ?>" <?= ($rstatus ?? '') === $k ? 'selected' : '' ?>><?= h($l) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
    <div class="rowline mb">
      <span>نمایش خودکار نظرات: <b><?= Reviews::autoApprove() ? 'روشن' : 'خاموش (نیاز به تایید مدیر)' ?></b></span>
      <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="a" value="store_reviews_auto">
        <input type="hidden" name="on" value="<?= Reviews::autoApprove() ? '0' : '1' ?>">
        <button class="btn ghost sm" type="submit">تغییر به <?= Reviews::autoApprove() ? 'بازبینی دستی' : 'نمایش خودکار' ?></button>
      </form>
    </div>
    <?php if (!$reviews): ?>
      <div class="empty">نظری ثبت نشده است.</div>
    <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>#</th><th>کالا</th><th>خریدار</th><th>امتیاز</th><th>متن</th><th>سفارش</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($reviews as $r): ?>
          <tr>
            <td class="small"><?= digits_fa((int)$r['id']) ?></td>
            <td class="small"><?= h((string)($r['product_title'] ?? '—')) ?><div class="mono muted"><?= h((string)($r['product_code'] ?? '')) ?></div></td>
            <td class="small"><?= h(trim((string)($r['first_name'] ?? '') . ' ' . (string)($r['last_name'] ?? '')) ?: ('#' . (int)$r['user_id'])) ?></td>
            <td class="small"><?= Reviews::stars((float)$r['rating']) ?></td>
            <td class="small"><?= h(mb_strimwidth((string)($r['comment'] ?? ''), 0, 70, '…')) ?></td>
            <td class="mono small"><?= h((string)($r['order_code'] ?? '')) ?></td>
            <td><span class="badge <?= $r['status'] === 'approved' ? 'b-ok' : ($r['status'] === 'pending' ? 'b-warn' : 'b-err') ?>">
              <?= ['approved' => 'تایید', 'pending' => 'در انتظار', 'rejected' => 'رد'][(string)$r['status']] ?? h((string)$r['status']) ?></span></td>
            <td>
              <div class="btn-group">
                <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline"><?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="review_moderate">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="status" value="approved">
                  <button class="btn ok sm" type="submit">✅</button>
                </form>
                <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline"><?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="review_moderate">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="status" value="rejected">
                  <button class="btn warn sm" type="submit">⛔</button>
                </form>
                <form method="post" action="<?= h(url('panel/index.php')) ?>" class="inline" onsubmit="return confirm('حذف شود؟')"><?= Auth::csrfField() ?>
                  <input type="hidden" name="a" value="review_delete">
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

<?php elseif ($tab === 'csv'): ?>
  <div class="grid g2">
    <div class="card">
      <h2>⬇️ خروجی CSV</h2>
      <div class="hint">همه کالاهای انبار با ستون‌های: <code class="mono"><?= h(implode(',', Store::csvColumns())) ?></code></div>
      <div class="btn-group mt">
        <a class="btn" href="<?= h(url('panel/download.php', ['p' => 'csv'])) ?>">📄 دانلود inventory.csv</a>
      </div>
    </div>
    <div class="card">
      <h2>⬆️ ورود CSV</h2>
      <form method="post" action="<?= h(url('panel/index.php')) ?>" enctype="multipart/form-data">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="a" value="inventory_import">
        <label>فایل CSV</label>
        <input type="file" name="csv" accept=".csv,text/csv" required>
        <div class="hint mt">
          اگر ستون <code class="mono">code</code> با کد کالای موجود بخواند، همان کالا به‌روزرسانی می‌شود
          (عنوان، قیمت، موجودی مطلق). ردیف بدون کد، کالای جدید می‌سازد.
        </div>
        <div class="btn-group mt"><button class="btn ok" type="submit">⬆️ بارگذاری</button></div>
      </form>
    </div>
  </div>

<?php else: ?>
  <div class="grid g2">
    <div class="card">
      <h2>🗂 دسته‌بندی‌ها</h2>
      <?php if (!$categories): ?><div class="empty">دسته‌ای نیست.</div><?php else: ?>
        <?php foreach ($categories as $c): ?>
          <div class="rowline"><span><?= h((string)$c['name']) ?> <span class="muted mono small"><?= h((string)$c['slug']) ?></span></span>
            <span class="badge <?= (int)$c['is_active'] ? 'b-ok' : 'b-mute' ?>"><?= (int)$c['is_active'] ? 'فعال' : 'غیرفعال' ?></span></div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="card">
      <h2>➕ دسته جدید</h2>
      <form method="post" action="<?= h(url('panel/index.php')) ?>">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="a" value="category_save">
        <label>نام</label><input type="text" name="name" required>
        <label>اسلاگ (اختیاری)</label><input type="text" name="slug" class="mono" dir="ltr">
        <label class="check"><input type="checkbox" name="is_active" value="1" checked> فعال</label>
        <div class="btn-group mt"><button class="btn" type="submit">ذخیره</button></div>
      </form>
    </div>
  </div>
<?php endif; ?>
