<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /business-vendor/');
    exit;
}

csrf_verify();

$userId      = $_SESSION['user_id'];
$name        = trim($_POST['business_name'] ?? '');
$nameKm      = trim($_POST['business_name_km'] ?? '');
// Description shows under the store name on the storefront — cap at 160 chars
// (matches the maxlength on the settings form).
$description   = mb_substr(trim($_POST['description'] ?? ''), 0, 160);
$descriptionKm = mb_substr(trim($_POST['description_km'] ?? ''), 0, 160);
// Cascade picker posts one comma-separated string of English category names —
// any level of the tree, not just parents. Keep only names that still exist.
$rawCats     = array_filter(array_map('trim', explode(',', $_POST['category'] ?? '')));

if (!$name) {
    $_SESSION['settings_error'] = 'Business name is required.';
    header('Location: /business-vendor/');
    exit;
}

$allCatNames = $pdo->query('SELECT name FROM categories')->fetchAll(PDO::FETCH_COLUMN);
$safeCats = array_values(array_unique(array_filter($rawCats, fn($c) => in_array($c, $allCatNames, true))));
$category = implode(', ', $safeCats);

$stmt = $pdo->prepare('UPDATE businesses SET name = ?, name_km = ?, description = ?, description_km = ?, category = ? WHERE user_id = ? AND deleted_at IS NULL');
$stmt->execute([$name, $nameKm ?: null, $description, $descriptionKm ?: null, $category, $userId]);

// Storefront layout — same form, saved together. Featured pick + ordered slots.
// Only touch products this shop owns, so a tampered POST can't feature or slot
// another shop's product.
$stmt = $pdo->prepare('SELECT id FROM businesses WHERE user_id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$userId]);
$businessId = $stmt->fetchColumn();

if ($businessId) {
    $stmt = $pdo->prepare('SELECT id FROM products WHERE business_id = ? AND active = 1 AND archived = 0');
    $stmt->execute([$businessId]);
    $ownedSet = array_flip(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));

    $featuredId = (int)($_POST['featured'] ?? 0);
    if (!isset($ownedSet[$featuredId])) {
        $featuredId = 0;
    }

    // Slots in the vendor's arranged order: owned only, not the featured hero,
    // de-duplicated so a product can't occupy two slots.
    $slots = [];
    foreach ((array)($_POST['slots'] ?? []) as $pid) {
        $pid = (int)$pid;
        if ($pid && $pid !== $featuredId && isset($ownedSet[$pid]) && !in_array($pid, $slots, true)) {
            $slots[] = $pid;
        }
    }

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE products SET is_featured = 0, storefront_order = NULL WHERE business_id = ?')->execute([$businessId]);
    if ($featuredId) {
        $pdo->prepare('UPDATE products SET is_featured = 1 WHERE id = ? AND business_id = ?')->execute([$featuredId, $businessId]);
    }
    $pos = 1;
    $upd = $pdo->prepare('UPDATE products SET storefront_order = ? WHERE id = ? AND business_id = ?');
    foreach ($slots as $pid) {
        $upd->execute([$pos++, $pid, $businessId]);
    }
    $pdo->commit();
}

$_SESSION['settings_success'] = 'Business info updated.';
header('Location: /business-vendor/');
exit;
