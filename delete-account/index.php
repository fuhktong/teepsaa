<?php session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);
// The public "how to delete your account" page that Google Play asks every app
// with accounts to link to. It needs no sign-in, because the person reading it
// may no longer have the app, or be able to sign in at all.
require __DIR__ . '/../config/i18n.php'; ?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<?php
require_once __DIR__ . '/../config/seo.php';
$t = seo_t();

$headTitle = $t['delacct_title'] . ' — teepsaa';
$headDesc  = $t['seo_desc_delacct'];
$headUrl   = 'https://teepsaa.com/delete-account/';
$headCss   = ['/privacy/privacy.css', '/delete-account/delete-account.css'];
require __DIR__ . '/../head/head.php';
?>
<body>
<?php require __DIR__ . '/../header/header.php'; ?>
<main>
    <div class="legal-wrap delacct">
        <h1><?= $t['delacct_title'] ?></h1>
        <p><?= $t['delacct_lead'] ?></p>

        <h2><?= $t['delacct_buyer_app_h'] ?></h2>
        <ol>
            <li><?= $t['delacct_buyer_app_1'] ?></li>
            <li><?= $t['delacct_buyer_app_2'] ?></li>
            <li><?= $t['delacct_buyer_app_3'] ?></li>
        </ol>

        <h2><?= $t['delacct_vendor_app_h'] ?></h2>
        <ol>
            <li><?= $t['delacct_vendor_app_1'] ?></li>
            <li><?= $t['delacct_vendor_app_2'] ?></li>
            <li><?= $t['delacct_vendor_app_3'] ?></li>
        </ol>
        <p><?= $t['delacct_vendor_note'] ?></p>

        <h2><?= $t['delacct_web_h'] ?></h2>
        <ul>
            <li><?= sprintf($t['delacct_web_buyer'], lang_href('/settings-buyer/?tab=danger')) ?></li>
            <li><?= sprintf($t['delacct_web_vendor'], BASE_URL_VENDOR . lang_href('/settings-vendor/?tab=danger')) ?></li>
        </ul>

        <h2><?= $t['delacct_nologin_h'] ?></h2>
        <p><?= sprintf($t['delacct_nologin_p'], lang_href('/contact/')) ?></p>

        <h2><?= $t['delacct_what_h'] ?></h2>
        <ul>
            <li><?= $t['delacct_what_1'] ?></li>
            <li><?= $t['delacct_what_2'] ?></li>
            <li><?= $t['delacct_what_3'] ?></li>
            <li><?= $t['delacct_what_4'] ?></li>
        </ul>

        <p><?= sprintf($t['delacct_privacy'], lang_href('/privacy/')) ?></p>
    </div>
</main>
<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
