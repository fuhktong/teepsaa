<?php
// The list of addresses handed to Google, Bing and friends. Served as
// /sitemap.xml (rewritten in .htaccess) and named in robots.php.
//
// Only pages that are genuinely public and worth a search result belong
// here. Anything behind a login, anything a filter can generate infinitely
// many of (see /search/'s noindex), and the vendor/admin subdomains are all
// deliberately absent.
require __DIR__ . '/config/db.php';

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

// Every page teepsaa.com serves that isn't a product or a shop. Kept in one
// list so adding a page means adding one line here, not another XML block.
$staticPages = [
    ['/',          'daily',   '1.0'],
    ['/search/',   'daily',   '0.8'],
    ['/about/',    'monthly', '0.6'],
    ['/help/',     'monthly', '0.4'],
    ['/contact/',  'monthly', '0.4'],
    ['/shipping/', 'monthly', '0.4'],
    ['/returns/',  'monthly', '0.4'],
    ['/careers/',  'monthly', '0.4'],
    ['/privacy/',  'monthly', '0.3'],
    ['/terms/',    'monthly', '0.3'],
];

$x = fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');

?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <?php foreach ($staticPages as [$path, $freq, $priority]): ?>
    <url>
        <loc><?= $base . $path ?></loc>
        <changefreq><?= $freq ?></changefreq>
        <priority><?= $priority ?></priority>
    </url>
    <?php endforeach; ?>
    <?php foreach ($businesses as $b): ?>
    <url>
        <loc><?= $base ?>/business/?id=<?= $x($b['public_id']) ?></loc>
        <?php if ($b['modified_at']): ?><lastmod><?= date('Y-m-d', strtotime($b['modified_at'])) ?></lastmod><?php endif; ?>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
        <?php if ($b['banner']): ?>
        <image:image><image:loc><?= $base ?>/uploads/<?= $x($b['banner']) ?></image:loc></image:image>
        <?php endif; ?>
    </url>
    <?php endforeach; ?>
    <?php foreach ($products as $p): ?>
    <url>
        <loc><?= $base ?>/product/?id=<?= $x($p['public_id']) ?></loc>
        <?php if ($p['modified_at']): ?><lastmod><?= date('Y-m-d', strtotime($p['modified_at'])) ?></lastmod><?php endif; ?>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
        <?php if ($p['photo']): ?>
        <image:image><image:loc><?= $base ?>/uploads/<?= $x($p['photo']) ?></image:loc></image:image>
        <?php endif; ?>
    </url>
    <?php endforeach; ?>
</urlset>
