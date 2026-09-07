<?php session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/markdown.php';

try {
    $page = $pdo->query("SELECT * FROM content_pages WHERE slug = 'returns'")->fetch();
} catch (PDOException $e) {
    $page = null;
}
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<?php
// The <h1> below prints this same row through pick_lang(), so the title in a
// search result always matches the heading on the page.
require_once __DIR__ . '/../config/seo.php';
$t = seo_t();
$seoTitle = $page ? pick_lang($page['title_en'], $page['title_km']) : $t['footer_returns'];

$headTitle = $seoTitle . ' — teepsaa';
$headDesc  = $t['seo_desc_returns'];
$headUrl   = 'https://teepsaa.com/returns/';
$headCss   = ['/returns/returns.css'];
require __DIR__ . '/../head/head.php';
?>
<body>
<?php require __DIR__ . '/../header/header.php'; ?>
<main>
    <div class="returns-wrap">
<?php if ($page): ?>
        <h1><?= htmlspecialchars(pick_lang($page['title_en'], $page['title_km'])) ?></h1>
        <?= render_markdown(pick_lang($page['body_en'], $page['body_km'])) ?>
<?php else: ?>
        <h1><?= htmlspecialchars($seoTitle) ?></h1>
        <p><?= $lang === 'km' ? 'មាតិកាមិនអាចប្រើប្រាស់បានទេនាពេលនេះ។' : 'This content is temporarily unavailable.' ?></p>
<?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
