<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

// Logging out is an explicit "not this device" — drop the remembered-device
// row too, otherwise the next page load would just sign you back in.
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-device.php';
admin_device_forget($pdo);

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
