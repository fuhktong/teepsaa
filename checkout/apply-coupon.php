<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/coupon.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /checkout/');
    exit;
}

csrf_verify();

$action = $_POST['action'] ?? '';

if ($action === 'remove') {
    unset($_SESSION['checkout_coupon_code']);
    header('Location: /checkout/');
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare('
    SELECT b.id AS business_id,
           COALESCE(pv.price_override, IF(p.sale_ends_at IS NOT NULL AND p.sale_ends_at > NOW(), p.sale_price, NULL), p.price) AS effective_price,
           ci.quantity
    FROM cart_items ci
    JOIN products p ON p.id = ci.product_id AND p.active = 1
    JOIN businesses b ON b.id = p.business_id AND b.approved = 1
    LEFT JOIN product_variants pv ON pv.id = ci.variant_id
    WHERE ci.buyer_user_id = ?
');
$stmt->execute([$userId]);
$subtotalsByBusiness = [];
foreach ($stmt->fetchAll() as $row) {
    $bid = (int)$row['business_id'];
    $subtotalsByBusiness[$bid] = ($subtotalsByBusiness[$bid] ?? 0.0) + $row['effective_price'] * $row['quantity'];
}

$code   = $_POST['code'] ?? '';
$result = validate_coupon($pdo, $code, $subtotalsByBusiness, $userId);

if ($result['valid']) {
    $_SESSION['checkout_coupon_code'] = strtoupper(trim($code));
    $_SESSION['cart_success'] = $result['message'];
} else {
    unset($_SESSION['checkout_coupon_code']);
    $_SESSION['cart_error'] = $result['message'];
}

header('Location: /checkout/');
exit;
