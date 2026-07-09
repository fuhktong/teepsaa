<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin' || !($_SESSION['is_admin'] ?? false)) {
    header('Location: /login-admin/');
    exit;
}

admin_require('content');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/content.php');
    exit;
}

csrf_verify();

const CONTENT_PAGE_SLUGS = ['privacy', 'terms', 'shipping', 'returns'];

$action = $_POST['action'] ?? '';
$slug   = $_POST['slug']   ?? '';

function redirect_content(string $slug, string $msg = '', bool $error = false): never {
    if ($msg) {
        $_SESSION[$error ? 'content_error' : 'content_success'] = $msg;
    }
    header('Location: /admin/content.php' . ($slug ? '?edit=' . urlencode($slug) . '#content-' . urlencode($slug) : ''));
    exit;
}

match ($action) {
    'save'  => do_save($slug),
    default => redirect_content(''),
};

function do_save(string $slug): void {
    global $pdo;

    if (!in_array($slug, CONTENT_PAGE_SLUGS, true)) {
        redirect_content('', 'Unknown page.', true);
    }

    $titleEn = trim($_POST['title_en'] ?? '');
    $titleKm = trim($_POST['title_km'] ?? '');
    $bodyEn  = trim($_POST['body_en']  ?? '');
    $bodyKm  = trim($_POST['body_km']  ?? '');

    if ($titleEn === '' || $titleKm === '' || $bodyEn === '' || $bodyKm === '') {
        redirect_content($slug, 'All fields are required.', true);
    }

    $stmt = $pdo->prepare('UPDATE content_pages SET title_en = ?, title_km = ?, body_en = ?, body_km = ?, updated_by = ? WHERE slug = ?');
    $stmt->execute([$titleEn, $titleKm, $bodyEn, $bodyKm, (int) $_SESSION['user_id'], $slug]);

    redirect_content($slug, 'Page updated.');
}
