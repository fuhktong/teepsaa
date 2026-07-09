<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/csrf.php';
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/coupon.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    http_response_code(401);
    echo json_encode(['valid' => false, 'message' => 'Please log in as a buyer.']);
    exit;
}

if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['valid' => false, 'message' => 'Invalid request.']);
    exit;
}

$code       = (string)($_POST['code'] ?? '');
$businessId = (int)($_POST['business_id'] ?? 0);
$subtotal   = (float)($_POST['subtotal'] ?? 0);

$result = validate_coupon($pdo, $code, [$businessId => $subtotal], (int)$_SESSION['user_id']);

echo json_encode([
    'valid'    => $result['valid'],
    'discount' => $result['valid'] ? $result['discount'] : 0,
    'message'  => $result['message'],
]);
