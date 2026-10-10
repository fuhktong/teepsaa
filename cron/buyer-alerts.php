<?php
// Alerts a buyer asked for without having to ask — run every 15 minutes:
//
//   */15 * * * *  /opt/alt/php83/usr/bin/php /home/u767733958/domains/teepsaa.com/public_html/cron/buyer-alerts.php
//
// 1. Saved items (wishlists). Back in stock, or cheaper than when the buyer
//    was last told. Each wishlist row remembers the price and stock it last
//    saw (alert_price, alert_in_stock); a new row is only recorded on its
//    first pass, so saving an item never sends anything by itself. At most
//    one alert per saved item per day: a vendor nudging a price down three
//    times in an afternoon is one message, sent once the day is up.
//
// 2. Followed shops (business_follows). Live products newer than the last one
//    the buyer was told about — one notification per shop per pass, naming
//    the product when there is one and counting them when there are more.
//
// Checked by comparing what is there now with what was seen last time, not by
// hooking every place a price or a stock count changes — the website's
// product form, the vendor app, variants, cancelled orders putting stock back.
// A missed hook would fail silently; this cannot miss one.
//
// In-app notification and push only, never email: these are things the buyer
// chose to watch, and a mailbox is the wrong place for "$2 off".
//
// Prices are shown in dollars whatever the buyer's currency setting, as the
// stored English text of every notification is.

// db.php brings currency.php (active_sale) and i18n.php, which brings slug.php
// (product_path) — requiring either again would redeclare its functions.
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/notify.php';

const ALERT_GAP_HOURS = 24;

// ── 1. Saved items ───────────────────────────────────────────────────

$rows = $pdo->query('
    SELECT w.id, w.buyer_user_id, w.alert_price, w.alert_in_stock,
           (w.alert_sent_at IS NOT NULL AND w.alert_sent_at > NOW() - INTERVAL ' . ALERT_GAP_HOURS . ' HOUR) AS recent,
           p.public_id, p.name, p.name_km, p.price, p.sale_percent, p.sale_ends_at, p.stock,
           (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) AS variant_count,
           (SELECT COALESCE(SUM(pv.stock), 0) FROM product_variants pv WHERE pv.product_id = p.id) AS variant_stock
      FROM wishlists w
      JOIN products p   ON p.id = w.product_id AND p.active = 1 AND p.archived = 0
      JOIN businesses b ON b.id = p.business_id AND b.approved = 1 AND b.suspended = 0
      JOIN buyers bu    ON bu.id = w.buyer_user_id AND bu.deleted_at IS NULL AND bu.suspended = 0
')->fetchAll();

$remember = $pdo->prepare('UPDATE wishlists SET alert_price = ?, alert_in_stock = ? WHERE id = ?');
$sent     = $pdo->prepare('UPDATE wishlists SET alert_price = ?, alert_in_stock = ?, alert_sent_at = NOW() WHERE id = ?');

foreach ($rows as $r) {
    $price   = active_sale($r) ? sale_price_for((float)$r['price'], $r) : round((float)$r['price'], 2);
    $inStock = (int)$r['variant_count'] > 0 ? (int)$r['variant_stock'] > 0 : (int)$r['stock'] > 0;

    if ($r['alert_price'] === null) {
        $remember->execute([$price, (int)$inStock, $r['id']]);
        continue;
    }

    $was     = (float)$r['alert_price'];
    $wasIn   = (int)$r['alert_in_stock'] === 1;
    $type    = null;
    if ($inStock && !$wasIn)                  $type = 'back_in_stock';
    elseif ($inStock && $price < $was - 0.004) $type = 'price_drop';

    if ($type && $r['recent']) continue;   // keep the old baseline; tell them tomorrow

    if ($type) {
        $data = ['name' => $r['name'], 'name_km' => $r['name_km'], 'price' => $price, 'was' => $was];
        $message = $type === 'back_in_stock'
            ? sprintf('Back in stock: "%s".', $r['name'])
            : sprintf('Price drop: "%s" is now $%s (was $%s).', $r['name'], number_format($price, 2), number_format($was, 2));
        notify($pdo, 'buyer', (int)$r['buyer_user_id'], $type, $message, product_path($r), $data);
        $sent->execute([$price, (int)$inStock, $r['id']]);
        continue;
    }

    // Nothing to say. Follow the price up, or the stock out, so the next drop
    // or restock is measured from here.
    if (abs($price - $was) > 0.004 || $inStock !== $wasIn) {
        $remember->execute([$price, (int)$inStock, $r['id']]);
    }
}

// ── 2. Followed shops ────────────────────────────────────────────────

$follows = $pdo->query('
    SELECT f.id, f.buyer_user_id, f.business_id, f.seen_product_id,
           b.public_id AS business_public_id, b.name AS business_name, b.name_km AS business_name_km
      FROM business_follows f
      JOIN businesses b ON b.id = f.business_id AND b.approved = 1 AND b.suspended = 0
      JOIN buyers bu    ON bu.id = f.buyer_user_id AND bu.deleted_at IS NULL AND bu.suspended = 0
')->fetchAll();

$fresh = $pdo->prepare('
    SELECT id, public_id, name, name_km
      FROM products
     WHERE business_id = ? AND id > ? AND active = 1 AND archived = 0
     ORDER BY id DESC
');
$seen = $pdo->prepare('UPDATE business_follows SET seen_product_id = ? WHERE id = ?');

foreach ($follows as $f) {
    $fresh->execute([$f['business_id'], $f['seen_product_id']]);
    $new = $fresh->fetchAll();
    if (!$new) continue;

    $count = count($new);
    $data  = [
        'shop'    => $f['business_name'],
        'shop_km' => $f['business_name_km'],
        'count'   => $count,
        'name'    => $new[0]['name'],
        'name_km' => $new[0]['name_km'],
    ];
    if ($count === 1) {
        $message = sprintf('New from %s: "%s".', $f['business_name'], $new[0]['name']);
        $link    = product_path($new[0]);
    } else {
        $message = sprintf('%s added %d new products.', $f['business_name'], $count);
        $link    = business_path(['public_id' => $f['business_public_id'], 'name' => $f['business_name']]);
    }
    notify($pdo, 'buyer', (int)$f['buyer_user_id'], 'shop_new_products', $message, $link, $data);
    $seen->execute([(int)$new[0]['id'], $f['id']]);
}
