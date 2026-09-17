<?php
// Add or edit a product. The app's version of products/save.php, minus photos —
// uploading a file from the phone is a later step.
//
// Where this deliberately differs from the website: save.php lumps every bad
// field into one 'Invalid product data.' message and redirects. A form with no
// browser in front of it has to say which field is wrong, so this returns a
// 422 with a field-keyed 'fields' object.
//
// Everything else is copied on purpose — the leaf-category rule, the
// business-ownership check, the all-or-nothing sale pair, the variant sync and
// the stock-sum override. An endpoint is a form with no browser in front of it:
// every check the page does happens here too.
require __DIR__ . '/../../config/api.php';

api_require_method('POST');
$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];
$body   = api_body();

$action = $body['action'] ?? '';
if ($action !== 'add' && $action !== 'edit') {
    api_json(['error' => 'bad_action', 'allowed' => ['add', 'edit']], 400);
}

// ── The businesses this vendor may list under ────────────────────────
$stmt = $pdo->prepare('SELECT id FROM businesses WHERE user_id = ? AND approved = 1 AND suspended = 0');
$stmt->execute([$userId]);
$bizIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

if (empty($bizIds)) {
    api_json(['error' => 'no_business'], 409);
}
$ph = implode(',', array_fill(0, count($bizIds), '?'));

// ── The product being edited ─────────────────────────────────────────
$existing = null;
if ($action === 'edit') {
    $productId = (int)($body['id'] ?? 0);
    if ($productId <= 0) api_json(['error' => 'missing_id'], 400);

    $stmt = $pdo->prepare("SELECT id, public_id, business_id, archived FROM products WHERE id = ? AND business_id IN ($ph)");
    $stmt->execute(array_merge([$productId], $bizIds));
    $existing = $stmt->fetch();
    if (!$existing) api_json(['error' => 'not_found'], 404);
}

// ── Fields ───────────────────────────────────────────────────────────
$fields = [];

$name          = trim((string)($body['name'] ?? ''));
$nameKm        = trim((string)($body['name_km'] ?? ''));
$description   = trim((string)($body['description'] ?? ''));
$descriptionKm = trim((string)($body['description_km'] ?? ''));

if ($name === '')              $fields['name'] = 'Enter a product name.';
if (mb_strlen($name) > 255)    $fields['name'] = 'Keep the name under 255 characters.';
if (mb_strlen($nameKm) > 255)  $fields['name_km'] = 'Keep the Khmer name under 255 characters.';

// is_numeric, not (float). (float)'abc' is 0.0, which would silently save a
// typo as a free product.
$priceRaw = $body['price'] ?? '';
if (!is_numeric($priceRaw))        { $fields['price'] = 'Enter a price.'; $price = 0; }
elseif ((float)$priceRaw < 0)      { $fields['price'] = 'The price cannot be negative.'; $price = 0; }
elseif ((float)$priceRaw > 99999999.99) { $fields['price'] = 'That price is too high.'; $price = 0; }
else                               { $price = round((float)$priceRaw, 2); }

$stockRaw = $body['stock'] ?? 0;
if (!is_numeric($stockRaw) || (int)$stockRaw < 0) { $fields['stock'] = 'Enter a stock count of 0 or more.'; $stock = 0; }
else                                              { $stock = (int)$stockRaw; }

$deliveryMethod = in_array($body['delivery_method'] ?? '', ['bike', 'tuktuk'], true)
    ? $body['delivery_method'] : 'bike';

// On edit the business is fixed — the website disables that dropdown. Taking
// the caller's word for it would let a product be moved between businesses,
// which nothing on the website can do.
if ($action === 'edit') {
    $businessId = (int)$existing['business_id'];
} else {
    $businessId = (int)($body['business_id'] ?? 0);
    if (!in_array($businessId, $bizIds, true)) $fields['business_id'] = 'Pick one of your businesses.';
}

