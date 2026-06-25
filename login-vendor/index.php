<?php
session_start();
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
    <title>Vendor Log in — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/login-vendor/login-vendor.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="auth-box">
        <h1>Vendor log in</h1>
        <?php if ($success): ?>
            <p class="auth-success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="auth-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" action="/login-vendor/login-vendor.php">
            <?= csrf_input() ?>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Log in</button>
        </form>
        <p class="auth-switch">No account? <a href="/register-vendor/">Register as a vendor</a></p>
        <p class="auth-switch"><a href="/forgot-password-vendor/">Forgot password?</a></p>
    </div>
</main>

<div class="admin-link-wrap">
    <a href="/login-admin/" class="admin-link">admin login</a>
</div>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
