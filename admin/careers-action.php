<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/app.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin' || !($_SESSION['is_admin'] ?? false)) {
    header('Location: /login-admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/careers.php');
    exit;
}

csrf_verify();

$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);

function redirect_careers(string $msg = '', bool $error = false): never {
    if ($msg) {
        $_SESSION[$error ? 'career_error' : 'career_success'] = $msg;
    }
    header('Location: /admin/careers.php');
    exit;
}

function redirect_apps(string $msg = '', bool $error = false): never {
    if ($msg) {
        $_SESSION[$error ? 'career_error' : 'career_success'] = $msg;
    }
    $job = (int) ($_POST['job_filter'] ?? 0);
    header('Location: /admin/careers-applications.php' . ($job ? '?job=' . $job : ''));
    exit;
}

match ($action) {
    'save'       => do_save($id),
    'toggle'     => do_toggle($id),
    'delete'     => do_delete($id),
    'app_status' => do_app_status($id),
    'app_delete' => do_app_delete($id),
    default      => redirect_careers(),
};

function do_save(int $id): void {
    global $pdo;

    $title       = trim($_POST['title'] ?? '');
    $titleKm     = trim($_POST['title_km'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $locationKm  = trim($_POST['location_km'] ?? '');
    $type        = trim($_POST['employment_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $descriptionKm = trim($_POST['description_km'] ?? '');
    $isOpen      = isset($_POST['is_open']) ? 1 : 0;

    if ($title === '') {
        redirect_careers('Title is required.', true);
    }

    if ($id) {
        $stmt = $pdo->prepare('UPDATE job_postings SET title = ?, title_km = ?, location = ?, location_km = ?, employment_type = ?, description = ?, description_km = ?, is_open = ? WHERE id = ?');
        $stmt->execute([$title, $titleKm ?: null, $location ?: null, $locationKm ?: null, $type ?: null, $description ?: null, $descriptionKm ?: null, $isOpen, $id]);
        redirect_careers('Posting updated.');
    }

    $stmt = $pdo->prepare('INSERT INTO job_postings (title, title_km, location, location_km, employment_type, description, description_km, is_open) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$title, $titleKm ?: null, $location ?: null, $locationKm ?: null, $type ?: null, $description ?: null, $descriptionKm ?: null, $isOpen]);
    redirect_careers('Posting added.');
}

function do_toggle(int $id): void {
    global $pdo;
    if (!$id) redirect_careers();
    $pdo->prepare('UPDATE job_postings SET is_open = 1 - is_open WHERE id = ?')->execute([$id]);
    redirect_careers();
}

function do_delete(int $id): void {
    global $pdo;
    if (!$id) redirect_careers('Invalid posting.', true);

    // Remove any résumé files belonging to this posting's applications.
    $files = $pdo->prepare('SELECT resume_file FROM job_applications WHERE job_id = ? AND resume_file IS NOT NULL');
    $files->execute([$id]);
    foreach ($files->fetchAll(PDO::FETCH_COLUMN) as $f) {
        $path = RESUME_DIR . '/' . basename($f);
        if (is_file($path)) @unlink($path);
    }

    // job_applications rows are removed via ON DELETE CASCADE.
    $pdo->prepare('DELETE FROM job_postings WHERE id = ?')->execute([$id]);
    redirect_careers('Posting deleted.');
}

function do_app_status(int $id): void {
    global $pdo;
    $status = $_POST['status'] ?? '';
    $valid  = ['new', 'reviewed', 'shortlisted', 'rejected'];
    if (!$id || !in_array($status, $valid, true)) redirect_apps();
    $pdo->prepare('UPDATE job_applications SET status = ? WHERE id = ?')->execute([$status, $id]);
    redirect_apps('Status updated.');
}

function do_app_delete(int $id): void {
    global $pdo;
    if (!$id) redirect_apps('Invalid application.', true);

    $row = $pdo->prepare('SELECT resume_file FROM job_applications WHERE id = ?');
    $row->execute([$id]);
    $app = $row->fetch();
    if ($app && $app['resume_file']) {
        $path = RESUME_DIR . '/' . basename($app['resume_file']);
        if (is_file($path)) @unlink($path);
    }

    $pdo->prepare('DELETE FROM job_applications WHERE id = ?')->execute([$id]);
    redirect_apps('Application deleted.');
}
