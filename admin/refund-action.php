<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';
require __DIR__ . '/../config/audit.php';
require __DIR__ . '/../config/notify.php';
require __DIR__ . '/../config/refund-action.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('refunds');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/refunds.php');
    exit;
}

csrf_verify();

$action  = $_POST['action'] ?? '';
$orderId = (int)($_POST['order_id'] ?? 0);

if (!$orderId) {
    header('Location: /admin/refunds.php');
    exit;
}

$redirectUrl = '/admin/refund.php?id=' . $orderId;

$emailKey = null;
// The bell notification normally shares the email's key; a decision after a
// disputed return words it its own way.
$notifType = null;
$notifData = [];

// A dispute waits for the vendor's reply, or for the reply window to close
// (config/refund-evidence.php). Orders put back by an overturn are long past it.
$replyDone = "(vendor_reply_at IS NOT NULL OR refund_requested_at IS NULL
               OR refund_requested_at <= NOW() - INTERVAL " . REFUND_REPLY_HOURS . " HOUR)";
$replyWait = 'The vendor still has time to reply — approve or reject once they do or the ' . REFUND_REPLY_HOURS . ' hours are up.';

if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE orders SET status = 'return_approved' WHERE id = ? AND status = 'refund_requested' AND $replyDone");
    $stmt->execute([$orderId]);
    if ($stmt->rowCount()) {
        $emailKey = 'refund_approved';
        audit_log($pdo, 'refund.approve', 'order', $orderId);
        $_SESSION['admin_success'] = 'Return approved — buyer has been notified to send item back.';
    } else {
        $_SESSION['admin_error'] = $replyWait;
    }

} elseif ($action === 'reject') {
    // The request came from a delivered order, so denying it puts the order
    // back exactly where it was — otherwise it can never reach 'completed'
    // and falls out of the payout queue, and the vendor is never paid.
    // The rejection is recorded in its own column instead of in status.
    $stmt = $pdo->prepare("UPDATE orders SET status = 'delivered', refund_rejected_at = NOW() WHERE id = ? AND status = 'refund_requested' AND $replyDone");
    $stmt->execute([$orderId]);
    if ($stmt->rowCount()) {
        $emailKey = 'refund_rejected';
        audit_log($pdo, 'refund.reject', 'order', $orderId);
        $_SESSION['admin_success'] = 'Refund request rejected.';
    } else {
        $_SESSION['admin_error'] = $replyWait;
    }

} elseif ($action === 'overturn') {
    // Second look went the buyer's way. Put the request back where it was so it
    // re-enters Orders → Refunds as 'Requested' and runs the normal approve
    // path — the buyer's own words in refund_reason are left untouched.
    // Only reachable while the order is still 'delivered': once the payout has
    // gone out the status is 'completed' and this matches nothing.
    $stmt = $pdo->prepare("UPDATE orders SET status = 'refund_requested', refund_rejected_at = NULL, refund_closed_at = NULL WHERE id = ? AND status = 'delivered' AND refund_rejected_at IS NOT NULL");
    $stmt->execute([$orderId]);
    if ($stmt->rowCount()) {
        audit_log($pdo, 'refund.overturn', 'order', $orderId);
        $_SESSION['admin_success'] = 'Rejection overturned — the request is open again. Approve the return to grant the refund.';
    } else {
        $_SESSION['admin_error'] = 'This rejection can no longer be overturned — the payout has already gone out.';
    }

} elseif ($action === 'confirm') {
    // The rejection stands. refund_rejected_at already blocks the buyer from
    // filing again, so the rest of the refund window is holding the payout
    // against something that can't happen — close it and let the vendor be paid.
    $stmt = $pdo->prepare("UPDATE orders SET refund_closed_at = NOW() WHERE id = ? AND status = 'delivered' AND refund_rejected_at IS NOT NULL AND refund_closed_at IS NULL");
    $stmt->execute([$orderId]);
    if ($stmt->rowCount()) {
        audit_log($pdo, 'refund.confirm', 'order', $orderId);
    }
    $_SESSION['admin_success'] = 'Rejection confirmed — the refund window is closed and the payout can be released.';

} elseif ($action === 'complete') {
    // A disputed return is decided below, never just marked refunded.
    $stmt = $pdo->prepare("UPDATE orders SET status = 'refunded', refunded_at = NOW(), refund_paid = subtotal - discount_amount WHERE id = ? AND status = 'return_received' AND return_disputed_at IS NULL");
    $stmt->execute([$orderId]);
    if ($stmt->rowCount()) {
        $emailKey = 'refund_sent';
        // Money out — same weight as a payout, so log the amount with it.
        $amtStmt = $pdo->prepare('SELECT subtotal FROM orders WHERE id = ?');
        $amtStmt->execute([$orderId]);
        audit_log($pdo, 'refund.complete', 'order', $orderId, [
            'amount' => (float)$amtStmt->fetchColumn(),
        ]);
    }
    $_SESSION['admin_success'] = 'Order marked as refunded. Remember to send the buyer their subtotal via ABA.';

} elseif ($action === 'decide_full' || $action === 'decide_part') {
    // The vendor said the return is used, empty or different; the admin has
    // looked and is refunding all or part of the product price. Delivery is
    // never refunded, so the most is subtotal − discount.
    $full = $pdo->prepare("SELECT ROUND(subtotal - discount_amount, 2) FROM orders WHERE id = ? AND status = 'return_received' AND return_disputed_at IS NOT NULL");
    $full->execute([$orderId]);
    $max = $full->fetchColumn();
    $amount = $action === 'decide_full' ? (float)$max : round((float)($_POST['amount'] ?? 0), 2);
    if ($max === false) {
        $_SESSION['admin_error'] = 'This refund has already been decided.';
    } elseif ($action === 'decide_part' && ($amount <= 0 || $amount >= (float)$max)) {
        $_SESSION['admin_error'] = 'A part refund must be more than $0 and less than $' . number_format((float)$max, 2) . '.';
    } else {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'refunded', refunded_at = NOW(), refund_paid = ? WHERE id = ? AND status = 'return_received' AND return_disputed_at IS NOT NULL");
        $stmt->execute([$amount, $orderId]);
        if ($stmt->rowCount()) {
            $emailKey = 'refund_sent';
            if ($action === 'decide_part') {
                $notifType = 'refund_part_sent';
                $notifData = ['amount' => '$' . number_format($amount, 2)];
            }
            audit_log($pdo, 'refund.' . $action, 'order', $orderId, ['amount' => $amount]);
        }
        $_SESSION['admin_success'] = 'Refund of $' . number_format($amount, 2) . ' recorded. Make sure it has gone to the buyer via ABA.';
    }

} elseif ($action === 'decide_none') {
    // No refund after a disputed return. The order goes back to 'delivered'
    // with the rejection recorded and the window closed, like a confirmed
    // rejection, so the vendor can be paid out. The item is still the buyer's:
    // the vendor posts it back, and the buyer pays the postage.
    $stmt = $pdo->prepare("UPDATE orders SET status = 'delivered', refund_rejected_at = NOW(), refund_closed_at = NOW() WHERE id = ? AND status = 'return_received' AND return_disputed_at IS NOT NULL");
    $stmt->execute([$orderId]);
    if ($stmt->rowCount()) {
        $emailKey  = 'refund_rejected';
        $notifType = 'refund_refused';
        audit_log($pdo, 'refund.decide_none', 'order', $orderId);
        $v = $pdo->prepare('SELECT o.public_id, o.created_at, b.user_id FROM orders o JOIN businesses b ON b.id = o.business_id WHERE o.id = ?');
        $v->execute([$orderId]);
        if ($vr = $v->fetch()) {
            $void = order_display_id($orderId, $vr['created_at']);
            notify($pdo, 'vendor', (int)$vr['user_id'], 'return_post_back',
                'No refund for order #' . $void . ' — post the item back to the buyer. The buyer pays the postage.',
                '/orders-vendor/refund.php?id=' . $vr['public_id'], ['ref' => $void]);
        }
        $_SESSION['admin_success'] = 'No refund. The buyer and the vendor have been told the item goes back to the buyer.';
    } else {
        $_SESSION['admin_error'] = 'This refund has already been decided.';
    }
}

