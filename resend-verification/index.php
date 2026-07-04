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
        <h1><?= $t['rv_title'] ?></h1>
        <?php if ($user && $user['email_verified_at']): ?>
            <p class="auth-success"><?= $t['rv_verified'] ?></p>
        <?php elseif ($success): ?>
            <p class="auth-success"><?= htmlspecialchars($success) ?></p>
            <p class="auth-switch"><?= $t['rv_didnt_arrive'] ?> <a href="/resend-verification/"><?= $t['rv_resend'] ?></a>.</p>
        <?php else: ?>
            <?php if ($error): ?>
                <p class="auth-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <p><?= sprintf($t['rv_sent'], '<strong>' . htmlspecialchars($user['email'] ?? '') . '</strong>') ?></p>
            <form method="POST" action="/resend-verification/resend.php">
                <?= csrf_input() ?>
                <button type="submit"><?= $t['rv_resend_email'] ?></button>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
