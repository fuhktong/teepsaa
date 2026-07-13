<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/csrf.php';
require __DIR__ . '/../../config/notify.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard-vendor/settings/?tab=danger');
    exit;
}

csrf_verify();

$userId   = $_SESSION['user_id'];
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare('SELECT password FROM vendors WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!password_verify($password, $row['password'])) {
    $_SESSION['settings_error'] = 'Incorrect password.';
    header('Location: /dashboard-vendor/settings/?tab=danger');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, banner FROM businesses WHERE user_id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$userId]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: /dashboard-vendor/settings/?tab=danger');
    exit;
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE business_id = ?');
$stmt->execute([$business['id']]);
if ($stmt->fetchColumn() > 0) {
    $_SESSION['settings_error'] = 'Cannot delete business — you must delete all of your products first, including archived ones.';
    header('Location: /dashboard-vendor/settings/?tab=danger');
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE business_id = ? AND status NOT IN ('completed','cancelled','refunded','refund_rejected')");
$stmt->execute([$business['id']]);
if ($stmt->fetchColumn() > 0) {
    $_SESSION['settings_error'] = 'Cannot delete business — all orders must be completed, cancelled, or refunded first.';
    header('Location: /dashboard-vendor/settings/?tab=danger');
    exit;
}

$uploadDir = __DIR__ . '/../../uploads/';

$stmt = $pdo->prepare('SELECT filename FROM photos WHERE business_id = ?');
$stmt->execute([$business['id']]);
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $filename) {
    if ($filename && file_exists($uploadDir . $filename)) @unlink($uploadDir . $filename);
}
$pdo->prepare('DELETE FROM photos WHERE business_id = ?')->execute([$business['id']]);

if ($business['banner'] && file_exists($uploadDir . $business['banner'])) {
    @unlink($uploadDir . $business['banner']);
}

// Soft delete: the row (and its orders, reviews, coupons, penalties) is kept for accounting
$pdo->prepare('UPDATE businesses SET deleted_at = NOW(), approved = -1, banner = NULL WHERE id = ?')
    ->execute([$business['id']]);

$vStmt = $pdo->prepare('SELECT name, email FROM vendors WHERE id = ?');
$vStmt->execute([$userId]);
if ($owner = $vStmt->fetch()) {
    [$subj, $html] = render_email_template($pdo, 'business_deleted', [
        'name'     => htmlspecialchars($owner['name']),
        'business' => htmlspecialchars($business['name']),
    ]);
    if ($html !== '') send_email($owner['email'], $subj, $html);
}

$_SESSION['settings_success'] = 'Your business has been deleted.';
header('Location: /dashboard-vendor/settings/?tab=danger');
exit;