if ($emailKey) {
    // The bell notification type matches the email template key one-to-one.
    $notifMsgs = [
        'refund_approved'  => 'Your refund was approved for order #%s — please send the item back.',
        'refund_rejected'  => 'Your refund request for order #%s was declined.',
        'refund_sent'      => 'Your refund for order #%s has been sent.',
        'refund_part_sent' => 'A part refund of %2$s for order #%1$s has been sent.',
        'refund_refused'   => 'No refund for order #%s after the return was checked. The shop will post the item back to you; you pay the postage.',
    ];
    $notifType = $notifType ?? $emailKey;
    $bStmt = $pdo->prepare(
        'SELECT o.public_id, o.created_at, bu.id AS buyer_id, bu.name, bu.email
         FROM orders o JOIN buyers bu ON bu.id = o.buyer_user_id
         WHERE o.id = ?'
    );
    $bStmt->execute([$orderId]);
    if ($buyer = $bStmt->fetch()) {
        $oid = order_display_id($orderId, $buyer['created_at']);
        [$subj, $html] = render_email_template($pdo, $emailKey, [
            'name'    => htmlspecialchars($buyer['name']),
            'order'   => $oid,
            'cta_url' => 'https://teepsaa.com/orders-buyer/order.php?id=' . $buyer['public_id'],
        ]);
        if ($html !== '') send_email($buyer['email'], $subj, $html);
        notify($pdo, 'buyer', (int)$buyer['buyer_id'], $notifType,
            sprintf($notifMsgs[$notifType] ?? 'Update on order #%s.', $oid, $notifData['amount'] ?? ''),
            '/orders-buyer/order.php?id=' . $buyer['public_id'], ['ref' => $oid] + $notifData);
    }
}

header('Location: ' . $redirectUrl);
exit;
