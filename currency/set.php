<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

$c = $_POST['currency'] ?? 'USD';
$_SESSION['currency'] = in_array($c, ['USD', 'KHR']) ? $c : 'USD';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$back    = '/';
if ($referer) {
    $refHost = parse_url($referer, PHP_URL_HOST);
    if ($refHost !== null && $refHost === ($_SERVER['HTTP_HOST'] ?? null)) {
        $back = $referer;
    }
}
header('Location: ' . $back);
exit;
