<?php
// When a product counts as running low — one rule for the vendor app's product
// list and its dashboard, so the two never disagree.
//
// Without variants: its stock is at or below its low_stock_threshold.
// With variants: the product's own stock means nothing (it lives in the
// variant rows), so any one option at or below the threshold counts. A sold-out
// option counts too: a buyer who wanted that size cannot have it.
//
// The threshold is the product's own, set on the product form (default 3).
//
// low_stock_alert() is checkout's half: after an order, tell the vendor when
// what was just bought has run low. For an option it is that option's stock
// that counts, and the message names it ("White Dress (Small)"). At most one
// alert per product per 24 hours (low_stock_notified_at), whichever option
// set it off. Used by checkout/confirm.php and api/v1/buyer/checkout-confirm.php;
// the caller has already required notify.php and mail.php.

if (!function_exists('low_stock_sql')) {
    /** An SQL condition, true for a low product. `$p` is the products alias. */
    function low_stock_sql(string $p = 'p'): string {
        return "(CASE WHEN EXISTS (SELECT 1 FROM product_variants lv WHERE lv.product_id = $p.id)
                      THEN EXISTS (SELECT 1 FROM product_variants lv WHERE lv.product_id = $p.id AND lv.stock <= $p.low_stock_threshold)
                      ELSE $p.stock <= $p.low_stock_threshold END)";
    }
}

if (!function_exists('low_stock_alert')) {
    function low_stock_alert(PDO $pdo, int $productId, ?int $variantId): void {
        $stmt = $pdo->prepare('
            SELECT p.id, p.public_id, p.name, p.low_stock_threshold,
                   ' . ($variantId ? 'pv.stock, pv.label' : 'p.stock, NULL AS label') . ',
                   v.id AS vendor_id, v.email AS vendor_email, v.name AS vendor_name
            FROM products p
            ' . ($variantId ? 'JOIN product_variants pv ON pv.product_id = p.id AND pv.id = ' . (int)$variantId : '') . '
            JOIN businesses b ON b.id = p.business_id
            JOIN vendors v ON v.id = b.user_id
            WHERE p.id = ?
              AND p.low_stock_threshold > 0
              AND (p.low_stock_notified_at IS NULL OR p.low_stock_notified_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
        ');
        $stmt->execute([$productId]);
        $lp = $stmt->fetch();
        if (!$lp || (int)$lp['stock'] > (int)$lp['low_stock_threshold']) return;

        $name     = $lp['name'] . ($lp['label'] !== null && $lp['label'] !== '' ? ' (' . $lp['label'] . ')' : '');
        $units    = (int)$lp['stock'];
        $unitWord = $units !== 1 ? 'units' : 'unit';
        notify($pdo, 'vendor', (int)$lp['vendor_id'], 'low_stock',
            'Low stock: "' . $name . '" — ' . $units . ' ' . $unitWord . ' remaining.',
            '/products/?action=edit&id=' . $lp['public_id'],
            ['name' => $name, 'units' => $units]
        );
        [$subj, $html] = render_email_template($pdo, 'low_stock', [
            'name'    => htmlspecialchars($lp['vendor_name']),
            'product' => htmlspecialchars($name),
            'units'   => $units,
            'cta_url' => 'https://teepsaa.com/products/?action=edit&id=' . $lp['public_id'],
        ]);
        if ($html !== '') send_email($lp['vendor_email'], $subj, $html);
        $pdo->prepare('UPDATE products SET low_stock_notified_at = NOW() WHERE id = ?')
            ->execute([$lp['id']]);
    }
}
