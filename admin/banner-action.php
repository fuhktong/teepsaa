<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin' || !($_SESSION['is_admin'] ?? false)) {
    header('Location: /login-admin/');
    exit;
}

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
    'delete'    => do_delete($id),
    'toggle'    => do_toggle($id),
    'move_up'   => do_move($id, 'up'),
    'move_down' => do_move($id, 'down'),
    default     => redirect_banners(),
};

function do_upload(): void {
    global $pdo;

    if (empty($_FILES['image']['tmp_name'])) {
        redirect_banners('No file uploaded.', true);
    }

    $file   = $_FILES['image'];
    $mime   = mime_content_type($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!array_key_exists($mime, $allowed)) {
        redirect_banners('File must be JPEG, PNG, or WebP.', true);
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        redirect_banners('File must be under 5 MB.', true);
    }

    $ext      = $allowed[$mime];
    $filename = 'banner_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest     = __DIR__ . '/../uploads/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        redirect_banners('Failed to save file.', true);
    }

    $title    = trim($_POST['title']    ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $link     = trim($_POST['link_url'] ?? '');

    $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM banners')->fetchColumn();

    $stmt = $pdo->prepare('INSERT INTO banners (title, subtitle, link_url, image_filename, sort_order) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $title    ?: null,
        $subtitle ?: null,
        $link     ?: null,
        $filename,
        $maxSort + 1,
    ]);

    redirect_banners('Banner uploaded.');
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
