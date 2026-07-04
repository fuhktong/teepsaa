<?php
session_start();
require __DIR__ . '/../config/csrf.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard-vendor/');
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
    <title>Forgot password — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/forgot-password-vendor/forgot-password-vendor.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="auth-box">
        <h1><?= $t['fp_title'] ?></h1>
        <?php if ($success): ?>
            <p class="auth-success"><?= htmlspecialchars($success) ?></p>
            <p class="auth-switch"><a href="/login-vendor/">&larr; <?= $t['auth_back_login'] ?></a></p>
        <?php else: ?>
            <?php if ($error): ?>
                <p class="auth-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <p class="auth-hint"><?= $t['fp_hint_vendor'] ?></p>
            <form method="POST" action="/forgot-password-vendor/request.php">
                <?= csrf_input() ?>
                <label for="email"><?= $t['login_email'] ?></label>
                <input type="email" id="email" name="email" required autofocus>
                <button type="submit"><?= $t['fp_send'] ?></button>
            </form>
            <p class="auth-switch"><a href="/login-vendor/">&larr; <?= $t['auth_back_login'] ?></a></p>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
