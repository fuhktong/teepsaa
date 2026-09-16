<?php
// Confirm the returned item arrived — the app's version of the one button on
// orders-vendor/refund.php, which posts to products/return-received.php.
//
// This moves the refund on to 'return_received', which is teepsaa's cue to pay
// the buyer back. It is the vendor saying "I have the item", so it is only
// offered while the return is in transit and it is never inferred.

require __DIR__ . '/../../config/api.php';
require __DIR__ . '/../../config/notify.php';

api_require_method('POST');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$body     = api_body();
$publicId = trim((string)($body['id'] ?? ''));
if ($publicId === '') api_json(['error' => 'missing_id'], 400);

$stmt = $pdo->prepare('
    SELECT o.id, o.status, b.deleted_at AS business_closed
      FROM orders o
      JOIN businesses b ON b.id = o.business_id
     WHERE o.public_id = ? AND b.user_id = ?
');
$stmt->execute([$publicId, $userId]);
$o = $stmt->fetch();

if (!$o) api_json(['error' => 'not_found'], 404);

$orderId = (int)$o['id'];

// A closed shop keeps its refunds visible but cannot act on them; teepsaa
// finishes those by hand. The website's UPDATE simply matches no rows in this
// case, which would look to the app like a lost request — say it plainly.
if ($o['business_closed'] !== null) {
    api_json(['error' => 'shop_closed'], 409);
}

if ($o['status'] !== 'return_dispatched') {
    api_json(['error' => 'wrong_status', 'status' => $o['status']], 409);
}

$upd = $pdo->prepare("
    UPDATE orders o
      JOIN businesses b ON b.id = o.business_id
       SET o.status = 'return_received'
     WHERE o.id = ? AND b.user_id = ? AND b.deleted_at IS NULL
       AND o.status = 'return_dispatched'
");
$upd->execute([$orderId, $userId]);

// Zero rows means something else got there first between the read and the
// write. Nothing changed, so report where the refund actually is rather than
// claiming a confirmation that never happened.
if ($upd->rowCount() === 0) {
    $now = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
    $now->execute([$orderId]);
    api_json(['error' => 'wrong_status', 'status' => $now->fetchColumn() ?: 'unknown'], 409);
}

// Confirmed at this point. A failure past here must not come back as a failed
// confirmation, or the vendor taps again and gets a 409 on a refund that did
// move on. The website sends this notification and no email; match it.
try {
    $buyerStmt = $pdo->prepare('
        SELECT o.public_id, o.created_at, o.buyer_user_id
          FROM orders o WHERE o.id = ?
    ');
    $buyerStmt->execute([$orderId]);
    if ($buyer = $buyerStmt->fetch()) {
        $oid = order_display_id($orderId, $buyer['created_at']);
        notify(
            $pdo, 'buyer', (int)$buyer['buyer_user_id'], 'return_received',
            'Your return for order #' . $oid . ' was received — your refund is being processed.',
            '/orders-buyer/order.php?id=' . $buyer['public_id'],
            ['ref' => $oid]
        );
    }
} catch (Throwable $e) {
    error_log('refund-received: notify failed for order ' . $orderId . ': ' . $e->getMessage());
}

api_json([
    'ok'           => true,
    'status'       => 'return_received',
    'refund_state' => 'return_received',
]);
