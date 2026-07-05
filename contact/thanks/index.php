<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Sent — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
</head>
<body>

<?php require __DIR__ . '/../../header/header.php'; ?>

<main>
    <div style="max-width:480px; padding: 3rem 0;">
        <h1 style="font-size:1.4rem; margin-bottom:0.75rem;">Message received</h1>
        <p style="color:#555; font-size:0.9rem; line-height:1.6; margin-bottom:1.5rem;">
            Thanks for reaching out. We'll get back to you at the email address you provided, usually within one business day.
        </p>
        <a href="/" style="color:#2d3a6b; font-size:0.9rem;">← Back to home</a>
    </div>
</main>

<?php require __DIR__ . '/../../footer/footer.php'; ?>

</body>
</html>
