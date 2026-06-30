<?php
session_start();
session_destroy();

// Allow returning to an internal page after logout (e.g. vendor signup).
// Only accept root-relative, non protocol-relative paths to avoid open redirects.
$next = $_GET['next'] ?? '/';
if (!is_string($next) || $next === '' || $next[0] !== '/' || str_starts_with($next, '//')) {
    $next = '/';
}

header('Location: ' . $next);
exit;
