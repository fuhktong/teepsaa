<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard-vendor/settings/?tab=business');
    exit;
}

csrf_verify();

$userId = (int)$_SESSION['user_id'];

// The vendor's shop.
$stmt = $pdo->prepare('SELECT id FROM businesses WHERE user_id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$userId]);
$businessId = $stmt->fetchColumn();

if (!$businessId) {
    header('Location: /dashboard-vendor/settings/?tab=business');
    exit;
}

// The set of product IDs this shop actually owns (and can show) — anything the
// form sends that isn't in here is ignored, so a tampered POST can't feature or
// slot another shop's product.
$stmt = $pdo->prepare('SELECT id FROM products WHERE business_id = ? AND active = 1 AND archived = 0');
$stmt->execute([$businessId]);
$ownedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
$ownedSet = array_flip($ownedIds);

$featuredId = (int)($_POST['featured'] ?? 0);
if (!isset($ownedSet[$featuredId])) {
    $featuredId = 0;
}

// Slots in the order the vendor arranged them: keep only owned products, drop
// the featured one (it's the hero, not a grid tile), and de-duplicate so a
// product can't occupy two slots.
$rawSlots = $_POST['slots'] ?? [];
$slots    = [];
foreach ((array)$rawSlots as $pid) {
    $pid = (int)$pid;
    if ($pid && $pid !== $featuredId && isset($ownedSet[$pid]) && !in_array($pid, $slots, true)) {
        $slots[] = $pid;
    }
}

$pdo->beginTransaction();

// Reset this shop's layout, then re-apply featured + slot positions.
$pdo->prepare('UPDATE products SET is_featured = 0, storefront_order = NULL WHERE business_id = ?')
    ->execute([$businessId]);

if ($featuredId) {
    $pdo->prepare('UPDATE products SET is_featured = 1 WHERE id = ? AND business_id = ?')
        ->execute([$featuredId, $businessId]);
}

$pos  = 1;
$upd  = $pdo->prepare('UPDATE products SET storefront_order = ? WHERE id = ? AND business_id = ?');
foreach ($slots as $pid) {
    $upd->execute([$pos, $pid, $businessId]);
    $pos++;
}

$pdo->commit();

$_SESSION['settings_success'] = 'Storefront updated.';
header('Location: /dashboard-vendor/settings/?tab=business');
exit;
