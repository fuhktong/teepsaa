<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';

if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['pending_role'])) {
        header('Location: /verify-email/');
    } elseif (($_SESSION['role'] ?? '') === 'vendor') {
        header('Location: /dashboard-vendor/');
    } else {
        header('Location: /dashboard-buyer/');
    }
    exit;
}

$error   = $_SESSION['auth_error']   ?? '';
$success = $_SESSION['auth_success'] ?? '';
unset($_SESSION['auth_error'], $_SESSION['auth_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/login-buyer/login-buyer.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="auth-box">
        <h1><?= $t['login_title'] ?></h1>
        <?php if ($success): ?>
            <p class="auth-success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="auth-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" action="/login-buyer/login-buyer.php">
            <?= csrf_input() ?>
            <label for="email"><?= $t['login_email'] ?></label>
            <input type="email" id="email" name="email" required autofocus>
            <label for="password"><?= $t['login_password'] ?></label>
            <input type="password" id="password" name="password" required>
            <button type="submit"><?= $t['login_submit'] ?></button>
        </form>
        <p class="auth-switch"><?= $t['login_no_account'] ?> <a href="/register-buyer/"><?= $t['login_register'] ?></a></p>
        <p class="auth-switch"><a href="/forgot-password-buyer/"><?= $t['login_forgot'] ?></a></p>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
