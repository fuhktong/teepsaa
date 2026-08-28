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
require __DIR__ . '/../config/notify.php';
require __DIR__ . '/../config/audit.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('payouts');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/orders.php');
    exit;
}

csrf_verify();

$orderId = (int)($_POST['order_id'] ?? 0);

// Everything the three guards below need, in one read.
$gStmt = $pdo->prepare("
    SELECT o.delivered_at, o.vendor_payout, o.self_deal_flags,
           p.confirmed_by, p.id AS payment_id,
           v.id AS vendor_id, v.aba_changed_at
    FROM orders o
    JOIN payments p   ON p.id = o.payment_id
    JOIN businesses b ON b.id = o.business_id
    JOIN vendors v    ON v.id = b.user_id
    WHERE o.id = ? AND o.status = 'delivered'
");
$gStmt->execute([$orderId]);
$guard = $gStmt->fetch();

$fail = function (string $msg) use ($orderId): never {
    $_SESSION['admin_error'] = $msg;
    header('Location: /admin/order.php?id=' . $orderId);
    exit;
};

// ── Guard 1: the buyer's refund window ────────────────────────────────────
// The UI already discourages this, but a misclick shouldn't cut the window short.
$deliveredAt = $guard['delivered_at'] ?? null;
if ($deliveredAt && (time() - strtotime($deliveredAt)) < PAYOUT_WINDOW_SECONDS) {
    $fail('Refund window still open until '
        . date('M j, g:ia', strtotime($deliveredAt) + PAYOUT_WINDOW_SECONDS)
        . ' — the payout can\'t be completed before then.');
}

// ── Guard 2: the vendor's bank-change hold ────────────────────────────────
// A vendor who changed their ABA details in the last 24 hours cannot be paid
// yet. This is the control against a hijacked vendor account redirecting a
// payout that is already queued. A super can push past it, deliberately —
// the checkbox on the payout screen — and the override is logged.
$bankChangedAt = $guard['aba_changed_at'] ?? null;
$holdActive    = $bankChangedAt && (time() - strtotime($bankChangedAt)) < BANK_CHANGE_HOLD_SECONDS;
$holdOverride  = $holdActive && admin_is_super() && !empty($_POST['override_bank_hold']);

if ($holdActive && !$holdOverride) {
    $fail('This vendor changed their bank details on '
        . date('M j, g:ia', strtotime($bankChangedAt))
        . '. Payouts to them are held until '
        . date('M j, g:ia', strtotime($bankChangedAt) + BANK_CHANGE_HOLD_SECONDS)
        . ' — confirm the change with the vendor directly before paying.');
}

// ── Guard 3: the two-person rule ──────────────────────────────────────────
// The admin who vouched that the buyer's money arrived must not also be the
// one who sends the vendor's money out. That single pair of hands is exactly
// what the Amazon vendor-fraud case turned on.
//
// A super admin is exempt, by design: while one person is running the whole
// operation there is no second admin to ask, and blocking here would just stop
// the business working. The exemption is not silent — it is recorded as
// payout.solo_override and surfaces in the daily activity email. Once a second
// admin exists, route payouts through them and these entries should stop.
$soloPayout = $guard && (int)$guard['confirmed_by'] === admin_id() && admin_id() > 0;
if ($soloPayout && !admin_is_super()) {
    $fail('You confirmed the payment on this order yourself, so you cannot also release the payout. '
        . 'Ask another admin to complete it.');
}

$stmt = $pdo->prepare("UPDATE orders SET status = ?, paid_out_by = ?, paid_out_at = NOW() WHERE id = ? AND status = ?");
$stmt->execute(['completed', admin_id(), $orderId, 'delivered']);

if ($stmt->rowCount() > 0) {
    $auditDetail = [
        'amount'       => (float)($guard['vendor_payout'] ?? 0),
        'vendor_id'    => (int)($guard['vendor_id'] ?? 0),
        'confirmed_by' => $guard['confirmed_by'] !== null ? (int)$guard['confirmed_by'] : null,
    ];
    if (!empty($guard['self_deal_flags'])) {
        $auditDetail['self_deal_flags'] = $guard['self_deal_flags'];
    }
    audit_log($pdo, 'payout.complete', 'order', $orderId, $auditDetail);

    if ($soloPayout) {
        audit_log($pdo, 'payout.solo_override', 'order', $orderId, [
            'amount' => (float)($guard['vendor_payout'] ?? 0),
            'reason' => 'Same admin confirmed the payment and released the payout (super admin exemption).',
        ]);
    }
    if ($holdOverride) {
        audit_log($pdo, 'payout.hold_override', 'order', $orderId, [
            'amount'          => (float)($guard['vendor_payout'] ?? 0),
            'vendor_id'       => (int)($guard['vendor_id'] ?? 0),
            'bank_changed_at' => $bankChangedAt,
        ]);
    }

    $vendorStmt = $pdo->prepare(
        'SELECT v.id AS vendor_id, v.email, v.name, o.id AS order_id, o.public_id AS order_public_id, o.created_at
         FROM orders o
         JOIN businesses b ON b.id = o.business_id
         JOIN vendors v ON v.id = b.user_id
         WHERE o.id = ?'
    );
    $vendorStmt->execute([$orderId]);
    $vendor = $vendorStmt->fetch();
    if ($vendor) {
        $oid = order_display_id((int)$vendor['order_id'], $vendor['created_at']);
        $msg = 'Your payout for order #' . $oid . ' has been sent to your ABA account.';
        notify($pdo, 'vendor', (int)$vendor['vendor_id'], 'payout_sent', $msg, '/orders-vendor/order.php?id=' . $vendor['order_public_id'], ['ref' => $oid]);
        [$subj, $html] = render_email_template($pdo, 'payout_sent', [
            'name'    => htmlspecialchars($vendor['name']),
            'order'   => $oid,
            'cta_url' => 'https://teepsaa.com/orders-vendor/order.php?id=' . $vendor['order_public_id'],
        ]);
        if ($html !== '') send_email($vendor['email'], $subj, $html);
    }
}

$_SESSION['admin_success'] = 'Order marked as completed. Payout recorded.';
header('Location: /admin/order.php?id=' . $orderId);
exit;
