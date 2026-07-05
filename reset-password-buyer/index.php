<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

$token    = trim($_GET['token'] ?? '');
$valid    = false;
$errorMsg = '';

if (!$token) {
    $errorMsg = 'Invalid or expired link. Please <a href="/forgot-password-buyer/">request a new one</a>.';
} else {
    $stmt = $pdo->prepare('
        SELECT id FROM password_resets
        WHERE token = ? AND role = ? AND used_at IS NULL
          AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ');
    $stmt->execute([$token, 'buyer']);
    $valid = (bool) $stmt->fetch();
    if (!$valid) {
        $errorMsg = 'This link has expired or has already been used. Please <a href="/forgot-password-buyer/">request a new one</a>.';
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
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/reset-password-buyer/reset-password-buyer.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="auth-box">
        <h1><?= $t['rp_title'] ?></h1>
        <?php if (!$valid): ?>
            <p class="auth-error"><?= $errorMsg ?></p>
        <?php else: ?>
            <?php if ($formError): ?>
                <p class="auth-error"><?= htmlspecialchars($formError) ?></p>
            <?php endif; ?>
            <form method="POST" action="/reset-password-buyer/reset.php">
                <?= csrf_input() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <label for="password"><?= $t['settings_new_pw'] ?></label>
                <input type="password" id="password" name="password" required minlength="8" autofocus>
                <label for="password_confirm"><?= $t['settings_confirm_pw'] ?></label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
                <button type="submit"><?= $t['rp_title'] ?></button>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
