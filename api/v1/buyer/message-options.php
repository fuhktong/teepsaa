<?php
// GET /api/v1/buyer/message-options.php
//
// What the app needs to draw the "contact support" form, as contact-buyer/
// shows it: the issue types (English value stored, label in the buyer's
// language) and the buyer's last fifty orders, every status, so one can be
// attached. Also says whether a pending request already blocks a new one.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/buyer-orders.php';

api_require_method('GET');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];
$t      = buyer_order_words();

$issueTypes = [
    ['value' => 'Order issue',   'label' => $t['contact_issue_order']],
    ['value' => 'Payment issue', 'label' => $t['contact_issue_payment']],
    ['value' => 'Account issue', 'label' => $t['contact_issue_account']],
    ['value' => 'Other',         'label' => $t['contact_issue_other']],
];

$stmt = $pdo->prepare('
    SELECT o.id, o.public_id, o.created_at,
           GROUP_CONCAT(CONCAT(oi.product_name, " x", oi.quantity) ORDER BY oi.id SEPARATOR ", ") AS items
      FROM orders o
      JOIN order_items oi ON oi.order_id = o.id
     WHERE o.buyer_user_id = ?
     GROUP BY o.id
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT 50
');
$stmt->execute([$userId]);

$orders = [];
foreach ($stmt->fetchAll() as $o) {
    $orders[] = [
        'id'    => $o['public_id'],
        'ref'   => buyer_order_ref((int)$o['id'], $o['created_at']),
        'items' => $o['items'],
    ];
}

$pendingStmt = $pdo->prepare("
    SELECT id FROM support_threads
     WHERE sender_id = ? AND sender_role = 'buyer' AND status = 'pending'
     LIMIT 1
");
$pendingStmt->execute([$userId]);
$pendingId = (int)($pendingStmt->fetchColumn() ?: 0);

api_json([
    'issue_types'       => $issueTypes,
    'orders'            => $orders,
    'subject_max'       => 255,
    'body_max'          => 2000,
    'can_start'         => $pendingId === 0,
    'pending_thread_id' => $pendingId ?: null,
]);
