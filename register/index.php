<?php
session_start();
require __DIR__ . '/../config/csrf.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard/');
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
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/register/register.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="auth-box">
        <h1><?= $t['register_title'] ?></h1>
        <?php if ($error): ?>
            <p class="auth-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" action="/register/register.php">
            <?= csrf_input() ?>
            <label for="email"><?= $t['register_email'] ?></label>
            <input type="email" id="email" name="email" required autofocus>
            <label for="password"><?= $t['register_password'] ?></label>
            <input type="password" id="password" name="password" required minlength="8">
            <label for="password_confirm"><?= $t['register_confirm'] ?></label>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
            <button type="submit"><?= $t['register_submit'] ?></button>
        </form>
        <p class="auth-switch"><?= $t['register_have_account'] ?> <a href="/login/"><?= $t['register_login'] ?></a></p>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
