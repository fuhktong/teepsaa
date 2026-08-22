<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/settings.php');
    exit;
}
csrf_verify();

$adminId = (int)$_SESSION['admin_id'];
$action  = $_POST['action'] ?? '';

// An admin can only ever revoke their own devices — admin_device_revoke()
// matches on admin_id as well as the row id.
if ($action === 'revoke') {
    $deviceId = (int)($_POST['device_id'] ?? 0);
    $wasCurrent = false;
    foreach (admin_device_list($pdo, $adminId) as $d) {
        if ((int)$d['id'] === $deviceId && $d['selector'] === admin_device_current_selector()) {
            $wasCurrent = true;
        }
    }
    admin_device_revoke($pdo, $adminId, $deviceId);
    $_SESSION['settings_success'] = $wasCurrent
        ? 'This device is no longer remembered. You will have to log in again next time.'
        : 'Device removed.';
} elseif ($action === 'revoke-others') {
    $n = admin_device_revoke_others($pdo, $adminId);
    $_SESSION['settings_success'] = $n === 0
        ? 'There were no other remembered devices.'
        : 'Signed out of ' . $n . ' other device' . ($n === 1 ? '' : 's') . '.';
} else {
    $_SESSION['settings_error'] = 'Unknown action.';
}

header('Location: /admin/settings.php');
exit;
