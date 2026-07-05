<?php
require __DIR__ . '/config/db.php';

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>';

$base = 'https://teepsaa.com';

$products = $pdo->query('
    SELECT p.public_id, p.updated_at
    FROM products p
    JOIN businesses b ON b.id = p.business_id
    WHERE p.active = 1 AND p.archived = 0 AND b.approved = 1
    ORDER BY p.id ASC
')->fetchAll();

$businesses = $pdo->query('
    SELECT public_id, updated_at FROM businesses WHERE approved = 1 ORDER BY id ASC
')->fetchAll();

?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?= $base ?>/</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc><?= $base ?>/search/</loc>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= $base ?>/browse/</loc>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc><?= $base ?>/help/</loc>
        <changefreq>monthly</changefreq>
        <priority>0.4</priority>
    </url>
    <url>
        <loc><?= $base ?>/privacy/</loc>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>
    <url>
        <loc><?= $base ?>/terms/</loc>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>
    <?php foreach ($businesses as $b): ?>
    <url>
        <loc><?= $base ?>/business/?id=<?= $b['public_id'] ?></loc>
        <?php if ($b['updated_at']): ?><lastmod><?= date('Y-m-d', strtotime($b['updated_at'])) ?></lastmod><?php endif; ?>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    <?php endforeach; ?>
    <?php foreach ($products as $p): ?>
    <url>
        <loc><?= $base ?>/product/?id=<?= $p['public_id'] ?></loc>
        <?php if ($p['updated_at']): ?><lastmod><?= date('Y-m-d', strtotime($p['updated_at'])) ?></lastmod><?php endif; ?>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
    <?php endforeach; ?>
</urlset>
