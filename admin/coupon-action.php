<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('coupons');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/coupons.php');
    exit;
}

csrf_verify();

$action = $_POST['action'] ?? '';

// starts_at is normalized to the start of the chosen day, expires_at to the
// end of it, so a coupon stays valid through the whole calendar day picked.
function normalize_coupon_date(?string $raw, string $time): ?string {
    $raw = trim((string)$raw);
    return $raw !== '' ? $raw . ' ' . $time : null;
}

if ($action === 'create') {
    $code      = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($_POST['code'] ?? '')));
    $type      = ($_POST['type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
    $value     = (float)($_POST['value'] ?? 0);
    $minOrder  = max(0, (float)($_POST['min_order'] ?? 0));
    $maxUses   = ($_POST['max_uses'] ?? '') !== '' ? max(1, (int)$_POST['max_uses']) : null;
    $startsAt  = normalize_coupon_date($_POST['starts_at'] ?? '', '00:00:00');
    $expiresAt = normalize_coupon_date($_POST['expires_at'] ?? '', '23:59:59');

    if (!$code || $value <= 0 || ($type === 'percent' && $value > 100)) {
        $_SESSION['admin_error'] = 'Enter a valid code and value (percent must be 100 or less).';
        header('Location: /admin/coupons.php');
        exit;
    }

    try {
        $pdo->prepare('INSERT INTO coupons (code, type, value, min_order, max_uses, starts_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$code, $type, $value, $minOrder, $maxUses, $startsAt, $expiresAt]);
        $_SESSION['admin_success'] = 'Coupon "' . $code . '" created.';
    } catch (\PDOException $e) {
        $_SESSION['admin_error'] = 'That code already exists.';
    }

} elseif ($action === 'edit') {
    $id        = (int)($_POST['id'] ?? 0);
    $value     = (float)($_POST['value'] ?? 0);
    $minOrder  = max(0, (float)($_POST['min_order'] ?? 0));
    $maxUses   = ($_POST['max_uses'] ?? '') !== '' ? max(1, (int)$_POST['max_uses']) : null;
    $startsAt  = normalize_coupon_date($_POST['starts_at'] ?? '', '00:00:00');
    $expiresAt = normalize_coupon_date($_POST['expires_at'] ?? '', '23:59:59');

    $typeStmt = $pdo->prepare('SELECT type, expires_at FROM coupons WHERE id = ?');
    $typeStmt->execute([$id]);
    $existing = $typeStmt->fetch();

    if (!$existing || ($existing['expires_at'] && strtotime($existing['expires_at']) < time())) {
        $_SESSION['admin_error'] = 'Expired coupons cannot be edited.';
        header('Location: /admin/coupons.php');
        exit;
    }
    if ($value <= 0 || ($existing['type'] === 'percent' && $value > 100)) {
        $_SESSION['admin_error'] = 'Enter a valid value (percent must be 100 or less).';
        header('Location: /admin/coupons.php');
        exit;
    }

    $pdo->prepare('UPDATE coupons SET value = ?, min_order = ?, max_uses = ?, starts_at = ?, expires_at = ? WHERE id = ?')
        ->execute([$value, $minOrder, $maxUses, $startsAt, $expiresAt, $id]);
    $_SESSION['admin_success'] = 'Coupon updated.';

} elseif ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare('UPDATE coupons SET active = 1 - active WHERE id = ?')->execute([$id]);
    $_SESSION['admin_success'] = 'Coupon updated.';

} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM coupons WHERE id = ? AND used_count = 0');
    $stmt->execute([$id]);
    $_SESSION['admin_success'] = $stmt->rowCount() > 0
        ? 'Coupon deleted.'
        : 'Cannot delete a coupon that has been used — deactivate it instead.';
}

header('Location: /admin/coupons.php');
exit;
