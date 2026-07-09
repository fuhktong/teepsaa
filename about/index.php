<?php session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);
require __DIR__ . '/../config/subdomain.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/about/about.css">
</head>
<body>
<?php require __DIR__ . '/../header/header.php'; ?>
<main>
    <div class="about-wrap">
        <h1><?= $t['about_title'] ?></h1>
        <p class="about-lead"><?= $t['about_lead'] ?></p>

        <section class="about-section">
            <h2><?= $t['about_mission_h'] ?></h2>
            <p><?= $t['about_mission_p'] ?></p>
        </section>

        <section class="about-section">
            <h2><?= $t['about_buyers_h'] ?></h2>
            <p><?= $t['about_buyers_p'] ?></p>
        </section>

        <section class="about-section">
            <h2><?= $t['about_vendors_h'] ?></h2>
            <p><?= $t['about_vendors_p'] ?> <a href="/register-vendor/"><?= $t['footer_sell_on'] ?> &rarr;</a></p>
        </section>

        <section class="about-section">
            <h2><?= $t['about_cambodia_h'] ?></h2>
            <p><?= $t['about_cambodia_p'] ?></p>
        </section>

        <div class="about-cta">
            <a class="about-btn about-btn-primary" href="/"><?= $t['about_cta_shop'] ?></a>
            <a class="about-btn" href="/careers/"><?= $t['about_cta_hiring'] ?></a>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
