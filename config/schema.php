<?php

// Structured data — the hidden block that lets a Google result show
// "★4.6 · 12 reviews · $18.00 · In stock" instead of a plain blue link.
// Written in JSON-LD, invisible to visitors, read directly by Google.
//
// Every number here is already on the page. This file is packaging, not new
// work: it takes the same rows the page renders and restates them in the
// vocabulary at schema.org. Where the visible page and this block disagree,
// Google treats it as a violation — so each builder below is fed the same
// variables the page itself renders from, never a second query.
//
// Companion to config/seo.php; both are required from a page's <head>.

require_once __DIR__ . '/seo.php';

// Your public profiles, once they exist. Google uses these to connect the
// site to its social accounts in the brand panel on the right of a result.
// The footer's three icons are still href="#" placeholders — fill these in
// (e.g. 'https://www.facebook.com/teepsaa') and the footer at the same time.
// Left empty the key is simply omitted, which is correct; a made-up or "#"
// address is worse than none.
const SCHEMA_SOCIAL = [
    // 'https://www.facebook.com/...',
    // 'https://www.instagram.com/...',
    // 'https://t.me/...',
];

// ── Emitting ─────────────────────────────────────────────────────────

// Wraps a block in its <script> tag. Nothing else in the codebase should
// build that tag by hand — the escaping below is the whole reason this
// function exists.
function schema_json(array $data): string {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return '';

    // A product description containing the literal text "</script>" would
    // otherwise close the tag early and dump the rest of the block into the
    // page as visible text. "<\/" is the JSON escape for the same character,
    // so Google reads an identical string and the browser sees no tag.
    $json = str_replace('</', '<\/', $json);

    return '<script type="application/ld+json">' . $json . '</script>';
}

// Several blocks nest others (a product page carries a Product *and* a
// BreadcrumbList). Emitting them as one @graph is tidier than three separate
// tags and is what Google's own examples do.
function schema_graph(array ...$blocks): string {
    $blocks = array_values(array_filter($blocks));
    if (!$blocks) return '';
    if (count($blocks) === 1) return schema_json($blocks[0]);

    foreach ($blocks as &$b) unset($b['@context']);
    unset($b);
    return schema_json(['@context' => 'https://schema.org', '@graph' => $blocks]);
}

// ── Shared pieces ────────────────────────────────────────────────────

// The absolute address of a page, in the language currently rendering, so
// the block always names the same address as the canonical tag above it.
function schema_url(string $path): string {
    return seo_url(seo_path($path), current_lang());
}

function schema_img(string $filename): string {
    return 'https://teepsaa.com/uploads/' . rawurlencode($filename);
}

// Prices are stored in USD and converted for display, so the hidden price
// has to follow whatever the visitor is actually seeing — quoting dollars on
// a page showing riel is the mismatch Google penalises. Mirrors the branch
// in format_price() (config/currency.php).
function schema_price(float $usd): array {
    if (($_SESSION['currency'] ?? 'USD') === 'KHR') {
        return [(string)(int)round($usd * KHR_RATE), 'KHR'];
    }
    return [number_format($usd, 2, '.', ''), 'USD'];
}

// Drops empty strings, empty arrays and nulls. Schema.org would rather a
// property be absent than present and blank.
function schema_clean(array $data): array {
    foreach ($data as $k => $v) {
        if (is_array($v) && $k !== '@graph') {
            $v = schema_clean($v);
            if (!$v) { unset($data[$k]); continue; }
            $data[$k] = $v;
        } elseif ($v === null || $v === '' || $v === []) {
            unset($data[$k]);
        }
    }
    return $data;
}

function schema_text(?string $s, int $max = 5000): string {
    $s = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$s)));
    return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
}

