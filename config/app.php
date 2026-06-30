<?php
define('ADMIN_EMAIL', 'dustint505@gmail.com');
define('FROM_EMAIL',  'orders@teepsaa.com');
define('SITE_URL',    'https://teepsaa.com');
define('DEV_MODE',    true);

// Private file storage — lives OUTSIDE the web root (a sibling of the
// project folder), so files here are never directly reachable by URL.
// Served only through auth-gated scripts (e.g. admin/resume.php).
define('PRIVATE_DIR', dirname(__DIR__, 2) . '/teepsaa-private');
define('RESUME_DIR',  PRIVATE_DIR . '/resumes');
