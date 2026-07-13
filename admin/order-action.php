<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';
require __DIR__ . '/../config/notify.php';

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

    $bStmt = $pdo->prepare(
        'SELECT o.public_id, o.created_at, bu.name, bu.email
         FROM orders o JOIN buyers bu ON bu.id = o.buyer_user_id
         WHERE o.id = ?'
    );
    $bStmt->execute([$orderId]);
    if ($buyer = $bStmt->fetch()) {
        $oid = order_display_id($orderId, $buyer['created_at']);
        [$subj, $html] = render_email_template($pdo, 'order_cancelled', [
            'name'    => htmlspecialchars($buyer['name']),
            'order'   => $oid,
            'cta_url' => 'https://teepsaa.com/dashboard-buyer/order.php?id=' . $buyer['public_id'],
        ]);
        if ($html !== '') send_email($buyer['email'], $subj, $html);
    }

    $_SESSION['admin_success'] = 'Order cancelled and stock restored.';
}

header('Location: ' . $returnUrl);
exit;
