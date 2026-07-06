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

admin_require('orders');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/orders.php');
    exit;
}

csrf_verify();

$action  = $_POST['action'] ?? '';
$orderId = (int)($_POST['order_id'] ?? 0);

if (!$orderId) {
    header('Location: /admin/orders.php');
    exit;
}

$returnUrl = '/admin/order.php?id=' . $orderId;

if ($action === 'save_note') {
    $note = trim($_POST['admin_note'] ?? '');
    $pdo->prepare('UPDATE orders SET admin_note = ? WHERE id = ?')
        ->execute([$note ?: null, $orderId]);
    $_SESSION['admin_success'] = 'Note saved.';

} elseif ($action === 'cancel') {
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order || !in_array($order['status'], ['pending', 'paid'])) {
        $_SESSION['admin_error'] = 'Order cannot be cancelled at this stage.';
        header('Location: ' . $returnUrl);
        exit;
    }

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE orders SET status = 'cancelled', admin_note = CONCAT(COALESCE(admin_note, ''), IF(admin_note IS NOT NULL, '\n', ''), ?) WHERE id = ?")
        ->execute(['[Cancelled by admin: ' . trim($_POST['cancel_reason'] ?? '') . ']', $orderId]);
    $pdo->prepare('
        UPDATE products p
        JOIN order_items oi ON oi.product_id = p.id
        SET p.stock = p.stock + oi.quantity
        WHERE oi.order_id = ?
    ')->execute([$orderId]);
    $pdo->commit();
    $_SESSION['admin_success'] = 'Order cancelled and stock restored.';
}

header('Location: ' . $returnUrl);
exit;
