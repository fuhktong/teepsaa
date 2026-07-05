<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login-buyer/');
    exit;
}

$role = $_SESSION['role'] ?? $_SESSION['pending_role'] ?? null;
if (!in_array($role, ['buyer', 'vendor'], true)) {
    header('Location: /login-buyer/');
    exit;
}

$table  = $role === 'buyer' ? 'buyers' : 'vendors';
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT email, email_verified_at FROM {$table} WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($user && $user['email_verified_at']) {
    $dest = $role === 'vendor' ? '/dashboard-vendor/' : '/dashboard-buyer/';
    header('Location: ' . $dest);
    exit;
}

$error   = $_SESSION['verify_error']   ?? '';
$success = $_SESSION['verify_success'] ?? '';
$devOtp  = $_SESSION['dev_otp']        ?? '';
unset($_SESSION['verify_error'], $_SESSION['verify_success'], $_SESSION['dev_otp']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/verify-email/verify-email.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="auth-box">
        <h1><?= $t['ve_title'] ?></h1>
        <p style="color:#6b7280;font-size:0.9rem;margin-bottom:1.5rem;"><?= sprintf($t['ve_sent'], '<strong>' . htmlspecialchars($user['email'] ?? '') . '</strong>') ?></p>


        <?php if ($error): ?>
            <p class="auth-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="auth-success"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

        <form method="POST" action="/verify-email/verify.php" class="otp-form" autocomplete="off">
            <?= csrf_input() ?>
            <div class="otp-wrap">
                <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" required autofocus>
                <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" required>
                <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" required>
                <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" required>
                <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" required>
                <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" required>
            </div>
            <input type="hidden" name="code" id="otp-hidden">
            <button type="submit" class="otp-submit"><?= $t['ve_verify'] ?></button>
        </form>

        <p class="auth-switch">
            <?= $t['ve_didnt_get'] ?>
            <form method="POST" action="/resend-verification/resend.php" style="display:inline">
                <?= csrf_input() ?>
                <button type="submit" class="btn-link-inline"><?= $t['ve_resend_code'] ?></button>
            </form>
        </p>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<?php if ($devOtp && defined('DEV_MODE') && DEV_MODE): ?>
<script>console.log('[DEV] Teepsaa OTP: <?= $devOtp ?>');</script>
<?php endif; ?>
<script>
(function () {
    var digits  = Array.from(document.querySelectorAll('.otp-digit'));
    var hidden  = document.getElementById('otp-hidden');
    var form    = document.querySelector('.otp-form');

    digits.forEach(function (inp, i) {
        inp.addEventListener('input', function () {
            inp.value = inp.value.replace(/\D/g, '').slice(-1);
            if (inp.value && i < digits.length - 1) digits[i + 1].focus();
            syncHidden();
        });

        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !inp.value && i > 0) {
                digits[i - 1].focus();
                digits[i - 1].value = '';
                syncHidden();
            }
        });

        inp.addEventListener('paste', function (e) {
            e.preventDefault();
            var pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
            pasted.split('').forEach(function (ch, j) {
                if (digits[j]) digits[j].value = ch;
            });
            var next = Math.min(pasted.length, digits.length - 1);
            digits[next].focus();
            syncHidden();
        });
    });

    function syncHidden() {
        hidden.value = digits.map(function (d) { return d.value; }).join('');
    }

    form.addEventListener('submit', function (e) {
        syncHidden();
        if (hidden.value.length !== 6) {
            e.preventDefault();
            digits[0].focus();
        }
    });
})();
</script>
</body>
</html>
