<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
// i18n.php gives this page current_lang() (and requires subdomain.php itself).
require __DIR__ . '/../config/i18n.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /orders-buyer/');
    exit;
}

$error   = $_SESSION['auth_error']   ?? '';
$success = $_SESSION['auth_success'] ?? '';
unset($_SESSION['auth_error'], $_SESSION['auth_success']);
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<?php
$headTitle = 'Forgot password — teepsaa';
$headCss   = ['/forgot-password-buyer/forgot-password-buyer.css'];
$headSeo   = false;
require __DIR__ . '/../head/head.php';
?>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="auth-box">
        <h1><?= $t['fp_title'] ?></h1>
        <?php if ($success): ?>
            <p class="auth-success"><?= htmlspecialchars($success) ?></p>
            <p class="auth-switch"><a href="/login-buyer/">&larr; <?= $t['auth_back_login'] ?></a></p>
        <?php else: ?>
            <?php if ($error): ?>
                <p class="auth-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <p class="auth-hint"><?= $t['fp_hint_buyer'] ?></p>
            <form method="POST" action="/forgot-password-buyer/request.php">
                <?= csrf_input() ?>
                <label for="email"><?= $t['login_email'] ?></label>
                <input type="email" id="email" name="email" required autofocus autocomplete="email">
                <button type="submit"><?= $t['fp_send'] ?></button>
            </form>
            <p class="auth-switch"><a href="/login-buyer/">&larr; <?= $t['auth_back_login'] ?></a></p>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
