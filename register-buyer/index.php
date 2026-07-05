<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'buyer';
    header('Location: ' . ($role === 'vendor' ? '/dashboard-vendor/' : '/dashboard-buyer/'));
    exit;
}

$error = $_SESSION['auth_error'] ?? '';
unset($_SESSION['auth_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/register-buyer/register-buyer.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="auth-box">
        <h1><?= $t['register_title'] ?></h1>
        <?php if ($error): ?>
            <p class="auth-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" action="/register-buyer/register-buyer.php">
            <?= csrf_input() ?>
            <label for="name"><?= $t['register_name'] ?></label>
            <input type="text" id="name" name="name" required autofocus>
            <label for="email"><?= $t['register_email'] ?></label>
            <input type="email" id="email" name="email" required>
            <label for="password"><?= $t['register_password'] ?></label>
            <input type="password" id="password" name="password" required minlength="8">
            <label for="password_confirm"><?= $t['register_confirm'] ?></label>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
            <button type="submit"><?= $t['register_submit'] ?></button>
        </form>
        <p class="auth-tos"><?= sprintf($t['auth_agree'], '<a href="/terms/">' . $t['footer_terms'] . '</a>', '<a href="/privacy/">' . $t['footer_privacy'] . '</a>') ?></p>
        <p class="auth-switch"><?= $t['register_have_account'] ?> <a href="/login-buyer/"><?= $t['register_login'] ?></a></p>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
