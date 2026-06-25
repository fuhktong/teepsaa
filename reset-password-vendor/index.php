<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

$token    = trim($_GET['token'] ?? '');
$valid    = false;
$errorMsg = '';

if (!$token) {
    $errorMsg = 'Invalid or expired link. Please <a href="/forgot-password-vendor/">request a new one</a>.';
} else {
    $stmt = $pdo->prepare('
        SELECT id FROM password_resets
        WHERE token = ? AND role = ? AND used_at IS NULL
          AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ');
    $stmt->execute([$token, 'vendor']);
    $valid = (bool) $stmt->fetch();
    if (!$valid) {
        $errorMsg = 'This link has expired or has already been used. Please <a href="/forgot-password-vendor/">request a new one</a>.';
    }
}

$formError = $_SESSION['auth_error'] ?? '';
unset($_SESSION['auth_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset password — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/reset-password-vendor/reset-password-vendor.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="auth-box">
        <h1>Reset password</h1>
        <?php if (!$valid): ?>
            <p class="auth-error"><?= $errorMsg ?></p>
        <?php else: ?>
            <?php if ($formError): ?>
                <p class="auth-error"><?= htmlspecialchars($formError) ?></p>
            <?php endif; ?>
            <form method="POST" action="/reset-password-vendor/reset.php">
                <?= csrf_input() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <label for="password">New password</label>
                <input type="password" id="password" name="password" required minlength="8" autofocus>
                <label for="password_confirm">Confirm new password</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
                <button type="submit">Reset password</button>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
