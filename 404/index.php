<?php
// The site-wide "not found" page. Two ways in:
//
//   1. Apache hands it every unmatched URL (ErrorDocument 404 in .htaccess).
//   2. A page requires it after setting $nfTitle/$nfBody and its own status
//      code — product/ and business/ do this for a removed listing, so a dead
//      product URL answers 404/410 with a real page instead of redirecting to
//      /search/ (which Google reads as a soft 404 and keeps re-crawling).
//
// Because of (2) the session and the status code are only set when this file
// is the entry point; an includer has already done both.
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'gc_maxlifetime'  => 28800,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
    ]);
    http_response_code(404);
}

require_once __DIR__ . '/../config/db.php';

if (!isset($t)) {
    $nfLang = current_lang();
    $t = require __DIR__ . '/../lang/' . (in_array($nfLang, ['en', 'km'], true) ? $nfLang : 'en') . '.php';
}

$nfTitle = $nfTitle ?? $t['nf_title'];
$nfBody  = $nfBody  ?? $t['nf_body'];
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($nfTitle) ?> — teepsaa</title>
    <!-- No canonical and no sitemap entry: this page must never be indexed
         under whatever address happened to produce it. -->
    <meta name="robots" content="noindex,follow">
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="icon" href="/images/teepsaa-icon-192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="/images/teepsaa-icon-180.png">
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/404/404.css">
</head>
<body>
<?php require __DIR__ . '/../header/header.php'; ?>
<main>
    <div class="nf-wrap">
        <div class="nf-mark">404</div>
        <h1><?= htmlspecialchars($nfTitle) ?></h1>
        <p class="nf-body"><?= htmlspecialchars($nfBody) ?></p>
        <div class="nf-actions">
            <a class="nf-btn nf-btn-primary" href="/"><?= $t['nf_home'] ?></a>
            <a class="nf-btn" href="/search/"><?= $t['nf_browse'] ?></a>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
