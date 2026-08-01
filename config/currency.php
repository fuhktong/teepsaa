<?php

define('KHR_RATE', 4100);

function format_price(float $usd): string {
    $currency = $_SESSION['currency'] ?? 'USD';
    if ($currency === 'KHR') {
        return '៛' . number_format((int)round($usd * KHR_RATE));
    }
    return '$' . number_format($usd, 2);
}

// A sale is "% off" that runs until sale_ends_at. It applies to the base price
// and (in cart/checkout/product-page logic) to each variant's own price too.
function active_sale(array $p): bool {
    return isset($p['sale_percent'], $p['sale_ends_at'])
        && $p['sale_percent'] !== null
        && (int)$p['sale_percent'] > 0
        && $p['sale_ends_at'] !== null
        && strtotime($p['sale_ends_at']) > time();
}

// The discounted price for a given base amount (base price or a variant price).
function sale_price_for(float $base, array $p): float {
    return round($base * (100 - (int)$p['sale_percent']) / 100, 2);
}

function price_html(array $p): string {
    if (active_sale($p)) {
        return '<span class="price-sale">'     . format_price(sale_price_for((float)$p['price'], $p)) . '</span>'
             . '<span class="price-original">' . format_price((float)$p['price'])                     . '</span>';
    }
    return format_price((float)$p['price']);
}
