<?php
define('ADMIN_EMAIL', 'dustint505@gmail.com');
define('FROM_EMAIL',  'orders@teepsaa.com');
define('SITE_URL',    'https://teepsaa.com');
// True only on local dev (MAMP/localhost/CLI has no teepsaa host) — gates
// dev-only output like the OTP echoed at registration, which must never
// reach production. Host-derived so a deploy can't ship it switched on.
define('DEV_MODE', in_array(strtok($_SERVER['HTTP_HOST'] ?? '', ':'), ['localhost', '127.0.0.1'], true));

// Private file storage — lives OUTSIDE the web root (a sibling of the
// project folder), so files here are never directly reachable by URL.
// Served only through auth-gated scripts (e.g. admin/resume.php).
define('PRIVATE_DIR', dirname(__DIR__, 2) . '/teepsaa-private');
define('RESUME_DIR',  PRIVATE_DIR . '/resumes');
