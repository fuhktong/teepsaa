<?php session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/markdown.php';

try {
    $page = $pdo->query("SELECT * FROM content_pages WHERE slug = 'shipping'")->fetch();
} catch (PDOException $e) {
    $page = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/shipping/shipping.css">
</head>
<body>
<?php require __DIR__ . '/../header/header.php'; ?>
<main>
    <div class="shipping-wrap">
<?php if ($page): ?>
        <h1><?= htmlspecialchars(pick_lang($page['title_en'], $page['title_km'])) ?></h1>
        <?= render_markdown(pick_lang($page['body_en'], $page['body_km'])) ?>
<?php else: ?>
        <h1><?= $lang === 'km' ? 'ការដឹកជញ្ជូន' : 'Shipping' ?></h1>
        <p><?= $lang === 'km' ? 'មាតិកាមិនអាចប្រើប្រាស់បានទេនាពេលនេះ។' : 'This content is temporarily unavailable.' ?></p>
<?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
