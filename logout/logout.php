<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

// Buyer/vendor logout only — an admin signed in in the same browser keeps
// their session (see /admin/logout.php). Dropping the whole session here
// used to sign the admin out too.
unset(
    $_SESSION['user_id'],
    $_SESSION['role'],
    $_SESSION['user_name'],
    $_SESSION['user_avatar'],
    $_SESSION['user_avatar_color'],
    $_SESSION['pending_role']
);

// Nothing left worth keeping — drop the session outright so no empty shell
// lingers with a stale CSRF token.
if (empty($_SESSION['admin_id'])) {
    session_destroy();
} else {
    // The other identity stays signed in, so the session survives — but the id
    // changes and the CSRF token goes with it, so nothing minted for the
    // identity that just left is still accepted.
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
}

// Allow returning to an internal page after logout (e.g. vendor signup).
// Only accept root-relative, non protocol-relative paths to avoid open redirects.
$next = $_GET['next'] ?? '/';
if (!is_string($next) || $next === '' || $next[0] !== '/' || str_starts_with($next, '//')) {
    $next = '/';
}

header('Location: ' . $next);
exit;
