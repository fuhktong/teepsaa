<?php
// The vendor's reply to a disputed refund request — the app's version of the
// reply form on orders-vendor/refund.php, which posts to
// products/refund-reply.php.
//
// multipart/form-data: `id` (the order's public id), `reply` (the vendor's
// words, required, up to REFUND_REPLY_MAX_LENGTH) and optionally `photos[]`,
// up to REFUND_PHOTOS_MAX. Once, within REFUND_REPLY_HOURS of the request
// (config/refund-evidence.php).

require __DIR__ . '/../../config/api.php';
require __DIR__ . '/../../config/notify.php';
require __DIR__ . '/../../config/refund-evidence.php';

api_require_method('POST');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

// A body larger than post_max_size arrives as nothing at all.
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    api_json(['error' => 'too_large'], 413);
}

$publicId = trim((string)($_POST['id'] ?? ''));
if ($publicId === '') api_json(['error' => 'missing_id'], 400);

$stmt = $pdo->prepare('SELECT o.id FROM orders o JOIN businesses b ON b.id = o.business_id WHERE o.public_id = ? AND b.user_id = ?');
$stmt->execute([$publicId, $userId]);
$orderId = (int)$stmt->fetchColumn();
if (!$orderId) api_json(['error' => 'not_found'], 404);

$err = refund_reply($pdo, $orderId, $userId, (string)($_POST['reply'] ?? ''), refund_uploaded_files());
if ($err === 'not_found')    api_json(['error' => 'not_found'], 404);
if ($err === 'shop_closed')  api_json(['error' => 'shop_closed'], 409);
if ($err === 'reply_closed') api_json(['error' => 'reply_closed'], 409);
if ($err === 'wrong_status') {
    $now = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
    $now->execute([$orderId]);
    api_json(['error' => 'wrong_status', 'status' => $now->fetchColumn() ?: 'unknown'], 409);
}
if ($err === 'missing_reply') api_json(['error' => 'missing_reply'], 422);
if ($err === 'too_long')      api_json(['error' => 'too_long', 'max' => REFUND_REPLY_MAX_LENGTH], 422);
if ($err === 'too_many_photos') api_json(['error' => 'too_many_photos', 'max' => REFUND_PHOTOS_MAX], 422);
if ($err) api_json(['error' => $err], 422);

api_json(['ok' => true]);
