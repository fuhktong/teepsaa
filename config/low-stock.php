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
// Checkout's low_stock notification still checks products without variants
// only (checkout/confirm.php, api/v1/buyer/checkout-confirm.php).

if (!function_exists('low_stock_sql')) {
    /** An SQL condition, true for a low product. `$p` is the products alias. */
    function low_stock_sql(string $p = 'p'): string {
        return "(CASE WHEN EXISTS (SELECT 1 FROM product_variants lv WHERE lv.product_id = $p.id)
                      THEN EXISTS (SELECT 1 FROM product_variants lv WHERE lv.product_id = $p.id AND lv.stock <= $p.low_stock_threshold)
                      ELSE $p.stock <= $p.low_stock_threshold END)";
    }
}
