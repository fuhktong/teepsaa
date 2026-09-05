<?php

// Everything a page puts in its <head> for search engines and link previews:
// the description, the canonical address, the hreflang pair, and the
// Facebook/Twitter share tags.

// A page's <head> runs before header.php loads $t, so any page wanting a
// translated title or meta description needs the strings earlier than that.
// Returns the same array header.php would; it checks isset($t) itself, so
// calling this first costs nothing.
function seo_t(): array {
    static $strings = null;
    if ($strings === null) {
        $lang = current_lang();
        $strings = require __DIR__ . '/../lang/' . ($lang === 'km' ? 'km' : 'en') . '.php';
    }
    return $strings;
}

// The address of a page with the language stripped out — "which page is
// this", independently of which language it happens to be showing. Accepts a
// full https:// address (what most callers pass) or a bare path, and falls
// back to the address actually being requested.
function seo_path(string $urlOrPath = ''): string {
    $raw   = $urlOrPath !== '' ? $urlOrPath : ($_SERVER['REQUEST_URI'] ?? '/');
    $parts = parse_url($raw);
    $path  = $parts['path'] ?? '/';

    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
        // `lang` names the language, not the page. Dropping it here is what
        // makes ?lang=km and the bare address agree on one canonical.
        unset($query['lang']);
    }
    return $path . ($query ? '?' . http_build_query($query) : '');
}

// The full address of one page in one language. Khmer is the default, so it
// keeps the bare address and English adds ?lang=en — see current_lang() in
// config/i18n.php for why the language is in the address at all.
function seo_url(string $path, string $lang): string {
    $url = 'https://teepsaa.com' . $path;
    if ($lang === DEFAULT_LANG) return $url;
    return $url . (str_contains($path, '?') ? '&' : '?') . 'lang=' . $lang;
}

// $canonicalUrl names the page (in either language — the language part is
// stripped and re-applied). $alternates emits the hreflang pair; pass false
// on a noindex page, where telling Google about translations of a page it
// has been told to ignore only muddies the signal.
function seo_meta(string $title, string $description = '', string $image = '', string $canonicalUrl = '', bool $alternates = true): string {
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

    $path = seo_path($canonicalUrl);
    $url  = seo_url($path, current_lang());

    $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    $tags = [
        '<meta name="description" content="' . $h($description) . '">',
        '<link rel="canonical" href="' . $h($url) . '">',
    ];

    if ($alternates) {
        // Tells Google these two addresses are the same page in two
        // languages, so it shows the Khmer one to a Khmer searcher and the
        // English one to an English searcher instead of treating them as
        // duplicates and picking one. Both versions list both, which is the
        // reciprocity Google requires. x-default is what a searcher in
        // neither language gets — Khmer, since that's who the site is for.
        $tags[] = '<link rel="alternate" hreflang="km" href="' . $h(seo_url($path, 'km')) . '">';
        $tags[] = '<link rel="alternate" hreflang="en" href="' . $h(seo_url($path, 'en')) . '">';
        $tags[] = '<link rel="alternate" hreflang="x-default" href="' . $h(seo_url($path, DEFAULT_LANG)) . '">';
    }

    return implode("\n    ", array_merge($tags, [
        '<meta property="og:title" content="' . $h($title) . '">',
        '<meta property="og:description" content="' . $h($description) . '">',
        '<meta property="og:image" content="' . $h($image) . '">',
        '<meta property="og:url" content="' . $h($url) . '">',
        '<meta property="og:locale" content="' . (current_lang() === 'km' ? 'km_KH' : 'en_US') . '">',
        '<meta property="og:site_name" content="teepsaa">',
        '<meta property="og:type" content="website">',
        '<meta name="twitter:card" content="summary_large_image">',
        '<meta name="twitter:title" content="' . $h($title) . '">',
        '<meta name="twitter:description" content="' . $h($description) . '">',
        '<meta name="twitter:image" content="' . $h($image) . '">',
    ]));
}
