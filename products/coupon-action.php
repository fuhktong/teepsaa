<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /products/?tab=coupons');
    exit;
}

csrf_verify();

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT id FROM businesses WHERE user_id = ? AND approved = 1 ORDER BY name ASC LIMIT 1');
$stmt->execute([$userId]);
$businessId = $stmt->fetchColumn();

if (!$businessId) {
    header('Location: /products/?tab=coupons');
    exit;
}

$action = $_POST['action'] ?? '';

// starts_at is normalized to the start of the chosen day, expires_at to the
// end of it, so a coupon stays valid through the whole calendar day picked.
function normalize_vendor_coupon_date(?string $raw, string $time): ?string {
    $raw = trim((string)$raw);
    return $raw !== '' ? $raw . ' ' . $time : null;
}

if ($action === 'create') {
    $code      = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($_POST['code'] ?? '')));
    $type      = ($_POST['type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
    $value     = (float)($_POST['value'] ?? 0);
    $minOrder  = max(0, (float)($_POST['min_order'] ?? 0));
    $maxUses   = ($_POST['max_uses'] ?? '') !== '' ? max(1, (int)$_POST['max_uses']) : null;
    $startsAt  = normalize_vendor_coupon_date($_POST['starts_at'] ?? '', '00:00:00');
    $expiresAt = normalize_vendor_coupon_date($_POST['expires_at'] ?? '', '23:59:59');

    if (!$code || $value <= 0 || ($type === 'percent' && $value > 100)) {
        $_SESSION['product_error'] = 'Enter a valid code and value (percent must be 100 or less).';
        header('Location: /products/?tab=coupons');
        exit;
    }

    try {
        $pdo->prepare('INSERT INTO coupons (code, business_id, type, value, min_order, max_uses, starts_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$code, $businessId, $type, $value, $minOrder, $maxUses, $startsAt, $expiresAt]);
        $_SESSION['product_success'] = 'Coupon "' . $code . '" created.';
    } catch (\PDOException $e) {
        $_SESSION['product_error'] = 'That code already exists.';
    }

} elseif ($action === 'edit') {
    $id        = (int)($_POST['id'] ?? 0);
    $value     = (float)($_POST['value'] ?? 0);
    $minOrder  = max(0, (float)($_POST['min_order'] ?? 0));
    $maxUses   = ($_POST['max_uses'] ?? '') !== '' ? max(1, (int)$_POST['max_uses']) : null;
    $startsAt  = normalize_vendor_coupon_date($_POST['starts_at'] ?? '', '00:00:00');
    $expiresAt = normalize_vendor_coupon_date($_POST['expires_at'] ?? '', '23:59:59');

    $typeStmt = $pdo->prepare('SELECT type, expires_at FROM coupons WHERE id = ? AND business_id = ?');
    $typeStmt->execute([$id, $businessId]);
    $existing = $typeStmt->fetch();

    if (!$existing || ($existing['expires_at'] && strtotime($existing['expires_at']) < time())) {
        $_SESSION['product_error'] = 'Expired coupons cannot be edited.';
        header('Location: /products/?tab=coupons');
        exit;
    }
    if ($value <= 0 || ($existing['type'] === 'percent' && $value > 100)) {
        $_SESSION['product_error'] = 'Enter a valid value (percent must be 100 or less).';
        header('Location: /products/?tab=coupons');
        exit;
    }

    $pdo->prepare('UPDATE coupons SET value = ?, min_order = ?, max_uses = ?, starts_at = ?, expires_at = ? WHERE id = ? AND business_id = ?')
        ->execute([$value, $minOrder, $maxUses, $startsAt, $expiresAt, $id, $businessId]);
    $_SESSION['product_success'] = 'Coupon updated.';

} elseif ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare('UPDATE coupons SET active = 1 - active WHERE id = ? AND business_id = ?')->execute([$id, $businessId]);
    $_SESSION['product_success'] = 'Coupon updated.';

} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM coupons WHERE id = ? AND business_id = ? AND used_count = 0');
    $stmt->execute([$id, $businessId]);
    $_SESSION['product_success'] = $stmt->rowCount() > 0
        ? 'Coupon deleted.'
        : 'Cannot delete a coupon that has been used — deactivate it instead.';
}

header('Location: /products/?tab=coupons');
exit;