$categoryId = (int)($body['category_id'] ?? 0);
if ($categoryId <= 0) {
    $fields['category_id'] = 'Pick a category.';
} else {
    $leaf = $pdo->prepare('
        SELECT id FROM categories
         WHERE id = ?
           AND id NOT IN (SELECT DISTINCT parent_id FROM categories WHERE parent_id IS NOT NULL)
    ');
    $leaf->execute([$categoryId]);
    if (!$leaf->fetch()) $fields['category_id'] = 'Pick the most specific category, not a group.';
}

// ── The sale ─────────────────────────────────────────────────────────
// A sale needs both a percent and an end time. save.php drops the pair when
// either is missing, silently — the vendor sets 20% with no date and believes
// the sale is running. Same all-or-nothing rule here, but said out loud.
$salePercentRaw = trim((string)($body['sale_percent'] ?? ''));
$saleEndsRaw    = trim((string)($body['sale_ends_at'] ?? ''));
$salePercent    = null;
$saleEndsAt     = null;

if ($salePercentRaw !== '' || $saleEndsRaw !== '') {
    if (!ctype_digit($salePercentRaw) || (int)$salePercentRaw < 1 || (int)$salePercentRaw > 90) {
        $fields['sale_percent'] = 'A sale is between 1% and 90% off.';
    } elseif ($saleEndsRaw === '') {
        $fields['sale_ends_at'] = 'Pick when the sale ends.';
    } else {
        $ts = strtotime($saleEndsRaw);
        if ($ts === false)      $fields['sale_ends_at'] = 'That end time is not a date.';
        elseif ($ts <= time())  $fields['sale_ends_at'] = 'The end time has to be in the future.';
        else {
            $salePercent = (int)$salePercentRaw;
            $saleEndsAt  = date('Y-m-d H:i:s', $ts);
        }
    }
}

// ── Variants ─────────────────────────────────────────────────────────
// A variant with a blank label is skipped, exactly as the website does — its
// rows come from a repeating form where an empty one means "not filled in".
$variantsIn = is_array($body['variants'] ?? null) ? $body['variants'] : [];
$variants   = [];
foreach ($variantsIn as $i => $v) {
    if (!is_array($v)) continue;
    $label = trim((string)($v['label'] ?? ''));
    if ($label === '') continue;

    if (mb_strlen($label) > 100) { $fields["variants.$i.label"] = 'Keep the option name under 100 characters.'; }

    $vPriceRaw = $v['price'] ?? '';
    $vPrice    = null;
    if ($vPriceRaw !== '' && $vPriceRaw !== null) {
        if (!is_numeric($vPriceRaw) || (float)$vPriceRaw < 0) $fields["variants.$i.price"] = 'Enter a price of 0 or more, or leave it blank.';
        else $vPrice = round((float)$vPriceRaw, 2);
    }

    $vStockRaw = $v['stock'] ?? 0;
    if (!is_numeric($vStockRaw) || (int)$vStockRaw < 0) $fields["variants.$i.stock"] = 'Enter a stock count of 0 or more.';

    $variants[] = [
        'id'       => (int)($v['id'] ?? 0),
        'label'    => $label,
        'label_km' => trim((string)($v['label_km'] ?? '')) ?: null,
        'stock'    => max(0, (int)$vStockRaw),
        'price'    => $vPrice,
    ];
}

if ($fields) {
    api_json(['error' => 'invalid', 'fields' => $fields], 422);
}

// ── Write ────────────────────────────────────────────────────────────
$pdo->beginTransaction();
try {
    if ($action === 'add') {
        $publicId = uuid_v4();
        $pdo->prepare('
            INSERT INTO products
                (business_id, category_id, name, name_km, description, description_km,
                 price, stock, delivery_method, sale_percent, sale_ends_at, public_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ')->execute([
            $businessId, $categoryId, $name, $nameKm ?: null, $description, $descriptionKm ?: null,
            $price, $stock, $deliveryMethod, $salePercent, $saleEndsAt, $publicId,
        ]);
        $productId = (int)$pdo->lastInsertId();
    } else {
        $productId = (int)$existing['id'];
        $publicId  = $existing['public_id'];

        // 'active' is optional. A caller that leaves it out keeps whatever the
        // product already had — this endpoint is for the form, and hide/show is
        // product-action.php's job.
        $activeSql    = '';
        $activeParams = [];
        if (array_key_exists('active', $body)) {
            $activeSql    = ', active = ?';
            $activeParams = [!empty($body['active']) && (int)$existing['archived'] === 0 ? 1 : 0];
        }

        // sale_price = NULL is copied from save.php: it is a cached figure and a
        // stale one would keep showing the old sale after the percent changed.
        $pdo->prepare("
            UPDATE products
               SET category_id = ?, name = ?, name_km = ?, description = ?, description_km = ?,
                   price = ?, stock = ?, delivery_method = ?, sale_percent = ?, sale_ends_at = ?,
                   sale_price = NULL$activeSql
             WHERE id = ? AND business_id IN ($ph)
        ")->execute(array_merge(
            [$categoryId, $name, $nameKm ?: null, $description, $descriptionKm ?: null,
             $price, $stock, $deliveryMethod, $salePercent, $saleEndsAt],
            $activeParams,
            [$productId],
            $bizIds
        ));

        // Restocking has to clear the flag, or the vendor never gets a second
        // low-stock warning after this one runs down again.
        $pdo->prepare('UPDATE products SET low_stock_notified_at = NULL WHERE id = ? AND stock > low_stock_threshold')
            ->execute([$productId]);
    }

    // Variant sync, copied from save_variants(): update the ones that came back
    // with an id, insert the new ones, delete everything not submitted.
    $submittedIds = [];
    foreach ($variants as $i => $v) {
        if ($v['id'] > 0) {
            $pdo->prepare('
                UPDATE product_variants
                   SET label = ?, label_km = ?, stock = ?, price_override = ?, sort_order = ?
                 WHERE id = ? AND product_id = ?
            ')->execute([$v['label'], $v['label_km'], $v['stock'], $v['price'], $i, $v['id'], $productId]);
            $submittedIds[] = $v['id'];
        } else {
            $pdo->prepare('
                INSERT INTO product_variants (product_id, label, label_km, stock, price_override, sort_order)
                VALUES (?,?,?,?,?,?)
            ')->execute([$productId, $v['label'], $v['label_km'], $v['stock'], $v['price'], $i]);
            $submittedIds[] = (int)$pdo->lastInsertId();
        }
    }

    if ($submittedIds) {
        $vph = implode(',', array_fill(0, count($submittedIds), '?'));
        $pdo->prepare("DELETE FROM product_variants WHERE product_id = ? AND id NOT IN ($vph)")
            ->execute(array_merge([$productId], $submittedIds));
        // With variants, the product's own stock is the sum of theirs — the
        // number the vendor typed in the stock field is ignored, same as the
        // website. The form greys that field out once a variant exists.
        $pdo->prepare('
            UPDATE products p
               SET p.stock = (SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id)
             WHERE p.id = ?
        ')->execute([$productId]);
    } else {
        $pdo->prepare('DELETE FROM product_variants WHERE product_id = ?')->execute([$productId]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

// Read the stock back rather than echoing what was sent — with variants the
// sum above is the real number and the vendor's figure was thrown away.
$out = $pdo->prepare('SELECT stock, active, archived FROM products WHERE id = ?');
$out->execute([$productId]);
$saved = $out->fetch();

api_json([
    'ok'        => true,
    'action'    => $action,
    'id'        => $productId,
    'public_id' => $publicId,
    'stock'     => (int)$saved['stock'],
    'active'    => (bool)$saved['active'],
    'archived'  => (bool)$saved['archived'],
]);