// Star ratings, and the one rule that must never be relaxed.
//
// Claiming a rating a product doesn't have is a manual-action offence, and
// the penalty is not confined to the offending page — Google can switch off
// rich results across the whole domain. At launch nearly every product has
// zero reviews, so this guard fires constantly and by design. Returns null,
// and schema_clean() then removes the key entirely.
function schema_rating(float $avg, int $count): ?array {
    if ($count < 1 || $avg <= 0) return null;
    return [
        '@type'       => 'AggregateRating',
        'ratingValue' => (string)round($avg, 1),
        'reviewCount' => $count,
        'bestRating'  => '5',
        'worstRating' => '1',
    ];
}

// ── Blocks ───────────────────────────────────────────────────────────

// The site itself: name, logo, social profiles. Homepage only — repeating it
// on every page tells Google nothing new.
function schema_organization(): array {
    return schema_clean([
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        '@id'      => 'https://teepsaa.com/#organization',
        'name'     => 'teepsaa',
        'url'      => 'https://teepsaa.com/',
        'logo'     => [
            '@type'  => 'ImageObject',
            'url'    => 'https://teepsaa.com/images/teepsaa-icon-512.png',
            'width'  => 512,
            'height' => 512,
        ],
        'sameAs' => array_values(SCHEMA_SOCIAL),
    ]);
}

