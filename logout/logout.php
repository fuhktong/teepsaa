<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

session_destroy();

// Allow returning to an internal page after logout (e.g. vendor signup).
// Only accept root-relative, non protocol-relative paths to avoid open redirects.
$next = $_GET['next'] ?? '/';
if (!is_string($next) || $next === '' || $next[0] !== '/' || str_starts_with($next, '//')) {
    $next = '/';
}

header('Location: ' . $next);
exit;
