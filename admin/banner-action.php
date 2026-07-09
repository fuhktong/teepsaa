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

admin_require('banners');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/banners.php');
    exit;
}

csrf_verify();

$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);

function redirect_banners(string $msg = '', bool $error = false): never {
    if ($msg) {
        $_SESSION[$error ? 'banner_error' : 'banner_success'] = $msg;
    }
    header('Location: /admin/banners.php');
    exit;
}

match ($action) {
    'upload'    => do_upload(),
    'edit'      => do_edit($id),
    'delete'    => do_delete($id),
    'toggle'    => do_toggle($id),
    'move_up'   => do_move($id, 'up'),
    'move_down' => do_move($id, 'down'),
    default     => redirect_banners(),
};

// Validate + store an uploaded image, returning its new filename (or null
// on no upload). Redirects with an error on a bad file.
function save_banner_image(): ?string {
    if (empty($_FILES['image']['tmp_name'])) {
        return null;
    }
    $file    = $_FILES['image'];
    $mime    = mime_content_type($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!array_key_exists($mime, $allowed)) {
        redirect_banners('File must be JPEG, PNG, or WebP.', true);
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        redirect_banners('File must be under 5 MB.', true);
    }
    $filename = 'banner_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../uploads/' . $filename)) {
        redirect_banners('Failed to save file.', true);
    }
    return $filename;
}

function do_upload(): void {
    global $pdo;

    $filename = save_banner_image();
    if ($filename === null) {
        redirect_banners('No file uploaded.', true);
    }

    $title       = trim($_POST['title']       ?? '');
    $titleKm     = trim($_POST['title_km']    ?? '');
    $subtitle    = trim($_POST['subtitle']    ?? '');
    $subtitleKm  = trim($_POST['subtitle_km'] ?? '');
    $link        = trim($_POST['link_url']    ?? '');

    $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM banners')->fetchColumn();

    $stmt = $pdo->prepare('INSERT INTO banners (title, title_km, subtitle, subtitle_km, link_url, image_filename, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $title      ?: null,
        $titleKm    ?: null,
        $subtitle   ?: null,
        $subtitleKm ?: null,
        $link       ?: null,
        $filename,
        $maxSort + 1,
    ]);

    redirect_banners('Banner uploaded.');
}

function do_edit(int $id): void {
    global $pdo;
    if (!$id) redirect_banners('Invalid banner.', true);

    $row = $pdo->prepare('SELECT image_filename FROM banners WHERE id = ?');
    $row->execute([$id]);
    $banner = $row->fetch();
    if (!$banner) redirect_banners('Banner not found.', true);

    $title      = trim($_POST['title']       ?? '');
    $titleKm    = trim($_POST['title_km']    ?? '');
    $subtitle   = trim($_POST['subtitle']    ?? '');
    $subtitleKm = trim($_POST['subtitle_km'] ?? '');
    $link       = trim($_POST['link_url']    ?? '');

    // Image is optional on edit — only replace if a new one was uploaded.
    $newImage = save_banner_image();

    $stmt = $pdo->prepare('UPDATE banners SET title = ?, title_km = ?, subtitle = ?, subtitle_km = ?, link_url = ?' . ($newImage ? ', image_filename = ?' : '') . ' WHERE id = ?');
    $params = [$title ?: null, $titleKm ?: null, $subtitle ?: null, $subtitleKm ?: null, $link ?: null];
    if ($newImage) $params[] = $newImage;
    $params[] = $id;
    $stmt->execute($params);

    // Remove the old image file if it was replaced.
    if ($newImage && $banner['image_filename'] && $banner['image_filename'] !== $newImage) {
        $old = __DIR__ . '/../uploads/' . $banner['image_filename'];
        if (is_file($old)) @unlink($old);
    }

    redirect_banners('Banner updated.');
}

function do_delete(int $id): void {
    global $pdo;
    if (!$id) redirect_banners('Invalid banner.', true);

    $row = $pdo->prepare('SELECT image_filename FROM banners WHERE id = ?');
    $row->execute([$id]);
    $banner = $row->fetch();
    if (!$banner) redirect_banners('Banner not found.', true);

    $pdo->prepare('DELETE FROM banners WHERE id = ?')->execute([$id]);

    $path = __DIR__ . '/../uploads/' . $banner['image_filename'];
    if (file_exists($path)) {
        @unlink($path);
    }

    redirect_banners('Banner deleted.');
}

function do_toggle(int $id): void {
    global $pdo;
    if (!$id) redirect_banners();
    $pdo->prepare('UPDATE banners SET active = 1 - active WHERE id = ?')->execute([$id]);
    redirect_banners();
}

function do_move(int $id, string $dir): void {
    global $pdo;
    if (!$id) redirect_banners();

    $rows = $pdo->query('SELECT id, sort_order FROM banners ORDER BY sort_order ASC, id ASC')->fetchAll();
    $idx  = array_search($id, array_column($rows, 'id'));

    if ($idx === false) redirect_banners();

    $swapIdx = $dir === 'up' ? $idx - 1 : $idx + 1;
    if ($swapIdx < 0 || $swapIdx >= count($rows)) redirect_banners();

    $a = $rows[$idx];
    $b = $rows[$swapIdx];

    $stmt = $pdo->prepare('UPDATE banners SET sort_order = ? WHERE id = ?');
    $stmt->execute([$b['sort_order'], $a['id']]);
    $stmt->execute([$a['sort_order'], $b['id']]);

    // if sort_orders were equal, force-separate them
    if ($a['sort_order'] === $b['sort_order']) {
        $stmt->execute([$swapIdx, $b['id']]);
        $stmt->execute([$idx,     $a['id']]);
    }

    redirect_banners();
}
