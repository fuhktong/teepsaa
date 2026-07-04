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
    <title>Log in — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/login/login.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="auth-box">
        <h1><?= $t['login_title'] ?></h1>
        <?php if ($error): ?>
            <p class="auth-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" action="/login/login.php">
            <?= csrf_input() ?>
            <label for="email"><?= $t['login_email'] ?></label>
            <input type="email" id="email" name="email" required autofocus>
            <label for="password"><?= $t['login_password'] ?></label>
            <input type="password" id="password" name="password" required>
            <button type="submit"><?= $t['login_submit'] ?></button>
        </form>
        <p class="auth-switch"><?= $t['login_no_account'] ?> <a href="/register/"><?= $t['login_register'] ?></a></p>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
