<?php

define('KHR_RATE', 4100);

function format_price(float $usd): string {
    $currency = $_SESSION['currency'] ?? 'USD';
    if ($currency === 'KHR') {
        return '៛' . number_format((int)round($usd * KHR_RATE));
    }
    return '$' . number_format($usd, 2);
}

function active_sale(array $p): bool {
    return isset($p['sale_price'], $p['sale_ends_at'])
        && $p['sale_price'] !== null
        && $p['sale_ends_at'] !== null
        && strtotime($p['sale_ends_at']) > time();
}

function price_html(array $p): string {
    if (active_sale($p)) {
        return '<span class="price-sale">'     . format_price((float)$p['sale_price']) . '</span>'
             . '<span class="price-original">' . format_price((float)$p['price'])      . '</span>';
    }
    return format_price((float)$p['price']);
}
