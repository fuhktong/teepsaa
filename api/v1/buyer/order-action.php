<?php
// The three things a buyer can do to an order, one endpoint:
//
//   { order_id, action: 'confirm_delivery' }                      confirm-delivery.php
//   { order_id, action: 'refund_request', reason_preset, reason_other, photos[] }  refund-request.php
//   { order_id, action: 'return_dispatch', return_tracking_url }  return-dispatch.php
//
// `order_id` is the public id. Every check those pages make is made again here,
// and each status check sits inside the UPDATE itself, so two taps that arrive
// together cannot both act — the second matches no row and gets wrong_status,
// and the vendor gets one notification, not two.
//
// Refunds: only from 'delivered', never after a refusal (a denied request is
// final), and only inside PAYOUT_WINDOW_SECONDS of delivered_at when that is
// set — the window the payout waits on.
//
// A refund request is sent as multipart/form-data when it carries photos
// (`photos[]`, up to REFUND_PHOTOS_MAX); the other actions may stay JSON.
// Every reason but a change of mind needs at least one photo, and a change of
// mind is approved on the spot (config/refund-evidence.php). The photos are
// stored only once every other check has passed, and removed again if the
// UPDATE finds the order already moved on.
//
// The return link goes through safe_external_url(): it is shown to the vendor
// and to admins as a clickable link, so javascript: and data: are refused.
//
// Replies with the order as order.php would draw it now.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/notify.php';
require __DIR__ . '/../../../config/buyer-orders.php';

api_require_method('POST');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

// A body larger than post_max_size arrives as nothing at all.
$isMultipart = str_starts_with((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data');
if ($isMultipart && empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    api_json(['error' => 'too_large', 'limit_mb' => 2], 413);
}

$body     = $_POST ?: api_body();
$publicId = trim((string)($body['order_id'] ?? ''));
$action   = (string)($body['action'] ?? '');
if ($publicId === '') api_json(['error' => 'missing_id'], 400);
if (!in_array($action, ['confirm_delivery', 'refund_request', 'return_dispatch'], true)) {
    api_json(['error' => 'bad_action'], 400);
}

$look = $pdo->prepare('
    SELECT o.id, o.public_id, o.status, o.created_at, o.delivered_at, o.refund_rejected_at,
           CASE WHEN o.delivered_at IS NULL OR TIMESTAMPDIFF(SECOND, o.delivered_at, NOW()) < ' . PAYOUT_WINDOW_SECONDS . ' THEN 1 ELSE 0 END AS refund_window_open,
           v.id AS vendor_id, v.name AS vendor_name, v.email AS vendor_email
      FROM orders o
      JOIN businesses b ON b.id = o.business_id
      JOIN vendors v ON v.id = b.user_id
     WHERE o.public_id = ? AND o.buyer_user_id = ?
');
$look->execute([$publicId, $userId]);
$order = $look->fetch();
if (!$order) api_json(['error' => 'not_found'], 404);

$orderId = (int)$order['id'];
$oid     = order_display_id($orderId, $order['created_at']);
$link    = '/orders-vendor/order.php?id=' . $order['public_id'];

/** Refused because the order is not where this action starts. Says where it is. */
$wrongStatus = function () use ($pdo, $orderId): void {
    $now = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
    $now->execute([$orderId]);
    api_json(['error' => 'wrong_status', 'status' => $now->fetchColumn() ?: 'unknown'], 409);
};

if ($action === 'confirm_delivery') {
    $upd = $pdo->prepare("
        UPDATE orders SET status = 'delivered', delivered_at = NOW()
         WHERE id = ? AND buyer_user_id = ? AND status = 'dispatched'
    ");
    $upd->execute([$orderId, $userId]);
    if ($upd->rowCount() === 0) $wrongStatus();

    // Done at this point. A failure past here must not read as a failed confirm.
    try {
        notify($pdo, 'vendor', (int)$order['vendor_id'], 'delivery_confirmed',
            'Delivery confirmed for order #' . $oid . ' — payout incoming.', $link, ['ref' => $oid]);
        [$subj, $html] = render_email_template($pdo, 'delivery_confirmed', [
            'name'    => htmlspecialchars((string)$order['vendor_name']),
            'order'   => $oid,
            'cta_url' => 'https://teepsaa.com' . $link,
        ]);
        if ($html !== '') send_email($order['vendor_email'], $subj, $html);
    } catch (Throwable $e) {
        error_log('buyer order-action: confirm notify failed for order ' . $orderId . ': ' . $e->getMessage());
    }
}

if ($action === 'refund_request') {
    [$reason, $err] = refund_reason_from((string)($body['reason_preset'] ?? ''), (string)($body['reason_other'] ?? ''));
    if ($err) api_json(['error' => $err], 400);
    $files = refund_uploaded_files();
    if (refund_needs_photo($reason) && !$files) api_json(['error' => 'missing_photo'], 400);
    if (count($files) > REFUND_PHOTOS_MAX)       api_json(['error' => 'too_many_photos', 'max' => REFUND_PHOTOS_MAX], 400);

    if ($order['status'] !== 'delivered') $wrongStatus();
    if ($order['refund_rejected_at'])     api_json(['error' => 'refund_denied'], 409);
    if (!$order['refund_window_open'])    api_json(['error' => 'refund_closed'], 409);

    [$photos, $err] = refund_photos_store($files, refund_needs_photo($reason));
    if ($err) api_json(['error' => $err], $err === 'save_failed' ? 500 : 400);

    $upd = $pdo->prepare("
        UPDATE orders
           SET status = ?, refund_reason = ?, refund_requested_at = NOW()
         WHERE id = ? AND buyer_user_id = ? AND status = 'delivered' AND refund_rejected_at IS NULL
    ");
    $upd->execute([refund_request_status($reason), $reason, $orderId, $userId]);
    if ($upd->rowCount() === 0) {
        refund_photos_discard($photos);
        $wrongStatus();
    }
    refund_photos_record($pdo, $orderId, 'buyer', 'request', $photos);

    try {
        refund_request_notify($pdo, $orderId);
    } catch (Throwable $e) {
        error_log('buyer order-action: refund notify failed for order ' . $orderId . ': ' . $e->getMessage());
    }
}

if ($action === 'return_dispatch') {
    // The whole link, scheme included — a missing https:// is not guessed at,
    // because guessing wrong sends the vendor somewhere the buyer never meant.
    $url = safe_external_url((string)($body['return_tracking_url'] ?? ''));
    if ($url === null) api_json(['error' => 'invalid_return_url'], 400);

    $upd = $pdo->prepare("
        UPDATE orders SET status = 'return_dispatched', return_tracking_url = ?
         WHERE id = ? AND buyer_user_id = ? AND status = 'return_approved'
    ");
    $upd->execute([$url, $orderId, $userId]);
    if ($upd->rowCount() === 0) $wrongStatus();

    try {
        notify($pdo, 'vendor', (int)$order['vendor_id'], 'return_dispatched',
            'The buyer shipped a return for order #' . $oid . ' — mark it received when it arrives.', $link, ['ref' => $oid]);
    } catch (Throwable $e) {
        error_log('buyer order-action: return notify failed for order ' . $orderId . ': ' . $e->getMessage());
    }
}

api_json(['ok' => true, 'order' => buyer_order_detail($pdo, $userId, $publicId)]);
