<?php
// The list of addresses handed to Google, Bing and friends. Served as
// /sitemap.xml (rewritten in .htaccess) and named in robots.php.
//
// Every page appears twice — once in Khmer (the bare address) and once in
// English (?lang=en) — and each entry names both, which is how Google learns
// they're one page in two languages rather than two duplicates competing with
// each other. See current_lang() in config/i18n.php.
//
// Only pages that are genuinely public and worth a search result belong here.
// Anything behind a login, anything a filter can generate infinitely many of
// (see /search/'s noindex), and the vendor/admin subdomains are all
// deliberately absent.
require __DIR__ . '/config/db.php';
require __DIR__ . '/config/seo.php';

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';

$base = 'https://teepsaa.com';

// <lastmod> should say when the page's content last changed. products and
// businesses only gained an updated_at column in
// database/migration-seo-updated-at.sql, and that file is hand-applied on
// the server, so check before relying on it rather than crashing the whole
// sitemap on a database where it hasn't been run yet.
$hasUpdated = function (string $table) use ($pdo): bool {
    try {
        return (bool)$pdo->query("SHOW COLUMNS FROM `$table` LIKE 'updated_at'")->fetch();
    } catch (PDOException $e) {
        return false;
    }
};
$pModified = $hasUpdated('products')   ? 'COALESCE(p.updated_at, p.created_at)' : 'p.created_at';
$bModified = $hasUpdated('businesses') ? 'COALESCE(updated_at, created_at)'     : 'created_at';

$products = $pdo->query("
    SELECT p.public_id,
           $pModified AS modified_at,
           (SELECT filename FROM product_photos WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS photo
    FROM products p
    JOIN businesses b ON b.id = p.business_id
    WHERE p.active = 1 AND p.archived = 0 AND b.approved = 1 AND b.suspended = 0
    ORDER BY p.id ASC
")->fetchAll();

$businesses = $pdo->query("
    SELECT public_id, $bModified AS modified_at, banner
    FROM businesses
    WHERE approved = 1 AND suspended = 0
    ORDER BY id ASC
")->fetchAll();

// One flat list of every address worth crawling: [path, changefreq,
// priority, last modified, primary image]. Built here so the XML below is
// a single loop rather than three near-identical blocks.
$pages = [
    ['/',          'daily',   '1.0', null, null],
    ['/search/',   'daily',   '0.8', null, null],
    ['/about/',    'monthly', '0.6', null, null],
    ['/help/',     'monthly', '0.4', null, null],
    ['/contact/',  'monthly', '0.4', null, null],
    ['/shipping/', 'monthly', '0.4', null, null],
    ['/returns/',  'monthly', '0.4', null, null],
    ['/careers/',  'monthly', '0.4', null, null],
    ['/privacy/',  'monthly', '0.3', null, null],
    ['/terms/',    'monthly', '0.3', null, null],
];
foreach ($businesses as $b) {
    $pages[] = ['/business/?id=' . $b['public_id'], 'weekly', '0.7', $b['modified_at'], $b['banner']];
}
foreach ($products as $p) {
    $pages[] = ['/product/?id=' . $p['public_id'], 'weekly', '0.6', $p['modified_at'], $p['photo']];
}

$x = fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');

?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <?php foreach ($pages as [$path, $freq, $priority, $modified, $image]): ?>
    <?php foreach (['km', 'en'] as $lang): ?>
    <url>
        <loc><?= $x(seo_url($path, $lang)) ?></loc>
        <xhtml:link rel="alternate" hreflang="km" href="<?= $x(seo_url($path, 'km')) ?>"/>
        <xhtml:link rel="alternate" hreflang="en" href="<?= $x(seo_url($path, 'en')) ?>"/>
        <xhtml:link rel="alternate" hreflang="x-default" href="<?= $x(seo_url($path, DEFAULT_LANG)) ?>"/>
        <?php if ($modified): ?><lastmod><?= date('Y-m-d', strtotime($modified)) ?></lastmod><?php endif; ?>
        <changefreq><?= $freq ?></changefreq>
        <priority><?= $priority ?></priority>
        <?php if ($image): ?>
        <?php // rawurlencode first: uploads are hex-named today, but a filename
              // with a space would otherwise emit an address that isn't valid. ?>
        <image:image><image:loc><?= $base ?>/uploads/<?= $x(rawurlencode($image)) ?></image:loc></image:image>
        <?php endif; ?>
    </url>
    <?php endforeach; ?>
    <?php endforeach; ?>
</urlset>
