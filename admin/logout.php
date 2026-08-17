<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

// Admin logout only — a buyer or vendor signed in in the same browser keeps
// their session. The mirror of /logout/logout.php.
unset(
    $_SESSION['admin_id'],
    $_SESSION['admin_role'],
    $_SESSION['admin_permissions']
);

if (empty($_SESSION['user_id'])) {
    session_destroy();
} else {
    session_regenerate_id(true);
}

header('Location: /login-admin/');
exit;
