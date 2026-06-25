<?php

function seo_meta(string $title, string $description = '', string $image = '', string $canonicalUrl = ''): string {
    static $base = 'https://teepsaa.com';
    $defaultDesc = 'Shop from local Phnom Penh businesses on teepsaa — fast delivery, authentic products.';

    $description = trim(strip_tags($description)) ?: $defaultDesc;
    if (mb_strlen($description) > 160) {
        $description = mb_substr($description, 0, 157) . '...';
    }

    if ($image) {
        $image = (strpos($image, 'http') === 0) ? $image : $base . '/uploads/' . $image;
    } else {
        $image = $base . '/images/og-default.png';
    }

    $url = $canonicalUrl ?: ($base . strtok($_SERVER['REQUEST_URI'] ?? '/', '?'));

    $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    return implode("\n    ", [
        '<meta name="description" content="' . $h($description) . '">',
        '<link rel="canonical" href="' . $h($url) . '">',
        '<meta property="og:title" content="' . $h($title) . '">',
        '<meta property="og:description" content="' . $h($description) . '">',
        '<meta property="og:image" content="' . $h($image) . '">',
        '<meta property="og:url" content="' . $h($url) . '">',
        '<meta property="og:site_name" content="teepsaa">',
        '<meta property="og:type" content="website">',
        '<meta name="twitter:card" content="summary_large_image">',
        '<meta name="twitter:title" content="' . $h($title) . '">',
        '<meta name="twitter:description" content="' . $h($description) . '">',
        '<meta name="twitter:image" content="' . $h($image) . '">',
    ]);
}
