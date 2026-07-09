<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';
require __DIR__ . '/../config/app.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin' || !($_SESSION['is_admin'] ?? false)) {
    header('Location: /login-admin/');
    exit;
}

admin_require('careers');

$appId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT a.name, a.resume_file FROM job_applications a WHERE a.id = ?');
$stmt->execute([$appId]);
$app = $stmt->fetch();

if (!$app || !$app['resume_file']) {
    http_response_code(404);
    exit('Résumé not found.');
}

// basename() neutralises any path-traversal in the stored value.
$file = basename($app['resume_file']);
$path = RESUME_DIR . '/' . $file;

if (!is_file($path)) {
    http_response_code(404);
    exit('Résumé file is missing.');
}

$ext   = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$types = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$mime = $types[$ext] ?? 'application/octet-stream';

// Friendly download name from the applicant.
$safeName = preg_replace('/[^A-Za-z0-9]+/', '-', trim($app['name'])) ?: 'applicant';
$download = $safeName . '-resume.' . $ext;

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $download . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