// Tells Google how searching teepsaa works, which is what lets it put a
// search box for your site inside its own results page.
function schema_website(): array {
    return schema_clean([
        '@context'        => 'https://schema.org',
        '@type'           => 'WebSite',
        '@id'             => 'https://teepsaa.com/#website',
        'name'            => 'teepsaa',
        'url'             => 'https://teepsaa.com/',
        'inLanguage'      => current_lang(),
        'publisher'       => ['@id' => 'https://teepsaa.com/#organization'],
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => 'https://teepsaa.com/search/?q={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ]);
}

// A product, with its price, stock and — only when it has them — its stars.
//
// $photos is the gallery rows as product/index.php fetched them; $variants
// likewise. Variants matter because a product with per-variant prices has a
// range, not a price, and stating a single figure that isn't the one on the
// page is exactly the mismatch Google flags.
function schema_product(array $product, array $photos, float $avgRating, int $reviewCount, array $variants = []): array {
    $onSale = active_sale($product);
    $priced = fn(float $base): float => $onSale ? sale_price_for($base, $product) : $base;

    // Every price the page can show: the base, plus any variant that
    // overrides it.
    $amounts = [];
    foreach ($variants as $v) {
        if ($v['price_override'] !== null && $v['price_override'] !== '') {
            $amounts[] = $priced((float)$v['price_override']);
        }
    }
    if (!$amounts) $amounts[] = $priced((float)$product['price']);

    // With variants the base row's stock column is unused — the page reads
    // each variant's own (see $outOfStock in product/index.php).
    $inStock = $variants
        ? (bool)array_filter($variants, fn($v) => (int)$v['stock'] > 0)
        : (int)$product['stock'] > 0;

    $url          = schema_url(product_path($product));
    $availability = 'https://schema.org/' . ($inStock ? 'InStock' : 'OutOfStock');
    $seller       = [
        '@type' => 'Organization',
        'name'  => pick_lang($product['business_name'] ?? '', $product['business_name_km'] ?? null),
    ];

    if (count(array_unique($amounts)) > 1) {
        [$low]  = schema_price(min($amounts));
        [$high, $currency] = schema_price(max($amounts));
        $offers = [
            '@type'         => 'AggregateOffer',
            'url'           => $url,
            'lowPrice'      => $low,
            'highPrice'     => $high,
            'priceCurrency' => $currency,
            'offerCount'    => count($amounts),
            'availability'  => $availability,
            'seller'        => $seller,
        ];
    } else {
        [$price, $currency] = schema_price($amounts[0]);
        $offers = [
            '@type'         => 'Offer',
            'url'           => $url,
            'price'         => $price,
            'priceCurrency' => $currency,
            'availability'  => $availability,
            'seller'        => $seller,
        ];
        // On sale, say when the price reverts, so Google stops advertising
        // the discount the moment it ends rather than on its next crawl.
        if ($onSale) {
            $offers['priceValidUntil'] = date('Y-m-d', strtotime($product['sale_ends_at']));
        }
    }

    return schema_clean([
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        'name'        => schema_text(lang_field($product, 'name'), 200),
        'description' => schema_text(lang_field($product, 'description')),
        'image'       => array_values(array_map(fn($r) => schema_img($r['filename']), $photos)),
        'sku'         => $product['public_id'],
        'url'         => $url,
        'offers'      => $offers,
        'aggregateRating' => schema_rating($avgRating, $reviewCount),
    ]);
}

// A shop. Store (rather than plain Organization) is what makes the page
// eligible for local, "near me" style results — hence the address and the
// map coordinates, which the vendor already supplied at sign-up.
function schema_store(array $business, float $avgRating, int $reviewCount, string $photo = ''): array {
    $street = trim(implode(' ', array_filter([
        $business['house_number'] ?? '',
        $business['address']      ?? '',
    ])));

    $address = schema_clean([
        '@type'           => 'PostalAddress',
        'streetAddress'   => schema_text($street, 200),
        'addressLocality' => schema_text($business['sangkat'] ?? '', 100),
        'addressRegion'   => schema_text($business['khan'] ?? '', 100),
        'addressCountry'  => 'KH',
    ]);
    // A locality is the minimum a PostalAddress needs to mean anything; fall
    // back to the city when the vendor left sangkat blank.
    if (!isset($address['addressLocality']) && !empty($business['city'])) {
        $address['addressLocality'] = schema_text($business['city'], 100);
    }

    $geo = (isset($business['lat'], $business['lng']) && (float)$business['lat'] !== 0.0)
        ? ['@type' => 'GeoCoordinates',
           'latitude'  => (float)$business['lat'],
           'longitude' => (float)$business['lng']]
        : null;

    return schema_clean([
        '@context'    => 'https://schema.org',
        '@type'       => 'Store',
        'name'        => schema_text(lang_field($business, 'name'), 200),
        'description' => schema_text(lang_field($business, 'description')),
        'image'       => $photo ? schema_img($photo) : '',
        'url'         => schema_url(business_path($business)),
        'address'     => $address,
        'geo'         => $geo,
        'parentOrganization' => ['@id' => 'https://teepsaa.com/#organization'],
        'aggregateRating'    => schema_rating($avgRating, $reviewCount),
    ]);
}

// The Home › Bags › Silk Krama trail. $crumbs is an ordered list of
// [label, path] pairs, the last being the current page (empty path). Google
// renders this in place of the raw address — a real gain here, where every
// address is a UUID nobody can read.
//
// A list rather than a label => path map on purpose: a product named the
// same as its own category would silently collapse two crumbs into one.
function schema_breadcrumb(array $crumbs): array {
    $items = [];
    $pos   = 0;
    foreach ($crumbs as [$label, $path]) {
        $label = schema_text((string)$label, 200);
        if ($label === '') continue;
        $items[] = schema_clean([
            '@type'    => 'ListItem',
            'position' => ++$pos,
            'name'     => $label,
            'item'     => $path !== '' ? schema_url($path) : '',
        ]);
    }
    if (count($items) < 2) return [];

    return [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    ];
}

// The help page's questions, so Google can show them inside the result and
// take up more of the screen. $sections is help/index.php's $faqs exactly as
// it builds it: section name => list of ['q' => ..., 'a' => ...].
function schema_faq(array $sections): array {
    $items = [];
    foreach ($sections as $faqs) {
        foreach ($faqs as $faq) {
            $q = schema_text($faq['q'] ?? '', 300);
            $a = schema_text($faq['a'] ?? '');
            if ($q === '' || $a === '') continue;
            $items[] = [
                '@type'          => 'Question',
                'name'           => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
            ];
        }
    }
    if (!$items) return [];

    return [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $items,
    ];
}
