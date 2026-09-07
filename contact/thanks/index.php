<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);
// i18n.php gives this page current_lang() (and requires subdomain.php itself).
require __DIR__ . '/../../config/i18n.php';

?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<?php
$headTitle = 'Message Sent — teepsaa';
$headSeo   = false;
require __DIR__ . '/../../head/head.php';
?>
<body>

<?php require __DIR__ . '/../../header/header.php'; ?>

<main>
    <div style="max-width:480px; padding: 3rem 0;">
        <h1 style="font-size:1.4rem; margin-bottom:0.75rem;">Message received</h1>
        <p style="color:#555; font-size:0.9rem; line-height:1.6; margin-bottom:1.5rem;">
            Thanks for reaching out. We'll get back to you at the email address you provided, usually within one business day.
        </p>
        <a href="/" style="color:#2d3a6b; font-size:0.9rem;">← Back to home</a>
    </div>
</main>

<?php require __DIR__ . '/../../footer/footer.php'; ?>

</body>
</html>
