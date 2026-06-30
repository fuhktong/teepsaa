<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/about/about.css">
</head>
<body>
<?php require __DIR__ . '/../header/header.php'; ?>
<main>
    <div class="about-wrap">
        <h1>About teepsaa</h1>
        <p class="about-lead">teepsaa is a local marketplace connecting buyers and vendors across Phnom Penh — built to make buying and selling online simple, friendly, and trustworthy for everyone in Cambodia.</p>

        <section class="about-section">
            <h2>Our mission</h2>
            <p>We want shopping made easy. teepsaa gives small local vendors a place to reach customers online, and gives buyers a single, dependable place to discover and order from them — without the hassle.</p>
        </section>

        <section class="about-section">
            <h2>For buyers</h2>
            <p>Browse products from local vendors, save what you love to your wishlist, and check out in a few taps. Prices show in US Dollars or Khmer Riel, and orders are delivered locally so your purchases reach you quickly.</p>
        </section>

        <section class="about-section">
            <h2>For vendors</h2>
            <p>Open a shop, list your products, and start reaching local buyers — no storefront required. Manage orders, message customers, and track payouts from one simple dashboard. <a href="/register-vendor/">Sell on teepsaa &rarr;</a></p>
        </section>

        <section class="about-section">
            <h2>Built for Cambodia</h2>
            <p>teepsaa works the way Phnom Penh shops: fully in Khmer and English, with local delivery and the payment methods people already use. We're a local team building for our own community, and we're just getting started.</p>
        </section>

        <div class="about-cta">
            <a class="about-btn about-btn-primary" href="/">Start shopping</a>
            <a class="about-btn" href="/careers/">We're hiring</a>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
