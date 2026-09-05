<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
// Including this is what gives a remembered device a session — see the tail
// of config/admin-auth.php — so arriving here on a known phone lands you
// straight on the dashboard instead of the form.
require __DIR__ . '/../config/admin-auth.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: /admin/');
    exit;
}

$error = $_SESSION['auth_error'] ?? '';
unset($_SESSION['auth_error']);
?>
<!DOCTYPE html>
<html lang="<?= ($_SESSION['lang'] ?? 'km') === 'km' ? 'km' : 'en' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="icon" href="/images/teepsaa-icon-192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="/images/teepsaa-icon-180.png">
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/login-admin/login-admin.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="auth-box">
        <h1>Log in</h1>
        <?php if ($error): ?>
            <p class="auth-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" action="/login-admin/login-admin.php">
            <?= csrf_input() ?>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus autocomplete="username">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <label class="auth-remember">
                <input type="checkbox" name="remember" value="1">
                Remember this device for 30 days
            </label>
            <button type="submit">Log in</button>
        </form>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
