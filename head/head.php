<?php
// The <head> every public page shares, in one file — the same arrangement as
// /header/ and /footer/, and for the same reason: the block was copied into
// ninety-odd pages and had already drifted. Only 50 of the 87 pages carrying
// the font preloads also carried the favicon lines, so which icon a visitor
// saw in their tab depended on which page they landed on first.
//
// Set the variables you need, then require it:
//
//     $headTitle = $t['about_title'] . ' — teepsaa';
//     $headDesc  = $t['seo_desc_about'];
//     $headUrl   = 'https://teepsaa.com/about/';
//     $headCss   = ['/about/about.css'];
//     require __DIR__ . '/../head/head.php';
//
// Every variable except $headTitle is optional:
//
//   $headDesc    meta description. Left out, seo_meta() falls back to the
//                site-wide sentence.
//   $headUrl     the canonical address. Left out, the address being requested
//                is used — right for a page with no query parameters, wrong
//                for anything filtered, so pass it on those.
//   $headImage   share image: a bare uploads filename or a full https:// URL.
//   $headCss     page stylesheets, in order. style.css, header.css and
//                footer.css are already included — don't list them again.
//   $headAlt     false on a noindex page: telling Google about translations of
//                a page you've asked it to ignore only muddies the signal.
//   $headRobots  e.g. 'noindex, follow'.
//   $headExtra   raw markup appended last — JSON-LD, a preload, a page's own
//                <style> block.
//   $headSeo     false skips seo_meta() entirely (no description, no canonical,
//                no share tags). For pages behind a login that want nothing.
//
// The variables are cleared at the end, so a page that sets $headCss doesn't
// leak it into anything else that requires this later.

require_once __DIR__ . '/../config/seo.php';

// seo_meta() and the <title> both want the translation strings, and header.php
// — which normally loads them — doesn't run until after </head>. seo_t()
// caches, and header.php skips its own load when $t is already set, so this
// costs nothing.
if (!isset($t)) {
    $t = seo_t();
}

$headTitle  = $headTitle  ?? 'teepsaa';
$headDesc   = $headDesc   ?? '';
$headUrl    = $headUrl    ?? '';
$headImage  = $headImage  ?? '';
$headCss    = $headCss    ?? [];
$headAlt    = $headAlt    ?? true;
$headRobots = $headRobots ?? '';
$headExtra  = $headExtra  ?? '';
$headSeo    = $headSeo    ?? true;
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($headTitle, ENT_QUOTES, 'UTF-8') ?></title>
<?php if ($headRobots !== ''): ?>
    <meta name="robots" content="<?= htmlspecialchars($headRobots, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<?php if ($headSeo): ?>
    <?= seo_meta($headTitle, $headDesc, $headImage, $headUrl, $headAlt) ?>

<?php endif; ?>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="icon" href="/images/teepsaa-icon-192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="/images/teepsaa-icon-180.png">
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
<?php foreach ($headCss as $headSheet): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($headSheet, ENT_QUOTES, 'UTF-8') ?>">
<?php endforeach; ?>
<?= $headExtra !== '' ? '    ' . $headExtra . "\n" : '' ?>
</head>
<?php
unset($headTitle, $headDesc, $headUrl, $headImage, $headCss,
      $headAlt, $headRobots, $headExtra, $headSeo, $headSheet);
