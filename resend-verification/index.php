<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

// Code entry is now at /verify-email/ — redirect anyone who lands here
header('Location: /verify-email/');
exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/resend-verification/resend-verification.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="auth-box">
        <h1>Verify your email</h1>
        <?php if ($user && $user['email_verified_at']): ?>
            <p class="auth-success">Your email address is verified.</p>
        <?php elseif ($success): ?>
            <p class="auth-success"><?= htmlspecialchars($success) ?></p>
            <p class="auth-switch">Didn't arrive? <a href="/resend-verification/">Resend</a>.</p>
        <?php else: ?>
            <?php if ($error): ?>
                <p class="auth-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <p>We sent a verification link to <strong><?= htmlspecialchars($user['email'] ?? '') ?></strong>. Check your inbox and click the link to activate your account.</p>
            <form method="POST" action="/resend-verification/resend.php">
                <?= csrf_input() ?>
                <button type="submit">Resend verification email</button>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
