<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('POST only.');
}
csrf_verify();

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
